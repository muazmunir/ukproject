<?php

namespace App\Services;

use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use App\Mail\SlotReminderMail;
use App\Mail\SlotNudgeMail;

class ProcessSlotTimer
{
    public function handle(ReservationSlot $slot, CarbonImmutable $now): void
{
    $res = $slot->reservation;

    if (!$res) return;

    $stopSettlementStatuses = ['refunded', 'refunded_partial', 'cancelled', 'in_dispute'];
   $stopReservationStatuses = ['cancelled', 'canceled'];


  $res = $slot->reservation;
if (!$res) return;

// Freeze (do NOT finalize) when in dispute
if (in_array((string)$res->settlement_status, ['in_dispute'], true)) {
    return;
}

// Stop processing when refunded/cancelled (full or partial) OR reservation cancelled OR not paid
// Stop ONLY when fully refunded or cancelled
if (
    in_array((string)$res->status, ['cancelled', 'canceled'], true) ||
    in_array((string)$res->settlement_status, ['refunded', 'cancelled'], true) ||
    (string)$res->payment_status !== 'paid'
) {
    if (!$slot->finalized_at) {
        $slot->update([
            'session_status' => 'cancelled',
            'finalized_at'   => $now,
        ]);
    }
    return;
}


    $start = CarbonImmutable::parse($slot->start_utc)->utc();
    $end   = $slot->end_utc ? CarbonImmutable::parse($slot->end_utc)->utc() : null;

    // ✅ 15-min reminder
    $reminderAt = $start->subMinutes(15);
    if (!$slot->reminder_15_sent_at && $now->gte($reminderAt)) {
        $this->sendReminder15($slot);
        $slot->update(['reminder_15_sent_at' => $now]);
    }

    // ✅ init join deadline (start+5)
    if (!$slot->wait_deadline_utc) {
        $slot->update(['wait_deadline_utc' => $start->addMinutes(5)]);
        $slot->refresh(); // ensure latest
    }

    // ✅ nudge 1 (client checked-in, coach missing)
    if (
        !$slot->nudge1_sent_at &&
        $now->gte($start->addSeconds(5)) &&
        $slot->client_checked_in_at &&
        !$slot->coach_checked_in_at
    ) {
        $this->sendNudge($slot, 1);
        $slot->update(['nudge1_sent_at' => $now]);
    }

    // ✅ nudge 2
    if (
        !$slot->nudge2_sent_at &&
        $now->gte($start->addMinutes(2)) &&
        $slot->client_checked_in_at &&
        !$slot->coach_checked_in_at
    ) {
        $this->sendNudge($slot, 2);
        $slot->update(['nudge2_sent_at' => $now]);
    }

    // ✅ JOIN DEADLINE (start+5 or extended)
    $joinDeadline = $slot->extended_until_utc
        ? CarbonImmutable::parse($slot->extended_until_utc)->utc()
        : CarbonImmutable::parse($slot->wait_deadline_utc)->utc();

    $bothCheckedIn = (bool)($slot->client_checked_in_at && $slot->coach_checked_in_at);

    // ✅ If NOT both checked-in by join deadline → NO-SHOW finalize
    if (!$slot->finalized_at && !$bothCheckedIn && $now->gte($joinDeadline)) {
        $this->finalizeNoShowAtJoinDeadline($slot, $now);
        app(\App\Services\ReservationSettlementService::class)->recompute($slot->reservation_id);
        return;
    }

    // ✅ If BOTH checked-in → complete only at END time
    if (!$slot->finalized_at && $bothCheckedIn && $end && $now->gte($end)) {
        $slot->update([
            'session_status' => 'completed',
            'finalized_at'   => $now,
            'completed_at'   => $now, // optional
        ]);

        app(\App\Services\ReservationSettlementService::class)->recompute($slot->reservation_id);
        return;
    }
}

private function finalizeNoShowAtJoinDeadline(ReservationSlot $slot, CarbonImmutable $now): void
{
    if ($slot->finalized_at) return;

    if ($slot->client_checked_in_at && !$slot->coach_checked_in_at) {
        $slot->update([
            'session_status'    => 'no_show_coach',
            'finalized_at'      => $now,
            'auto_cancelled_at' => $now,
        ]);
        return;
    }

    if ($slot->coach_checked_in_at && !$slot->client_checked_in_at) {
        $slot->update([
            'session_status' => 'no_show_client',
            'finalized_at'   => $now,
        ]);
        return;
    }

    if (!$slot->client_checked_in_at && !$slot->coach_checked_in_at) {
        $slot->update([
            'session_status'    => 'no_show_both',
            'finalized_at'      => $now,
            'auto_cancelled_at' => $now,
        ]);
        return;
    }
}


    private function sendReminder15(ReservationSlot $slot): void
    {
        $res = $slot->reservation;
        if (!$res) return;

        $res->loadMissing(['client', 'service.coach']);
        if (!$res->client?->email || !$res->service?->coach?->email) return;

        // For debugging, consider ->send() until queue is verified
        Mail::to($res->client->email)->send(new SlotReminderMail($slot, 'client'));
        Mail::to($res->service->coach->email)->send(new SlotReminderMail($slot, 'coach'));
    }

    private function sendNudge(ReservationSlot $slot, int $attempt): void
    {
        $res = $slot->reservation;
        if (!$res) return;

        $res->loadMissing(['service.coach']);
        if (!$res->service?->coach?->email) return;

        Mail::to($res->service->coach->email)->send(new SlotNudgeMail($slot, $attempt));
    }

   private function finalizeSlot(ReservationSlot $slot, CarbonImmutable $now): void
{
    if ($slot->finalized_at) return;

    // ✅ BOTH attended -> complete
    if ($slot->client_checked_in_at && $slot->coach_checked_in_at) {
        $slot->update([
            'session_status' => 'completed',
            'finalized_at'   => $now,
            'completed_at'   => $now, // if you have this column (optional)
        ]);

        app(\App\Services\ReservationSettlementService::class)->recompute($slot->reservation_id);
        return;
    }

    if ($slot->client_checked_in_at && !$slot->coach_checked_in_at) {
        $slot->update([
            'session_status'    => 'no_show_coach',
            'finalized_at'      => $now,
            'auto_cancelled_at' => $now,
        ]);
    } elseif ($slot->coach_checked_in_at && !$slot->client_checked_in_at) {
        $slot->update([
            'session_status' => 'no_show_client',
            'finalized_at'   => $now,
        ]);
    } elseif (!$slot->client_checked_in_at && !$slot->coach_checked_in_at) {
        $slot->update([
            'session_status'    => 'no_show_both',
            'finalized_at'      => $now,
            'auto_cancelled_at' => $now,
        ]);
    }

    app(\App\Services\ReservationSettlementService::class)->recompute($slot->reservation_id);
}

}
