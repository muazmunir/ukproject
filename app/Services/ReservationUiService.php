<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use Carbon\Carbon;

class ReservationUiService
{
    /**
     * Slot states that mean “this slot is finished / finalized”
     */
    private array $finalStatuses = [
        'completed',
        'no_show_coach',
        'no_show_client',
        'no_show_both',
        'cancelled',
        'canceled',
    ];

    /**
     * Slot states that should count as “completed” for UI completion rules.
     * ✅ Your rule: no_show_client should be treated like completed.
     */
    private array $completedLikeStates = [
        'completed',
        'no_show_client',
    ];

    /**
     * If ANY slot is in these states, we must NOT show “Mark as complete”
     * (refund-track / cancelled track)
     */
    private array $blockCompleteStates = [
        'no_show_coach',
        'no_show_both',
        'cancelled',
        'canceled',
    ];

    private function norm(?string $v): string
    {
        return strtolower(trim((string) $v));
    }

    /**
     * Prefer end_utc for "real end", fallback finalized_at, fallback start_utc.
     */
    private function slotEndMomentUtc(ReservationSlot $slot): ?Carbon
    {
        $t = $slot->end_utc ?? $slot->finalized_at ?? $slot->start_utc ?? null;
        return $t ? Carbon::parse($t, 'UTC')->utc() : null;
    }

    /**
     * Finalize slot if its end moment has passed and status is not final.
     * This prevents “stuck” bookings because of unexpected session_status strings.
     */
   private function finalizeSlotIfEnded(ReservationSlot $slot): bool
{
    $end = $this->slotEndMomentUtc($slot);
    if (! $end) return false;

    $now = now('UTC');

    if ($now->lt($end)) return false;

    $status = $this->norm($slot->session_status);

    if (in_array($status, $this->finalStatuses, true)) return false;

    $autoCompleteStates = ['in_progress', 'live', 'started', 'ongoing'];

    if (in_array($status, $autoCompleteStates, true)) {
        $slot->session_status = 'completed';
       $slot->finalized_at   = $slot->finalized_at ?? now('UTC');
        $slot->save();
        return true;
    }

    if ($status === '' || $status === 'booked' || $status === 'confirmed') {
        $slot->session_status = 'completed';
        $slot->finalized_at   = $slot->finalized_at ?? now('UTC');
        $slot->save();
        return true;
    }

    return false;
}

