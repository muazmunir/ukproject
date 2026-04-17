<?php

namespace App\Http\Controllers;

use App\Models\ReservationSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

class ReservationSlotSessionController extends Controller
{
    /* =========================
     | Helpers (UTC + deadlines)
     ========================= */

    private function startUtc(ReservationSlot $slot): ?CarbonImmutable
    {
        return $slot->start_utc ? CarbonImmutable::parse($slot->start_utc)->utc() : null;
    }

    private function endUtc(ReservationSlot $slot): ?CarbonImmutable
    {
        return $slot->end_utc ? CarbonImmutable::parse($slot->end_utc)->utc() : null;
    }

    // Base wait deadline = start + 5
    private function baseDeadline(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMinutes(5);
    }

    // Extended wait deadline = start + 10
    private function extendedDeadline(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMinutes(10);
    }

    private function effectiveDeadline(ReservationSlot $slot, CarbonImmutable $start): CarbonImmutable
    {
        if (!empty($slot->extended_until_utc)) {
            return CarbonImmutable::parse($slot->extended_until_utc)->utc();
        }
        if (!empty($slot->wait_deadline_utc)) {
            return CarbonImmutable::parse($slot->wait_deadline_utc)->utc();
        }
        return $this->baseDeadline($start);
    }

    // Join window: start-5 to start+5 OR start+10 (if extended)
    private function canJoinNow(ReservationSlot $slot, CarbonImmutable $start, CarbonImmutable $now): bool
    {
        $open = $start->subMinutes(5);

        // If extended -> allow until start+10
        if (!empty($slot->extended_until_utc)) {
            $close = CarbonImmutable::parse($slot->extended_until_utc)->utc(); // start+10
            return $now->between($open, $close);
        }

        // Not extended -> allow until start+5
        $close = $start->addMinutes(5);
        return $now->between($open, $close);
    }

    private function setBaseDeadlineIfMissing(ReservationSlot $slot, CarbonImmutable $start): void
    {
        if (empty($slot->wait_deadline_utc)) {
            $slot->wait_deadline_utc = $this->baseDeadline($start);
        }
    }

    /**
     * Auto finalize if deadline passed.
     * - If one joined and other didn't => no_show_(missing)
     * - If none joined => no_show_both
     * NOTE: wallet/refund rules MUST be applied inside ReservationSettlementService (or SlotFinalizationService)
     */
    private function autoFinalizeIfOverdue(ReservationSlot $slot, CarbonImmutable $start, CarbonImmutable $now): void
    {
        if (!empty($slot->finalized_at)) return;

        $deadline = $this->effectiveDeadline($slot, $start);
        if ($now->lt($deadline)) return;

        $clientIn = !empty($slot->client_checked_in_at);
        $coachIn  = !empty($slot->coach_checked_in_at);

        // Decide final status
        if ($clientIn && !$coachIn) {
            $slot->session_status = 'no_show_coach';
        } elseif (!$clientIn && $coachIn) {
            // Coach waiting → slot considered completed, with info “client didn’t come”
            $slot->session_status = 'no_show_client';
        } elseif (!$clientIn && !$coachIn) {
            $slot->session_status = 'no_show_both';
        } else {
            // both checked in but deadline passed: nothing to do
            return;
        }

        $slot->auto_cancelled_at = $slot->auto_cancelled_at ?? now();
        $slot->finalized_at      = $slot->finalized_at ?? now();

        // audit
        $info = is_array($slot->info_json) ? $slot->info_json : (json_decode($slot->info_json ?? '[]', true) ?: []);
        $info['auto_finalized'] = true;
        $info['auto_finalized_at_utc'] = $now->toIso8601String();
        $info['deadline_utc'] = $deadline->toIso8601String();
        $slot->info_json = $info;

        $slot->save();

        // IMPORTANT: This service must apply your wallet rules:
        // - no_show_coach => refund full (service + platform) to client wallet, coach penalty (10% service) to Zaivias
        // - no_show_client => slot marked completed/no_show_client (coach earns; client doesn't get service refund)
        // - no_show_both => client pays platform fee, coach pays 10% service
        app(\App\Services\ReservationSettlementService::class)->recompute($slot->reservation_id);
    }

