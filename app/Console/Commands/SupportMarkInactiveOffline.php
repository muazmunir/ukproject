<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\SupportQueueService;
use Carbon\Carbon;

class SupportMarkInactiveOffline extends Command
{
    protected $signature = 'support:mark-inactive-offline {--mins=10}';
    protected $description = 'Mark inactive staff offline based on last_activity_at and requeue chats';

    public function handle(): int
    {
        $mins = max(2, (int) $this->option('mins'));
        $nowUtc = now()->utc();
        $cutoff = $nowUtc->copy()->subMinutes($mins)->toDateTimeString();

        $users = DB::table('users')
            ->select('id','role','support_status','last_activity_at')
            ->whereIn(DB::raw('LOWER(role)'), ['admin','manager','superadmin'])
            ->where(function($q) use ($cutoff){
                $q->whereNull('last_activity_at')
                  ->orWhere('last_activity_at', '<', $cutoff);
            })
            ->get();

        $queue = app(SupportQueueService::class);

        foreach ($users as $u) {
            $role = strtolower(trim((string) ($u->role ?? '')));
            $roleForQueue = ($role === 'superadmin') ? 'admin' : $role;

            $cur = strtolower((string) ($u->support_status ?? ''));

            // ✅ NEVER override leave statuses
            if (in_array($cur, ['holiday','authorized_absence','unauthorized_absence'], true)) continue;

            // ✅ only force if in normal working statuses
            if (!in_array($cur, ['available','break','meeting','tech_issues','admin'], true)) continue;

            // already offline? skip
            if ($cur === 'offline') continue;

            $now = $nowUtc->toDateTimeString();

            DB::transaction(function() use ($u, $nowUtc, $now) {
                // close open log
                $open = DB::table('agent_status_logs')
                    ->where('user_id', $u->id)
                    ->whereNull('ended_at')
                    ->orderByDesc('started_at')
                    ->first();

                if ($open) {
                    $start = Carbon::parse($open->started_at)->utc();
                    $end = $nowUtc->copy();
                    if ($end->lessThanOrEqualTo($start)) $end = $start->copy()->addSecond();

                    DB::table('agent_status_logs')->where('id', $open->id)->update([
                        'ended_at' => $end->toDateTimeString(),
                        'updated_at' => now(),
                    ]);
                }

                // set offline
                DB::table('users')->where('id', $u->id)->update([
                    'support_status'       => 'offline',
                    'support_status_since' => $now,
                    'updated_at'           => now(),
                ]);

                // open offline log
                DB::table('agent_status_logs')->insert([
                    'user_id' => $u->id,
                    'status' => 'offline',
                    'reason' => 'Auto: inactivity',
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

            // ✅ requeue + dispatch
            $queue->requeueStaffOpenConversations($u->id, $roleForQueue);
            if ($roleForQueue === 'manager') $queue->dispatchManagers();
            else $queue->dispatchAdmins();
        }

        return self::SUCCESS;
    }
}