    public function postSessionFlags(Reservation $reservation, $tz = null): array
    {
        $now = now('UTC');

       $reservation->loadMissing(['slots', 'dispute']);

        // finalize ended slots (UI safety)
       $slotChanged = false;

foreach ($reservation->slots as $slot) {
    if ($this->finalizeSlotIfEnded($slot)) {
        $slotChanged = true;
    }
}


        $slots = $reservation->slots;

        $totalSlots = $slots->count();

        // counts
        $finalizedSlots = $slots->filter(function ($s) {
            return in_array($this->norm($s->session_status), $this->finalStatuses, true);
        })->count();

        $completedSlots = $slots->filter(function ($s) {
            return $this->norm($s->session_status) === 'completed';
        })->count();

        $clientCompletedSlots = $slots->filter(function ($s) {
            return in_array($this->norm($s->session_status), $this->completedLikeStates, true);
        })->count();

        $allSessionsFinished        = $totalSlots > 0 && $finalizedSlots === $totalSlots;
        $allSessionsCompleted       = $totalSlots > 0 && $completedSlots === $totalSlots;
        $allSessionsClientCompleted = $totalSlots > 0 && $clientCompletedSlots === $totalSlots;

        /**
         * ✅ Your requirement:
         * Show dispute button as soon as the LAST SLOT ENDS.
         *
         * So the dispute window is:
         *   starts = lastSlotEndMoment
         *   ends   = lastSlotEndMoment + 48h
         *
         * IMPORTANT: withinDisputeWindow must be TRUE only when:
         *   now >= lastSlotMoment AND now <= disputeWindowEnds
         */

        // last moment (prefer end_utc; fallback handled in slotEndMomentUtc)
       $lastMoment = null;
if ($totalSlots > 0) {
    $lastMoment = $slots
        ->map(fn ($s) => $this->slotEndMomentUtc($s))
        ->filter()
        ->max();
}

$disputeWindowEnds = $lastMoment ? $lastMoment->copy()->addHours(48) : null;

// ✅ Compare UNIX timestamps
$withinDisputeWindow = false;
if ($lastMoment && $disputeWindowEnds) {
    $nowTs  = $now->timestamp;
    $lastTs = $lastMoment->timestamp;
    $endTs  = $disputeWindowEnds->timestamp;

    $withinDisputeWindow = ($nowTs >= $lastTs) && ($nowTs <= $endTs);
}
        $isPaid       = ($reservation->payment_status === 'paid');
        $notCancelled = !in_array($this->norm($reservation->status), ['cancelled', 'canceled'], true);

        $settlement = $this->norm($reservation->settlement_status);

$refundStatus = $this->norm($reservation->refund_status ?? null);
$refundMinor  = (int)($reservation->refund_total_minor ?? 0);

// refund-track is determined by settlement_status (source of truth)
$isRefundTrack = in_array($settlement, ['refund_pending','refunded','refunded_partial'], true);

// refund "locks" dispute/complete only when we are in refund track AND status indicates money decision/flow
$isRefundFinal = $isRefundTrack && in_array($refundStatus, [
    'pending_choice',
    'processing',
    'succeeded',
], true);

// optional: expose for UI
$isRefundFailed = $isRefundTrack && ($refundStatus === 'failed');
        // ✅ final means funds decided already => no dispute / no complete
    $isSettlementFinal = in_array($settlement, [
    'paid',
    'settled',
    'refunded',
    'refunded_partial',
    'cancelled',
], true);

// refund_pending is NOT final (client may need to choose / processing / retry)

        // ✅ dispute existence and dispute finalized check (decision taken => hard lock forever)
     $dispute = $reservation->dispute; // latest dispute (coach or client)

$hasDispute =
    !empty($reservation->disputed_by_client_at)
 || !empty($reservation->disputed_by_coach_at)
 || (bool) $dispute;
        $isDisputeFinalized = $dispute && !empty($dispute->resolved_at);

        $noDisputeAny =
            empty($reservation->disputed_by_client_at) &&
            empty($reservation->disputed_by_coach_at) &&
            ! $hasDispute;

        /**
         * ✅ Mark as complete rules:
         * - paid
         * - window is open (now between lastMoment and lastMoment+48h)
         * - no dispute
         * - NOT settlement final
         * - ALL slots completed-like (completed OR no_show_client)
         * - AND NOT refund/cancel track (no_show_coach/no_show_both/cancelled)
         */
        $allSlotsCompletedLike = $totalSlots > 0 && $slots->every(function ($s) {
            return in_array($this->norm($s->session_status), $this->completedLikeStates, true);
        });

        $hasBlockComplete = $slots->contains(function ($s) {
            return in_array($this->norm($s->session_status), $this->blockCompleteStates, true);
        });

        $canCompleteBase =
            $notCancelled
            && $isPaid
           && ! $isSettlementFinal
&& ! $isRefundFinal
            && ! $isDisputeFinalized
            && $withinDisputeWindow
            && $noDisputeAny
            && $allSlotsCompletedLike
            && ! $hasBlockComplete;

        $canCompleteClient = $canCompleteBase && empty($reservation->completed_by_client_at);
        $canCompleteCoach  = $canCompleteBase && empty($reservation->completed_by_coach_at);

        /**
         * ✅ Dispute rules (UPDATED to match your requirement):
         * - paid
         * - NOT settlement final
         * - NOT cancelled
         * - NOT dispute finalized
         * - NO existing dispute
         * - within window that STARTS at last slot end (not before)
         * - AND user has NOT already marked complete
         *
         * NOTE: We do NOT gate on allSessionsFinished anymore,
         * because statuses may lag; time is source of truth.
         */
        $canDisputeClient =
            $notCancelled
            && $isPaid
           && ! $isSettlementFinal
&& ! $isRefundFinal
            && ! $isDisputeFinalized
            && ! $hasDispute
            && $withinDisputeWindow
            && empty($reservation->completed_by_client_at);

        $canDisputeCoach =
            $notCancelled
            && $isPaid
            && ! $isSettlementFinal
            && ! $isDisputeFinalized
            && ! $isRefundFinal
            && ! $hasDispute
            && $withinDisputeWindow
            && empty($reservation->completed_by_coach_at);

        return [
            'totalSlots'                 => $totalSlots,
            'finalizedSlots'             => $finalizedSlots,
            'completedSlots'             => $completedSlots,
            'clientCompletedSlots'       => $clientCompletedSlots,

            'allSessionsFinished'        => $allSessionsFinished,
            'allSessionsCompleted'       => $allSessionsCompleted,
            'allSessionsClientCompleted' => $allSessionsClientCompleted,

            // ✅ time truth
            'lastSlotMoment'             => $lastMoment,
            'disputeWindowEnds'          => $disputeWindowEnds,
            'withinDisputeWindow'        => $withinDisputeWindow,

            'hasDispute'                 => $hasDispute,
            'isDisputeFinalized'         => (bool) $isDisputeFinalized,

            'canCompleteClient'          => $canCompleteClient,
            'canCompleteCoach'           => $canCompleteCoach,

            // ✅ final booleans to use directly in Blade
            'canDisputeClient'           => $canDisputeClient,
            'canDisputeCoach'            => $canDisputeCoach,
            'debug_now_ts'   => $now?->timestamp,
'debug_last_ts'  => $lastMoment?->timestamp,
'debug_end_ts'   => $disputeWindowEnds?->timestamp,
'debug_now'      => $now?->toDateTimeString(),
'debug_last'     => $lastMoment?->toDateTimeString(),
'debug_end'      => $disputeWindowEnds?->toDateTimeString(),

'settlement_status'   => $settlement,
'refund_status'       => $refundStatus,
'refund_total_minor'  => $refundMinor,
'isRefundTrack'       => $isRefundTrack,
'isRefundFinal'       => $isRefundFinal,
'isRefundFailed'      => $isRefundFailed ?? false,
        ];
    }
}