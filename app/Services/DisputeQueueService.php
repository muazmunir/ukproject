<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use Illuminate\Support\Facades\DB;
use App\Models\DisputeSummary;

class DisputeQueueService
{
    public function dispatchAdmins(int $limit = 20): int
    {
        return $this->dispatchAgents($limit);
    }

    private function slaStart(array &$update, $now): void
    {
        $update['sla_started_at'] = $now;
        // DO NOT reset sla_total_seconds here (we want cumulative across reassignments)
    }

   private function slaStop(Dispute $d, array &$update, $now): void
{
    $total = (int)($d->sla_total_seconds ?? 0);

    if ($d->sla_started_at) {
        // Ensure Carbon instance
        $start = $d->sla_started_at instanceof \Carbon\CarbonInterface
            ? $d->sla_started_at
            : \Carbon\Carbon::parse($d->sla_started_at);

        // diffInSeconds is always >= 0
        $elapsed = $start->diffInSeconds($now);

        // safety clamp
        $elapsed = max(0, (int)$elapsed);

        $total += $elapsed;
    }

    $update['sla_total_seconds'] = max(0, (int)$total);
    $update['sla_started_at'] = null;
}

    /**
     * Assign queue disputes to available online staff (admins + managers).
     * FIFO. Max 3 active disputes per staff.
     * SLA starts immediately on assignment, stops on close/resolved.
     */
    public function dispatchAgents(int $limit = 20): int
    {
        return DB::transaction(function () use ($limit) {

            // ✅ Queue = open + unassigned + not resolved
            $disputes = Dispute::query()
                ->whereNull('resolved_at')
                ->where('status', 'open')
                ->whereNull('assigned_staff_id')
                ->orderByRaw('COALESCE(last_message_at, created_at) ASC')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($disputes->isEmpty()) return 0;

            $staff = DB::table('users')
                ->whereIn('role', ['admin', 'manager'])
                ->where('support_status', 'available')
                ->where('support_presence', 'online')
                ->lockForUpdate()
                ->get(['id', 'role', 'username']);

            if ($staff->isEmpty()) return 0;

            // active load per staff: assigned AND not resolved AND status opened
            $counts = Dispute::query()
                ->whereNull('resolved_at')
                ->where('status', 'opened')
                ->whereNotNull('assigned_staff_id')
                ->select('assigned_staff_id', DB::raw('COUNT(*) cnt'))
                ->groupBy('assigned_staff_id')
                ->pluck('cnt', 'assigned_staff_id')
                ->toArray();

            $load = [];
            foreach ($staff as $s) {
                $load[$s->id] = (int)($counts[$s->id] ?? 0);
            }

            $assigned = 0;
            $now = now();

            foreach ($disputes as $d) {

                $pickId = collect($load)
                    ->filter(fn($v) => $v < 3)
                    ->sort()
                    ->keys()
                    ->first();

                if (!$pickId) break;

                $picked = $staff->firstWhere('id', $pickId);
                $pickedRole = (string)($picked->role ?? 'admin');

              $update = [
    'assigned_staff_id'   => (int)$pickId,
    'assigned_staff_role' => $pickedRole,
    'assigned_at'         => $now,

    // assigned disputes are opened
    'status'              => 'opened',

    // ✅ if it was in_review earlier, assignment means it’s back in flow
    'in_review_started_at'=> null,

    'updated_at'          => $now,
];
                // ✅ SLA starts immediately on assignment
                $this->slaStart($update, $now);

                $ok = Dispute::where('id', $d->id)
                    ->whereNull('resolved_at')
                    ->whereNull('assigned_staff_id')
                    ->where('status', 'open')
                    ->update($update);

                if ($ok) {
                    $assigned++;
                    $load[$pickId]++;

                    DisputeMessage::create([
                        'dispute_id'     => (int)$d->id,
                        'sender_user_id' => (int)$pickId,
                        'sender_role'    => 'system',
                        'target_role'    => 'admin',
                        'channel'        => 'admin',
                        'message'        => '[System] Dispute assigned to staff.',
                    ]);
                }
            }

            return $assigned;
        });
    }

    /**
     * Close (NOT resolved) -> becomes in_review and unassigned.
     * SLA must STOP at close.
     */
 

public function closeToInReview(int $disputeId, int $staffId, string $summary): bool
{
    return (bool) DB::transaction(function () use ($disputeId, $staffId, $summary) {

        $d = Dispute::lockForUpdate()->find($disputeId);
        if (!$d) return false;
        if (!empty($d->resolved_at)) return false;

        if ((int)($d->assigned_staff_id ?? 0) !== (int)$staffId) return false;

        $now = now();

        // ✅ Save summary history
        DisputeSummary::create([
            'dispute_id' => (int)$disputeId,
            'staff_id'   => (int)$staffId,
            'staff_role' => (string)(auth()->user()->role ?? null),
            'summary'    => $summary,
        ]);

        // ✅ Update latest summary on dispute for quick display
        $d->forceFill([
            'latest_summary'       => $summary,
            'latest_summary_by_id' => (int)$staffId,
            'latest_summary_at'    => $now,
        ])->save();

       $update = [
    'status'              => 'in_review',

    // ✅ start "days since closed"
    'in_review_started_at'=> $now,

    // unassign
    'assigned_staff_id'   => null,
    'assigned_staff_role' => null,
    'assigned_at'         => null,

    'updated_at'          => $now,
];

        $this->slaStop($d, $update, $now);

        $ok = Dispute::where('id', $disputeId)
            ->whereNull('resolved_at')
            ->where('assigned_staff_id', (int)$staffId)
            ->update($update);

        if ($ok) {
            DisputeMessage::create([
                'dispute_id'     => (int)$disputeId,
                'sender_user_id' => (int)$staffId,
                'sender_role'    => 'system',
                'target_role'    => 'admin',
                'channel'        => 'admin',
                'message'        => '[System] Conversation closed. Dispute moved to In Review.',
            ]);
        }

        return $ok > 0;
    });
}

    /**
     * Requeue staff disputes (offline/unavailable) -> open + unassigned.
     * SLA stops because staff is no longer handling it.
     */
    public function requeueStaffOpenedDisputes(int $staffId): int
    {
        return DB::transaction(function () use ($staffId) {

            $rows = Dispute::query()
                ->whereNull('resolved_at')
                ->where('status', 'opened')
                ->where('assigned_staff_id', (int)$staffId)
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) return 0;

            $count = 0;
            $now = now();

            foreach ($rows as $d) {
               $update = [
    'status'              => 'open',

    'assigned_staff_id'   => null,
    'assigned_staff_role' => null,
    'assigned_at'         => null,

    // keep or clear review timer? for requeue, it’s NOT in_review
    'in_review_started_at'=> null,

    'updated_at'          => $now,
];
                $this->slaStop($d, $update, $now);

                $ok = Dispute::where('id', $d->id)
                    ->whereNull('resolved_at')
                    ->where('assigned_staff_id', (int)$staffId)
                    ->update($update);

                if ($ok) {
                    $count++;

                    DisputeMessage::create([
                        'dispute_id' => (int)$d->id,
                        'sender_user_id' => (int)$staffId,
                        'sender_role'    => 'system',
                        'target_role'    => 'admin',
                        'channel'        => 'admin',
                        'message'        => '[System] Staff unavailable. Dispute returned to queue.',
                    ]);
                }
            }

            return $count;
        });
    }
}