<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ServiceFee;
use App\Models\WalletTransaction;
use App\Models\Users;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscrowService
{
    /**
     * Reservation-level states that must NEVER auto-complete or release coach funds.
     */
    private array $blockedReservationStatuses = [
        'cancelled',
        'canceled',
        'no_show',
    ];

    private array $blockedSettlementStatuses = [
        'cancelled',
        'canceled',
        'refund_pending',
        'refunded',
        'refunded_partial',
        'in_dispute',
    ];

    /**
     * Slot-level states that block normal escrow release.
     *
     * NOTE:
     * - no_show_client is intentionally NOT blocked,
     *   because by your rule that session is still payout-eligible.
     */
    private array $blockedSlotStatuses = [
        'cancelled',
        'canceled',
        'no_show_coach',
        'no_show_both',
    ];

    /**
     * Release escrow for a reservation:
     * - Only when BOTH completed and no dispute
     * - NEVER for cancelled / refund / dispute tracks
     * - NEVER when blocked slot states exist
     * - Moves funds from coach pending escrow -> coach withdrawable balance
     * - Writes a ledger entry (wallet_transactions) with balance_type=withdrawable
     * - Marks payment escrow_released
     * - Marks reservation completed + settlement_status paid
     */
    public function releaseReservation(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation) {

            $reservation = Reservation::with([
                    'service.coach',
                    'externalPayment',
                    'walletPayment',
                    'payment',
                    'slots',
                ])
                ->lockForUpdate()
                ->find($reservation->id);

            if (! $reservation) {
                return false;
            }

            $status = strtolower(trim((string) ($reservation->status ?? '')));
            $settlement = strtolower(trim((string) ($reservation->settlement_status ?? '')));

            if ($this->isBlockedForRelease($reservation, $status, $settlement)) {
                Log::info('ESCROW_RELEASE_BLOCKED_STATE', [
                    'reservation_id' => $reservation->id,
                    'status' => $status,
                    'settlement_status' => $settlement,
                    'cancelled_at' => $reservation->cancelled_at,
                ]);

                return false;
            }

            $payment = $reservation->externalPayment ?? $reservation->walletPayment ?? $reservation->payment;
            if (! $payment) {
                Log::warning('ESCROW_RELEASE_NO_PAYMENT', [
                    'reservation_id' => $reservation->id,
                ]);
                return false;
            }

            if (($payment->status ?? null) === 'escrow_released') {
                return false;
            }

            if (strtolower((string) ($reservation->payment_status ?? '')) !== 'paid') {
                return false;
            }

            $coachId = (int) ($reservation->service?->coach_id ?? $reservation->coach_id ?? 0);
            if ($coachId <= 0) {
                return false;
            }

            /** @var Users|null $coach */
            $coach = Users::lockForUpdate()->find($coachId);
            if (! $coach) {
                Log::warning('ESCROW_RELEASE_MISSING_COACH', [
                    'reservation_id' => $reservation->id,
                    'coach_id' => $coachId,
                ]);
                return false;
            }

            $hasDispute = ! empty($reservation->disputed_by_client_at) || ! empty($reservation->disputed_by_coach_at);
            if ($hasDispute) {
                return false;
            }

            $bothCompleted = ! empty($reservation->completed_by_client_at) && ! empty($reservation->completed_by_coach_at);
            if (! $bothCompleted) {
                return false;
            }

            $slotStatuses = collect($reservation->slots ?? [])
                ->map(fn ($slot) => strtolower(trim((string) ($slot->session_status ?? ''))));

            if ($slotStatuses->contains(fn ($st) => in_array($st, $this->blockedSlotStatuses, true))) {
                Log::info('ESCROW_RELEASE_BLOCKED_SLOT_STATE', [
                    'reservation_id' => $reservation->id,
                    'slot_statuses' => $slotStatuses->values()->all(),
                ]);

                return false;
            }

            $already = WalletTransaction::where('reservation_id', $reservation->id)
                ->where('user_id', $coach->id)
                ->whereIn('reason', ['escrow_release', 'coach_earnings_release'])
                ->where('type', 'credit')
                ->whereIn('balance_type', ['withdrawable', WalletService::BAL_WITHDRAW])
                ->exists();

            if ($already) {
                Log::info('ESCROW_RELEASE_ALREADY_EXISTS', [
                    'reservation_id' => $reservation->id,
                    'coach_id' => $coach->id,
                ]);
                return false;
            }

            $serviceSubtotalMinor = (int) ($reservation->subtotal_minor ?? 0);
            if ($serviceSubtotalMinor <= 0) {
                Log::warning('ESCROW_RELEASE_NON_POSITIVE_SUBTOTAL', [
                    'reservation_id' => $reservation->id,
                    'subtotal_minor' => $serviceSubtotalMinor,
                ]);
                return false;
            }

            $coachFeeMinor = (int) ($reservation->coach_fee_minor ?? 0);
            $coachFeePercent = 0.0;

            if ($coachFeeMinor <= 0) {
                $coachFeePercent = (float) (ServiceFee::where('slug', 'coach_commission')
                    ->where('is_active', 1)
                    ->value('amount') ?? 0);

                $coachFeeMinor = (int) round($serviceSubtotalMinor * ($coachFeePercent / 100));
            } else {
                $coachFeePercent = (float) ($reservation->coach_fee_amount ?? 0);
            }

            $coachNetMinor = max(0, $serviceSubtotalMinor - $coachFeeMinor);

            if ($coachNetMinor <= 0) {
                Log::warning('ESCROW_RELEASE_NON_POSITIVE_NET', [
                    'reservation_id' => $reservation->id,
                    'subtotal_minor' => $serviceSubtotalMinor,
                    'coach_fee_minor' => $coachFeeMinor,
                ]);
                return false;
            }

            $pending = (int) ($coach->pending_escrow_minor ?? 0);
            $withdrawable = (int) ($coach->withdrawable_minor ?? 0);

            $coach->pending_escrow_minor = max(0, $pending - $coachNetMinor);
            $coach->withdrawable_minor = $withdrawable + $coachNetMinor;
            $coach->save();

            WalletTransaction::create([
                'user_id'             => $coach->id,
                'type'                => 'credit',
                'balance_type'        => 'withdrawable',
                'reason'              => 'escrow_release',
                'payment_id'          => $payment->id,
                'reservation_id'      => $reservation->id,
                'payout_id'           => null,
                'amount_minor'        => $coachNetMinor,
                'balance_after_minor' => (int) $coach->withdrawable_minor,
                'currency'            => $payment->currency ?? $reservation->currency ?? 'USD',
                'meta'                => [
                    'coach_fee_percent' => $coachFeePercent,
                    'coach_fee_minor'   => $coachFeeMinor,
                    'source'            => $payment->provider ?? null,
                    'moved_from'        => 'pending_escrow_minor',
                    'moved_to'          => 'withdrawable_minor',
                    'service'           => 'EscrowService',
                ],
            ]);

            $payment->status = 'escrow_released';
            $payment->escrow_released_at = now();
            $payment->coach_earnings = $coachNetMinor;
            $payment->platform_fee = max(0, (int) ($reservation->total_minor ?? 0) - $coachNetMinor);
            $payment->save();

            $clientFeeMinor = (int) ($reservation->fees_minor ?? 0);
            $platformEarnedMinor = max(0, $clientFeeMinor + $coachFeeMinor);

            $reservation->status = 'completed';
            $reservation->settlement_status = 'paid';
            $reservation->completed_at = $reservation->completed_at ?? now();
            $reservation->coach_gross_minor = $serviceSubtotalMinor;
            $reservation->coach_commission_minor = $coachFeeMinor;
            $reservation->coach_earned_minor = $coachNetMinor;
            $reservation->coach_net_minor = $coachNetMinor;
            $reservation->platform_fee_minor = $platformEarnedMinor;
            $reservation->platform_penalty_minor = 0;
            $reservation->platform_earned_minor = $platformEarnedMinor;
            $reservation->platform_fee_refund_requested_minor = 0;
            $reservation->platform_fee_refunded_minor = 0;
            $reservation->client_penalty_minor = 0;
            $reservation->coach_penalty_minor = 0;
            $reservation->save();

            Log::info('ESCROW_RELEASE_SUCCESS', [
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'coach_id' => $coach->id,
                'coach_net_minor' => $coachNetMinor,
            ]);

            return true;
        });
    }

    /**
     * Auto complete if expired (48h after last slot end) and no dispute:
     * - marks both completed
     * - triggers releaseReservation()
     * - NEVER runs for cancelled / refund / dispute / blocked slot tracks
     */
    public function autoCompleteIfExpired(Reservation $reservation): bool
    {
        $shouldRelease = DB::transaction(function () use ($reservation) {

            $reservation = Reservation::with([
                    'slots',
                    'externalPayment',
                    'walletPayment',
                    'payment',
                ])
                ->lockForUpdate()
                ->find($reservation->id);

            if (! $reservation) {
                return false;
            }

            $status = strtolower(trim((string) ($reservation->status ?? '')));
            $settlement = strtolower(trim((string) ($reservation->settlement_status ?? '')));

            if ($this->isBlockedForRelease($reservation, $status, $settlement)) {
                Log::info('AUTO_COMPLETE_BLOCKED_STATE', [
                    'reservation_id' => $reservation->id,
                    'status' => $status,
                    'settlement_status' => $settlement,
                    'cancelled_at' => $reservation->cancelled_at,
                ]);

                return false;
            }

            if (! empty($reservation->disputed_by_client_at) || ! empty($reservation->disputed_by_coach_at)) {
                return false;
            }

            if (strtolower((string) ($reservation->payment_status ?? '')) !== 'paid') {
                return false;
            }

            $payment = $reservation->externalPayment ?? $reservation->walletPayment ?? $reservation->payment;
            if (! $payment || ($payment->status ?? null) === 'escrow_released') {
                return false;
            }

            $slots = $reservation->slots ?? collect();
            if ($slots->isEmpty()) {
                return false;
            }

            $slotStatuses = $slots->map(fn ($slot) => strtolower(trim((string) ($slot->session_status ?? ''))));

            if ($slotStatuses->contains(fn ($st) => in_array($st, $this->blockedSlotStatuses, true))) {
                Log::info('AUTO_COMPLETE_BLOCKED_SLOT_STATE', [
                    'reservation_id' => $reservation->id,
                    'slot_statuses' => $slotStatuses->values()->all(),
                ]);

                return false;
            }

            $lastEndRaw = $slots->max('end_utc');
            if (! $lastEndRaw) {
                return false;
            }

            $lastEnd = CarbonImmutable::parse($lastEndRaw)->utc();
            $expiresAt = $lastEnd->addHours(48);

            if (CarbonImmutable::now('UTC')->lt($expiresAt)) {
                return false;
            }

            if (empty($reservation->completed_by_client_at)) {
                $reservation->completed_by_client_at = now();
            }

            if (empty($reservation->completed_by_coach_at)) {
                $reservation->completed_by_coach_at = now();
            }

            $reservation->save();

            Log::info('AUTO_COMPLETE_MARKED_COMPLETE', [
                'reservation_id' => $reservation->id,
                'expires_at' => (string) $expiresAt,
            ]);

            return true;
        });

        if (! $shouldRelease) {
            return false;
        }

        return $this->releaseReservation($reservation);
    }

    private function isBlockedForRelease(Reservation $reservation, ?string $status = null, ?string $settlement = null): bool
    {
        $status = $status ?? strtolower(trim((string) ($reservation->status ?? '')));
        $settlement = $settlement ?? strtolower(trim((string) ($reservation->settlement_status ?? '')));

        if (in_array($status, $this->blockedReservationStatuses, true)) {
            return true;
        }

        if (in_array($settlement, $this->blockedSettlementStatuses, true)) {
            return true;
        }

        if (! empty($reservation->cancelled_at)) {
            return true;
        }

        return false;
    }
}