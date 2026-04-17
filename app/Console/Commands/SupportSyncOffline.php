<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\SupportQueueService;
use App\Services\DisputeQueueService;

class SupportSyncOffline extends Command
{
    /**
     * If user goes inactive while in these statuses, KEEP COUNTING that status.
     * But always set support_presence = offline so chats go to queue.
     */
    private const KEEP_COUNTING_WHEN_GONE = [
        'break',
        'tech_issues',
        'holiday',
        'authorized_absence',
        'unauthorized_absence',
    ];

    /**
     * If user goes inactive while in these statuses, SWITCH to unauthorized_absence
     * and start counting unauthorized_absence.
     */
    private const SWITCH_TO_UA_WHEN_GONE = [
        'available',
        'admin',
        'meeting',
    ];

    protected $signature = 'support:sync-offline
        {--mins=3 : Minutes without heartbeat to mark presence offline}
        {--presence=1 : 1=update users.support_presence to offline, 0=do not touch users table}';

    protected $description = 'On heartbeat timeout for admin/manager: set presence offline (to queue chats). If status is available/admin/meeting -> switch to unauthorized_absence and start counting it. If break/tech/leave -> keep counting that status. No OFFLINE analytics logs.';

    public function handle(): int
    {
        $mins = (int) $this->option('mins');
        if ($mins <= 0) $mins = 3;

        $presenceOn = ((int) $this->option('presence')) === 1;

        $nowUtc = now()->utc();
        $nowStr = $nowUtc->toDateTimeString();
        $cutoff = $nowUtc->copy()->subMinutes($mins);

        // Candidates: stale heartbeat and not already presence-offline
        $users = DB::table('users')
            ->select(
                'id',
                'role',
                'support_presence',
                'support_status',
                'support_status_since',
                'absence_return_required',
                'absence_return_since',
                'absence_kind',
                'absence_status',
                'absence_start_at',
                'absence_end_at',
                'last_activity_at'
            )
            ->whereIn('role', ['admin', 'manager', 'superadmin'])
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<', $cutoff->toDateTimeString())
            // pick stale users if either:
// - presence isn't offline (normal case), OR
// - status is one of the "switch to UA" statuses AND they are not already UA
->where(function ($q) {
    $q->whereNull('support_presence')
      ->orWhereRaw('LOWER(support_presence) <> ?', ['offline'])
      ->orWhere(function ($q2) {
          $q2->whereIn(DB::raw('LOWER(support_status)'), ['available','admin','meeting'])
             ->whereRaw('LOWER(support_status) <> ?', ['unauthorized_absence']);
      });
})
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users to mark offline.');
            return self::SUCCESS;
        }

        $processed = 0;
        $keptCounting = 0;
        $switchedToUA = 0;

        // requeue after transaction
        $requeueTargets = [];

