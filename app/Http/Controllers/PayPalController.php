<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\CarbonImmutable;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Payment;
use App\Models\ServiceFee;
use App\Models\WalletTransaction;
use App\Mail\BookingConfirmedClientMail;
use App\Mail\BookingConfirmedCoachMail;
use App\Services\WalletService;
use App\Services\PaymentStatusService;
use App\Support\AnalyticsLogger;

class PayPalController extends Controller
{
    private PayPalHttpClient $client;

    public function __construct()
    {
        $clientId = config('services.paypal.client_id');
        $secret   = config('services.paypal.client_secret');
        $mode     = config('services.paypal.mode', 'sandbox');

        $env = $mode === 'live'
            ? new ProductionEnvironment($clientId, $secret)
            : new SandboxEnvironment($clientId, $secret);

        $this->client = new PayPalHttpClient($env);
    }

    public function create(Request $request, PaymentStatusService $statusService)
    {
        $data = $request->validate([
            'service_id'   => ['required', 'integer', 'exists:services,id'],
            'package_id'   => ['required', 'integer', 'exists:service_packages,id'],
            'client_tz'    => ['nullable', 'string'],
            'environment'  => ['nullable', 'string', 'max:120'],
            'note'         => ['nullable', 'string', 'max:2000'],
            'days'         => ['required', 'array', 'min:1'],
            'days.*.date'  => ['required', 'date_format:Y-m-d'],
            'days.*.start' => ['required', 'date'],
            'days.*.end'   => ['required', 'date'],
            'use_platform_credit' => ['nullable', 'boolean'],
        ]);

        $service = Service::with('coach')->findOrFail($data['service_id']);
        $package = ServicePackage::findOrFail($data['package_id']);

        if ($package->service_id !== $service->id) {
            abort(409, 'This package no longer belongs to the selected service.');
        }

        AnalyticsLogger::log($request, 'paypal_checkout_started', [
            'group'      => 'booking',
            'client_id'  => (int) auth()->id(),
            'coach_id'   => (int) ($service->coach_id ?? 0),
            'service_id' => (int) $service->id,
            'meta'       => [
                'package_id'          => (int) $package->id,
                'days_count'          => count($data['days'] ?? []),
                'use_platform_credit' => (bool) $request->boolean('use_platform_credit'),
            ],
        ]);

        $clientTz = $data['client_tz'] ?: config('app.timezone', 'UTC');

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
            $coachFee = ($coachFeeRow->type === 'percent')
                ? round($base * ($coachFeeAmount / 100), 2)
                : round($coachFeeAmount, 2);
        }

        $subtotalMinor = (int) round(round($base, 2) * 100);
        $feesMinor     = (int) round(round($fees, 2) * 100);
        $totalMinor    = $subtotalMinor + $feesMinor;
        $coachFeeMinor = (int) round($coachFee * 100);
        $coachNetMinor = max(0, $subtotalMinor - $coachFeeMinor);

        $usePlatform   = (bool) $request->boolean('use_platform_credit');
        $availPlatform = (int) (auth()->user()->platform_credit_minor ?? 0);

        $walletUseMinor = $usePlatform ? min($availPlatform, $totalMinor) : 0;
        $payableMinor   = max(0, $totalMinor - $walletUseMinor);

        $fundingStatus = ($walletUseMinor > 0 && $payableMinor > 0)
            ? 'mixed'
            : ($walletUseMinor > 0 ? 'wallet_only' : 'external_only');

        $reservation = null;
        $walletHoldTxId = null;