    /* =========================
     | CLIENT CHECK-IN
     ========================= */
    public function clientCheckin(Request $request, ReservationSlot $slot)
    {
        $reservation = $slot->reservation;

        if (! $reservation || $reservation->client_id !== $request->user()->id) abort(403);

        if ($slot->client_checked_in_at) {
            return response()->json(['ok' => true, 'message' => 'Already checked in.']);
        }

        $data = $request->validate([
           
            'lat'  => ['nullable', 'numeric'],
            'lng'  => ['nullable', 'numeric'],
        ]);

        $now   = CarbonImmutable::now('UTC');
        $start = $this->startUtc($slot);

        if (! $start) {
            return response()->json(['ok' => false, 'message' => 'Slot has no start time.'], 422);
        }

        // ✅ Enforce join window: start-5 → start+5 (or start+10 if extended)
        if (! $this->canJoinNow($slot, $start, $now)) {
            $close = !empty($slot->extended_until_utc) ? CarbonImmutable::parse($slot->extended_until_utc)->utc() : $start->addMinutes(5);
            return response()->json([
                'ok' => false,
                'message' => 'You can join from 5 minutes before until ' . $close->toIso8601String() . ' (UTC).',
            ], 422);
        }

      

        return DB::transaction(function () use ($slot, $now, $start, $data) {

            $slot = ReservationSlot::lockForUpdate()->findOrFail($slot->id);

            // base deadline stored for frontend/automation
            $this->setBaseDeadlineIfMissing($slot, $start);

            $slot->client_checked_in_at = $now;
            $slot->client_lat = $data['lat'] ?? null;
            $slot->client_lng = $data['lng'] ?? null;

            if ($slot->coach_checked_in_at) {
                $slot->session_status = 'live';
            } else {
                $slot->session_status = 'waiting_for_coach';
            }

            $slot->save();

            return response()->json(['ok' => true]);
        });
    }

    /* =========================
     | COACH CHECK-IN
     ========================= */
    public function coachCheckin(Request $request, ReservationSlot $slot)
    {
        $reservation = $slot->reservation;
        $reservation?->loadMissing('service');

        if (! $reservation || optional($reservation->service)->coach_id !== $request->user()->id) abort(403);

        if ($slot->coach_checked_in_at) {
            return response()->json(['ok' => true, 'message' => 'Already checked in.']);
        }

        $data = $request->validate([
          
            'lat'  => ['nullable', 'numeric'],
            'lng'  => ['nullable', 'numeric'],
        ]);

        $now   = CarbonImmutable::now('UTC');
        $start = $this->startUtc($slot);

        if (! $start) {
            return response()->json(['ok' => false, 'message' => 'Slot has no start time.'], 422);
        }

        // ✅ Enforce join window: start-5 → start+5 (or start+10 if extended)
        if (! $this->canJoinNow($slot, $start, $now)) {
            $close = !empty($slot->extended_until_utc) ? CarbonImmutable::parse($slot->extended_until_utc)->utc() : $start->addMinutes(5);
            return response()->json([
                'ok' => false,
                'message' => 'You can join from 5 minutes before until ' . $close->toIso8601String() . ' (UTC).',
            ], 422);
        }

    

        return DB::transaction(function () use ($slot, $now, $start, $data) {

            $slot = ReservationSlot::lockForUpdate()->findOrFail($slot->id);

            $this->setBaseDeadlineIfMissing($slot, $start);

            $slot->coach_checked_in_at = $now;
            $slot->coach_lat = $data['lat'] ?? null;
            $slot->coach_lng = $data['lng'] ?? null;

            if ($slot->client_checked_in_at) {
                $slot->session_status = 'live';
            } else {
                $slot->session_status = 'waiting_for_client';
            }

            $slot->save();

            return response()->json(['ok' => true]);
        });
    }

    /* =========================
     | EXTEND WAIT (client OR coach)
     | Allowed at start+4 → before start+5
     | Only if one checked-in and other missing
     ========================= */
    public function extendWait(Request $request, ReservationSlot $slot)
    {
        $reservation = $slot->reservation;
        $reservation?->loadMissing('service');

        $user = $request->user();

        $isClient = $reservation && $reservation->client_id === $user->id;
        $isCoach  = $reservation && optional($reservation->service)->coach_id === $user->id;

        if (! $isClient && ! $isCoach) abort(403);

        return DB::transaction(function () use ($slot, $isClient, $isCoach) {

            $slot = ReservationSlot::lockForUpdate()->findOrFail($slot->id);

            if (!empty($slot->finalized_at)) {
                return response()->json(['ok' => false, 'message' => 'Slot already finalized.'], 422);
            }

            $start = $this->startUtc($slot);
            if (! $start) {
                return response()->json(['ok' => false, 'message' => 'Slot has no start time.'], 422);
            }

            $now = CarbonImmutable::now('UTC');

            $extendAllowedAt = $start->addMinutes(4);
            $baseDeadline    = $start->addMinutes(5);

            // only within start+4 to < start+5
            if ($now->lt($extendAllowedAt)) {
                return response()->json(['ok' => false, 'message' => 'Extend allowed after 4 minutes.'], 422);
            }
            if ($now->gte($baseDeadline)) {
                return response()->json(['ok' => false, 'message' => 'Too late to extend.'], 422);
            }

            if (!empty($slot->extended_until_utc)) {
                return response()->json(['ok' => false, 'message' => 'Already extended.'], 422);
            }

            $clientIn = !empty($slot->client_checked_in_at);
            $coachIn  = !empty($slot->coach_checked_in_at);

            // must be exactly "one joined, one missing"
            if (!(($clientIn && !$coachIn) || (!$clientIn && $coachIn))) {
                return response()->json(['ok' => false, 'message' => 'Extend not applicable.'], 422);
            }

            // who is allowed to click extend?
            // - if client waiting for coach => client may extend
            // - if coach waiting for client => coach may extend
            if ($clientIn && !$coachIn && !$isClient) {
                return response()->json(['ok' => false, 'message' => 'Only the waiting client can extend.'], 403);
            }
            if ($coachIn && !$clientIn && !$isCoach) {
                return response()->json(['ok' => false, 'message' => 'Only the waiting coach can extend.'], 403);
            }

            // ✅ Extend to start+10
            $slot->extended_until_utc = $this->extendedDeadline($start);
            $slot->wait_deadline_utc  = $slot->extended_until_utc; // keep consistent

            $info = is_array($slot->info_json) ? $slot->info_json : (json_decode($slot->info_json ?? '[]', true) ?: []);
            $info['extended'] = true;
            $info['extended_by'] = $clientIn ? 'client' : 'coach';
            $info['extended_at_utc'] = $now->toIso8601String();
            $slot->info_json = $info;

            $slot->save();

            return response()->json(['ok' => true]);
        });
    }

