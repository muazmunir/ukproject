<?php
// app/Http/Controllers/Coach/CalendarController.php
namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CoachWeeklyHour;
use App\Models\CoachUnavailability;
use App\Models\CoachAvailabilityOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use App\Models\ReservationSlot;
use App\Models\Reservation;


class CalendarController extends Controller
{
    // ---- Helpers ----
    private function safeTz(?string $tz, ?string $fallback = null): string
    {
        $fallback = $fallback ?: config('app.timezone', 'UTC');
        $tz = is_string($tz) ? trim($tz) : '';
        try { return $tz ? (new \DateTimeZone($tz))->getName() : $fallback; }
        catch (\Throwable) { return $fallback; }
    }

    private function coachTz(Request $r): string
    {
        return $this->safeTz($r->user()->timezone ?? null);
    }

    /**
     * Does the ISO string already include an explicit zone? e.g. ...Z or ...+05:00
     */
    private function isoHasOffset(string $iso): bool
    {
        return (bool) preg_match('/([+-]\d{2}:\d{2}|Z)$/', $iso);
    }

    /**
     * Parse any ISO string to UTC:
     * - If it already carries an offset/Z, trust it (no fallback tz passed).
     * - Else parse with the provided fallback tz.
     */
    private function parseIsoToUtc(string $iso, string $fallbackTz): CarbonImmutable
    {
        return $this->isoHasOffset($iso)
            ? CarbonImmutable::parse($iso)->utc()
            : CarbonImmutable::parse($iso, $fallbackTz)->utc();
    }

    private function boundsToUtc(string $start, string $end, string $fallbackTz): array
    {
        $a = $this->parseIsoToUtc($start, $fallbackTz);
        $b = $this->parseIsoToUtc($end,   $fallbackTz);
        if ($b->lte($a)) { $b = $a->addMinute(); }
        return [$a, $b];
    }

    public function index(Request $r)
    {
        return view('coach.calendar.index', [
            'coachTz' => $this->coachTz($r),
        ]);
    }

    // ---------- Weekly schedule ----------
    public function getSchedule(Request $r)
    {
        $rows = CoachWeeklyHour::where('coach_id', $r->user()->id)
            ->orderBy('weekday')->orderBy('start_time')
            ->get(['weekday','start_time','end_time']);

        return response()->json($rows);
    }

