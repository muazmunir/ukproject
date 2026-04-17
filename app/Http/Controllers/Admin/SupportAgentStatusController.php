<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupportQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Support\Shift;
use App\Services\DisputeQueueService;

class SupportAgentStatusController extends Controller
{
    // ✅ ONLY statuses user can set from dropdown
    private const ALLOWED = ['available','break','meeting','tech_issues','admin'];

   public function update(Request $request, SupportQueueService $queue, DisputeQueueService $disputeQueue)
    {
        $user = $request->user();

        $role = strtolower(trim((string) $user->role));
        if (!in_array($role, ['admin','manager','superadmin'], true)) {
            return response()->json(['ok' => false, 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'status' => ['required','string', Rule::in(self::ALLOWED)],
            'reason' => ['nullable','string','max:255'],
        ]);

        $requested = strtolower((string) $request->input('status'));
        $reason    = $request->input('reason');

        $nowUtc = now()->utc();

        /**
         * ============================================================
         * ✅ LEAVE WINDOW / RETURN REQUIRED HANDLING (UTC)
         * ============================================================
         */
        $startUtc = $user->absence_start_at ? Carbon::parse($user->absence_start_at, 'UTC') : null;
        $endUtc   = $user->absence_end_at   ? Carbon::parse($user->absence_end_at, 'UTC')   : null;

        $kind = strtolower((string) $user->absence_kind);   // holiday|absence|null
        $type = strtolower((string) $user->absence_status); // authorized|unauthorized|null

        $hasLeave = !empty($user->absence_kind) && !empty($user->absence_status) && $startUtc && $endUtc;

        // end EXCLUSIVE
        $isActiveWindow = $hasLeave
            && $nowUtc->greaterThanOrEqualTo($startUtc)
            && $nowUtc->lessThan($endUtc);

        // lock only for holiday OR authorized absence
        $activeLock = $isActiveWindow && (
            ($kind === 'holiday') ||
            ($kind === 'absence' && $type === 'authorized')
        );

        if ($activeLock) {
            return response()->json([
                'ok'     => false,
                'locked' => true,
                'phase'  => 'active',
                'message'=> 'Your Status Is Locked Due To Active Leave Until ' .
                    ($endUtc ? $endUtc->timezone($user->timezone ?: 'UTC')->format('d M Y, H:i') : ''),
            ], 423);
        }

        // Post window lock (return required): only allow Available
       // Post window state (return required): allow ANY dropdown status to return
if ((bool)($user->absence_return_required ?? false)) {

    // returning: clear leave fields + close any open logs
    DB::transaction(function () use ($user, $nowUtc) {
        $this->closeOpenLogs($user->id, $nowUtc);

        DB::table('users')->where('id', $user->id)->update([
            'absence_kind'            => null,
            'absence_status'          => null,
            'absence_start_at'        => null,
            'absence_end_at'          => null,
            'absence_return_required' => false,
            'absence_return_since'    => null,
            'absence_set_at'          => $nowUtc->toDateTimeString(),
            'updated_at'              => now(),
        ]);
    });

    // continue to normal flow (set requested + log)
}

        /**
         * ============================================================
         * ✅ NORMAL STATUS UPDATE FLOW
         * ============================================================
         */
        $now = now(); // ok if DB stores UTC; you’re using UTC everywhere anyway
        $shouldLog = Shift::isWithinShiftNow($user, $now);

        // prev status (for queue logic)
        $prevStatus = $shouldLog
            ? (string) DB::table('agent_status_logs')
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->value('status')
            : (string) ($user->support_status ?? '');

            $newPresence = (string) DB::table('users')->where('id', $user->id)->value('support_presence');

        // anti-spam only if logging
        if ($shouldLog) {
            $lastLog = DB::table('agent_status_logs')
                ->where('user_id', $user->id)
                ->orderByDesc('started_at')
                ->first();

            if ($lastLog) {
                $lastStarted = Carbon::parse($lastLog->started_at);
                if ($lastStarted->diffInSeconds($now) < 2) {
                    if ($lastLog->ended_at === null && $lastLog->status === $requested) {
                        DB::table('agent_status_logs')->where('id', $lastLog->id)->update([
                            'reason'     => $reason,
                            'updated_at' => $now,
                        ]);

                        return response()->json([
                            'ok'      => true,
                            'status'  => $requested,
                            'ignored' => true,
                            'logged'  => true,
                        ]);
                    }

                    return response()->json([
                        'ok'      => true,
                        'status'  => $prevStatus ?: $requested,
                        'ignored' => true,
                        'logged'  => true,
                        'message' => 'Duplicate/rapid status update ignored',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($user, $requested, $reason, $nowUtc, $shouldLog) {

            // ✅ Always close ALL open logs first (prevents parallel open intervals)
            $this->closeOpenLogs($user->id, $nowUtc);

            // ✅ Update user status
            // IMPORTANT RULE:
            // - On manual status change, user is "online" EXCEPT when status is break/tech_issues,
            //   where we want chats to queue, so set presence offline.
            $presence = in_array($requested, ['break','tech_issues'], true) ? 'offline' : 'online';

            DB::table('users')->where('id', $user->id)->update([
                'support_status'         => $requested,
                'support_status_since'   => $nowUtc->toDateTimeString(),

                'support_presence'       => $presence,
                'support_presence_since' => $nowUtc->toDateTimeString(),

                'updated_at'             => now(),
            ]);

            // outside shift => do not write new status log
            if (!$shouldLog) return;

            DB::table('agent_status_logs')->insert([
                'user_id'    => $user->id,
                'status'     => $requested,
                'reason'     => $reason,
                'started_at' => $nowUtc->toDateTimeString(),
                'ended_at'   => null,
                'created_at' => $nowUtc->toDateTimeString(),
                'updated_at' => $nowUtc->toDateTimeString(),
            ]);

            DB::table('admin_action_logs')->insert([
                'admin_user_id' => $user->id,
                'action'        => 'support_status_update',
                'target_type'   => 'user',
                'target_id'     => $user->id,
                'meta'          => json_encode(['status' => $requested, 'reason' => $reason]),
                'ip'            => request()->ip(),
                'user_agent'    => substr((string) request()->userAgent(), 0, 255),
                'created_at'    => $nowUtc->toDateTimeString(),
                'updated_at'    => $nowUtc->toDateTimeString(),
            ]);
        });

        /**
         * ============================================================
         * ✅ QUEUE LOGIC
         * ============================================================
         */
        $requeued = 0;
        $dispatched_agents = 0;
        $dispatched_managers = 0;

        $requeued_disputes = 0;
$dispute_dispatched = 0;

        // superadmin behaves like admin for assignment
        $roleForQueue = $role === 'superadmin' ? 'admin' : $role;

        // ✅ Disputes: if staff becomes unavailable, requeue their OPENED disputes
if (in_array($requested, ['tech_issues','break'], true) && $prevStatus !== $requested) {
    $requeued_disputes = $disputeQueue->requeueStaffOpenedDisputes((int)$user->id);

    // after freeing them, assign queued disputes to other online staff
    $dispute_dispatched = $disputeQueue->dispatchAgents();
}

        // 1) If staff becomes unavailable (tech_issues), requeue ALL their open chats
        if ($requested === 'tech_issues' && $prevStatus !== 'tech_issues') {

       



            $requeued = $queue->requeueStaffOpenConversations($user->id, $roleForQueue);

            $dispatched_agents   = $queue->dispatchAgents();
            $dispatched_managers = $queue->dispatchManagers();
        }

        // ✅ NEW: If staff goes to BREAK, also requeue so chats go to queue immediately
        if ($requested === 'break' && $prevStatus !== 'break') {
            $requeued = $queue->requeueStaffOpenConversations($user->id, $roleForQueue);

            $dispatched_agents   = $queue->dispatchAgents();
            $dispatched_managers = $queue->dispatchManagers();
        }

        // 2) If staff becomes available, run BOTH dispatchers
        if ($prevStatus !== 'available' && $requested === 'available') {
            $dispatched_agents   = $queue->dispatchAgents();
            $dispatched_managers = $queue->dispatchManagers();
        }

        return response()->json([
            'ok' => true,
            'status' => $requested,
            'logged' => (bool) $shouldLog,
            'requeued' => $requeued,
            'dispatched_agents' => $dispatched_agents,
            'dispatched_managers' => $dispatched_managers,
            'requeued_disputes' => $requeued_disputes,
    'dispute_dispatched' => $dispute_dispatched,

        ]);
    }

    public function heartbeat(Request $request)
    {
        $u = $request->user();
        $nowUtc = now()->utc();

        // ✅ only heartbeat timestamp; do NOT flip presence online
        DB::table('users')->where('id', $u->id)->update([
            'last_activity_at' => $nowUtc->toDateTimeString(),
            'updated_at'       => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function closeOpenLogs(int $userId, Carbon $nowUtc, ?array $onlyStatuses = null, ?array $exceptStatuses = null): void
    {
        $q = DB::table('agent_status_logs')
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->lockForUpdate();

        if ($onlyStatuses) $q->whereIn('status', $onlyStatuses);
        if ($exceptStatuses) $q->whereNotIn('status', $exceptStatuses);

        $openLogs = $q->orderByDesc('started_at')->get();

        foreach ($openLogs as $open) {
            $start = Carbon::parse($open->started_at, 'UTC');
            $end   = $nowUtc->copy();
            if ($end->lessThanOrEqualTo($start)) $end = $start->copy()->addSecond();

            DB::table('agent_status_logs')->where('id', $open->id)->update([
                'ended_at'   => $end->toDateTimeString(),
                'updated_at' => $end->toDateTimeString(),
            ]);
        }
    }
}