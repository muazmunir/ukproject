<?php
// app/Http/Controllers/ServiceAvailabilityController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\CoachWeeklyHour;
use App\Models\CoachUnavailability;
use App\Models\CoachAvailabilityOverride;
use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class ServiceAvailabilityController extends Controller
{
    // ---------- Helpers ----------

    private function safeTz(?string $tz, ?string $fallback = null): string
    {
        $fallback = $fallback ?: config('app.timezone', 'UTC');
        $tz = is_string($tz) ? trim($tz) : '';

        try {
            return $tz ? (new \DateTimeZone($tz))->getName() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /** Does an ISO end with an explicit offset or Z? */
    private function isoHasOffset(string $iso): bool
    {
        return (bool) preg_match('/([+-]\d{2}:\d{2}|Z)$/', $iso);
    }

    /** Parse any ISO string to UTC; respect embedded offset if present */
    private function parseIsoToUtc(string $iso, string $fallbackTz): CarbonImmutable
    {
        return $this->isoHasOffset($iso)
            ? CarbonImmutable::parse($iso)->utc()
            : CarbonImmutable::parse($iso, $fallbackTz)->utc();
    }

    private function rangesIntersect(CarbonImmutable $aStart, CarbonImmutable $aEnd, CarbonImmutable $bStart, CarbonImmutable $bEnd): bool
    {
        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }

    private function subtractRanges(array $wins, array $blocks): array
    {
        $out = [];

        foreach ($wins as [$ws, $we]) {
            $segments = [[$ws, $we]];

            foreach ($blocks as [$bs, $be]) {
                $next = [];

                foreach ($segments as [$ss, $se]) {
                    if ($be->lte($ss) || $bs->gte($se)) {
                        $next[] = [$ss, $se];
                        continue;
                    }

                    if ($bs->gt($ss)) {
                        $next[] = [$ss, $bs];
                    }

                    if ($be->lt($se)) {
                        $next[] = [$be, $se];
                    }
                }

                $segments = $next;
                if (!$segments) {
                    break;
                }
            }

            foreach ($segments as $seg) {
                if ($seg[1]->gt($seg[0])) {
                    $out[] = $seg;
                }
            }
        }

        return $out;
    }

    private function mergeRanges(array $ranges): array
    {
        if (!$ranges) {
            return [];
        }

        usort($ranges, fn($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($ranges as [$s, $e]) {
            if (!$merged) {
                $merged[] = [$s, $e];
                continue;
            }

            [$ps, $pe] = $merged[count($merged) - 1];

            if ($s->lte($pe)) {
                $merged[count($merged) - 1] = [$ps, $e->max($pe)];
            } else {
                $merged[] = [$s, $e];
            }
        }

        return $merged;
    }

   private function floorToQuantum($t, int $q = 30): CarbonImmutable
{
    if (!$t instanceof CarbonImmutable) {
        $t = CarbonImmutable::parse($t);
    }

    $t = $t->setSecond(0);
    $m = $t->minute % $q;

    return $t->subMinutes($m);
}

    /**
     * GET /services/{service}/availability?start=&end=&tz=
     *
     * Background bands only (weekly, unavailability, overrides).
     * Reservation blocks are applied in day() where we generate slots.
     */
    public function show(Request $r, Service $service)
    {
        $coach = $service->coach;
        abort_unless($coach, 404);

        $coachTz = $this->safeTz(
            $coach->timezone
            ?? optional($coach->profile)->timezone
            ?? optional($coach->coachProfile)->timezone
            ?? null
        );

        $viewTz = $this->safeTz($r->query('tz') ?: $coachTz);

        $startQ = $r->query('start');
        $endQ   = $r->query('end');

        if (!$startQ || !$endQ) {
            return response()->json(['message' => 'start/end required'], 422);
        }

        $visStartUtc = $this->parseIsoToUtc($startQ, $viewTz);
        $visEndUtc   = $this->parseIsoToUtc($endQ,   $viewTz);

        $un = CoachUnavailability::query()
            ->where('coach_id', $coach->id)
            ->where(function ($q) use ($visStartUtc, $visEndUtc) {
                $q->whereBetween('start_utc', [$visStartUtc, $visEndUtc])
                  ->orWhereBetween('end_utc', [$visStartUtc, $visEndUtc])
                  ->orWhere(function ($q2) use ($visStartUtc, $visEndUtc) {
                      $q2->where('start_utc', '<=', $visStartUtc)
                         ->where('end_utc', '>=', $visEndUtc);
                  });
            })
            ->get(['start_utc', 'end_utc', 'reason']);

        $ov = CoachAvailabilityOverride::query()
            ->where('coach_id', $coach->id)
            ->where(function ($q) use ($visStartUtc, $visEndUtc) {
                $q->whereBetween('start_utc', [$visStartUtc, $visEndUtc])
                  ->orWhereBetween('end_utc', [$visStartUtc, $visEndUtc])
                  ->orWhere(function ($q2) use ($visStartUtc, $visEndUtc) {
                      $q2->where('start_utc', '<=', $visStartUtc)
                         ->where('end_utc', '>=', $visEndUtc);
                  });
            })
            ->get(['start_utc', 'end_utc', 'reason']);

        $weekly = CoachWeeklyHour::query()
            ->where('coach_id', $coach->id)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['weekday', 'start_time', 'end_time']);

        $events = [];

        // Weekly availability in coach TZ
        if ($weekly->count()) {
            $coachDayStart = $visStartUtc->setTimezone($coachTz)->startOfDay();
            $coachDayEnd   = $visEndUtc->setTimezone($coachTz)->endOfDay();

            if ($coachDayEnd->gte($coachDayStart)) {
                $period = CarbonPeriod::create($coachDayStart, '1 day', $coachDayEnd);

                foreach ($period as $coachLocalDay) {
                    $weekday = (int) $coachLocalDay->dayOfWeek; // 0=Sun ... 6=Sat

                    foreach ($weekly->where('weekday', $weekday) as $row) {
                        $startLocalCoach = CarbonImmutable::parse(
                            $coachLocalDay->format('Y-m-d') . ' ' . $row->start_time,
                            $coachTz
                        );

                        $endLocalCoach = CarbonImmutable::parse(
                            $coachLocalDay->format('Y-m-d') . ' ' . $row->end_time,
                            $coachTz
                        );

                        if ($endLocalCoach->lte($startLocalCoach)) {
                            $endLocalCoach = $endLocalCoach->addDay();
                        }

                        $events[] = [
                            'start' => $startLocalCoach->toIso8601String(),
                            'end'   => $endLocalCoach->toIso8601String(),
                            'display' => 'background',
                            'classNames' => ['avail-blue', 'fc-bg-event'],
                            'extendedProps' => ['type' => 'weekly'],
                        ];
                    }
                }
            }
        }

        // Unavailability
        foreach ($un as $u) {
            $events[] = [
                'start' => $u->start_utc->toIso8601String(),
                'end'   => $u->end_utc->toIso8601String(),
                'display' => 'background',
                'classNames' => ['unavail-red', 'fc-bg-event'],
                'extendedProps' => [
                    'type' => 'unavailability',
                    'reason' => $u->reason,
                ],
            ];
        }

        // Availability overrides
        foreach ($ov as $o) {
            $events[] = [
                'start' => $o->start_utc->toIso8601String(),
                'end'   => $o->end_utc->toIso8601String(),
                'display' => 'background',
                'classNames' => ['avail-blue', 'fc-bg-event'],
                'extendedProps' => [
                    'type' => 'override',
                    'reason' => $o->reason,
                ],
            ];
        }

        return response()->json([
            'debug' => compact('coachTz', 'viewTz', 'startQ', 'endQ', 'visStartUtc', 'visEndUtc'),
            'events' => $events,
        ]);
    }

    /**
     * GET /services/{service}/availability/day?date=YYYY-MM-DD&tz=...&hpd=1&step=30
     *
     * Rules:
     * - Weekly availability creates the base windows
     * - Unavailability subtracts from weekly
     * - Availability overrides add back time
     * - Booked + paid reservations block time
     * - Coach-cancelled reservations block time ONLY if the reservation's FIRST slot
     *   was within next 0-7 days when cancelled
     * - Any slot that has already started in the CLIENT/VIEW timezone is NOT returned
     */
    public function day(Request $r, Service $service)
    {
        $coach = $service->coach;
        abort_unless($coach, 404);

        $coachTz = $this->safeTz(
            $coach->timezone
            ?? optional($coach->profile)->timezone
            ?? optional($coach->coachProfile)->timezone
            ?? null
        );

        $date = $r->query('date'); // YYYY-MM-DD in client/view timezone
        if (!$date) {
            return response()->json(['windows' => [], 'slots' => []], 422);
        }

        $viewTz = $this->safeTz($r->query('tz') ?: $coachTz);

        $hpd  = (float) $r->query('hpd', 0);
        $step = (int) $r->query('step', 30);

        if ($step < 5) {
            $step = 5;
        }
        if ($step % 5) {
            $step = 30;
        }

        $slotMinutes = (int) round($hpd * 60);

        // Client-selected day in VIEW TZ
        $viewDayStart = CarbonImmutable::createFromFormat('Y-m-d H:i', $date . ' 00:00', $viewTz);
        $viewDayEnd   = $viewDayStart->addDay();

        // Same instant represented in COACH TZ
        $d0 = $viewDayStart->setTimezone($coachTz);
        $d1 = $viewDayEnd->setTimezone($coachTz);

        // "Now" according to the viewing client timezone
        $viewerNowUtc = CarbonImmutable::now($viewTz)->utc();

        // 1) Weekly windows for coach-local dates touched by this view day
        $weeklyRows = CoachWeeklyHour::query()
            ->where('coach_id', $coach->id)
            ->get(['weekday', 'start_time', 'end_time']);

        $weekly = [];

        $coachDatesToCheck = [];
        $cursor = $d0->startOfDay();
        $last   = $d1->endOfDay();

        while ($cursor->lte($last)) {
            $coachDatesToCheck[] = $cursor;
            $cursor = $cursor->addDay();
        }

        foreach ($coachDatesToCheck as $coachDate) {
            $weekday = (int) $coachDate->dayOfWeek; // 0=Sun ... 6=Sat

            foreach ($weeklyRows->where('weekday', $weekday) as $row) {
                $s = CarbonImmutable::parse($coachDate->format('Y-m-d') . ' ' . $row->start_time, $coachTz);
                $e = CarbonImmutable::parse($coachDate->format('Y-m-d') . ' ' . $row->end_time,   $coachTz);

                if ($e->lte($s)) {
                    $e = $e->addDay();
                }

                // Clip to the current client-visible day window represented in coach TZ
                $s = $s->max($d0);
                $e = $e->min($d1);

                if ($e->gt($s)) {
                    $weekly[] = [$s, $e];
                }
            }
        }

        $weekly = $this->mergeRanges($weekly);

        // 2) Unavailability
        $un = CoachUnavailability::query()
            ->where('coach_id', $coach->id)
            ->where(function ($q) use ($d0, $d1) {
                $q->whereBetween('start_utc', [$d0->utc(), $d1->utc()])
                  ->orWhereBetween('end_utc', [$d0->utc(), $d1->utc()])
                  ->orWhere(function ($q2) use ($d0, $d1) {
                      $q2->where('start_utc', '<=', $d0->utc())
                         ->where('end_utc', '>=', $d1->utc());
                  });
            })
            ->get();

        $unLocal = [];
        foreach ($un as $u) {
            $s = $u->start_utc->setTimezone($coachTz)->max($d0);
            $e = $u->end_utc->setTimezone($coachTz)->min($d1);

            if ($e->gt($s)) {
                $unLocal[] = [$s, $e];
            }
        }

        $unLocal = $this->mergeRanges($unLocal);

        // 3) Availability overrides
        $ov = CoachAvailabilityOverride::query()
            ->where('coach_id', $coach->id)
            ->where(function ($q) use ($d0, $d1) {
                $q->whereBetween('start_utc', [$d0->utc(), $d1->utc()])
                  ->orWhereBetween('end_utc', [$d0->utc(), $d1->utc()])
                  ->orWhere(function ($q2) use ($d0, $d1) {
                      $q2->where('start_utc', '<=', $d0->utc())
                         ->where('end_utc', '>=', $d1->utc());
                  });
            })
            ->get();

        $ovLocal = [];
        foreach ($ov as $o) {
            $s = $o->start_utc->setTimezone($coachTz)->max($d0);
            $e = $o->end_utc->setTimezone($coachTz)->min($d1);

            if ($e->gt($s)) {
                $ovLocal[] = [$s, $e];
            }
        }

        $ovLocal = $this->mergeRanges($ovLocal);

        // Base free windows: (weekly - unavailability) + overrides
        $weeklyMinusUn = $this->subtractRanges($weekly, $unLocal);
        $final = array_merge($weeklyMinusUn, $ovLocal);
        $merged = $this->mergeRanges($final);

        // 4) Reservation blocks
        $slotRows = ReservationSlot::query()
            ->whereHas('reservation', function ($q) use ($coach) {
                $q->where('coach_id', $coach->id)
                  ->where(function ($qq) {
                      $qq->where(function ($a) {
                          $a->where('status', 'booked')
                            ->where('payment_status', 'paid');
                      })->orWhere(function ($b) {
                          $b->whereIn('status', ['cancelled', 'canceled'])
                            ->where('payment_status', 'paid')
                            ->whereRaw('LOWER(cancelled_by) = ?', ['coach'])
                            ->whereNotNull('cancelled_at');
                      });
                  });
            })
            ->where(function ($q) use ($d0, $d1) {
                $q->whereBetween('start_utc', [$d0->utc(), $d1->utc()])
                  ->orWhereBetween('end_utc', [$d0->utc(), $d1->utc()])
                  ->orWhere(function ($q2) use ($d0, $d1) {
                      $q2->where('start_utc', '<=', $d0->utc())
                         ->where('end_utc', '>=', $d1->utc());
                  });
            })
            ->with(['reservation:id,status,payment_status,cancelled_by,cancelled_at'])
            ->get(['id', 'reservation_id', 'start_utc', 'end_utc']);

        // Match coach calendar rule:
        // use FIRST slot start of reservation to decide 0-7 day coach-cancel block
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

        $blocks = [];

        foreach ($slotRows as $slot) {
            $res = $slot->reservation;
            if (!$res) {
                continue;
            }

            $startUtc = CarbonImmutable::parse($slot->start_utc)->utc();
            $endUtc   = CarbonImmutable::parse($slot->end_utc)->utc();

            $sLocal = $startUtc->setTimezone($coachTz)->max($d0);
            $eLocal = $endUtc->setTimezone($coachTz)->min($d1);

            if ($eLocal->lte($sLocal)) {
                continue;
            }

            $isActiveBooked = (
                $res->status === 'booked' &&
                $res->payment_status === 'paid'
            );

            $isCoachCancelWithin7Days = false;

            if (
                in_array($res->status, ['cancelled', 'canceled'], true) &&
                strtolower((string) $res->cancelled_by) === 'coach' &&
                $res->payment_status === 'paid' &&
                $res->cancelled_at
            ) {
                $firstStartUtcRaw = $firstStarts[$slot->reservation_id] ?? null;

                if ($firstStartUtcRaw) {
                    $firstStartUtc = CarbonImmutable::parse($firstStartUtcRaw)->utc();
                    $cancelAtUtc   = CarbonImmutable::parse($res->cancelled_at)->utc();

                    $hoursLeft = $cancelAtUtc->diffInRealHours($firstStartUtc, false);

                    // Block only when first session was within next 0-168 hours
                    $isCoachCancelWithin7Days = ($hoursLeft >= 0 && $hoursLeft <= 168);
                }
            }

            if ($isActiveBooked || $isCoachCancelWithin7Days) {
                $blocks[] = [$sLocal, $eLocal];
            }
        }

        // subtract reservation blocks
        $merged = $this->subtractRanges($merged, $blocks);
        $merged = $this->mergeRanges($merged);

        // return windows in UTC
        $windows = array_map(fn($w) => [
            'start' => $w[0]->utc()->toIso8601String(),
            'end'   => $w[1]->utc()->toIso8601String(),
        ], $merged);

        if ($slotMinutes <= 0) {
            return response()->json([
                'windows' => $windows,
                'slots' => [],
            ]);
        }

        // 5) Generate slots, but DO NOT return past/started slots for the client timezone
        $slots = [];

        foreach ($merged as [$s, $e]) {
            $s = $s->setSecond(0);
            $e = $e->setSecond(0);

            $scan = $this->floorToQuantum($s, $step);
            $latestStart = $e->subMinutes($slotMinutes);

            while ($scan->lt($s)) {
                $scan = $scan->addMinutes($step);
            }

            while ($scan->lte($latestStart)) {
                $slotEnd = $scan->addMinutes($slotMinutes);

                $slotStartUtc = $scan->utc();
                $slotEndUtc   = $slotEnd->utc();

                // IMPORTANT:
                // if slot has already started in viewer/client timezone, do not show it
                // this automatically hides crossed/past slots like 8-10 when current time is 10
                if ($slotStartUtc->lte($viewerNowUtc)) {
                    $scan = $scan->addMinutes($step);
                    continue;
                }

                $slots[] = [
                    'start' => $slotStartUtc->toIso8601String(),
                    'end'   => $slotEndUtc->toIso8601String(),
                ];

                $scan = $scan->addMinutes($step);
            }
        }

        if ($r->boolean('debug')) {
            return response()->json([
                'debug' => [
                    'coachTz' => $coachTz,
                    'viewTz'  => $viewTz,
                    'inputDate' => $date,
                    'coachDayWindow' => [
                        $d0->format('Y-m-d H:i:s T'),
                        $d1->format('Y-m-d H:i:s T'),
                    ],
                    'viewerNow' => CarbonImmutable::now($viewTz)->format('Y-m-d H:i:s T'),
                    'viewerNowUtc' => $viewerNowUtc->toIso8601String(),
                    'hpd' => $hpd,
                    'step' => $step,
                    'blocked_count' => count($blocks),
                    'weekly_count' => count($weekly),
                    'unavailability_count' => count($unLocal),
                    'override_count' => count($ovLocal),
                    'final_window_count' => count($merged),
                ],
                'windows' => $windows,
                'slots'   => $slots,
            ]);
        }

        return response()->json([
            'windows' => $windows,
            'slots'   => $slots,
        ]);
    }
}