        DB::transaction(function () use (
            $users, $nowUtc, $nowStr, $presenceOn,
            &$processed, &$keptCounting, &$switchedToUA, &$requeueTargets
        ) {
            foreach ($users as $u) {
                $processed++;

                // Lock and read current open status log (actual counting status)
                $open = DB::table('agent_status_logs')
                    ->where('user_id', $u->id)
                    ->whereNull('ended_at')
                    ->orderByDesc('started_at')
                    ->lockForUpdate()
                    ->first();

                $openStatus = $open ? strtolower((string) $open->status) : null;
                $current = $openStatus ?: strtolower((string) ($u->support_status ?? ''));

                // ===== A) Leave window handling: if active holiday/authorized absence -> keep locked
                $startUtc = $u->absence_start_at ? Carbon::parse($u->absence_start_at, 'UTC') : null;
                $endUtc   = $u->absence_end_at   ? Carbon::parse($u->absence_end_at, 'UTC')   : null;

                $kind = strtolower((string) ($u->absence_kind ?? ''));   // holiday|absence|null
                $type = strtolower((string) ($u->absence_status ?? '')); // authorized|unauthorized|null

                $hasLeave = $kind !== '' && $type !== '' && $startUtc && $endUtc;

                // end EXCLUSIVE (match controller)
                $isActiveWindow = $hasLeave
                    && $nowUtc->greaterThanOrEqualTo($startUtc)
                    && $nowUtc->lessThan($endUtc);

                $activeLock = $isActiveWindow && (
                    ($kind === 'holiday') ||
                    ($kind === 'absence' && $type === 'authorized')
                );

                if ($activeLock) {
                    // Presence offline so chats go to queue; keep leave counting as-is.
                    if ($presenceOn) {
                        DB::table('users')->where('id', $u->id)->update([
                            'support_presence'       => 'offline',
                            'support_presence_since' => $nowStr,
                            'updated_at'             => now(),
                        ]);
                    }

                    // requeue (not reachable)
                    $role = strtolower((string) ($u->role ?? 'admin'));
                    if ($role === 'superadmin') $role = 'admin';
                    if (!in_array($role, ['admin', 'manager'], true)) $role = 'admin';
                    $requeueTargets[$u->id] = $role;

                    $keptCounting++;
                    continue;
                }

                // If leave window ENDED and return_required is not set, force UA until they return
                $windowEnded = $hasLeave && $nowUtc->greaterThanOrEqualTo($endUtc);

                if ($windowEnded && !(bool) ($u->absence_return_required ?? false)) {
                    // Close any open log first (no parallel)
                    if ($open) {
                        $start = Carbon::parse($open->started_at, 'UTC');
                        $end   = $nowUtc->copy();
                        if ($end->lessThanOrEqualTo($start)) $end = $start->copy()->addSecond();

                        DB::table('agent_status_logs')->where('id', $open->id)->update([
                            'ended_at'   => $end->toDateTimeString(),
                            'updated_at' => $end->toDateTimeString(),
                        ]);
                    }

                    // Switch to UA + return required
                    if ($presenceOn) {
                        DB::table('users')->where('id', $u->id)->update([
                            'support_status'          => 'unauthorized_absence',
                            'support_status_since'    => $nowStr,
                            'support_presence'        => 'offline',
                            'support_presence_since'  => $nowStr,
                            'absence_return_required' => true,
                            'absence_return_since'    => $nowStr,
                            'updated_at'              => now(),
                        ]);
                    } else {
                        // even if presenceOn=0, still enforce status analytics correctness
                        DB::table('users')->where('id', $u->id)->update([
                            'support_status'          => 'unauthorized_absence',
                            'support_status_since'    => $nowStr,
                            'absence_return_required' => true,
                            'absence_return_since'    => $nowStr,
                            'updated_at'              => now(),
                        ]);
                    }

                    DB::table('agent_status_logs')->insert([
                        'user_id'    => $u->id,
                        'status'     => 'unauthorized_absence',
                        'reason'     => 'leave_window_ended',
                        'started_at' => $nowStr,
                        'ended_at'   => null,
                        'created_at' => $nowStr,
                        'updated_at' => $nowStr,
                    ]);

                    $role = strtolower((string) ($u->role ?? 'admin'));
                    if ($role === 'superadmin') $role = 'admin';
                    if (!in_array($role, ['admin', 'manager'], true)) $role = 'admin';
                    $requeueTargets[$u->id] = $role;

                    $switchedToUA++;
                    continue;
                }

                // ===== B) Normal inactivity handling (logout/heartbeat timeout style)
                // Always mark presence offline (so chats go to queue)
                if ($presenceOn) {
                    DB::table('users')->where('id', $u->id)->update([
                        'support_presence'       => 'offline',
                        'support_presence_since' => $nowStr,
                        'updated_at'             => now(),
                    ]);
                }

                // If current status must keep counting, do NOT close/open logs
                if ($current && in_array($current, self::KEEP_COUNTING_WHEN_GONE, true)) {
                    $role = strtolower((string) ($u->role ?? 'admin'));
                    if ($role === 'superadmin') $role = 'admin';
                    if (!in_array($role, ['admin', 'manager'], true)) $role = 'admin';
                    $requeueTargets[$u->id] = $role;

                    $keptCounting++;
                    continue;
                }

                // If current is not in switch-to-UA list (or empty), we still treat as UA fallback.
                // But this primarily targets available/admin/meeting.
                // Close any open log (no parallel)
                if ($open) {
                    $start = Carbon::parse($open->started_at, 'UTC');
                    $end   = $nowUtc->copy();
                    if ($end->lessThanOrEqualTo($start)) $end = $start->copy()->addSecond();

                    DB::table('agent_status_logs')->where('id', $open->id)->update([
                        'ended_at'   => $end->toDateTimeString(),
                        'updated_at' => $end->toDateTimeString(),
                    ]);
                }

                // Update user status -> UA and mark return required
                DB::table('users')->where('id', $u->id)->update([
                    'support_status'          => 'unauthorized_absence',
                    'support_status_since'    => $nowStr,
                    'absence_return_required' => true,
                    'absence_return_since'    => $nowStr,
                    // presence fields already set above if presenceOn=1
                    'updated_at'              => now(),
                ]);

                // Open UA log if not already open (defensive)
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
                        'reason'     => 'heartbeat_timeout',
                        'started_at' => $nowStr,
                        'ended_at'   => null,
                        'created_at' => $nowStr,
                        'updated_at' => $nowStr,
                    ]);
                }

                $role = strtolower((string) ($u->role ?? 'admin'));
                if ($role === 'superadmin') $role = 'admin';
                if (!in_array($role, ['admin', 'manager'], true)) $role = 'admin';
                $requeueTargets[$u->id] = $role;

                $switchedToUA++;
            }
        });

        // Requeue + dispatch OUTSIDE the big transaction
       // Requeue + dispatch OUTSIDE the big transaction
$queue = app(SupportQueueService::class);
$disputeQueue = app(DisputeQueueService::class);

// Support chats
$totalRequeued = 0;
foreach ($requeueTargets as $uid => $role) {
    $totalRequeued += (int) $queue->requeueStaffOpenConversations((int) $uid, (string) $role);
}
$assignedAgents   = $queue->dispatchAgents();
$assignedManagers = $queue->dispatchManagers();

// Disputes
$totalDisputeRequeued = 0;
foreach (array_keys($requeueTargets) as $uid) {
    $totalDisputeRequeued += (int) $disputeQueue->requeueStaffOpenedDisputes((int)$uid);
}
$assignedDisputes = $disputeQueue->dispatchAgents();

       $this->info(
    "Processed: {$processed}, Kept counting: {$keptCounting}, Switched to UA: {$switchedToUA} | " .
    "Chat requeued: {$totalRequeued}, Chat assigned agents: {$assignedAgents}, Chat assigned managers: {$assignedManagers} | " .
    "Dispute requeued: {$totalDisputeRequeued}, Dispute assigned: {$assignedDisputes}"
);

        return self::SUCCESS;
    }
}