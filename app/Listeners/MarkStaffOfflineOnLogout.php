<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\SupportQueueService;
use App\Services\DisputeQueueService;

class MarkStaffOfflineOnLogout
{
    /**
     * If user logs out while in these statuses, KEEP COUNTING the status.
     * But always set support_presence = offline so chats go to queue.
     */
    private const KEEP_COUNTING_ON_LOGOUT = [
        'break',
        'tech_issues',
        'holiday',
        'authorized_absence',
        'unauthorized_absence',
    ];

    /**
     * If user logs out while in these statuses, SWITCH to unauthorized_absence.
     * (This covers your: available/admin/meeting -> UA on logout)
     */
    private const SWITCH_TO_UA_ON_LOGOUT = [
        'available',
        'admin',
        'meeting',
    ];

    public function handle(Logout $event): void
    {
        $u = $event->user;
        if (!$u) return;

        $role = strtolower(trim((string) ($u->role ?? '')));
        if (!in_array($role, ['admin', 'manager', 'superadmin'], true)) return;

        $nowUtc = now()->utc();
        $nowStr = $nowUtc->toDateTimeString();

        DB::transaction(function () use ($u, $nowUtc, $nowStr) {

            // 1) Presence offline for UI (so chats go in queue)
            DB::table('users')->where('id', $u->id)->update([
                'support_presence'       => 'offline',
                'support_presence_since' => $nowStr,
                'updated_at'             => now(),
            ]);

            // 2) Find current open log (counting status)
            $open = DB::table('agent_status_logs')
                ->where('user_id', $u->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            $openStatus = $open ? strtolower((string) $open->status) : null;

            // Fall back to user.support_status if no open log
            $current = $openStatus ?: strtolower((string) ($u->support_status ?? ''));

            // 3) If status must keep counting, do NOT close/open anything.
            if ($current && in_array($current, self::KEEP_COUNTING_ON_LOGOUT, true)) {
                return;
            }

            // 4) Otherwise (available/admin/meeting or unknown), switch to unauthorized_absence
            // Close current open log (no parallel)
            if ($open) {
                $start = Carbon::parse($open->started_at, 'UTC');
                $end   = $nowUtc->copy();
                if ($end->lessThanOrEqualTo($start)) $end = $start->copy()->addSecond();

                DB::table('agent_status_logs')->where('id', $open->id)->update([
                    'ended_at'   => $end->toDateTimeString(),
                    'updated_at' => $nowStr,
                ]);
            }

            // Update user status -> UA and require return
            DB::table('users')->where('id', $u->id)->update([
                'support_status'          => 'unauthorized_absence',
                'support_status_since'    => $nowStr,
                'absence_return_required' => true,
                'absence_return_since'    => $nowStr,
                'updated_at'              => now(),
            ]);

            // Open UA interval if not already open (defensive)
            $uaOpen = DB::table('agent_status_logs')
                ->where('user_id', $u->id)
                ->whereRaw('LOWER(status) = ?', ['unauthorized_absence'])
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->exists();

            if (!$uaOpen) {
                DB::table('agent_status_logs')->insert([
                    'user_id'    => $u->id,
                    'status'     => 'unauthorized_absence',
                    'reason'     => 'logout',
                    'started_at' => $nowStr,
                    'ended_at'   => null,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ]);
            }
        });

        $this->requeueAndDispatch($u->id, $role);
    }

   private function requeueAndDispatch(int $userId, string $role): void
{
    $queue = app(SupportQueueService::class);
    $roleForQueue = ($role === 'superadmin') ? 'admin' : $role;

    // ✅ Support chats
    $queue->requeueStaffOpenConversations($userId, $roleForQueue);

    // ✅ Disputes
    $disputeQueue = app(DisputeQueueService::class);
    $disputeQueue->requeueStaffOpenedDisputes($userId);

    // ✅ Dispatch both systems
    $queue->dispatchAgents();
    $queue->dispatchManagers();

    $disputeQueue->dispatchAgents();
}
}