<?php

namespace App\Support;

class SupportPresence
{
    /**
     * These statuses must KEEP COUNTING even if:
     * - user logs out
     * - heartbeat timeout happens
     *
     * We never auto-switch these to unauthorized_absence.
     */
    private const KEEP_COUNTING_STATUSES = [
        'break',
        'tech_issues',
        'holiday',
        'authorized_absence',
        'unauthorized_absence',
    ];

    /**
     * True if the user's current status must keep counting
     * and should NOT be force-switched to unauthorized_absence.
     */
    public static function shouldKeepCounting($u): bool
    {
        $status = strtolower((string) ($u->support_status ?? ''));

        if (in_array($status, self::KEEP_COUNTING_STATUSES, true)) {
            return true;
        }

        return false;
    }

    /**
     * True if user is in active leave window (holiday or authorized absence).
     * Used for locking dropdown.
     */
    public static function isActiveLeaveWindow($u): bool
    {
        if (!$u->absence_kind || !$u->absence_status || !$u->absence_start_at || !$u->absence_end_at) {
            return false;
        }

        $now   = now()->utc();
        $start = \Carbon\Carbon::parse($u->absence_start_at)->utc();
        $end   = \Carbon\Carbon::parse($u->absence_end_at)->utc();

        $isActive = $now->greaterThanOrEqualTo($start) && $now->lessThan($end);

        if (!$isActive) return false;

        $kind = strtolower((string) $u->absence_kind);
        $type = strtolower((string) $u->absence_status);

        return (
            $kind === 'holiday' ||
            ($kind === 'absence' && $type === 'authorized')
        );
    }

    /**
     * True if user must return manually (post-holiday / post-authorized absence).
     */
    public static function requiresReturn($u): bool
    {
        return (bool) ($u->absence_return_required ?? false);
    }
}