<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook as StripeWebhook;
use Carbon\CarbonImmutable;
use App\Services\WalletService;
use App\Support\AnalyticsLogger;

use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Payment;
use App\Models\ServiceFee;
use App\Models\WalletTransaction;

class PaymentController extends Controller
{
    private function stripe(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    private function fundingStatus(int $payableMinor, int $appliedMinor): string
    {
        if ($payableMinor === 0) {
            return 'wallet_only';
        }

        return $appliedMinor > 0 ? 'mixed' : 'external_only';
    }

    private function normalizeCheckoutMethod(?string $method, int $payableMinor): string
    {
        if ($payableMinor === 0) {
            return 'wallet_only';
        }

        $method = strtolower(trim((string) $method));

        return in_array($method, ['card', 'wallet', 'paypal'], true)
            ? $method
            : 'card';
    }

    private function normalizeWalletType(?string $walletType): ?string
    {
        $walletType = strtolower(trim((string) $walletType));

        return in_array($walletType, ['apple_pay', 'google_pay'], true)
            ? $walletType
            : null;
    }

    private function mapReservationPaymentStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded'               => 'paid',
            'processing'              => 'processing',
            'requires_action'         => 'requires_action',
            'requires_confirmation'   => 'pending',
            'requires_capture'        => 'pending',
            'requires_payment_method' => 'failed',
            'canceled'                => 'cancelled',
            default                   => 'pending',
        };
    }

    private function mapReservationStatus(string $stripeStatus, ?string $currentStatus = null): string
    {
        return match ($stripeStatus) {
            'succeeded' => 'booked',
            'canceled', 'requires_payment_method' => 'pending',
            default => $currentStatus ?: 'pending',
        };
    }

    private function walletDetailsFromCharge($charge): array
    {
        $details = $charge->payment_method_details ?? null;

        $brand = null;
        $walletType = null;
        $paymentChannel = null;
        $method = 'CARD';

        if ($details) {
            $paymentChannel = $details->type ?? null;

            if (isset($details->card)) {
                $brand = $details->card->brand ?? null;
                $walletType = $details->card->wallet->type ?? null;
            }

            if ($walletType) {
                $paymentChannel = 'wallet';
            } elseif (($details->type ?? null) === 'card') {
                $paymentChannel = 'card';
            }

            $method = strtoupper($brand ?: ($details->type ?? 'card'));
        }

        return [
            'method'          => $method,
            'payment_channel' => $paymentChannel,
            'wallet_type'     => $walletType ? strtolower($walletType) : null,
            'network_brand'   => $brand ? strtolower($brand) : null,
            'receipt_url'     => $charge->receipt_url ?? null,
        ];
    }

    public function createIntent(Request $r)
    {
        $data = $r->validate([
            'service_id'        => ['required', 'integer', 'exists:services,id'],
            'package_id'        => ['required', 'integer', 'exists:service_packages,id'],
            'client_tz'         => ['nullable', 'string'],
            'environment'       => ['nullable', 'string', 'max:120'],
            'note'              => ['nullable', 'string', 'max:2000'],
            'days'              => ['required', 'array', 'min:1'],
            'days.*.date'       => ['required', 'date_format:Y-m-d'],
            'days.*.start'      => ['required', 'date'],
            'days.*.end'        => ['required', 'date'],
            'payment_intent_id' => ['nullable', 'string'],

            'use_platform_credit'         => ['nullable', 'string', 'in:0,1'],
            'platform_credit_apply_minor' => ['nullable', 'integer', 'min:0'],
            'payable_minor'               => ['nullable', 'integer', 'min:0'],

            'checkout_method' => ['nullable', 'string', 'in:card,wallet,paypal'],
            'wallet_type'     => ['nullable', 'string', 'in:apple_pay,google_pay'],
        ]);

        $service = Service::with('coach')->findOrFail($data['service_id']);
        $package = ServicePackage::findOrFail($data['package_id']);

        if ((int) $package->service_id !== (int) $service->id) {
            abort(409, 'This package no longer belongs to the selected service.');
        }

        $clientTz = $data['client_tz'] ?: config('app.timezone', 'UTC');

        AnalyticsLogger::log($r, 'stripe_checkout_intent_started', [
            'group'      => 'booking',
            'client_id'  => (int) auth()->id(),
            'coach_id'   => (int) ($service->coach_id ?? 0),
            'service_id' => (int) $service->id,
            'meta'       => [
                'package_id'          => (int) $package->id,
                'payment_intent_id'   => $data['payment_intent_id'] ?? null,
                'days_count'          => count($data['days'] ?? []),
                'use_platform_credit' => (string) ($data['use_platform_credit'] ?? '0') === '1',
                'checkout_method'     => $data['checkout_method'] ?? 'card',
                'wallet_type'         => $data['wallet_type'] ?? null,
            ],
        ]);

        if (! is_null($package->total_hours) && $package->total_hours > 0) {
            $totalHours = (float) $package->total_hours;
        } else {
            $totalHours = 0.0;

            foreach ($data['days'] as $d) {
                $start = CarbonImmutable::parse($d['start']);
                $end   = CarbonImmutable::parse($d['end']);
                $minutes = $end->diffInRealMinutes($start, true);
                $totalHours += $minutes / 60;
            }

            $totalHours = round($totalHours, 2);
        }

        $base = 0.0;
        if (! is_null($package->total_price) && $package->total_price > 0) {
            $base = (float) $package->total_price;
        } elseif (! is_null($package->hourly_rate) && $package->hourly_rate > 0) {
            $base = (float) $package->hourly_rate * $totalHours;
        }

        $clientFeeRow = ServiceFee::where('is_active', true)
            ->where(function ($q) {
                $q->where('party', 'client')
                  ->orWhere('slug', 'client_commission');
            })
            ->first();

        $fees = 0.0;
        if ($clientFeeRow) {
            $fees = $clientFeeRow->type === 'percent'
                ? round($base * ((float) $clientFeeRow->amount / 100), 2)
                : round((float) $clientFeeRow->amount, 2);
        }

        $coachFeeRow = ServiceFee::where('is_active', true)
            ->where(function ($q) {
                $q->where('party', 'coach')
                  ->orWhere('slug', 'coach_commission');
            })
            ->first();

        $coachFeeType   = null;
        $coachFeeAmount = null;
        $coachFee       = 0.0;

        if ($coachFeeRow) {
            $coachFeeType   = $coachFeeRow->type;
            $coachFeeAmount = (float) $coachFeeRow->amount;
            $coachFee = $coachFeeRow->type === 'percent'
                ? round($base * ($coachFeeAmount / 100), 2)
                : round($coachFeeAmount, 2);
        }

        $subtotal      = round($base, 2);
        $total         = round($subtotal + $fees, 2);
        $subtotalMinor = (int) round($subtotal * 100);
        $feesMinor     = (int) round($fees * 100);
        $totalMinor    = (int) round($total * 100);

        $coachFeeMinor = (int) round($coachFee * 100);
        $coachNetMinor = max(0, $subtotalMinor - $coachFeeMinor);

        $useWallet      = (string) ($data['use_platform_credit'] ?? '0') === '1';
        $requestedApply = (int) ($data['platform_credit_apply_minor'] ?? 0);
        $avail          = (int) (auth()->user()->platform_credit_minor ?? 0);

        $appliedMinor = $useWallet ? min($requestedApply, $avail, $totalMinor) : 0;
        $payableMinor = max(0, $totalMinor - $appliedMinor);
        $fundingStatus = $this->fundingStatus($payableMinor, $appliedMinor);

        $checkoutMethod = $this->normalizeCheckoutMethod($data['checkout_method'] ?? 'card', $payableMinor);
        $walletType     = $this->normalizeWalletType($data['wallet_type'] ?? null);

        $stripe = $this->stripe();

        return DB::transaction(function () use (
            $r,
            $data,
            $service,
            $package,
            $clientTz,
            $subtotalMinor,
            $feesMinor,
            $totalMinor,
            $payableMinor,
            $appliedMinor,
            $coachFeeType,
            $coachFeeAmount,
            $coachFeeMinor,
            $coachNetMinor,
            $totalHours,
            $fundingStatus,
            $checkoutMethod,
            $walletType,
            $stripe
        ) {
            $reservation = null;
            $existingPi = null;

            if (! empty($data['payment_intent_id'])) {
                try {
                    $existingPi = $stripe->paymentIntents->retrieve($data['payment_intent_id'], []);
                    $resIdMeta  = $existingPi->metadata['reservation_id'] ?? null;
                    $userIdMeta = (int) ($existingPi->metadata['user_id'] ?? 0);

                    if (
                        $resIdMeta &&
                        $userIdMeta === (int) auth()->id() &&
                        ! in_array($existingPi->status, ['succeeded', 'canceled'], true)
                    ) {
                        $reservation = Reservation::find($resIdMeta);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to retrieve existing PaymentIntent during createIntent()', [
                        'payment_intent_id' => $data['payment_intent_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (! $reservation) {
                $reservation = Reservation::create([
                    'service_id'        => $service->id,
                    'package_id'        => $package->id,
                    'client_id'         => auth()->id(),
                    'coach_id'          => $service->coach_id ?? null,
                    'client_tz'         => $clientTz,
                    'environment'       => $data['environment'] ?? null,
                    'note'              => $data['note'] ?? null,

                    'service_title_snapshot' => $service->title,
                    'package_name_snapshot'  => $package->name,
                    'package_hourly_rate'    => $package->hourly_rate,
                    'package_total_price'    => $package->total_price,
                    'package_hours_per_day'  => $package->hours_per_day,
                    'package_total_days'     => $package->total_days,
                    'package_total_hours'    => $package->total_hours,
                    'priced_at'              => now(),

                    'currency'          => 'USD',
                    'subtotal_minor'    => $subtotalMinor,
                    'fees_minor'        => $feesMinor,
                    'total_minor'       => $totalMinor,

                    'coach_fee_type'    => $coachFeeType,
                    'coach_fee_amount'  => $coachFeeAmount,
                    'coach_fee_minor'   => $coachFeeMinor,
                    'coach_net_minor'   => $coachNetMinor,

                    'wallet_platform_credit_used_minor' => $appliedMinor,
                    'payable_minor'     => $payableMinor,
                    'funding_status'    => $fundingStatus,

                    'checkout_method'   => $checkoutMethod,
                    'wallet_type'       => $walletType,

                    'total_hours'       => $totalHours,
                    'status'            => 'pending',
                    'payment_status'    => $payableMinor > 0 ? 'requires_payment' : 'paid',
                    'provider'          => $payableMinor > 0 ? 'stripe' : 'wallet',
                ]);

                foreach ($data['days'] as $d) {
                    ReservationSlot::create([
                        'reservation_id' => $reservation->id,
                        'slot_date'      => $d['date'],
                        'start_utc'      => CarbonImmutable::parse($d['start'])->utc(),
                        'end_utc'        => CarbonImmutable::parse($d['end'])->utc(),
                    ]);
                }
            } else {
                $reservation->update([
                    'client_tz'      => $clientTz,
                    'environment'    => $data['environment'] ?? null,
                    'note'           => $data['note'] ?? null,

                    'subtotal_minor' => $subtotalMinor,
                    'fees_minor'     => $feesMinor,
                    'total_minor'    => $totalMinor,

                    'coach_fee_type'   => $coachFeeType,
                    'coach_fee_amount' => $coachFeeAmount,
                    'coach_fee_minor'  => $coachFeeMinor,
                    'coach_net_minor'  => $coachNetMinor,

                    'wallet_platform_credit_used_minor' => $appliedMinor,
                    'payable_minor'    => $payableMinor,
                    'funding_status'   => $fundingStatus,

                    'checkout_method'  => $checkoutMethod,
                    'wallet_type'      => $walletType,

                    'total_hours'      => $totalHours,
                    'status'           => $payableMinor > 0 ? 'pending' : 'booked',
                    'payment_status'   => $payableMinor > 0 ? 'requires_payment' : 'paid',
                    'provider'         => $payableMinor > 0 ? 'stripe' : 'wallet',
                    'priced_at'        => now(),
                ]);

                ReservationSlot::where('reservation_id', $reservation->id)->delete();

                foreach ($data['days'] as $d) {
                    ReservationSlot::create([
                        'reservation_id' => $reservation->id,
                        'slot_date'      => $d['date'],
                        'start_utc'      => CarbonImmutable::parse($d['start'])->utc(),
                        'end_utc'        => CarbonImmutable::parse($d['end'])->utc(),
                    ]);
                }
            }

            $existingHoldId = (int) ($reservation->wallet_hold_tx_id ?? 0);

            $existingHeldWalletPay = Payment::where('reservation_id', $reservation->id)
                ->where('provider', 'wallet')
                ->where('status', 'held')
                ->lockForUpdate()
                ->first();

            $existingHeldAmount = (int) ($existingHeldWalletPay?->amount_total ?? 0);

            if ($existingHoldId > 0 && $existingHeldAmount !== (int) $appliedMinor) {
                app(WalletService::class)->reverseHold($existingHoldId, 'stripe_reprice');

                $reservation->update(['wallet_hold_tx_id' => null]);

                if ($existingHeldWalletPay) {
                    $existingHeldWalletPay->update(['status' => 'cancelled']);
                }

                $existingHoldId = 0;
            }

            if ((int) $appliedMinor > 0 && $existingHoldId <= 0) {
                Payment::create([
                    'reservation_id'      => $reservation->id,
                    'provider'            => 'wallet',
                    'provider_payment_id' => 'wallet_hold_' . $reservation->id,
                    'method'              => 'PLATFORM_CREDIT',
                    'payment_channel'     => 'platform_credit',
                    'wallet_type'         => null,
                    'network_brand'       => null,
                    'status'              => 'held',
                    'currency'            => 'USD',
                    'amount_total'        => (int) $appliedMinor,
                    'meta'                => [
                        'reason' => 'reservation_hold',
                        'source' => 'stripe_create_intent',
                    ],
                ]);

                $holdTxId = app(WalletService::class)->holdPlatformCredit(
                    auth()->id(),
                    (int) $appliedMinor,
                    'reservation_hold',
                    $reservation->id
                );

                if ($holdTxId) {
                    $reservation->update(['wallet_hold_tx_id' => $holdTxId]);
                }
            }

            $pi = null;

            if ($payableMinor > 0) {
                $intentPayload = [
                    'amount'               => $payableMinor,
                    'currency'             => 'usd',
                    'payment_method_types' => ['card'],
                    'capture_method'       => 'automatic',
                    'metadata'             => [
                        'reservation_id'  => (string) $reservation->id,
                        'user_id'         => (string) (auth()->id() ?? 0),
                        'service_id'      => (string) $service->id,
                        'package_id'      => (string) $package->id,
                        'checkout_method' => (string) $checkoutMethod,
                        'wallet_type'     => (string) ($walletType ?? ''),
                        'funding_status'  => (string) $fundingStatus,
                    ],
                ];

                if (
                    $existingPi &&
                    ($existingPi->id === ($data['payment_intent_id'] ?? null)) &&
                    ! in_array($existingPi->status, ['succeeded', 'canceled'], true)
                ) {
                    $pi = $stripe->paymentIntents->update($existingPi->id, $intentPayload);
                } else {
                    $pi = $stripe->paymentIntents->create($intentPayload, [
                        'idempotency_key' => 'pi:new:res-' . $reservation->id,
                    ]);
                }

                $reservation->update([
                    'payment_intent_id' => $pi->id,
                    'provider'          => 'stripe',
                    'status'            => 'pending',
                    'payment_status'    => 'requires_payment',
                ]);
            } else {
                $reservation->update([
                    'payment_intent_id' => null,
                    'provider'          => 'wallet',
                ]);

                $holdId = (int) ($reservation->wallet_hold_tx_id ?? 0);

                if ($holdId > 0) {
                    app(WalletService::class)->postHold($holdId);

                    Payment::where('reservation_id', $reservation->id)
                        ->where('provider', 'wallet')
                        ->where('status', 'held')
                        ->update([
                            'status'       => 'succeeded',
                            'succeeded_at' => now(),
                        ]);

                    $reservation->update(['wallet_hold_tx_id' => null]);
                }

                $reservation->update([
                    'status'         => 'booked',
                    'payment_status' => 'paid',
                    'provider'       => 'wallet',
                    'booked_at'      => $reservation->booked_at ?? now(),
                ]);
            }

            return response()->json([
                'payment_intent_id' => $pi?->id,
                'client_secret'     => $pi?->client_secret,
                'reservation_id'    => $reservation->id,
                'applied_minor'     => $appliedMinor,
                'payable_minor'     => $payableMinor,
                'funding_status'    => $fundingStatus,
                'checkout_method'   => $checkoutMethod,
                'wallet_type'       => $walletType,
            ]);
        });
    }

    public function success(Request $r)
    {
        $piId = $r->query('pi');

        if (! $piId) {
            return view('payments.success')->with([
                'status'  => 'unknown',
                'pi_id'   => null,
                'message' => 'Missing PaymentIntent id.',
            ]);
        }

        try {
            $pi = $this->stripe()->paymentIntents->retrieve($piId, []);
        } catch (\Throwable $e) {
            Log::warning('Stripe PI retrieve error on success()', ['e' => $e->getMessage()]);
            $pi = null;
        }

        $status = $pi->status ?? 'unknown';

        return view('payments.success')->with([
            'status'  => $status,
            'pi_id'   => $piId,
            'message' => match ($status) {
                'succeeded'       => 'Payment succeeded. Your reservation is confirmed.',
                'processing'      => 'Your payment is processing. You’ll get a confirmation once it succeeds.',
                'requires_action' => 'Additional authentication is required to complete your payment.',
                default           => 'We could not confirm the payment yet.',
            },
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sig     = $request->header('Stripe-Signature');
        $secret  = config('services.stripe.webhook_secret');

        try {
            $event = StripeWebhook::constructEvent($payload, $sig, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature validation failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
            case 'payment_intent.processing':
            case 'payment_intent.payment_failed':
            case 'payment_intent.canceled': {
                $pi = $event->data->object;
                $reservationId = $pi->metadata['reservation_id'] ?? null;

                if (! $reservationId) {
                    break;
                }

                DB::transaction(function () use ($request, $pi, $reservationId) {
                    $res = Reservation::lockForUpdate()->find($reservationId);
                    if (! $res) {
                        return;
                    }

                    $newPaymentStatus = $this->mapReservationPaymentStatus((string) $pi->status);

                    $pay = Payment::firstOrNew([
                        'provider'            => 'stripe',
                        'provider_payment_id' => $pi->id,
                    ]);

                    $latestChargeId  = $pi->latest_charge ?? null;
                    $receiptUrl      = $pay->receipt_url;
                    $method          = $pay->method ?: 'CARD';
                    $paymentChannel  = $pay->payment_channel ?? (($pi->metadata['checkout_method'] ?? null) === 'wallet' ? 'wallet' : 'card');
                    $walletType      = $pay->wallet_type ?? ($pi->metadata['wallet_type'] ?? null);
                    $networkBrand    = $pay->network_brand ?? null;

                    if ($latestChargeId) {
                        try {
                            $ch = $this->stripe()->charges->retrieve($latestChargeId, []);
                            $enriched = $this->walletDetailsFromCharge($ch);

                            $receiptUrl     = $enriched['receipt_url'] ?? $receiptUrl;
                            $method         = $enriched['method'] ?? $method;
                            $paymentChannel = $enriched['payment_channel'] ?? $paymentChannel;
                            $walletType     = $enriched['wallet_type'] ?? $walletType;
                            $networkBrand   = $enriched['network_brand'] ?? $networkBrand;
                        } catch (\Throwable $e) {
                            Log::warning('Failed to retrieve latest charge during PI webhook', [
                                'payment_intent_id' => $pi->id,
                                'latest_charge_id'  => $latestChargeId,
                                'error'             => $e->getMessage(),
                            ]);
                        }
                    }

                    $pay->fill([
                        'reservation_id'         => $res->id,
                        'method'                 => $method ?: 'CARD',
                        'payment_channel'        => $paymentChannel,
                        'wallet_type'            => $walletType,
                        'network_brand'          => $networkBrand,
                        'status'                 => $pi->status,
                        'currency'               => strtoupper((string) ($pi->currency ?? 'USD')),
                        'amount_total'           => (int) $pi->amount,
                        'provider_charge_id'     => $latestChargeId,
                        'receipt_url'            => $receiptUrl,
                        'succeeded_at'           => $pi->status === 'succeeded' ? ($pay->succeeded_at ?? now()) : $pay->succeeded_at,
                        'meta'                   => json_encode($pi, JSON_UNESCAPED_SLASHES),
                    ]);

                    $pay->save();

                    $isPaid   = $pi->status === 'succeeded';
                    $isFailed = in_array($pi->status, ['requires_payment_method', 'canceled'], true);

                    $holdId = (int) ($res->wallet_hold_tx_id ?? 0);

                    if ($holdId <= 0) {
                        $holdTx = WalletTransaction::where('reservation_id', $res->id)
                            ->where('balance_type', WalletService::BAL_PLATFORM)
                            ->where('type', 'debit')
                            ->where('status', 'hold')
                            ->lockForUpdate()
                            ->first();

                        $holdId = (int) ($holdTx?->id ?? 0);
                    }

                    if ($isPaid) {
                        if ($holdId > 0) {
                            app(WalletService::class)->postHold($holdId);
                        }

                        Payment::where('reservation_id', $res->id)
                            ->where('provider', 'wallet')
                            ->where('status', 'held')
                            ->update([
                                'status'       => 'succeeded',
                                'succeeded_at' => now(),
                            ]);

                        if ((int) ($res->wallet_hold_tx_id ?? 0) > 0) {
                            $res->update(['wallet_hold_tx_id' => null]);
                        }
                    }

                    if ($isFailed) {
                        if ($holdId > 0) {
                            app(WalletService::class)->reverseHold($holdId, 'stripe_failed');
                        }

                        Payment::where('reservation_id', $res->id)
                            ->where('provider', 'wallet')
                            ->where('status', 'held')
                            ->update(['status' => 'cancelled']);

                        if ((int) ($res->wallet_hold_tx_id ?? 0) > 0) {
                            $res->update(['wallet_hold_tx_id' => null]);
                        }
                    }

                    $res->update([
                        'status'            => $this->mapReservationStatus((string) $pi->status, $res->status),
                        'payment_status'    => $newPaymentStatus,
                        'provider'          => 'stripe',
                        'payment_intent_id' => $pi->id,
                        'checkout_method'   => $walletType ? 'wallet' : ($pi->metadata['checkout_method'] ?? $res->checkout_method),
                        'wallet_type'       => $walletType ?: ($res->wallet_type ?? null),
                        'booked_at'         => $isPaid ? ($res->booked_at ?? now()) : $res->booked_at,
                    ]);
                });

                break;
            }

            case 'charge.succeeded': {
                $ch   = $event->data->object;
                $piId = $ch->payment_intent ?? null;

                if (! $piId) {
                    break;
                }

                try {
                    $pi = $this->stripe()->paymentIntents->retrieve($piId, []);
                } catch (\Throwable $e) {
                    Log::warning('Failed to retrieve PI during charge.succeeded webhook', [
                        'payment_intent_id' => $piId,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }

                $reservationId = $pi->metadata['reservation_id'] ?? null;
                if (! $reservationId) {
                    break;
                }

                DB::transaction(function () use ($ch, $pi, $reservationId) {
                    $res = Reservation::lockForUpdate()->find($reservationId);
                    if (! $res) {
                        return;
                    }

                    $pay = Payment::firstOrNew([
                        'provider'            => 'stripe',
                        'provider_payment_id' => $pi->id,
                    ]);

                    $enriched = $this->walletDetailsFromCharge($ch);

                    $pay->fill([
                        'reservation_id'      => $res->id,
                        'method'              => $enriched['method'],
                        'payment_channel'     => $enriched['payment_channel'],
                        'wallet_type'         => $enriched['wallet_type'],
                        'network_brand'       => $enriched['network_brand'],
                        'status'              => $pi->status,
                        'currency'            => strtoupper((string) ($ch->currency ?? 'USD')),
                        'amount_total'        => (int) $ch->amount,
                        'provider_charge_id'  => $ch->id,
                        'receipt_url'         => $enriched['receipt_url'],
                        'succeeded_at'        => $pay->succeeded_at ?? now(),
                        'meta'                => json_encode($pi, JSON_UNESCAPED_SLASHES),
                    ]);

                    $pay->save();

                    if ($pi->status === 'succeeded') {
                        $res->update([
                            'status'          => 'booked',
                            'payment_status'  => 'paid',
                            'provider'        => 'stripe',
                            'checkout_method' => $enriched['wallet_type'] ? 'wallet' : ($res->checkout_method ?? 'card'),
                            'wallet_type'     => $enriched['wallet_type'] ?? ($res->wallet_type ?? null),
                            'booked_at'       => $res->booked_at ?? now(),
                        ]);
                    }
                });

                break;
            }
        }

        return response()->json(['received' => true]);
    }
}