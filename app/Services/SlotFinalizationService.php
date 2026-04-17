<?php

namespace App\Services;

use App\Models\ReservationSlot;
use Illuminate\Support\Facades\DB;

class SlotFinalizationService
{
    public function finalizeEndedSlots(int $limit = 500): int
    {
        $now = now('UTC'); // safer since end_utc is UTC

        return DB::transaction(function () use ($now, $limit) {

            $slots = ReservationSlot::query()
                ->whereNotNull('end_utc')
                ->where('end_utc', '<=', $now)
                ->whereIn('session_status', [
                    'pending','waiting_for_coach','waiting_for_client','in_progress','started',
                ])
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            $count = 0;
            $reservationIds = [];

            foreach ($slots as $slot) {
                $clientIn = !empty($slot->client_checked_in_at);
                $coachIn  = !empty($slot->coach_checked_in_at);

                if ($clientIn && $coachIn) {
                    $slot->session_status = 'completed';
                } elseif (!$clientIn && !$coachIn) {
                    $slot->session_status = 'no_show_both';      // ✅ match recompute
                } elseif ($coachIn && !$clientIn) {
                    $slot->session_status = 'no_show_client';    // ✅ match recompute
                } else {
                    $slot->session_status = 'no_show_coach';     // ✅ already matches
                }

                $slot->finalized_at = $slot->finalized_at ?? $now; // if you have this column
                $slot->save();

                $reservationIds[] = (int)$slot->reservation_id;
                $count++;
            }

            $reservationIds = array_values(array_unique($reservationIds));
            $settler = app(\App\Services\ReservationSettlementService::class);

            foreach ($reservationIds as $rid) {
                $settler->recompute($rid);
            }

            return $count;
        });
    }
}