        try {
            DB::transaction(function () use (
                $data,
                $service,
                $package,
                $clientTz,
                $subtotalMinor,
                $feesMinor,
                $totalMinor,
                $totalHours,
                $coachFeeType,
                $coachFeeAmount,
                $coachFeeMinor,
                $coachNetMinor,
                $walletUseMinor,
                $payableMinor,
                $fundingStatus,
                &$reservation,
                &$walletHoldTxId
            ) {
                $reservation = Reservation::create([
                    'service_id'        => $service->id,
                    'package_id'        => $package->id,
                    'client_id'         => auth()->id(),
                    'coach_id'          => $service->coach_id ?? null,
                    'client_tz'         => $clientTz,
                    'environment'       => $data['environment'] ?? null,
                    'note'              => $data['note'] ?? null,

                    'wallet_platform_credit_used_minor' => $walletUseMinor,
                    'payable_minor'                     => $payableMinor,
                    'funding_status'                    => $fundingStatus,

                    'service_title_snapshot' => $service->title,
                    'package_name_snapshot'  => $package->name,
                    'package_hourly_rate'    => $package->hourly_rate,
                    'package_total_price'    => $package->total_price,
                    'package_hours_per_day'  => $package->hours_per_day,
                    'package_total_days'     => $package->total_days,
                    'package_total_hours'    => $package->total_hours,

                    'currency'          => 'USD',
                    'subtotal_minor'    => $subtotalMinor,
                    'fees_minor'        => $feesMinor,
                    'total_minor'       => $totalMinor,

                    'coach_fee_type'    => $coachFeeType,
                    'coach_fee_amount'  => $coachFeeAmount,
                    'coach_fee_minor'   => $coachFeeMinor,
                    'coach_net_minor'   => $coachNetMinor,

                    'total_hours'       => $totalHours,
                    'priced_at'         => now(),

                    'status'         => ($payableMinor > 0 ? 'pending' : 'booked'),
                    'payment_status' => ($payableMinor > 0 ? 'requires_payment' : 'paid'),
                    'booked_at'      => ($payableMinor > 0 ? null : now()),
                    'provider'       => ($payableMinor > 0 ? 'paypal' : 'wallet'),
                ]);

                if ($walletUseMinor > 0) {
                    Payment::create([
                        'reservation_id'      => $reservation->id,
                        'provider'            => 'wallet',
                        'provider_payment_id' => 'wallet_hold_' . $reservation->id,
                        'method'              => 'PLATFORM_CREDIT',
                        'status'              => 'pending',
                        'provider_status'     => 'HELD',
                        'currency'            => 'USD',
                        'amount_total'        => $walletUseMinor,
                        'meta'                => [
                            'reason' => 'reservation_hold',
                            'source' => 'paypal_create',
                        ],
                    ]);

                    $walletHoldTxId = app(WalletService::class)->holdPlatformCredit(
                        auth()->id(),
                        $walletUseMinor,
                        'reservation_hold',
                        $reservation->id
                    );

                    if ($walletHoldTxId) {
                        $reservation->update([
                            'wallet_hold_tx_id' => $walletHoldTxId,
                        ]);
                    }
                }

                foreach ($data['days'] as $d) {
                    ReservationSlot::create([
                        'reservation_id' => $reservation->id,
                        'slot_date'      => $d['date'],
                        'start_utc'      => CarbonImmutable::parse($d['start'])->utc(),
                        'end_utc'        => CarbonImmutable::parse($d['end'])->utc(),
                    ]);
                }
            });

            if ($payableMinor <= 0) {
                if ($walletHoldTxId) {
                    app(WalletService::class)->postHold($walletHoldTxId);
                }

                Payment::where('reservation_id', $reservation->id)
                    ->where('provider', 'wallet')
                    ->where('status', 'pending')
                    ->where('provider_status', 'HELD')
                    ->update([
                        'status'          => 'succeeded',
                        'provider_status' => 'POSTED',
                        'succeeded_at'    => now(),
                        'meta'            => [
                            'reason' => 'wallet_only_via_paypal_create',
                        ],
                    ]);

                AnalyticsLogger::log($request, 'booking_paid_wallet_only', [
                    'group'          => 'booking',
                    'client_id'      => (int) auth()->id(),
                    'coach_id'       => (int) ($reservation->coach_id ?? 0),
                    'service_id'     => (int) ($reservation->service_id ?? 0),
                    'reservation_id' => (int) $reservation->id,
                    'meta'           => [
                        'provider'          => 'wallet',
                        'total_minor'       => (int) $totalMinor,
                        'wallet_used_minor' => (int) $walletUseMinor,
                        'funding_status'    => 'wallet_only',
                        'source'            => 'paypal_create',
                    ],
                ]);

                $this->sendBookingEmails($reservation);

                return redirect()->route('client.home', ['tab' => 'bookings'])
                    ->with('success', 'Booking confirmed using your platform credit.');
            }

            $req = new OrdersCreateRequest();
            $req->prefer('return=representation');
            $req->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $reservation->id,
                    'custom_id'    => (string) $reservation->id,
                    'invoice_id'   => (string) $reservation->id,
                    'description'  => 'Zaivias booking for ' . $service->title,
                    'amount'       => [
                        'currency_code' => 'USD',
                        'value'         => number_format($payableMinor / 100, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name'          => config('app.name', 'Zaivias'),
                    'landing_page'        => 'NO_PREFERENCE',
                    'user_action'         => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url'          => route('paypal.success'),
                    'cancel_url'          => route('paypal.cancel'),
                ],
            ];

            $res   = $this->client->execute($req);
            $order = $res->result;
            $orderId = $order->id ?? null;
            $providerStatus = (string) ($order->status ?? 'CREATED');

            Log::info('PayPal order created', [
                'reservation_id'  => $reservation->id ?? null,
                'order_id'        => $orderId,
                'provider_status' => $providerStatus,
                'payable_minor'   => $payableMinor,
            ]);

            if (! $orderId) {
                throw new \RuntimeException('Could not create PayPal order.');
            }

            DB::transaction(function () use (
                $reservation,
                $order,
                $orderId,
                $providerStatus,
                $payableMinor,
                $subtotalMinor,
                $feesMinor,
                $walletUseMinor,
                $statusService
            ) {
                $reservation = Reservation::lockForUpdate()->find($reservation->id);

                $reservation->update([
                    'payment_intent_id' => $orderId,
                    'provider'          => 'paypal',
                ]);

                Payment::updateOrCreate(
                    [
                        'provider'            => 'paypal',
                        'provider_payment_id' => $orderId,
                    ],
                    [
                        'reservation_id'         => $reservation->id,
                        'method'                 => 'paypal',
                        'status'                 => $statusService->normalize('paypal', $providerStatus),
                        'provider_status'        => $providerStatus,
                        'provider_order_id'      => $orderId,
                        'currency'               => 'USD',
                        'amount_total'           => $payableMinor,
                        'service_subtotal_minor' => $subtotalMinor,
                        'client_fee_minor'       => $feesMinor,
                        'coach_fee_minor'        => 0,
                        'coach_earnings'         => 0,
                        'platform_fee'           => $feesMinor,
                        'provider_charge_id'     => null,
                        'receipt_url'            => null,
                        'meta'                   => [
                            'wallet_used_minor' => $walletUseMinor,
                            'payable_minor'     => $payableMinor,
                            'paypal_order'      => json_decode(json_encode($order), true),
                        ],
                    ]
                );
            });

            AnalyticsLogger::log($request, 'paypal_order_created', [
                'group'          => 'payment',
                'client_id'      => (int) auth()->id(),
                'coach_id'       => (int) ($reservation->coach_id ?? 0),
                'service_id'     => (int) ($reservation->service_id ?? 0),
                'reservation_id' => (int) $reservation->id,
                'meta'           => [
                    'provider'          => 'paypal',
                    'order_id'          => $orderId,
                    'provider_status'   => $providerStatus,
                    'subtotal_minor'    => (int) $subtotalMinor,
                    'fees_minor'        => (int) $feesMinor,
                    'total_minor'       => (int) ($subtotalMinor + $feesMinor),
                    'wallet_used_minor' => (int) $walletUseMinor,
                    'payable_minor'     => (int) $payableMinor,
                    'funding_status'    => $walletUseMinor > 0 && $payableMinor > 0
                        ? 'mixed'
                        : ($walletUseMinor > 0 ? 'wallet_only' : 'external_only'),
                ],
            ]);

            $approvalUrl = null;
            if (! empty($order->links)) {
                foreach ($order->links as $link) {
                    if (($link->rel ?? null) === 'approve') {
                        $approvalUrl = $link->href;
                        break;
                    }
                }
            }

            if (! $approvalUrl) {
                throw new \RuntimeException('Could not get PayPal approval URL.');
            }

            return redirect()->away($approvalUrl);

        } catch (\Throwable $e) {
            try {
                if (! empty($reservation?->id)) {
                    DB::transaction(function () use ($reservation) {
                        $reservation = Reservation::lockForUpdate()->find($reservation->id);
                        if (! $reservation) {
                            return;
                        }

                        $this->releaseWalletHoldAndCancelWalletPayment($reservation, 'paypal_create_failed');

                        $reservation->update([
                            'payment_status' => 'failed',
                            'status'         => 'pending',
                        ]);

                        Payment::where('reservation_id', $reservation->id)
                            ->where('provider', 'paypal')
                            ->whereIn('status', ['pending', 'processing'])
                            ->update([
                                'status'          => 'failed',
                                'provider_status' => 'CREATE_FAILED',
                            ]);
                    });
                }
            } catch (\Throwable $ignored) {
            }

            AnalyticsLogger::log($request, 'paypal_create_failed', [
                'group'          => 'payment',
                'client_id'      => (int) auth()->id(),
                'coach_id'       => (int) ($reservation->coach_id ?? 0),
                'service_id'     => (int) ($reservation->service_id ?? 0),
                'reservation_id' => (int) ($reservation->id ?? 0),
                'meta'           => [
                    'error' => $e->getMessage(),
                ],
            ]);

            Log::error('PayPalController@create failed', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'PayPal error: ' . $e->getMessage());
        }
    }

    /**
     * GET /paypal/success?token=<orderId>
     * Captures the order after buyer approval.
     */
    public function success(Request $request, PaymentStatusService $statusService)
    {
        $orderId = $request->query('token');

        if (! $orderId) {
            return redirect()->route('client.bookings.index')
                ->with('error', 'Missing PayPal order id.');
        }

        try {
            $existingPay = Payment::where('provider', 'paypal')
                ->where(function ($q) use ($orderId) {
                    $q->where('provider_payment_id', $orderId)
                      ->orWhere('provider_order_id', $orderId);
                })
                ->first();

            if ($existingPay && $existingPay->status === 'succeeded') {
                Log::info('PayPal success hit but payment already succeeded', [
                    'order_id' => $orderId,
                    'payment_id' => $existingPay->id,
                ]);

                return redirect()
                    ->route('client.home', ['tab' => 'bookings'])
                    ->with('success', 'PayPal payment already completed successfully.');
            }

            $captureReq = new OrdersCaptureRequest($orderId);
            $captureReq->prefer('return=representation');
            $res    = $this->client->execute($captureReq);
            $result = $res->result;

            $providerStatus = (string) ($result->status ?? 'UNKNOWN');
            $normalized     = $statusService->normalize('paypal', $providerStatus);

            $pu = $result->purchase_units[0] ?? null;
            $capture   = $pu && ! empty($pu->payments->captures[0]) ? $pu->payments->captures[0] : null;
            $captureId = $capture->id ?? null;

            $reservationId = null;
            if ($pu) {
                if (! empty($pu->custom_id)) {
                    $reservationId = (int) $pu->custom_id;
                } elseif (! empty($pu->reference_id)) {
                    $reservationId = (int) $pu->reference_id;
                } elseif (! empty($pu->invoice_id)) {
                    $reservationId = (int) $pu->invoice_id;
                }
            }

            $amountValue  = null;
            $currencyCode = 'USD';
            if (! empty($capture?->amount)) {
                $amt          = $capture->amount;
                $amountValue  = isset($amt->value) ? (float) $amt->value : null;
                $currencyCode = $amt->currency_code ?? 'USD';
            }

            Log::info('PayPal success capture result', [
                'order_id'         => $orderId,
                'provider_status'  => $providerStatus,
                'normalized'       => $normalized,
                'capture_id'       => $captureId,
                'reservation_id'   => $reservationId,
                'amount_value'     => $amountValue,
                'currency_code'    => $currencyCode,
            ]);

            DB::transaction(function () use (
                $orderId,
                $reservationId,
                $providerStatus,
                $normalized,
                $amountValue,
                $currencyCode,
                $captureId,
                $result
            ) {
                $pay = Payment::where('provider', 'paypal')
                    ->where(function ($q) use ($orderId) {
                        $q->where('provider_payment_id', $orderId)
                          ->orWhere('provider_order_id', $orderId);
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $pay && $reservationId) {
                    $pay = Payment::where('provider', 'paypal')
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->latest('id')
                        ->first();
                }

                if (! $pay) {
                    $pay = new Payment([
                        'provider'            => 'paypal',
                        'provider_payment_id' => $orderId,
                        'provider_order_id'   => $orderId,
                    ]);
                }

                $reservation = $reservationId
                    ? Reservation::lockForUpdate()->find($reservationId)
                    : ($pay->reservation_id ? Reservation::lockForUpdate()->find($pay->reservation_id) : null);

                $pay->reservation_id       = $reservation ? $reservation->id : $pay->reservation_id;
                $pay->method               = 'paypal';
                $pay->status               = $normalized;
                $pay->provider_status      = $providerStatus;
                $pay->provider_order_id    = $orderId;
                $pay->provider_payment_id  = $pay->provider_payment_id ?: $orderId;
                $pay->provider_capture_id  = $captureId ?: $pay->provider_capture_id;
                $pay->currency             = strtoupper($currencyCode ?: 'USD');
                $pay->provider_charge_id   = null;
                $pay->receipt_url          = null;

                if ($amountValue !== null) {
                    $pay->amount_total = (int) round($amountValue * 100);
                }

                if ($reservation) {
                    $pay->service_subtotal_minor = $reservation->subtotal_minor;
                    $pay->client_fee_minor       = $reservation->fees_minor;
                    $pay->platform_fee           = $reservation->fees_minor;
                }

                $pay->succeeded_at = $normalized === 'succeeded'
                    ? ($pay->succeeded_at ?? now())
                    : $pay->succeeded_at;

                $pay->meta = array_merge((array) $pay->meta, [
                    'paypal_capture_response' => json_decode(json_encode($result), true),
                ]);

                $pay->save();

                if ($reservation) {
                    if ($normalized === 'succeeded') {
                        $this->finalizeSuccessfulExternalPayment($reservation, 'paypal', $orderId);
                    } elseif (in_array($normalized, ['failed', 'cancelled'], true)) {
                        $this->failExternalPaymentReservation($reservation, 'paypal_success_capture_not_completed');
                    }
                }
            });

            $reservation = null;
            if ($reservationId) {
                $reservation = Reservation::with(['slots', 'service', 'client', 'coach'])->find($reservationId);
                if ($normalized === 'succeeded' && $reservation) {
                    $this->sendBookingEmails($reservation);
                }
            }

            AnalyticsLogger::log($request, $normalized === 'succeeded' ? 'paypal_payment_succeeded' : 'paypal_payment_status_update', [
                'group'          => 'payment',
                'client_id'      => (int) ($reservation?->client_id ?? 0),
                'coach_id'       => (int) ($reservation?->coach_id ?? 0),
                'service_id'     => (int) ($reservation?->service_id ?? 0),
                'reservation_id' => (int) ($reservationId ?? 0),
                'meta'           => [
                    'provider'        => 'paypal',
                    'order_id'        => $orderId,
                    'capture_id'      => $captureId,
                    'provider_status' => $providerStatus,
                    'normalized'      => $normalized,
                    'amount_minor'    => $amountValue !== null ? (int) round($amountValue * 100) : null,
                    'currency'        => strtoupper((string) $currencyCode),
                ],
            ]);

            $msg = $normalized === 'succeeded'
                ? 'PayPal payment completed successfully.'
                : 'We are still confirming your PayPal payment. Status: ' . $providerStatus;

            return redirect()
                ->route('client.home', ['tab' => 'bookings'])
                ->with($normalized === 'succeeded' ? 'success' : 'error', $msg);

        } catch (\Throwable $e) {
            Log::error('PayPal capture error', [
                'msg'      => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return redirect()->route('client.bookings.index')
                ->with('error', 'We could not confirm the PayPal payment yet.');
        }
    }

    public function cancel(Request $request)
    {
        $token = $request->query('token');
        $reservationForAnalytics = null;

        if ($token) {
            DB::transaction(function () use ($token, &$reservationForAnalytics) {
                $pay = Payment::where('provider', 'paypal')
                    ->where(function ($q) use ($token) {
                        $q->where('provider_payment_id', $token)
                          ->orWhere('provider_order_id', $token);
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $pay) {
                    return;
                }

                $pay->update([
                    'status'          => 'cancelled',
                    'provider_status' => 'CANCELLED',
                    'meta'            => array_merge((array) $pay->meta, [
                        'cancelled_at'  => now()->toIso8601String(),
                        'cancel_source' => 'paypal_cancel_url',
                    ]),
                ]);

                if (! $pay->reservation_id) {
                    return;
                }

                $res = Reservation::lockForUpdate()->find($pay->reservation_id);
                if (! $res) {
                    return;
                }

                $this->releaseWalletHoldAndCancelWalletPayment($res, 'paypal_cancelled');

                $res->update([
                    'status'         => 'pending',
                    'payment_status' => 'failed',
                ]);

                $reservationForAnalytics = $res->fresh();
            });
        }

        AnalyticsLogger::log($request, 'paypal_payment_cancelled', [
            'group'          => 'payment',
            'reservation_id' => (int) ($reservationForAnalytics->id ?? 0),
            'client_id'      => (int) ($reservationForAnalytics->client_id ?? 0),
            'coach_id'       => (int) ($reservationForAnalytics->coach_id ?? 0),
            'service_id'     => (int) ($reservationForAnalytics->service_id ?? 0),
            'meta'           => [
                'provider' => 'paypal',
                'token'    => $token,
            ],
        ]);

        return redirect()->route('client.bookings.index')
            ->with('error', 'You cancelled the PayPal payment.');
    }

    private function finalizeSuccessfulExternalPayment(Reservation $reservation, string $provider, ?string $paymentIntentId = null): void
    {
        $holdId = (int) ($reservation->wallet_hold_tx_id ?? 0);

        if ($holdId <= 0) {
            $holdTx = WalletTransaction::where('reservation_id', $reservation->id)
                ->where('balance_type', WalletService::BAL_PLATFORM)
                ->where('type', 'debit')
                ->where('status', 'hold')
                ->lockForUpdate()
                ->first();

            $holdId = (int) ($holdTx?->id ?? 0);
        }

        if ($holdId > 0) {
            app(WalletService::class)->postHold($holdId);

            Payment::where('reservation_id', $reservation->id)
                ->where('provider', 'wallet')
                ->where('status', 'pending')
                ->where('provider_status', 'HELD')
                ->update([
                    'status'          => 'succeeded',
                    'provider_status' => 'POSTED',
                    'succeeded_at'    => now(),
                ]);
        }

        $reservation->update([
            'status'            => 'booked',
            'payment_status'    => 'paid',
            'provider'          => $provider,
            'payment_intent_id' => $paymentIntentId ?: $reservation->payment_intent_id,
            'booked_at'         => $reservation->booked_at ?? now(),
            'wallet_hold_tx_id' => null,
        ]);
    }

    private function failExternalPaymentReservation(Reservation $reservation, string $reason): void
    {
        $this->releaseWalletHoldAndCancelWalletPayment($reservation, $reason);

        $reservation->update([
            'status'         => 'pending',
            'payment_status' => 'failed',
        ]);
    }

    private function releaseWalletHoldAndCancelWalletPayment(Reservation $reservation, string $reason): void
    {
        $holdId = (int) ($reservation->wallet_hold_tx_id ?? 0);

        if ($holdId > 0) {
            app(WalletService::class)->reverseHold($holdId, $reason);
            $reservation->update(['wallet_hold_tx_id' => null]);
        } else {
            $holdTx = WalletTransaction::where('reservation_id', $reservation->id)
                ->where('balance_type', WalletService::BAL_PLATFORM)
                ->where('type', 'debit')
                ->where('status', 'hold')
                ->lockForUpdate()
                ->first();

            if ($holdTx) {
                app(WalletService::class)->reverseHold((int) $holdTx->id, $reason);
            }

            $reservation->update(['wallet_hold_tx_id' => null]);
        }

        Payment::where('reservation_id', $reservation->id)
            ->where('provider', 'wallet')
            ->where('status', 'pending')
            ->where('provider_status', 'HELD')
            ->update([
                'status'          => 'cancelled',
                'provider_status' => 'CANCELLED',
            ]);
    }

    private function sendBookingEmails(Reservation $reservation): void
    {
        $reservation->loadMissing(['slots', 'service', 'client', 'coach']);

        if ($reservation->status !== 'booked' || $reservation->payment_status !== 'paid') {
            return;
        }

        $clientTz = $reservation->client_tz ?: config('app.timezone', 'UTC');
        $coachTz  = $reservation->coach?->timezone ?: config('app.timezone', 'UTC');

        $formatSlots = function (string $tz) use ($reservation) {
            return $reservation->slots
                ->sortBy('start_utc')
                ->map(function ($slot) use ($tz) {
                    $start = CarbonImmutable::parse($slot->start_utc)->setTimezone($tz);
                    $end   = CarbonImmutable::parse($slot->end_utc)->setTimezone($tz);

                    return [
                        'date'  => $start->format('D, d M Y'),
                        'start' => $start->format('h:i A'),
                        'end'   => $end->format('h:i A'),
                    ];
                })->values()->all();
        };

        if (is_null($reservation->client_booking_emailed_at) && $reservation->client?->email) {
            $slotsClient = $formatSlots($clientTz);

            Mail::to($reservation->client->email)
                ->send(new BookingConfirmedClientMail($reservation, $slotsClient, $clientTz));

            $reservation->forceFill(['client_booking_emailed_at' => now()])->save();
        }

        if (is_null($reservation->coach_booking_emailed_at) && $reservation->coach?->email) {
            $slotsCoach = $formatSlots($coachTz);

            Mail::to($reservation->coach->email)
                ->send(new BookingConfirmedCoachMail($reservation, $slotsCoach, $coachTz));

            $reservation->forceFill(['coach_booking_emailed_at' => now()])->save();
        }
    }
}