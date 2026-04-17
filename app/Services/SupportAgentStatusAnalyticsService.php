<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupportAgentStatusAnalyticsService
{
    private const LOG_STATUSES = [
        'available',
        'break',
        'meeting',
        'tech_issues',
        'admin',
        'holiday',
        'authorized_absence',
        'unauthorized_absence',
    ];

    private const STATUSES = [
      
        'available',
        'break',
        'meeting',
        'tech_issues',
        'admin',
        'holiday',
        'authorized_absence',
        'unauthorized_absence',
    ];

    public function buildForUser(
        int $userId,
        string $range,
        string $displayTz = 'UTC',
        ?string $anchor = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        try {
            new \DateTimeZone($displayTz);
        } catch (\Throwable $e) {
            $displayTz = 'UTC';
        }

        $userShift = DB::table('users')
            ->select('id', 'timezone', 'shift_enabled', 'shift_start', 'shift_end', 'shift_days')
            ->where('id', $userId)
            ->first();

        $nowUtc = Carbon::now('UTC');
        $nowDisplay = $nowUtc->copy()->setTimezone($displayTz);

        [$fromDisplay, $toDisplay, $granularity] = $this->resolveWindow(
            $range,
            $nowDisplay,
            $displayTz,
            $anchor,
            $from,
            $to
        );

        $fromUtc = $fromDisplay->copy()->setTimezone('UTC');
        $toUtc = $toDisplay->copy()->setTimezone('UTC');

        $logs = DB::table('agent_status_logs')
            ->select(['status', 'started_at', 'ended_at'])
            ->where('user_id', $userId)
            ->whereIn('status', self::LOG_STATUSES)
            ->where('started_at', '<', $toUtc->format('Y-m-d H:i:s'))
            ->where(function ($q) use ($fromUtc) {
                $q->whereNull('ended_at')
                    ->orWhere('ended_at', '>', $fromUtc->format('Y-m-d H:i:s'));
            })
            ->orderBy('started_at')
            ->get();

        $workingIntervals = $this->workingIntervalsForBucket(
            $userShift,
            $fromDisplay->copy(),
            $toDisplay->copy(),
            $displayTz
        );

        $sessions = $this->buildSessions(
            $workingIntervals,
            $logs,
            $fromUtc,
            $toUtc,
            $displayTz,
            $nowUtc
        );

        $bucketSeconds = [];
        $bucketKeys = [];
        $labels = [];

        foreach ($this->makeBuckets($fromDisplay, $toDisplay, $granularity) as $bucket) {
            $labels[] = $bucket['label'];
            $bucketKeys[] = $bucket['key'];
            $bucketSeconds[$bucket['key']] = array_fill_keys(self::STATUSES, 0);
        }

        foreach ($sessions as $session) {
            $status = strtolower((string) $session['status']);
           if (!in_array($status, self::STATUSES, true)) {
    continue;
}

            $this->allocateSessionIntoBuckets(
                $bucketSeconds,
                $status,
                Carbon::createFromFormat('Y-m-d H:i:s', $session['start'], $displayTz),
                Carbon::createFromFormat('Y-m-d H:i:s', $session['end'], $displayTz),
                $granularity,
                $toDisplay,
                $displayTz
            );
        }

       

        $orderedStatuses = [
           
            'available',
            'break',
            'meeting',
            'admin',
            'tech_issues',
            'holiday',
            'authorized_absence',
            'unauthorized_absence',
        ];

        $datasets = [];
        foreach ($orderedStatuses as $status) {
            $datasets[] = [
                'key' => $status,
                'label' => $this->pretty($status),
                'data' => array_map(function (string $key) use ($bucketSeconds, $status) {
                    return round((($bucketSeconds[$key][$status] ?? 0) / 3600), 2);
                }, $bucketKeys),
                'stack' => 'status',
            ];
        }

        $totalsSeconds = array_fill_keys(self::STATUSES, 0);
        foreach ($bucketSeconds as $row) {
            foreach (self::STATUSES as $status) {
                $totalsSeconds[$status] += (int) ($row[$status] ?? 0);
            }
        }

        $totalsMinutes = [];
        $totalsHours = [];
        foreach ($totalsSeconds as $status => $seconds) {
            $totalsMinutes[$status] = (int) floor($seconds / 60);
            $totalsHours[$status] = round($seconds / 3600, 2);
        }

        return [
            'range' => $range,
            'display_tz' => $displayTz,
            'from' => $fromDisplay->toDateTimeString(),
            'to' => $toDisplay->toDateTimeString(),
            'labels' => $labels,
            'datasets' => $datasets,
            'totals' => $totalsHours,
            'totals_hours' => $totalsHours,
            'totals_minutes' => $totalsMinutes,
            'totals_seconds' => $totalsSeconds,
            'sessions' => $sessions,
        ];
    }

    public function buildForUsers(
        iterable $users,
        string $range,
        ?string $anchor = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        $payload = [];

        foreach ($users as $user) {
            $payload[(int) $user->id] = $this->buildForUser(
                (int) $user->id,
                $range,
                (string) ($user->timezone ?: 'UTC'),
                $anchor,
                $from,
                $to
            );
        }

        return $payload;
    }

    private function resolveWindow(
        string $range,
        Carbon $nowDisplay,
        string $tz,
        ?string $anchor,
        ?string $from,
        ?string $to
    ): array {
        $range = strtolower($range);

        $anchorDate = $anchor ?: $nowDisplay->format('Y-m-d');
        $anchorDt = Carbon::createFromFormat('Y-m-d', $anchorDate, $tz)->startOfDay();

        if ($range === 'custom') {
            $fromDate = $from ?: $anchorDate;
            $toDate = $to ?: $anchorDate;

            $fromDisplay = Carbon::createFromFormat('Y-m-d', $fromDate, $tz)->startOfDay();
            $toDisplay = Carbon::createFromFormat('Y-m-d', $toDate, $tz)->endOfDay();

            if ($toDisplay->greaterThan($nowDisplay)) {
                $toDisplay = $nowDisplay->copy();
            }

            return [$fromDisplay, $toDisplay, 'hour'];
        }

        return match ($range) {
            'daily' => [
                $anchorDt->copy()->startOfDay(),
                $anchorDt->copy()->endOfDay()->min($nowDisplay),
                'minute',
            ],
            'weekly' => [
                $anchorDt->copy()->startOfWeek(),
                $anchorDt->copy()->startOfWeek()->addDays(6)->endOfDay()->min($nowDisplay),
                'day',
            ],
            'monthly' => [
                $anchorDt->copy()->startOfMonth(),
                $anchorDt->copy()->endOfMonth()->endOfDay()->min($nowDisplay),
                'day',
            ],
            'yearly' => [
                $anchorDt->copy()->startOfYear(),
                $anchorDt->copy()->endOfYear()->endOfDay()->min($nowDisplay),
                'month',
            ],
            'lifetime' => [
                Carbon::create(2000, 1, 1, 0, 0, 0, $tz),
                $nowDisplay->copy(),
                'month',
            ],
            default => [
                $nowDisplay->copy()->startOfWeek(),
                $nowDisplay->copy(),
                'day',
            ],
        };
    }

    private function makeBuckets(Carbon $from, Carbon $to, string $granularity): array
    {
        $output = [];
        $cursor = $this->bucketStart($from, $granularity);

        while ($cursor->lessThanOrEqualTo($to)) {
            $output[] = [
                'key' => $this->bucketKey($cursor, $granularity),
                'label' => $this->bucketLabel($cursor, $granularity),
            ];
            $cursor = $this->nextBucketStart($cursor, $granularity);
        }

        return $output;
    }

    private function bucketStart(Carbon $dateTime, string $granularity): Carbon
    {
        return match ($granularity) {
            'minute' => $dateTime->copy()->startOfMinute(),
            'hour' => $dateTime->copy()->startOfHour(),
            'day' => $dateTime->copy()->startOfDay(),
            'month' => $dateTime->copy()->startOfMonth(),
            default => $dateTime->copy(),
        };
    }

    private function nextBucketStart(Carbon $bucketStart, string $granularity): Carbon
    {
        return match ($granularity) {
            'minute' => $bucketStart->copy()->addMinute(),
            'hour' => $bucketStart->copy()->addHour(),
            'day' => $bucketStart->copy()->addDay(),
            'month' => $bucketStart->copy()->addMonth(),
            default => $bucketStart->copy()->addDay(),
        };
    }

    private function bucketKey(Carbon $bucketStart, string $granularity): string
    {
        return match ($granularity) {
            'minute' => $bucketStart->format('Y-m-d H:i:00'),
            'hour' => $bucketStart->format('Y-m-d H:00:00'),
            'day' => $bucketStart->format('Y-m-d 00:00:00'),
            'month' => $bucketStart->format('Y-m-01 00:00:00'),
            default => $bucketStart->toDateTimeString(),
        };
    }

    private function bucketLabel(Carbon $bucketStart, string $granularity): string
    {
        return match ($granularity) {
            'minute' => $bucketStart->format('H:i'),
            'hour' => $bucketStart->format('H:00'),
            'day' => $bucketStart->format('M d'),
            'month' => $bucketStart->format('M Y'),
            default => $bucketStart->toDateTimeString(),
        };
    }

    private function workingIntervalsForBucket($user, Carbon $bucketStart, Carbon $bucketEnd, string $tz): array
    {
        if (!$user || !(bool) ($user->shift_enabled ?? false)) {
            return [];
        }

        $days = $user->shift_days ?? [];
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($days)) {
            $days = [];
        }
        $days = array_map('intval', $days);

        $start = (string) ($user->shift_start ?? '');
        $end = (string) ($user->shift_end ?? '');
        if ($start === '' || $end === '') {
            return [];
        }

        $cursorDay = $bucketStart->copy()->setTimezone($tz)->startOfDay();
        $lastDay = $bucketEnd->copy()->setTimezone($tz)->startOfDay();
        $output = [];

        while ($cursorDay->lte($lastDay)) {
            $isoDay = (int) $cursorDay->isoWeekday();

            if (in_array($isoDay, $days, true)) {
                $workingStart = $cursorDay->copy()->setTimeFromTimeString($start);
                $workingEnd = $cursorDay->copy()->setTimeFromTimeString($end);

                if ($workingEnd->lessThanOrEqualTo($workingStart)) {
                    $workingEnd = $workingEnd->addDay();
                }

                $intervalStart = $workingStart->copy()->max($bucketStart);
                $intervalEnd = $workingEnd->copy()->min($bucketEnd);

                if ($intervalEnd->greaterThan($intervalStart)) {
                    $output[] = [$intervalStart, $intervalEnd];
                }
            }

            $cursorDay->addDay();
        }

        return $output;
    }

    private function intersectSeconds(Carbon $startA, Carbon $endA, Carbon $startB, Carbon $endB): int
    {
        $start = $startA->copy()->max($startB);
        $end = $endA->copy()->min($endB);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return $start->diffInSeconds($end);
    }

    private function workingSecondsForBucket(array $intervals): int
    {
        $sum = 0;
        foreach ($intervals as [$start, $end]) {
            $sum += $start->diffInSeconds($end);
        }

        return $sum;
    }

    private function buildSessions(
        array $workingIntervals,
        Collection $logs,
        Carbon $fromUtc,
        Carbon $toUtc,
        string $displayTz,
        Carbon $nowUtc
    ): array {
        if (!$workingIntervals) {
            return [];
        }

        $segments = [];

        foreach ($logs as $log) {
            $status = strtolower((string) $log->status);
            if (!in_array($status, self::LOG_STATUSES, true)) {
                continue;
            }

            $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $log->started_at, 'UTC')
                ->max($fromUtc);
            $endUtc = $log->ended_at
                ? Carbon::createFromFormat('Y-m-d H:i:s', $log->ended_at, 'UTC')
                : $nowUtc->copy();
            $endUtc = $endUtc->min($toUtc);

            if ($endUtc->lessThanOrEqualTo($startUtc)) {
                continue;
            }

            $segmentStart = $startUtc->copy()->setTimezone($displayTz);
            $segmentEnd = $endUtc->copy()->setTimezone($displayTz);

            foreach ($workingIntervals as [$workingStart, $workingEnd]) {
                $clippedStart = $segmentStart->copy()->max($workingStart);
                $clippedEnd = $segmentEnd->copy()->min($workingEnd);

                if ($clippedEnd->greaterThan($clippedStart)) {
                    $segments[] = [
                        'status' => $status,
                        'start' => $clippedStart->copy(),
                        'end' => $clippedEnd->copy(),
                    ];
                }
            }
        }

        usort($segments, fn(array $a, array $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        $merged = [];
        foreach ($segments as $segment) {
            if (!$merged) {
                $merged[] = $segment;
                continue;
            }

            $lastIndex = count($merged) - 1;
            $last = $merged[$lastIndex];

            if (
                $last['status'] === $segment['status']
                && $segment['start']->lessThanOrEqualTo($last['end'])
            ) {
                $merged[$lastIndex]['end'] = $last['end']->copy()->max($segment['end']);
                continue;
            }

            $merged[] = $segment;
        }

        $sessionPayload = [];
        foreach ($merged as $segment) {
            $sessionPayload[] = [
                'status' => $segment['status'],
                'start' => $segment['start']->format('Y-m-d H:i:s'),
                'end' => $segment['end']->format('Y-m-d H:i:s'),
                'seconds' => $segment['start']->diffInSeconds($segment['end']),
                'minutes' => (int) floor($segment['start']->diffInSeconds($segment['end']) / 60),
            ];
        }

        return $sessionPayload;
    }

  

   

    private function allocateSessionIntoBuckets(
        array &$bucketSeconds,
        string $status,
        Carbon $sessionStart,
        Carbon $sessionEnd,
        string $granularity,
        Carbon $windowEnd,
        string $displayTz
    ): void {
        $cursor = $sessionStart->copy();

        while ($cursor->lessThan($sessionEnd)) {
            $bucketStart = $this->bucketStart($cursor, $granularity);
            $bucketEnd = $this->nextBucketStart($bucketStart, $granularity)->min($windowEnd);
            $chunkEnd = $sessionEnd->copy()->min($bucketEnd);

            if ($chunkEnd->lessThanOrEqualTo($cursor)) {
                break;
            }

            $key = $this->bucketKey($bucketStart, $granularity);
            if (!array_key_exists($key, $bucketSeconds)) {
                $bucketSeconds[$key] = array_fill_keys(self::STATUSES, 0);
            }

            $bucketSeconds[$key][$status] += $cursor->diffInSeconds($chunkEnd);
            $cursor = $chunkEnd;
        }
    }

    private function pretty(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
