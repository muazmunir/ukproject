<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Users;

class ApplyScheduledLeave extends Command
{
    protected $signature = 'zaivias:apply-leave';
    protected $description = 'Apply scheduled absence/holiday windows and enforce post-absence unauthorized state';

    public function handle(): int
    {
        $now = now()->utc();

        Users::query()
            ->whereNotNull('absence_start_at')
            ->whereNotNull('absence_end_at')
            ->whereNotNull('absence_kind')
            ->chunkById(200, function ($users) use ($now) {
                foreach ($users as $user) {
                    $this->processUser($user, $now);
                }
            });

        return self::SUCCESS;
    }

    private function processUser(Users $user, Carbon $now): void
    {
        $start = Carbon::parse($user->absence_start_at)->utc();
        $end   = Carbon::parse($user->absence_end_at)->utc();

        if ($end->lessThanOrEqualTo($start)) {
            return;
        }

        /**
         * ⏱ ACTIVE WINDOW
         * start <= now < end
         */
        if ($now->between($start, $end)) {
            $this->applyActiveLeave($user, $now);
            return;
        }

        /**
         * ⛔ POST WINDOW
         * now >= end → unauthorized until manual return
         */
        if ($now->greaterThanOrEqualTo($end)) {
            $this->applyPostUnauthorized($user, $now);
        }
    }

   private function applyActiveLeave(Users $user, Carbon $now): void
{
    $kind = strtolower((string) $user->absence_kind);
    $type = strtolower((string) ($user->absence_status ?? 'unauthorized'));

    // holiday approved => always force holiday
    if ($kind === 'holiday') {
        $this->forceStatus($user, 'holiday', 'Holiday started', $now);
        return;
    }

    // absence approved => always force authorized_absence
    if ($type === 'authorized') {
        $this->forceStatus($user, 'authorized_absence', 'Authorized absence started', $now);
        return;
    }

    // absence rejected => set unauthorized_absence ONCE, then allow user to override
    // If user already changed away from unauthorized_absence, do NOT force again.
    if ($type === 'unauthorized') {

        // if they already moved away, leave them
        if ($user->support_status && $user->support_status !== 'unauthorized_absence') {
            return;
        }

        // if it's already unauthorized_absence, also stop forcing (so user can change next)
        if ($user->support_status === 'unauthorized_absence') {
            return;
        }

        // otherwise set it once
        $this->forceStatus($user, 'unauthorized_absence', 'Rejected absence window started', $now);
        return;
    }
}


private function forceStatus(Users $user, string $status, string $reason, Carbon $now): void
{
    if ($user->support_status === $status) return;

    DB::transaction(function () use ($user, $status, $reason, $now) {

        DB::table('agent_status_logs')
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at'   => $now,
                'updated_at' => $now,
            ]);

        DB::table('agent_status_logs')->insert([
            'user_id'    => $user->id,
            'status'     => $status,
            'reason'     => $reason,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user->forceFill([
            'support_status'       => $status,
            'support_status_since' => $now,
        ])->save();
    });
}

   private function applyPostUnauthorized(Users $user, Carbon $now): void
{
    $kind = strtolower((string) $user->absence_kind);
    $type = strtolower((string) ($user->absence_status ?? ''));

    // ✅ never post-force for holiday
    if ($kind === 'holiday') {
        $this->clearLeaveIfExpired($user, $now);
        return;
    }

    // ✅ never post-force for rejected absence (unauthorized windows)
    if ($type === 'unauthorized') {
        $this->clearLeaveIfExpired($user, $now);
        return;
    }

    // ✅ only for authorized absence if you want "return required"
    if ($type === 'authorized') {
        if ($user->support_status !== 'unauthorized_absence') {
            $this->forceStatus($user, 'unauthorized_absence', 'Leave ended — awaiting return', $now);
        }
        return;
    }
}


private function clearLeaveIfExpired(Users $user, Carbon $now): void
{
    DB::transaction(function () use ($user, $now) {
        $user->forceFill([
            'absence_kind'     => null,
            'absence_status'   => null,
            'absence_start_at' => null,
            'absence_end_at'   => null,
            'absence_set_at'   => $now,
        ])->save();
    });
}

}
