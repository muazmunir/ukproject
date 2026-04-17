<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupportLeaveTick extends Command
{
    protected $signature = 'support:leave-tick';
    protected $description = 'Apply leave windows: holiday/authorized absence lock + presence offline; post-window -> unauthorized_absence until return; rejected absence does NOT change status.';

    public function handle(): int
    {
        $nowUtc = now()->utc();

        DB::table('users')
            ->select([
                'id','timezone',
                'role',
                'absence_kind','absence_status',
                'absence_start_at','absence_end_at',
                'absence_return_required','absence_return_since',
                'support_status','support_status_since',
                'support_presence','support_presence_since',
            ])
            ->whereNotNull('absence_kind')
            ->whereNotNull('absence_status')
            ->whereNotNull('absence_start_at')
            ->whereNotNull('absence_end_at')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($nowUtc) {
                foreach ($users as $u) {
                    $this->processUser($u, $nowUtc);
                }
            });

        return self::SUCCESS;
    }

    /**
     * Transition counting status (agent_status_logs + users.support_status).
     * Ensures no parallel open logs.
     */
    private function transitionStatus(int $userId, string $newStatus, Carbon $sinceUtc, ?string $reason = null): void
    {
        $sinceUtc = $sinceUtc->copy()->utc();

        DB::transaction(function () use ($userId, $newStatus, $sinceUtc, $reason) {

            // 1) Close currently-open log (if any) - lock rowset to avoid races
            $open = DB::table('agent_status_logs')
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if ($open) {
                $openStart = Carbon::parse($open->started_at)->utc();
                $end = $sinceUtc->copy();
                if ($end->lessThanOrEqualTo($openStart)) {
                    $end = $openStart->copy()->addSecond();
                }

                // If already same status open, just align user table and return (no duplicate rows)
                if (strtolower((string)$open->status) === strtolower($newStatus)) {
                    DB::table('users')->where('id', $userId)->update([
                        'support_status'       => $newStatus,
                        'support_status_since' => $openStart->toDateTimeString(),
                        'updated_at'           => now(),
                    ]);
                    return;
                }

                DB::table('agent_status_logs')->where('id', $open->id)->update([
                    'ended_at'   => $end->toDateTimeString(),
                    'updated_at' => now(),
                ]);
            }

            // 2) Open new log
            DB::table('agent_status_logs')->insert([
                'user_id'    => $userId,
                'status'     => $newStatus,
                'reason'     => $reason,
                'started_at' => $sinceUtc->toDateTimeString(),
                'ended_at'   => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3) Update user current status
            DB::table('users')->where('id', $userId)->update([
                'support_status'       => $newStatus,
                'support_status_since' => $sinceUtc->toDateTimeString(),
                'updated_at'           => now(),
            ]);
        });
    }

    private function forcePresenceOffline(int $userId, Carbon $sinceUtc): void
    {
        $sinceUtc = $sinceUtc->copy()->utc();

        DB::table('users')->where('id', $userId)->update([
            'support_presence'       => 'offline',
            'support_presence_since' => $sinceUtc->toDateTimeString(),
            'updated_at'             => now(),
        ]);
    }

    private function processUser(object $u, Carbon $nowUtc): void
    {
        $kind = strtolower((string) $u->absence_kind);      // holiday|absence
        $type = strtolower((string) $u->absence_status);    // authorized|unauthorized

        $startUtc = Carbon::parse($u->absence_start_at)->utc();
        $endUtc   = Carbon::parse($u->absence_end_at)->utc();

        if ($endUtc->lessThanOrEqualTo($startUtc)) return;

        // Active: start <= now < end   (end excluded)
        $isActive = $nowUtc->greaterThanOrEqualTo($startUtc) && $nowUtc->lessThan($endUtc);

        // Post: now >= end
        $isPost   = $nowUtc->greaterThanOrEqualTo($endUtc);

        /* ==========================================================
         | 1) ACTIVE WINDOW
         | - holiday => FORCE holiday (locked) + presence offline
         | - absence authorized => FORCE authorized_absence (locked) + presence offline
         | - absence unauthorized (rejected) => DO NOTHING (do NOT force UA)
         * ========================================================== */
        if ($isActive) {

            if ($kind === 'holiday') {
                $this->transitionStatus($u->id, 'holiday', $startUtc, 'Auto: holiday window');
                $this->forcePresenceOffline($u->id, $nowUtc); // chats queue
                return;
            }

            if ($kind === 'absence' && $type === 'authorized') {
                $this->transitionStatus($u->id, 'authorized_absence', $startUtc, 'Auto: authorized absence window');
                $this->forcePresenceOffline($u->id, $nowUtc); // chats queue
                return;
            }

            if ($kind === 'absence' && $type === 'unauthorized') {
                // ✅ REJECTED ABSENCE RULE:
                // Do NOT change status to unauthorized_absence.
                // Just leave whatever status they currently have.
                // Optionally, you can still set presence offline if your business wants it,
                // but your requirement says "no need to change status"; presence is handled elsewhere.
                return;
            }

            return;
        }

        /* ==========================================================
         | 2) POST WINDOW (now >= end)
         | - holiday => unauthorized_absence until return (ONLY ONCE) + presence offline
         | - absence authorized => unauthorized_absence until return (ONLY ONCE) + presence offline
         | - absence unauthorized (rejected) => clear leave window ONLY (no UA)
         * ========================================================== */
        if ($isPost) {

            // ✅ Holiday => after end, go unauthorized_absence until return (ONLY ONCE)
            if ($kind === 'holiday') {
                if ((bool) $u->absence_return_required) return;

                $this->transitionStatus($u->id, 'unauthorized_absence', $endUtc, 'Auto: post-holiday until return');
                $this->forcePresenceOffline($u->id, $nowUtc);

                DB::table('users')->where('id', $u->id)->update([
                    'absence_return_required' => true,
                    'absence_return_since'    => $endUtc->toDateTimeString(),
                    'updated_at'              => now(),
                ]);
                return;
            }

            // ✅ Rejected absence => clear leave window (no post enforcement, no UA)
            if ($kind === 'absence' && $type === 'unauthorized') {
                $this->clearLeaveWindow($u->id);
                return;
            }

            // ✅ Authorized absence => post unauthorized until return (ONLY ONCE)
            if ($kind === 'absence' && $type === 'authorized') {
                if ((bool) $u->absence_return_required) return;

                $this->transitionStatus($u->id, 'unauthorized_absence', $endUtc, 'Auto: post-absence until return');
                $this->forcePresenceOffline($u->id, $nowUtc);

                DB::table('users')->where('id', $u->id)->update([
                    'absence_return_required' => true,
                    'absence_return_since'    => $endUtc->toDateTimeString(),
                    'updated_at'              => now(),
                ]);
                return;
            }

            // fallback: clear leave window
            $this->clearLeaveWindow($u->id);
            return;
        }

        // 3) Future (scheduled): do nothing
    }

    private function clearLeaveWindow(int $userId): void
    {
        DB::table('users')->where('id', $userId)->update([
            'absence_kind'            => null,
            'absence_status'          => null,
            'absence_start_at'        => null,
            'absence_end_at'          => null,
            'absence_return_required' => false,
            'absence_return_since'    => null,
            'updated_at'              => now(),
        ]);
    }
}