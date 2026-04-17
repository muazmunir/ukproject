<?php
// app/Support/Shift.php
// app/Support/Shift.php
namespace App\Support;

use App\Models\Users;
use Carbon\Carbon;

class Shift
{
  public static function isWithinShiftNow(Users $u, ?Carbon $nowUtc = null): bool
  {
    // If shift not configured, treat as "always log"
    if (empty($u->shift_enabled)) return true;
    if (!$u->shift_start || !$u->shift_end) return true;

    $tz = $u->timezone ?: 'UTC';

    // use now in user's TZ for comparison
    $nowTz = ($nowUtc ?: now())->copy()->setTimezone($tz);

    // Optional: if you later store shift_days JSON in users
    $days = $u->shift_days ?: null; // [1..7]
    if (is_array($days) && count($days)) {
      if (!in_array($nowTz->isoWeekday(), $days, true)) return false;
    }

    $start = $nowTz->copy()->setTimeFromTimeString(substr((string)$u->shift_start, 0, 8));
    $end   = $nowTz->copy()->setTimeFromTimeString(substr((string)$u->shift_end, 0, 8));

    // Normal shift (same day)
    if ($start->lte($end)) {
      return $nowTz->betweenIncluded($start, $end);
    }

    // Overnight shift (e.g. 22:00 -> 06:00)
    // Valid if now >= start OR now <= end
    return $nowTz->gte($start) || $nowTz->lte($end);
  }
}