    public function saveSchedule(Request $r)
    {
        $data = $r->validate([
            'items' => 'required|array',
            'items.*.weekday'    => 'required|integer|min:0|max:6',
            'items.*.start_time' => 'required|date_format:H:i',
            'items.*.end_time'   => 'required|date_format:H:i',
        ]);

        DB::transaction(function () use ($r, $data) {
            CoachWeeklyHour::where('coach_id', $r->user()->id)->delete();
            $payload = [];
            foreach ($data['items'] as $it) {
                if ($it['start_time'] === $it['end_time']) continue; // skip zero-length
                $payload[] = [
                    'coach_id'   => $r->user()->id,
                    'weekday'    => (int)$it['weekday'],
                    'start_time' => $it['start_time'],
                    'end_time'   => $it['end_time'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($payload) CoachWeeklyHour::insert($payload);
        });

        return response()->json(['ok'=>true]);
    }

    // ---------- Unavailability ----------
    public function storeUnavailability(Request $r)
{
    $tz = $this->coachTz($r);

    $v = $r->validate([
        'start'  => 'required|string', // ISO (partial-day) or YYYY-MM-DD (all-day)
        'end'    => 'required|string',
        'reason' => 'nullable|string|max:255',
        'repeat' => 'nullable|in:none,daily,weekly',
        'allDay' => 'nullable|boolean',
    ]);

    $repeat   = $v['repeat'] ?? 'none';
    $isAllDay = (bool) ($v['allDay'] ?? false);

    // Base range
    $ranges = [[$v['start'], $v['end']]];

    // Build repeats
    if ($repeat === 'daily' || $repeat === 'weekly') {
        if ($isAllDay) {
            // Treat as date-only; keep adding days/weeks to the DATE itself.
            $s0 = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $v['start'], 'UTC');
            $e0 = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $v['end'],   'UTC');
            $n  = $repeat === 'daily' ? 13 : 7;

            for ($i = 1; $i <= $n; $i++) {
                $ss = $repeat === 'daily' ? $s0->addDays($i)  : $s0->addWeeks($i);
                $ee = $repeat === 'daily' ? $e0->addDays($i)  : $e0->addWeeks($i);
                $ranges[] = [$ss->format('Y-m-d'), $ee->format('Y-m-d')];
            }
        } else {
            // Partial-day: generate next occurrences in the COACH tz, keeping the same local wall time.
            $s0Local = $this->isoHasOffset($v['start'])
                ? \Carbon\CarbonImmutable::parse($v['start'])->setTimezone($tz)
                : \Carbon\CarbonImmutable::parse($v['start'], $tz);

            $e0Local = $this->isoHasOffset($v['end'])
                ? \Carbon\CarbonImmutable::parse($v['end'])->setTimezone($tz)
                : \Carbon\CarbonImmutable::parse($v['end'], $tz);

            $n = $repeat === 'daily' ? 13 : 7;
            for ($i = 1; $i <= $n; $i++) {
                $ss = $repeat === 'daily' ? $s0Local->addDays($i)  : $s0Local->addWeeks($i);
                $ee = $repeat === 'daily' ? $e0Local->addDays($i)  : $e0Local->addWeeks($i);
                // Emit ISO strings with coach offset; backend will convert to UTC once.
                $ranges[] = [$ss->toIso8601String(), $ee->toIso8601String()];
            }
        }
    }

    DB::transaction(function () use ($r, $tz, $ranges, $v, $isAllDay) {   // <-- include $isAllDay
        foreach ($ranges as [$ls, $le]) {
            if ($isAllDay) {
                // YYYY-MM-DD semantics: snap to UTC midnights [00:00Z, 00:00Z)
                $a = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i:s', $ls.' 00:00:00', 'UTC');
                $b = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i:s', $le.' 00:00:00', 'UTC');
                if ($b->lte($a)) { $b = $a->addDay(); }
            } else {
                // Partial-day: trust offsets if present; else parse in coach tz → UTC
                [$a, $b] = $this->boundsToUtc($ls, $le, $tz);
            }

            \App\Models\CoachUnavailability::create([
                'coach_id'  => $r->user()->id,
                'start_utc' => $a,
                'end_utc'   => $b,
                'reason'    => $v['reason'] ?? null,
            ]);
        }
    });

    return response()->json(['ok' => true]);
}


    public function clearUnavailability(Request $r)
    {
        $tz = $this->coachTz($r);
        $v = $r->validate([
            'start' => 'required|string',
            'end'   => 'required|string',
        ]);
        [$a,$b] = $this->boundsToUtc($v['start'], $v['end'], $tz); // <-- trusts offsets

        CoachUnavailability::where('coach_id',$r->user()->id)
            ->where(function($q) use ($a,$b){
                $q->whereBetween('start_utc', [$a,$b])
                  ->orWhereBetween('end_utc',   [$a,$b])
                  ->orWhere(function($q2) use ($a,$b){
                      $q2->where('start_utc','<=',$a)->where('end_utc','>=',$b);
                  });
            })->delete();

        return response()->json(['ok'=>true]);
    }

    // ---------- Availability override ----------
    // app/Http/Controllers/Coach/CalendarController.php

public function storeAvailabilityOverride(Request $r)
{
    $coach   = $r->user();
    $tz      = $coach->timezone ?: config('app.timezone', 'UTC'); // coach-local tz
    $reason  = trim((string) $r->input('reason', ''));

    // Two ways this can come in:
    // A) drag selection (ISO start/end in coach tz)
    // B) month view + manual times: date + start_time + end_time in coach tz
    $source = $r->input('source', 'drag'); // 'drag' | 'manual'
    $allDay = $r->boolean('allDay', false);

    if ($source === 'manual') {
        // Expect: date (Y-m-d), start_time (HH:mm), end_time (HH:mm) in coach tz
        $date  = $r->input('date');
        $st    = $r->input('start_time');
        $et    = $r->input('end_time');

        if (!$date || !$st || !$et) {
            return response()->json(['message' => 'date, start_time, end_time required'], 422);
        }

        try {
            $startLocal = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $st", $tz);
            $endLocal   = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i', "$date $et", $tz);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid date/time format'], 422);
        }

        // If end <= start, roll to next day (typical overnight case)
        if ($endLocal->lessThanOrEqualTo($startLocal)) {
            $endLocal = $endLocal->addDay();
        }

        $startUtc = $startLocal->utc();
        $endUtc   = $endLocal->utc();
    } else {
        // 'drag' path: expect start/end ISO strings interpreted in coach tz
        // JS will send tz explicitly but we still prefer coach tz from DB.
        $startIn  = $r->input('start'); // e.g. "2025-11-03T14:00:00"
        $endIn    = $r->input('end');

        if (!$startIn || !$endIn) {
            return response()->json(['message' => 'start/end required'], 422);
        }

        try {
            $startUtc = \Carbon\CarbonImmutable::parse($startIn, $tz)->utc();
            $endUtc   = \Carbon\CarbonImmutable::parse($endIn,   $tz)->utc();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid start/end'], 422);
        }

        if ($endUtc->lessThanOrEqualTo($startUtc)) {
            return response()->json(['message' => 'End must be after start'], 422);
        }
    }