    /* =========================
     | STATUS (polling endpoint)
     | - returns deadline + popup flags
     | - auto-finalizes if overdue
     ========================= */
    public function status(Request $request, ReservationSlot $slot)
    {
        $reservation = $slot->reservation;
        $reservation?->loadMissing('service');

        $user = $request->user();

        $isClient = $reservation && $reservation->client_id === $user->id;
        $isCoach  = $reservation && optional($reservation->service)->coach_id === $user->id;

        if (! $isClient && ! $isCoach) abort(403);

        $now   = CarbonImmutable::now('UTC');
        $start = $this->startUtc($slot);

        if ($start) {
            // auto-finalize when time is up
            $this->autoFinalizeIfOverdue($slot, $start, $now);

            // refresh (in case it changed)
            $slot->refresh();
        }

        $start = $this->startUtc($slot);
        $end   = $this->endUtc($slot);

        $deadline = $start ? $this->effectiveDeadline($slot, $start) : null;
        $extendAt = $start ? $start->addMinutes(4) : null;

        $clientIn = !empty($slot->client_checked_in_at);
        $coachIn  = !empty($slot->coach_checked_in_at);

        $canExtend = false;
        if ($start && $deadline && empty($slot->finalized_at) && empty($slot->extended_until_utc)) {
            // only the waiting party can extend between start+4 and start+5
            $baseDeadline = $start->addMinutes(5);

            $withinExtendWindow = $now->gte($start->addMinutes(4)) && $now->lt($baseDeadline);

            if ($withinExtendWindow) {
                if ($clientIn && !$coachIn && $isClient) $canExtend = true;
                if ($coachIn && !$clientIn && $isCoach)  $canExtend = true;
            }
        }

        // Popups:
        // - nudge1 at start+5s
        // - nudge2 at start+2m
        $shouldNudge1 = $start && $clientIn && !$coachIn && empty($slot->nudge1_sent_at) && $now->gte($start->addSeconds(5));
        $shouldNudge2 = $start && $clientIn && !$coachIn && empty($slot->nudge2_sent_at) && $now->gte($start->addMinutes(2));

        return response()->json([
            'slot_id' => $slot->id,

            // state
            'session_status' => $slot->session_status,
            'finalized_at'   => $slot->finalized_at,
            'auto_cancelled_at' => $slot->auto_cancelled_at,

            // checkins
            'client_checked_in' => $clientIn,
            'coach_checked_in'  => $coachIn,

            // timing
            'now_ts'      => $now->timestamp,
            'start_ts'    => $start?->timestamp,
            'end_ts'      => $end?->timestamp,
            'extend_at_ts'=> $extendAt?->timestamp,
            'deadline_ts' => $deadline?->timestamp,

            // extension
            'extended' => !empty($slot->extended_until_utc),
            'extended_until_ts' => !empty($slot->extended_until_utc)
                ? CarbonImmutable::parse($slot->extended_until_utc)->utc()->timestamp
                : null,

            // popups
            'should_nudge1' => (bool) $shouldNudge1,
            'should_nudge2' => (bool) $shouldNudge2,

            // UI flags
            'can_extend' => $canExtend,
            'can_join_call' => ($clientIn && $coachIn && $end && $now->lt($end)),
        ]);
    }
}
