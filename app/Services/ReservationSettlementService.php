<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ServiceFee;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class ReservationSettlementService
{
    private array $finalStatuses = [
        'completed',
        'no_show_coach',
        'no_show_client',
        'no_show_both',
        'cancelled',
        'canceled',
    ];

    public function adminPayCoachNow(Reservation $res): void
    {
        DB::transaction(function () use ($res) {

            $res = Reservation::lockForUpdate()
                ->with(['service', 'payment'])
                ->findOrFail((int) $res->id);

            $settlement = strtolower((string) ($res->settlement_status ?? ''));

            // already financially finalized
            if (in_array($settlement, ['paid'], true)) {
                return;
            }

            // do not allow payout/release in refund/cancel tracks
            // allow in_dispute for manual admin resolution
            if (in_array($settlement, ['refund_pending', 'refunded', 'refunded_partial', 'cancelled', 'canceled'], true)) {
                return;
            }

            $gross = (int) ($res->subtotal_minor ?? 0);

            $coachFeeMinor = (int) ($res->coach_fee_minor ?? 0);

            if ($coachFeeMinor <= 0) {
                $coachFeeRow = ServiceFee::where('is_active', true)
                    ->where(function ($q) {
                        $q->where('party', 'coach')
                            ->orWhere('slug', 'coach_commission');
                    })
                    ->first();

                if ($coachFeeRow) {
                    $coachFeeMinor = $coachFeeRow->type === 'percent'
                        ? (int) round($gross * ((float) $coachFeeRow->amount / 100))
                        : (int) round(((float) $coachFeeRow->amount) * 100);
                }
            }

            $net = max(0, $gross - $coachFeeMinor);

            $coachId = (int) ($res->coach_id ?? ($res->service?->coach_id ?? 0));
            if ($coachId <= 0) {
                return;
            }

            $paymentId = $res->payment?->id;
            $currency  = $res->currency ?? 'USD';

            $alreadyReleased = WalletTransaction::where('reservation_id', $res->id)
                ->where('reason', 'coach_earnings_release')
                ->where('type', 'credit')
                ->where('balance_type', WalletService::BAL_WITHDRAW)
                ->exists();

            if (! $alreadyReleased) {
                $held = WalletTransaction::where('reservation_id', $res->id)
                    ->where('reason', 'escrow_hold')
                    ->where('type', 'credit')
                    ->where('balance_type', WalletService::BAL_ESCROW)
                    ->exists();

                if ($held) {
                    $this->releasePendingEscrowIfExists($res, $coachId, $net, $currency);
                } else {
                    if ($net > 0) {
                        app(WalletService::class)->creditWithdrawable(
                            $coachId,
                            $net,
                            'coach_earnings_release',
                            $res->id,
                            $paymentId,
                            ['rule' => 'admin_pay_coach_now'],
                            $currency
                        );
                    }
                }
            }

            $releasedNow = WalletTransaction::where('reservation_id', $res->id)
                ->where('reason', 'coach_earnings_release')
                ->where('type', 'credit')
                ->where('balance_type', WalletService::BAL_WITHDRAW)
                ->exists();

            if (! $releasedNow) {
                return;
            }

            $clientFeeMinor = (int) ($res->fees_minor ?? 0);
            $platformEarnedMinor = max(0, $clientFeeMinor + $coachFeeMinor);

            $res->forceFill([
                'settlement_status'      => 'paid',
                'coach_gross_minor'      => $gross,
                'coach_commission_minor' => $coachFeeMinor,
                'coach_earned_minor'     => $net,
                'coach_net_minor'        => $net,
                'platform_earned_minor'  => $platformEarnedMinor,
                'platform_fee_refund_requested_minor' => 0,
                'platform_fee_refunded_minor'         => 0,
            ])->save();
        });
    }

    public function recompute(int $reservationId): void
    {
        \Log::info('RECOMPUTE HIT', ['reservation_id' => $reservationId]);

        DB::transaction(function () use ($reservationId) {

            $res = Reservation::lockForUpdate()
                ->with(['service', 'payment', 'slots'])
                ->findOrFail((int) $reservationId);

            $slots = $res->slots ?? collect();
            if ($slots->isEmpty()) {
                return;
            }

            $statuses = $slots->map(fn ($s) => strtolower(trim((string) ($s->session_status ?? ''))));

            \Log::info('RECOMPUTE STATE', [
                'reservation_id'      => $res->id,
                'reservation_status'  => $res->status,
                'payment_status'      => $res->payment_status,
                'settlement_status'   => $res->settlement_status,
                'completed_at'        => $res->completed_at,
                'disputed_by_client'  => $res->disputed_by_client_at,
                'disputed_by_coach'   => $res->disputed_by_coach_at,
                'slot_statuses'       => $statuses->values()->all(),
            ]);

            $allFinal = $statuses->every(fn ($st) => in_array($st, $this->finalStatuses, true));

          if ($allFinal) {
    $resStatus = strtolower((string) ($res->status ?? ''));
    $hasCancelled = $statuses->contains(fn ($st) => in_array($st, ['cancelled', 'canceled'], true));

    \Log::info('RECOMPUTE ALL FINAL', [
        'reservation_id'     => $res->id,
        'current_status'     => $res->status,
        'current_settlement' => $res->settlement_status,
        'slot_statuses'      => $statuses->values()->all(),
        'has_cancelled_slot' => $hasCancelled,
    ]);

    if ($statuses->contains('no_show_coach') || $statuses->contains('no_show_both')) {
        if (! in_array($resStatus, ['cancelled', 'canceled', 'completed', 'no_show'], true)) {
            \Log::info('MARKING NO SHOW', [
                'reservation_id' => $res->id,
            ]);

            $res->forceFill([
                'status' => 'no_show',
            ])->save();
        }
    } elseif (! $hasCancelled && ! in_array($resStatus, ['cancelled', 'canceled', 'completed'], true)) {
        \Log::info('MARKING COMPLETED', [
            'reservation_id' => $res->id,
        ]);

        $res->forceFill([
            'status'       => 'completed',
            'completed_at' => $res->completed_at ?? now(),
        ])->save();
    }
}
            $settlement = strtolower((string) ($res->settlement_status ?? ''));

            \Log::info('CHECK TERMINAL SETTLEMENT', [
                'reservation_id'    => $res->id,
                'settlement_status' => $settlement,
            ]);

            if (in_array($settlement, [
                'paid',
                'refunded',
                'refunded_partial',
                'cancelled',
                'canceled',
                'refund_pending',
                'in_dispute',
            ], true)) {
                \Log::info('TERMINAL SETTLEMENT RETURN', [
                    'reservation_id' => $res->id,
                    'settlement'     => $settlement,
                ]);

                return;
            }

            if (strtolower((string) ($res->payment_status ?? '')) !== 'paid') {
                return;
            }

            if ($statuses->contains('no_show_coach')) {
                $this->applyCoachNoShow($res);
                return;
            }

            if ($statuses->contains('no_show_both')) {
                $this->applyBothNoShow($res);
                return;
            }

            if ($statuses->contains(fn ($st) => in_array($st, ['cancelled', 'canceled'], true))) {
    \Log::info('CANCELLED_SLOT_BLOCKS_NORMAL_SETTLEMENT', [
        'reservation_id' => $res->id,
        'slot_statuses' => $statuses->values()->all(),
    ]);

    return;
}

            if (! $allFinal) {
                return;
            }

            $hasDispute = ! empty($res->disputed_by_client_at) || ! empty($res->disputed_by_coach_at);
            if ($hasDispute) {
                $res->forceFill([
                    'settlement_status' => 'in_dispute',
                    'coach_gross_minor' => (int) $res->subtotal_minor,
                ])->save();

                return;
            }

            $lastEndRaw = $slots->max('end_utc');
            $lastEnd = $lastEndRaw
                ? \Carbon\CarbonImmutable::parse($lastEndRaw)->utc()
                : \Carbon\CarbonImmutable::now('UTC');

            $releaseAt = $lastEnd->addHours(48);

            $res->forceFill([
                'last_slot_end_utc' => $lastEnd,
                'escrow_release_at' => $releaseAt,
            ])->save();

            $clientCompleted = ! empty($res->completed_by_client_at);
            $coachCompleted  = ! empty($res->completed_by_coach_at);
            $now             = \Carbon\CarbonImmutable::now('UTC');

            if ($clientCompleted && $coachCompleted) {
                $this->applyNormalSettlement($res);
                return;
            }

            if ($clientCompleted) {
                $this->applyNormalSettlement($res);
                return;
            }

            if ($coachCompleted && ! $clientCompleted) {
                if ($now->gte($releaseAt)) {
                    $this->applyNormalSettlement($res);
                    return;
                }

                $this->ensurePendingEscrowRecorded($res);
                $res->forceFill([
                    'settlement_status' => 'pending',
                ])->save();

                return;
            }

            if (! $clientCompleted && ! $coachCompleted) {
                if ($now->gte($releaseAt)) {
                    $this->applyNormalSettlement($res);
                    return;
                }

                $this->ensurePendingEscrowRecorded($res);
                $res->forceFill([
                    'settlement_status' => 'pending',
                ])->save();

                return;
            }
        });
    }

    private function applyCoachNoShow(Reservation $res): void
    {
        $settlement = strtolower((string) ($res->settlement_status ?? ''));
        $refundStatus = strtolower((string) ($res->refund_status ?? ''));

        if ($settlement === 'refund_pending' && in_array($refundStatus, ['pending_choice', 'processing', 'partial', 'succeeded', 'failed'], true)) {
            return;
        }

        $subtotal = (int) $res->subtotal_minor;
        $total    = (int) $res->total_minor;

        $coachId = $this->coachId($res);
        if ($coachId <= 0) {
            return;
        }

        $coachPenalty = (int) round($subtotal * 0.10);

        $payment   = $res->payment;
        $paymentId = $payment?->id;
        $currency  = $res->currency ?? ($payment?->currency ?? 'USD');

        $alreadyPenalty = WalletTransaction::where('reservation_id', $res->id)
            ->where('user_id', $coachId)
            ->where('type', 'debit')
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->where('reason', 'penalty_coach_no_show_10pct')
            ->exists();

        if (! $alreadyPenalty && $coachPenalty > 0) {
            app(WalletService::class)->debit(
                $coachId,
                $coachPenalty,
                'penalty_coach_no_show_10pct',
                $res->id,
                $paymentId,
                ['rule' => 'coach_no_show_penalty_10pct'],
                $currency,
                WalletService::BAL_WITHDRAW,
                true
            );
        }

        $res->forceFill([
            'settlement_status'      => 'refund_pending',
            'status'                 => 'no_show',
            'refund_total_minor'     => $total,
            'refund_status'          => 'pending_choice',
            'refund_method'          => null,
            'refund_requested_at'    => $res->refund_requested_at ?? now(),
            'refund_processed_at'    => null,
            'refund_error'           => null,

            'client_penalty_minor'   => 0,
            'coach_penalty_minor'    => $coachPenalty,

            'coach_gross_minor'      => 0,
            'coach_commission_minor' => 0,
            'coach_earned_minor'     => 0,
            'coach_net_minor'        => 0,

            'platform_fee_minor'     => 0,
            'platform_penalty_minor' => $coachPenalty,
            'platform_earned_minor'  => $coachPenalty,

            'platform_fee_refund_requested_minor' => (int) ($res->fees_minor ?? 0),
            'platform_fee_refunded_minor'         => 0,
        ])->save();

        $this->cancelRemainingSlots($res, 'cancelled');
    }

    private function applyBothNoShow(Reservation $res): void
    {
        $settlement = strtolower((string) ($res->settlement_status ?? ''));
        $refundStatus = strtolower((string) ($res->refund_status ?? ''));

        if ($settlement === 'refund_pending' && in_array($refundStatus, ['pending_choice', 'processing', 'partial', 'succeeded', 'failed'], true)) {
            return;
        }

        $subtotal = (int) $res->subtotal_minor;
        $fees     = (int) $res->fees_minor;

        $coachId = $this->coachId($res);
        if ($coachId <= 0) {
            return;
        }

        $coachPenalty = (int) round($subtotal * 0.10);

        $payment   = $res->payment;
        $paymentId = $payment?->id;
        $currency  = $res->currency ?? ($res->payment?->currency ?? 'USD');

        $alreadyPenalty = WalletTransaction::where('reservation_id', $res->id)
            ->where('user_id', $coachId)
            ->where('type', 'debit')
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->where('reason', 'penalty_both_no_show_10pct')
            ->exists();

        if (! $alreadyPenalty && $coachPenalty > 0) {
            app(WalletService::class)->debit(
                $coachId,
                $coachPenalty,
                'penalty_both_no_show_10pct',
                $res->id,
                $paymentId,
                ['rule' => 'both_no_show_penalty_10pct'],
                $currency,
                WalletService::BAL_WITHDRAW,
                true
            );
        }

        $res->forceFill([
            'settlement_status'      => 'refund_pending',
            'status'                 => 'no_show',
            'refund_total_minor'     => $subtotal,
            'refund_status'          => 'pending_choice',
            'refund_method'          => null,
            'refund_requested_at'    => $res->refund_requested_at ?? now(),
            'refund_processed_at'    => null,
            'refund_error'           => null,

            'client_penalty_minor'   => 0,
            'coach_penalty_minor'    => $coachPenalty,

            'coach_gross_minor'      => 0,
            'coach_commission_minor' => 0,
            'coach_earned_minor'     => 0,
            'coach_net_minor'        => 0,

            'platform_fee_minor'     => $fees,
            'platform_penalty_minor' => $coachPenalty,
            'platform_earned_minor'  => ($fees + $coachPenalty),

            'platform_fee_refund_requested_minor' => 0,
            'platform_fee_refunded_minor'         => 0,
        ])->save();

        $this->cancelRemainingSlots($res, 'cancelled');
    }

    private function applyNormalSettlement(Reservation $res): void
    {
        $gross = (int) $res->subtotal_minor;

        $coachFeeMinor = (int) ($res->coach_fee_minor ?? 0);

        if ($coachFeeMinor <= 0) {
            $coachFeeRow = ServiceFee::where('is_active', true)
                ->where(function ($q) {
                    $q->where('party', 'coach')
                        ->orWhere('slug', 'coach_commission');
                })
                ->first();

            if ($coachFeeRow) {
                $coachFeeMinor = $coachFeeRow->type === 'percent'
                    ? (int) round($gross * ((float) $coachFeeRow->amount / 100))
                    : (int) round(((float) $coachFeeRow->amount) * 100);
            }
        }

        $net = max(0, $gross - $coachFeeMinor);

        $clientFeeMinor = (int) ($res->fees_minor ?? 0);
        $platformEarnedMinor = max(0, $clientFeeMinor + $coachFeeMinor);

        $coachId = $this->coachId($res);
        if ($coachId <= 0) {
            return;
        }

        $paymentId = $res->payment?->id;
        $currency  = $res->currency ?? ($res->payment?->currency ?? 'USD');

        $already = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'coach_earnings_release')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->exists();

        if ($already) {
            $res->forceFill([
                'settlement_status'      => 'paid',
                'coach_gross_minor'      => $gross,
                'coach_commission_minor' => $coachFeeMinor,
                'coach_earned_minor'     => $net,
                'coach_net_minor'        => $net,
                'platform_fee_minor'     => $platformEarnedMinor,
                'platform_penalty_minor' => 0,
                'platform_earned_minor'  => $platformEarnedMinor,
                'platform_fee_refund_requested_minor' => 0,
                'platform_fee_refunded_minor'         => 0,
                'client_penalty_minor'   => 0,
                'coach_penalty_minor'    => 0,
            ])->save();

            return;
        }

        $this->releasePendingEscrowIfExists($res, $coachId, $net, $currency);

        $held = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'escrow_hold')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_ESCROW)
            ->exists();

        $released = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'coach_earnings_release')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->exists();

        if (! $held && ! $released && $net > 0) {
            app(WalletService::class)->creditWithdrawable(
                $coachId,
                $net,
                'coach_earnings_release',
                $res->id,
                $paymentId,
                ['rule' => 'normal_settlement', 'net_rule' => 'gross_minus_platform_fee'],
                $currency
            );
        }

        $finalReleased = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'coach_earnings_release')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->exists();

        if (! $finalReleased) {
            return;
        }

        $update = [
            'settlement_status'      => 'paid',
            'client_penalty_minor'   => 0,
            'coach_penalty_minor'    => 0,
            'platform_fee_minor'     => $platformEarnedMinor,
            'platform_penalty_minor' => 0,
            'platform_earned_minor'  => $platformEarnedMinor,
            'platform_fee_refund_requested_minor' => 0,
            'platform_fee_refunded_minor'         => 0,
            'coach_gross_minor'      => $gross,
            'coach_commission_minor' => $coachFeeMinor,
            'coach_earned_minor'     => $net,
            'coach_net_minor'        => $net,
        ];

        $res->forceFill($update)->save();
    }

    private function ensurePendingEscrowRecorded(Reservation $res): void
    {
        $gross = (int) $res->subtotal_minor;
        $coachFeeMinor = (int) ($res->coach_fee_minor ?? 0);
        $net = max(0, $gross - $coachFeeMinor);

        if ($net <= 0) {
            return;
        }

        $coachId = $this->coachId($res);
        if ($coachId <= 0) {
            return;
        }

        $paymentId = $res->payment?->id;
        $currency  = $res->currency ?? ($res->payment?->currency ?? 'USD');

        $alreadyHold = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'escrow_hold')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_ESCROW)
            ->exists();

        if ($alreadyHold) {
            return;
        }

        app(WalletService::class)->credit(
            $coachId,
            $net,
            'escrow_hold',
            $res->id,
            $paymentId,
            ['rule' => 'hold_until_release'],
            $currency,
            WalletService::BAL_ESCROW
        );
    }

    private function releasePendingEscrowIfExists(Reservation $res, int $coachId, int $net, string $currency): void
    {
        $paymentId = $res->payment?->id;

        $held = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'escrow_hold')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_ESCROW)
            ->exists();

        if (! $held) {
            return;
        }

        $released = WalletTransaction::where('reservation_id', $res->id)
            ->where('reason', 'coach_earnings_release')
            ->where('type', 'credit')
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->exists();

        if ($released) {
            return;
        }

        app(WalletService::class)->debit(
            $coachId,
            $net,
            'escrow_release_debit_pending',
            $res->id,
            $paymentId,
            ['rule' => 'release_pending_to_withdrawable'],
            $currency,
            WalletService::BAL_ESCROW,
            true
        );

        app(WalletService::class)->creditWithdrawable(
            $coachId,
            $net,
            'coach_earnings_release',
            $res->id,
            $paymentId,
            ['rule' => 'release_pending_to_withdrawable'],
            $currency
        );
    }

    private function cancelRemainingSlots(Reservation $res, string $reason, ?\Carbon\CarbonImmutable $now = null): void
    {
        $now = $now ?: \Carbon\CarbonImmutable::now('UTC');

        $res->loadMissing('slots');

        $res->slots()
            ->whereNull('finalized_at')
            ->update([
                'session_status' => $reason,
                'finalized_at'   => $now,
            ]);
    }

    private function coachId(Reservation $res): int
    {
        $cid = (int) ($res->coach_id ?? 0);
        if ($cid > 0) {
            return $cid;
        }

        return (int) ($res->service?->coach_id ?? 0);
    }

    private function refundClientForDispute(
        Reservation $res,
        int $requestedRefundMinor,
        bool $feesRefundable,
        string $reasonBase
    ): void {
        $res->loadMissing(['payments']);

        $walletUsedMinor = (int) ($res->wallet_platform_credit_used_minor ?? 0);
        $externalPaidMinor = (int) $res->payments()
            ->whereIn('provider', ['stripe', 'paypal'])
            ->where('status', 'succeeded')
            ->sum('amount_total');
        $feesMinor = (int) ($res->fees_minor ?? 0);

        $method = strtolower((string) ($res->refund_method ?? 'wallet_credit'));
        if (! in_array($method, ['wallet_credit', 'original_payment'], true)) {
            $method = 'wallet_credit';
        }

        if ($externalPaidMinor <= 0 && $walletUsedMinor > 0) {
            $method = 'wallet_credit';
        }

        [$walletPart, $externalPart, $splitMeta] = app(RefundSplitService::class)->computeRefundSplit(
            $walletUsedMinor,
            $externalPaidMinor,
            $feesMinor,
            $requestedRefundMinor,
            $method,
            $feesRefundable
        );

        $actualRefundMinor = (int) ($walletPart + $externalPart);

        $res->forceFill([
            'refund_method'         => $method,
            'refund_total_minor'    => $actualRefundMinor,
            'refund_wallet_minor'   => $walletPart,
            'refund_external_minor' => $externalPart,
            'refund_requested_at'   => $res->refund_requested_at ?? now(),
            'refund_processed_at'   => null,
            'refund_error'          => null,
            'refund_status'         => 'pending_choice',
            'settlement_status'     => 'refund_pending',
            'platform_earned_minor' => $feesRefundable ? 0 : (int) ($res->fees_minor ?? 0),
            'coach_earned_minor'    => 0,
            'coach_net_minor'       => 0,
            'platform_fee_refund_requested_minor' => $feesRefundable ? (int) ($res->fees_minor ?? 0) : 0,
            'platform_fee_refunded_minor'         => 0,
        ])->save();

        if ($externalPaidMinor <= 0 && $walletUsedMinor > 0) {
            app(RefundChoiceService::class)->process($res->fresh(), 'wallet_credit', null);
        }
    }

    public function adminRefundFullAmountDecision(Reservation $reservation, int $byAdminId): array
    {
        return $this->adminRefundDecision(
            $reservation,
            (int) ($reservation->total_minor ?? 0),
            true,
            $byAdminId
        );
    }

    public function adminRefundServiceOnlyDecision(Reservation $reservation, int $byAdminId): array
    {
        return $this->adminRefundDecision(
            $reservation,
            (int) ($reservation->subtotal_minor ?? 0),
            false,
            $byAdminId
        );
    }

    private function adminRefundDecision(
        Reservation $reservation,
        int $requestedRefundMinor,
        bool $feesRefundable,
        int $byAdminId
    ): array {
        return DB::transaction(function () use ($reservation, $requestedRefundMinor, $feesRefundable, $byAdminId) {

            $res = Reservation::lockForUpdate()
                ->with(['externalPayment', 'walletPayment', 'payment', 'service'])
                ->findOrFail((int) $reservation->id);

            if (strtolower((string) ($res->payment_status ?? '')) !== 'paid') {
                return ['ok' => false, 'message' => 'Booking is not paid.'];
            }

            if ($requestedRefundMinor <= 0) {
                return ['ok' => false, 'message' => 'Refund amount is 0.'];
            }

            $coachReleased = WalletTransaction::where('reservation_id', $res->id)
                ->where('reason', 'coach_earnings_release')
                ->where('type', 'credit')
                ->where('balance_type', WalletService::BAL_WITHDRAW)
                ->exists();

            if ($coachReleased) {
                return ['ok' => false, 'message' => 'Cannot refund: coach earnings already released.'];
            }

            $walletUsedMinor = (int) ($res->wallet_platform_credit_used_minor ?? 0);

            $externalPaidMinor = (int) $res->payments()
                ->whereIn('provider', ['stripe', 'paypal'])
                ->where('status', 'succeeded')
                ->sum('amount_total');

            $feesMinor = (int) ($res->fees_minor ?? 0);

            $isWalletOnly = ($walletUsedMinor > 0 && $externalPaidMinor <= 0);

            $chosenMethod = strtolower((string) ($res->refund_method ?? ''));
            if (! in_array($chosenMethod, ['wallet_credit', 'original_payment'], true)) {
                $chosenMethod = 'wallet_credit';
            }
            if ($isWalletOnly) {
                $chosenMethod = 'wallet_credit';
            }

            [$walletPart, $externalPart] = app(RefundSplitService::class)->computeRefundSplit(
                $walletUsedMinor,
                $externalPaidMinor,
                $feesMinor,
                $requestedRefundMinor,
                $chosenMethod,
                $feesRefundable
            );

            $actualRefundMinor = (int) ($walletPart + $externalPart);

            $res->forceFill([
                'refund_total_minor'    => $actualRefundMinor,
                'refund_wallet_minor'   => $walletPart,
                'refund_external_minor' => $externalPart,
                'refund_requested_at'   => $res->refund_requested_at ?? now(),
                'refund_processed_at'   => null,
                'refund_error'          => null,
                'settlement_status'     => 'refund_pending',
                'refund_status'         => 'pending_choice',
                'refund_method'         => $isWalletOnly ? 'wallet_credit' : null,
                'platform_earned_minor' => $feesRefundable ? 0 : (int) ($res->fees_minor ?? 0),
                'coach_earned_minor'    => 0,
                'coach_net_minor'       => 0,
                'platform_fee_refund_requested_minor' => $feesRefundable ? (int) ($res->fees_minor ?? 0) : 0,
                'platform_fee_refunded_minor'         => 0,
            ])->save();

            if ($isWalletOnly) {
                $res->refund_method = 'wallet_credit';
                $res->save();

                return app(RefundChoiceService::class)->process($res, 'wallet_credit', $byAdminId);
            }

            $method = strtolower((string) ($res->refund_method ?? ''));
            if (in_array($method, ['wallet_credit', 'original_payment'], true)) {
                return app(RefundChoiceService::class)->process($res, $method, $byAdminId);
            }

            return [
                'ok' => true,
                'message' => 'Refund is pending client choice (wallet or original payment).',
            ];
        });
    }
}