    // 💾 Persist in UTC — make sure your migration uses UTC column names consistently.
    // Example columns: starts_at_utc, ends_at_utc, reason
    $row = \App\Models\CoachAvailabilityOverride::create([
        'coach_id'      => $coach->id,
        'start_utc' => $startUtc,   // timestamps are saved in UTC
        'end_utc'   => $endUtc,
        'reason'        => $reason ?: null,
    ]);

    // Return event as coach-local for immediate rendering feedback (optional)
    return response()->json([
        'id'     => $row->id,
        'title'  => $reason ?: __('Availability override'),
        'allDay' => $allDay,
        'start'  => $startUtc->setTimezone($tz)->toIso8601String(),
        'end'    => $endUtc->setTimezone($tz)->toIso8601String(),
        'classNames' => ['avail-override'],
      ]);
}


    // ---------- Events feed (coach & client share shape) ----------
    public function events(Request $r)
    {
        $user = $r->user();
        $coachId = $user->id;
        $coachTz = $this->coachTz($r);

        $viewTz = $this->safeTz($r->query('tz') ?? $coachTz);
        $startQ = $r->query('start'); // may already include Z/+offset
        $endQ   = $r->query('end');

        if (!$startQ || !$endQ) return response()->json([]);

        // Visible bounds → UTC (trust offset if present)
        $visStartUtc = $this->parseIsoToUtc($startQ, $viewTz);
        $visEndUtc   = $this->parseIsoToUtc($endQ,   $viewTz);

        // Load rows intersecting the window
        $un = CoachUnavailability::where('coach_id',$coachId)
            ->where(function($q) use ($visStartUtc,$visEndUtc){
                $q->whereBetween('start_utc', [$visStartUtc,$visEndUtc])
                  ->orWhereBetween('end_utc',   [$visStartUtc,$visEndUtc])
                  ->orWhere(function($q2) use ($visStartUtc,$visEndUtc){
                      $q2->where('start_utc','<=',$visStartUtc)->where('end_utc','>=',$visEndUtc);
                  });
            })->get();

        $ov = CoachAvailabilityOverride::where('coach_id',$coachId)
            ->where(function($q) use ($visStartUtc,$visEndUtc){
                $q->whereBetween('start_utc', [$visStartUtc,$visEndUtc])
                  ->orWhereBetween('end_utc',   [$visStartUtc,$visEndUtc])
                  ->orWhere(function($q2) use ($visStartUtc,$visEndUtc){
                      $q2->where('start_utc','<=',$visStartUtc)->where('end_utc','>=',$visEndUtc);
                  });
            })->get();

        $weekly = CoachWeeklyHour::where('coach_id',$coachId)->get();

        $events = [];

        // 1) Weekly availability (background, painted from coach's TZ)
        if ($weekly->count()) {
            $period = CarbonPeriod::create(
                $visStartUtc->setTimezone($coachTz)->startOfDay(),
                '1 day',
                $visEndUtc->setTimezone($coachTz)->subDay()->endOfDay()
            );

            foreach ($period as $coachLocalDay) {
                $weekday = (int)$coachLocalDay->dayOfWeek; // 0..6 (coach tz)
                foreach ($weekly->where('weekday',$weekday) as $row) {
                    $startLocalCoach = CarbonImmutable::parse(
                        $coachLocalDay->format('Y-m-d').' '.$row->start_time, $coachTz
                    );
                    $endLocalCoach   = CarbonImmutable::parse(
                        $coachLocalDay->format('Y-m-d').' '.$row->end_time,   $coachTz
                    );
                    if ($endLocalCoach->lte($startLocalCoach)) {
                        $endLocalCoach = $endLocalCoach->addDay();
                    }

                    // Output ISO with offset (coach tz); FullCalendar converts to its view TZ
                    $events[] = [
                        'start'      => $startLocalCoach->toIso8601String(),
                        'end'        => $endLocalCoach->toIso8601String(),
                        'display'    => 'background',
                        'classNames' => ['available-weekly','fc-bg-event'],
                    ];
                }
            }
        }

        // 2) Unavailability (stored UTC → ISO Z)
        foreach ($un as $u) {
            $events[] = [
                'start'      => $u->start_utc->toIso8601String(),
                'end'        => $u->end_utc->toIso8601String(),
                'display'    => 'background',
                'classNames' => ['unavail-partial','fc-bg-event'],
                'extendedProps' => ['reason' => $u->reason],
            ];
        }

        // 3) Overrides (stored UTC → ISO Z)
        foreach ($ov as $o) {
            $events[] = [
                'start'      => $o->start_utc->toIso8601String(),
                'end'        => $o->end_utc->toIso8601String(),
                'display'    => 'background',
                'classNames' => ['avail-override','fc-bg-event'],
                'extendedProps' => ['reason' => $o->reason],
            ];
        }
        // 4) Booked + Cancelled slots (stored UTC → ISO Z, FullCalendar will show in coach tz)
// 4) Booked slots (foreground) + Blocked slots (background)
$slotRows = ReservationSlot::query()
    ->whereHas('reservation', function ($q) use ($coachId) {
        $q->where('coach_id', $coachId)
          ->where(function ($qq) {
              // Booked (show as normal event)
              $qq->where(function ($a) {
                  $a->where('status', 'booked')
                    ->where('payment_status', 'paid');
              })
              // Coach-cancelled candidates (we'll decide block/free by 0–7 days rule below)
              ->orWhere(function ($b) {
                  $b->whereIn('status', ['cancelled','canceled'])
                    ->where('payment_status', 'paid')
                    ->whereRaw('LOWER(cancelled_by) = ?', ['coach'])
                    ->whereNotNull('cancelled_at');
              });
          });
    })
    ->where(function ($q) use ($visStartUtc, $visEndUtc) {
        // intersects visible window
        $q->whereBetween('start_utc', [$visStartUtc, $visEndUtc])
          ->orWhereBetween('end_utc',   [$visStartUtc, $visEndUtc])
          ->orWhere(function ($q2) use ($visStartUtc, $visEndUtc) {
              $q2->where('start_utc', '<=', $visStartUtc)
                 ->where('end_utc',   '>=', $visEndUtc);
          });
    })
    ->with(['reservation:id,status,payment_status,cancelled_by,cancelled_at'])
    ->get(['id','reservation_id','start_utc','end_utc']);

// First session start per reservation (min start_utc)
$resIds = $slotRows->pluck('reservation_id')->unique()->values();

$firstStarts = [];
if ($resIds->count()) {
    $firstStarts = ReservationSlot::query()
        ->whereIn('reservation_id', $resIds)
        ->selectRaw('reservation_id, MIN(start_utc) as first_start_utc')
        ->groupBy('reservation_id')
        ->pluck('first_start_utc', 'reservation_id')
        ->all();
}

foreach ($slotRows as $s) {
    $res = $s->reservation;
    if (!$res) continue;

    $startIso = CarbonImmutable::parse($s->start_utc)->utc()->toIso8601String();
    $endIso   = CarbonImmutable::parse($s->end_utc)->utc()->toIso8601String();

    // A) BOOKED => show as normal (foreground) event
    if ($res->status === 'booked' && $res->payment_status === 'paid') {
        $events[] = [
            'id'    => 'booked-'.$s->id,
            'title' => 'Booked',
            'start' => $startIso,
            'end'   => $endIso,
            'classNames' => ['res-booked-slot'],
            'extendedProps' => [
                'type' => 'booked',
                'reservation_id' => $s->reservation_id,
            ],
        ];
        continue;
    }

    // B) COACH-CANCELLED => show BLOCKED background ONLY if first session was within 0–7 days at cancel time
    $isCoachCancelled =
        in_array($res->status, ['cancelled','canceled'], true)
        && strtolower((string)$res->cancelled_by) === 'coach'
        && $res->payment_status === 'paid'
        && $res->cancelled_at;

    if ($isCoachCancelled) {
        $firstStartUtcRaw = $firstStarts[$s->reservation_id] ?? null;
        if (!$firstStartUtcRaw) continue;

        $firstStartUtc = CarbonImmutable::parse($firstStartUtcRaw)->utc();
        $cancelAtUtc   = CarbonImmutable::parse($res->cancelled_at)->utc();

        // hours left until FIRST session start at cancellation time
        $hoursLeft = $cancelAtUtc->diffInRealHours($firstStartUtc, false);

        // 0–7 days (0–168 hours) => blocked
        $shouldBlock = ($hoursLeft >= 0 && $hoursLeft <= 168);

        if ($shouldBlock) {
            $events[] = [
                'id'      => 'blocked-'.$s->id,
                'start'   => $startIso,
                'end'     => $endIso,
                // 'display' => 'background',
                'title'   => 'Cancelled By Coach',
                'classNames' => ['res-blocked-slot'],
                'extendedProps' => [
                    'type' => 'blocked',
                    'reservation_id' => $s->reservation_id,
                    'rule' => 'coach_cancel_0_7_days',
                    'hours_left' => $hoursLeft,
                ],
            ];
        }

        // if NOT blocked (coach cancelled 7+ days away) => show nothing (free)
        continue;
    }

    // NOTE:
    // - Client-cancelled never comes into this query (we don't include it),
    //   and even if you did, it should stay FREE => no event.
}

        return response()->json($events);
    }

    // ---------- Persist OS timezone ----------
    public function lockTimezone(Request $r)
{
    $v = $r->validate(['tz' => 'required|string']);
    $tz = $this->safeTz($v['tz']);

    if ($r->user()) {
        // Only set if empty to avoid clobbering a deliberate choice
        if (empty($r->user()->timezone)) {
            $r->user()->forceFill(['timezone' => $tz])->save();
        }
    } else {
        session(['guest_timezone' => $tz]);
    }
    return response()->json(['ok' => true, 'tz' => $r->user()->timezone ?? $tz]);
}
}
