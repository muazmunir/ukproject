<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CancellationService
{
    public function canCancel(Reservation $reservation): bool
    {
        if ($reservation->payment_status !== 'paid') {
            return false;
        }

        if (in_array(strtolower((string) $reservation->status), ['cancelled', 'canceled'], true)) {
            return false;
        }

        $reservation->loadMissing('slots');

        if ($reservation->slots->contains(fn ($s) => $s->client_checked_in_at || $s->coach_checked_in_at)) {
            return false;
        }

        $firstStart = $reservation->slots->min('start_utc');
        if (! $firstStart) {
            return false;
        }

        return now()->lt($firstStart);
    }

    public function cancel(
        Reservation $reservation,
        string $by,
        ?string $reason = null
    ): bool {
        return DB::transaction(function () use ($reservation, $by, $reason) {

            $reservation = Reservation::with([
                'slots',
                'service',
                'externalPayment',
                'walletPayment',
            ])
                ->lockForUpdate()
                ->find($reservation->id);

            if (! $reservation || ! $this->canCancel($reservation)) {
                return false;
            }

            $externalPayment = $reservation->externalPayment;
            $walletPayment   = $reservation->walletPayment;

            $payment   = $externalPayment ?? $walletPayment;
            $paymentId = $payment?->id;

            $firstStart = CarbonImmutable::parse(
                $reservation->slots->min('start_utc')
            )->utc();

            $now = CarbonImmutable::now('UTC');
            $hoursUntil = $now->diffInRealHours($firstStart, false);

            $subtotal = (int) $reservation->subtotal_minor;
            $fees     = (int) $reservation->fees_minor;
            $total    = (int) $reservation->total_minor;

            $currency = $reservation->currency ?? ($payment?->currency ?? 'USD');

            $walletUsedMinor   = (int) ($reservation->wallet_platform_credit_used_minor ?? 0);
            $externalPaidMinor = (int) ($reservation->externalPayment?->amount_total ?? 0);

            $isWalletOnly   = ($walletUsedMinor > 0 && $externalPaidMinor <= 0);

            $refund         = 0;
            $clientPenalty  = 0;
            $coachPenalty   = 0;
            $coachComp      = 0;
            $platformEarned = 0;

            $platformFeeRefundRequestedMinor = 0;

            if (in_array($by, ['admin', 'system'], true)) {
                $refund = $total;
                $platformFeeRefundRequestedMinor = $fees;
            } elseif ($hoursUntil >= 48) {
                $refund = $total;
                $platformFeeRefundRequestedMinor = $fees;
            } elseif ($hoursUntil >= 24) {
                if ($by === 'coach') {
                    $refund = $total;
                    $coachPenalty = (int) round($subtotal * 0.10);
                    $platformFeeRefundRequestedMinor = $fees;
                } else {
                    $coachComp = (int) round($subtotal * 0.10);
                    $platformEarned = $fees;

                    $clientPenalty = $fees + $coachComp;
                    $refund = max(0, $total - $clientPenalty);
                    $platformFeeRefundRequestedMinor = 0;
                }
            } else {
                if ($by === 'coach') {
                    $refund = $total;
                    $coachPenalty = (int) round($subtotal * 0.20);
                    $platformFeeRefundRequestedMinor = $fees;
                } else {
                    $coachComp = (int) round($subtotal * 0.20);
                    $platformEarned = $fees;

                    $clientPenalty = $fees + $coachComp;
                    $refund = max(0, $total - $clientPenalty);
                    $platformFeeRefundRequestedMinor = 0;
                }
            }

            $coachId = (int) ($reservation->coach_id ?? ($reservation->service?->coach_id ?? 0));

            if ($coachPenalty > 0 && $coachId > 0) {
                $alreadyPenalty = WalletTransaction::where('reservation_id', $reservation->id)
                    ->where('user_id', $coachId)
                    ->where('type', 'debit')
                    ->where('balance_type', WalletService::BAL_WITHDRAW)
                    ->where('reason', 'cancel_penalty')
                    ->exists();

                if (! $alreadyPenalty) {
                    app(WalletService::class)->debit(
                        $coachId,
                        $coachPenalty,
                        'cancel_penalty',
                        $reservation->id,
                        $paymentId,
                        [
                            'rule' => 'coach_cancel_penalty',
                            'by' => $by,
                            'hours_until' => $hoursUntil,
                        ],
                        $currency,
                        WalletService::BAL_WITHDRAW,
                        true
                    );
                }
            }

            if ($coachComp > 0 && $coachId > 0) {
                $alreadyComp = WalletTransaction::where('reservation_id', $reservation->id)
                    ->where('user_id', $coachId)
                    ->where('type', 'credit')
                    ->where('balance_type', WalletService::BAL_WITHDRAW)
                    ->where('reason', 'cancel_compensation')
                    ->exists();

                if (! $alreadyComp) {
                    app(WalletService::class)->creditWithdrawable(
                        $coachId,
                        $coachComp,
                        'cancel_compensation',
                        $reservation->id,
                        $paymentId,
                        [
                            'rule' => 'client_cancel_compensation',
                            'by' => $by,
                            'hours_until' => $hoursUntil,
                        ],
                        $currency
                    );
                }
            }

            $method = strtolower((string) ($reservation->refund_method ?? ''));
            if (! in_array($method, ['wallet_credit', 'original_payment'], true)) {
                $method = '';
            }

            if ($isWalletOnly) {
                $reservation->refund_method = 'wallet_credit';
                $method = 'wallet_credit';
            }

            $refundStatus = 'none';

            if ($refund > 0) {
                if ($isWalletOnly) {
                    $reservation->refund_method = 'wallet_credit';

                    $this->refundClientOnCancel($reservation, $refund, $currency, [
                        'by' => $by,
                        'hours_until' => $hoursUntil,
                        'forced_wallet' => true,
                        'immediate' => true,
                    ]);

                    $reservation->refresh();

                    $refundStatus = (string) ($reservation->refund_status ?? 'processing');
                    $reservation->refund_requested_at = $reservation->refund_requested_at ?? now();
                } else {
                    if ($method) {
                        $this->refundClientOnCancel($reservation, $refund, $currency, [
                            'by' => $by,
                            'hours_until' => $hoursUntil,
                            'immediate' => true,
                        ]);

                        $reservation->refresh();

                        $refundStatus = (string) ($reservation->refund_status ?? 'processing');
                        $reservation->refund_requested_at = $reservation->refund_requested_at ?? now();
                    } else {
                        $refundStatus = 'pending_choice';
                        $reservation->refund_method = null;
                        $reservation->refund_requested_at = now();
                    }
                }
            }

            $reservation->refresh();

            $isFullRefund = ($refund > 0 && $refund === $total);

            if ($refund <= 0) {
                $refundStatus = 'none';
                $reservation->refund_method = null;
                $reservation->refund_requested_at = null;
                $platformFeeRefundRequestedMinor = 0;
            }

            $finalRefundStatus = (string) ($reservation->refund_status ?? $refundStatus);

            $settlementStatus =
                in_array($finalRefundStatus, ['pending_choice', 'processing', 'failed', 'partial'], true)
                    ? 'refund_pending'
                    : (
                        $finalRefundStatus === 'succeeded'
                            ? ($isFullRefund ? 'refunded' : 'refunded_partial')
                            : 'cancelled'
                    );

            $refundAlreadyFinalized = in_array($finalRefundStatus, ['succeeded', 'processing', 'partial', 'failed'], true);

            $reservation->forceFill([
                'status'                => 'cancelled',
                'cancelled_by'          => $by,
                'cancelled_at'          => now(),
                'cancel_reason'         => $reason,

                'refund_status'         => $refundAlreadyFinalized ? $reservation->refund_status : $refundStatus,
                'refund_total_minor'    => $refundAlreadyFinalized
                    ? (int) ($reservation->refund_total_minor ?? $refund)
                    : $refund,

                'client_penalty_minor'  => $clientPenalty,
                'coach_penalty_minor'   => $coachPenalty,

                'coach_gross_minor'      => 0,
                'coach_commission_minor' => 0,
                'coach_earned_minor'     => 0,
                'coach_net_minor'        => 0,
                'coach_comp_minor'       => $coachComp,
                'coach_comp_created_at'  => $coachComp > 0
                    ? ($reservation->coach_comp_created_at ?? now())
                    : null,

                'platform_earned_minor'               => $platformEarned,
                'platform_fee_refund_requested_minor' => $platformFeeRefundRequestedMinor,
                'platform_fee_refunded_minor'         => $refundAlreadyFinalized
                    ? (int) ($reservation->platform_fee_refunded_minor ?? 0)
                    : 0,

                'settlement_status' => $settlementStatus,
            ])->save();

            foreach ($reservation->slots as $slot) {
                $slot->session_status = 'cancelled';
                $slot->finalized_at = now();
                $slot->save();
            }

            return true;
        });
    }

    private function refundClientOnCancel(
        Reservation $reservation,
        int $refundMinor,
        string $currency,
        array $meta = []
    ): void {
        $clientId = (int) $reservation->client_id;

        $externalPayment = $reservation->externalPayment;
        $walletPayment   = $reservation->walletPayment;

        $payment   = $externalPayment ?? $walletPayment;
        $paymentId = $payment?->id;

        $method = strtolower((string) ($reservation->refund_method ?? 'wallet_credit'));
        if (! in_array($method, ['wallet_credit', 'original_payment'], true)) {
            $method = 'wallet_credit';
        }

        $walletUsedMinor   = (int) ($reservation->wallet_platform_credit_used_minor ?? 0);
        $externalPaidMinor = (int) ($reservation->externalPayment?->amount_total ?? 0);
        $feesMinor         = (int) $reservation->fees_minor;

        if ($externalPaidMinor <= 0 && $walletUsedMinor > 0) {
            $method = 'wallet_credit';
        }

        $alreadyWallet = WalletTransaction::where('reservation_id', $reservation->id)
            ->where('user_id', $clientId)
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_PLATFORM)
            ->where('reason', 'cancel_refund')
            ->exists();

        $alreadyExternal = WalletTransaction::where('reservation_id', $reservation->id)
            ->where('user_id', $clientId)
            ->where('type', 'credit')
            ->where('balance_type', 'external')
            ->where('reason', 'cancel_refund_external')
            ->exists();

        $byMeta = strtolower((string) ($meta['by'] ?? ''));
        $byDb   = strtolower((string) ($reservation->cancelled_by ?? ''));

        $feesRefundable = in_array($byMeta, ['coach', 'admin', 'system'], true)
            || in_array($byDb, ['coach', 'admin', 'system'], true);

        [$walletPart, $externalPart, $splitMeta] = app(RefundSplitService::class)->computeRefundSplit(
            $walletUsedMinor,
            $externalPaidMinor,
            $feesMinor,
            $refundMinor,
            $method,
            $feesRefundable
        );

        $actualFeeRefundMinor = $feesRefundable
            ? min($feesMinor, $walletPart + $externalPart)
            : 0;

        $reservation->refund_wallet_minor = $walletPart;
        $reservation->refund_external_minor = $externalPart;
        $reservation->platform_fee_refunded_minor = $actualFeeRefundMinor;
        $reservation->save();

        $refund = Refund::create([
            'reservation_id'         => $reservation->id,
            'payment_id'             => $paymentId,
            'requested_by_user_id'   => $clientId,
            'provider'               => $payment?->provider,
            'method'                 => $method,
            'requested_amount_minor' => $refundMinor,
            'actual_amount_minor'    => $walletPart + $externalPart,
            'wallet_amount_minor'    => $walletPart,
            'external_amount_minor'  => $externalPart,
            'currency'               => $currency,
            'status'                 => 'processing',
            'wallet_status'          => $walletPart > 0 ? 'pending' : 'not_applicable',
            'external_status'        => $externalPart > 0 ? 'pending' : 'not_applicable',
            'provider_order_id'      => $payment?->provider_order_id,
            'provider_capture_id'    => $payment?->provider_capture_id,
            'requested_at'           => now(),
            'meta' => [
                'cancel_reason' => $meta['by'] ?? null,
                'hours_until'   => $meta['hours_until'] ?? null,
                'split'         => $splitMeta,
            ],
        ]);

        if ($walletPart > 0) {
            if (! $alreadyWallet) {
                try {
                    app(WalletService::class)->creditPlatform(
                        $clientId,
                        $walletPart,
                        'cancel_refund',
                        $reservation->id,
                        $paymentId,
                        $meta + [
                            'refund_method' => $method,
                            'split' => $splitMeta,
                        ],
                        $currency
                    );

                    $refund->wallet_status = 'succeeded';
                } catch (\Throwable $e) {
                    $refund->wallet_status = 'failed';
                }
            } else {
                $refund->wallet_status = 'succeeded';
            }
        }

        if ($externalPart > 0) {
            if (! $payment || ! in_array(strtolower((string) $payment->provider), ['paypal', 'stripe'], true)) {
                $refund->external_status = 'failed';
            } elseif ($alreadyExternal) {
                $refund->external_status = 'succeeded';
            } else {
                $result = app(PaymentRefundService::class)->refundToOriginal($payment, $externalPart, 'cancel_refund');

                $ok = (bool) ($result['ok'] ?? false);
                $providerRefundId = (string) ($result['provider_refund_id'] ?? '');

                $refund->external_status = $ok ? 'succeeded' : 'failed';
                $refund->provider_refund_id = $providerRefundId ?: $refund->provider_refund_id;

                if ($ok) {
                    $payment->refunded_minor = (int) ($payment->refunded_minor ?? 0) + $externalPart;
                    $payment->provider_refund_id = $providerRefundId ?: ($payment->provider_refund_id ?? null);
                    $payment->refund_status = 'succeeded';
                    $payment->refunded_at = $payment->refunded_at ?? now();
                    $payment->save();
                } else {
                    $payment->refund_status = 'failed';
                    $payment->save();
                }

                WalletTransaction::create([
                    'user_id'             => $clientId,
                    'type'                => 'credit',
                    'balance_type'        => 'external',
                    'reason'              => 'cancel_refund_external',
                    'payment_id'          => $payment->id,
                    'reservation_id'      => $reservation->id,
                    'amount_minor'        => $externalPart,
                    'balance_after_minor' => 0,
                    'currency'            => $currency,
                    'meta' => [
                        'refund_method'      => 'original_payment',
                        'provider'           => $payment->provider ?? null,
                        'provider_refund_id' => $providerRefundId ?: null,
                        'ok'                 => $ok,
                        'error'              => $result['error'] ?? null,
                        'split'              => $splitMeta,
                    ] + $meta,
                ]);
            }
        }

        if ($externalPart <= 0) {
            $refund->external_status = 'not_applicable';
        }

        if (
            in_array($refund->wallet_status, ['succeeded', 'not_applicable'], true) &&
            in_array($refund->external_status, ['succeeded', 'not_applicable'], true)
        ) {
            $refund->status = 'succeeded';
        } elseif (
            $refund->wallet_status === 'failed' ||
            $refund->external_status === 'failed'
        ) {
            $refund->status = 'failed';
        } else {
            $refund->status = 'partial';
        }

        $refund->processed_at = now();
        $refund->save();

        $succeededWalletMinor = $refund->wallet_status === 'succeeded'
            ? (int) $refund->wallet_amount_minor
            : 0;

        $succeededExternalMinor = $refund->external_status === 'succeeded'
            ? (int) $refund->external_amount_minor
            : 0;

        $succeededTotalMinor = $succeededWalletMinor + $succeededExternalMinor;

        $requestedPlatformFeeRefundMinor = (int) ($reservation->platform_fee_refund_requested_minor ?? 0);
        $actualPlatformFeeRefundedMinor = min($requestedPlatformFeeRefundMinor, $succeededTotalMinor);

        $reservation->refund_method = $method;
        $reservation->refund_wallet_minor = $succeededWalletMinor;
        $reservation->refund_external_minor = $succeededExternalMinor;
        $reservation->refund_total_minor = $succeededTotalMinor;
        $reservation->platform_fee_refunded_minor = $actualPlatformFeeRefundedMinor;

        if ($refund->status === 'succeeded') {
            $reservationTotalMinor = (int) ($reservation->total_minor ?? 0);
            $isFullRefund = $succeededTotalMinor > 0
                && $reservationTotalMinor > 0
                && $succeededTotalMinor === $reservationTotalMinor;

            $reservation->refund_status = 'succeeded';
            $reservation->refund_processed_at = now();
            $reservation->refund_error = null;
            $reservation->settlement_status = $isFullRefund ? 'refunded' : 'refunded_partial';
        } elseif ($refund->status === 'failed') {
            $reservation->refund_status = 'failed';
            $reservation->refund_processed_at = now();
            $reservation->refund_error = 'Refund failed';
            $reservation->settlement_status = 'refund_pending';
        } else {
            $reservation->refund_status = 'partial';
            $reservation->refund_processed_at = now();
            $reservation->refund_error = null;
            $reservation->settlement_status = 'refund_pending';
        }

        $reservation->save();
    }

    public function quote(Reservation $reservation, string $by): ?array
    {
        if (! $this->canCancel($reservation)) {
            return null;
        }

        $reservation->loadMissing(['slots', 'externalPayment', 'walletPayment']);

        $firstStart = $reservation->slots->min('start_utc');
        if (! $firstStart) {
            return null;
        }

        $firstStartUtc = CarbonImmutable::parse($firstStart)->utc();
        $nowUtc = CarbonImmutable::now('UTC');
        $hoursUntil = $nowUtc->diffInRealHours($firstStartUtc, false);

        $subtotal = (int) $reservation->subtotal_minor;
        $fees     = (int) $reservation->fees_minor;
        $total    = (int) $reservation->total_minor;

        $refund         = 0;
        $clientPenalty  = 0;
        $coachPenalty   = 0;
        $coachComp      = 0;
        $platformEarned = 0;

        if (in_array($by, ['admin', 'system'], true)) {
            $refund = $total;
        } elseif ($hoursUntil >= 48) {
            $refund = $total;
        } elseif ($hoursUntil >= 24) {
            if ($by === 'coach') {
                $refund = $total;
                $coachPenalty = (int) round($subtotal * 0.10);
            } else {
                $coachComp = (int) round($subtotal * 0.10);
                $platformEarned = $fees;
                $clientPenalty = $fees + $coachComp;
                $refund = max(0, $total - $clientPenalty);
            }
        } else {
            if ($by === 'coach') {
                $refund = $total;
                $coachPenalty = (int) round($subtotal * 0.20);
            } else {
                $coachComp = (int) round($subtotal * 0.20);
                $platformEarned = $fees;
                $clientPenalty = $fees + $coachComp;
                $refund = max(0, $total - $clientPenalty);
            }
        }

        $walletUsedMinor   = (int) ($reservation->wallet_platform_credit_used_minor ?? 0);
        $externalPaidMinor = (int) ($reservation->externalPayment?->amount_total ?? 0);

        $allowedMethods = ['wallet_credit'];

        if ($externalPaidMinor > 0) {
            $allowedMethods = ['wallet_credit', 'original_payment'];
        }

        if ($externalPaidMinor <= 0 && $walletUsedMinor > 0) {
            $allowedMethods = ['wallet_credit'];
        }

        return [
            'hours_until'            => $hoursUntil,
            'currency'               => $reservation->currency ?? 'USD',
            'subtotal_minor'         => $subtotal,
            'fees_minor'             => $fees,
            'total_minor'            => $total,
            'refund_minor'           => $refund,
            'client_penalty_minor'   => $clientPenalty,
            'coach_penalty_minor'    => $coachPenalty,
            'coach_comp_minor'       => $coachComp,
            'platform_earned_minor'  => $platformEarned,
            'refund_method'          => $reservation->refund_method ?? 'wallet_credit',
            'allowed_refund_methods' => $allowedMethods,
            'wallet_used_minor'      => $walletUsedMinor,
            'external_paid_minor'    => $externalPaidMinor,
        ];
    }
}