<?php

namespace App\Http\Controllers\SuperAdmin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\Users;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminLogsController extends Controller
{
    public function index(Request $request)
    {
        $viewerTz = auth()->user()->timezone ?? config('app.timezone');

        $q      = trim((string) $request->query('q', ''));
        $action = (string) $request->query('action', 'all');

        // ✅ Time filters
        $range   = (string) $request->query('range', 'lifetime'); // lifetime|daily|weekly|monthly|yearly|custom|window
        $date    = (string) $request->query('date', '');          // YYYY-MM-DD (for "window")
        $from    = (string) $request->query('from', '');          // HH:MM (for "window")
        $to      = (string) $request->query('to', '');            // HH:MM (for "window")
        $startAt = (string) $request->query('start_at', '');      // YYYY-MM-DDTHH:MM (for "custom")
        $endAt   = (string) $request->query('end_at', '');        // YYYY-MM-DDTHH:MM (for "custom")

        [$startUtc, $endUtc] = $this->resolveUtcRange(
            $range, $viewerTz, $date, $from, $to, $startAt, $endAt
        );

        $logs = AdminActionLog::query()
            ->with(['admin'])
            ->when($action !== 'all', fn ($qq) => $qq->where('action', $action))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('admin', function ($u) use ($q) {
                    $u->where('email', 'like', "%{$q}%")
                      ->orWhere('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
            // ✅ apply time range if present
            ->when($startUtc && $endUtc, function ($qq) use ($startUtc, $endUtc) {
                $qq->whereBetween('created_at', [$startUtc, $endUtc]);
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // ✅ Collect target users from THIS PAGE only (no N+1)
        $targetUserIds = $logs->getCollection()
            ->filter(fn ($l) => $l->target_type === Users::class && !empty($l->target_id))
            ->pluck('target_id')
            ->unique()
            ->values();

        $targetUsers = Users::withTrashed()
            ->whereIn('id', $targetUserIds)
            ->get()
            ->keyBy('id');

        $actions = [
            'soft_locked','soft_unlocked','hard_locked','hard_unlocked',
            'delete_user','delete_service','payment_toggle','restore_service',
            'restore_user_client','restore_user_coach',
        ];

        return view('superadmin.security.logs', compact(
            'logs','q','action','actions','targetUsers',
            'range','date','from','to','startAt','endAt','viewerTz'
        ));
    }

    private function resolveUtcRange(
        string $range,
        string $tz,
        string $date,
        string $from,
        string $to,
        string $startAt,
        string $endAt
    ): array {
        $range = strtolower(trim($range));

        // default: no filter
        $start = null;
        $end   = null;

        $now = Carbon::now($tz);

        try {
            if ($range === 'daily') {
                $start = $now->copy()->startOfDay();
                $end   = $now->copy()->endOfDay();
            } elseif ($range === 'weekly') {
                $start = $now->copy()->startOfWeek(); // Monday by default (Carbon)
                $end   = $now->copy()->endOfWeek();
            } elseif ($range === 'monthly') {
                $start = $now->copy()->startOfMonth();
                $end   = $now->copy()->endOfMonth();
            } elseif ($range === 'yearly') {
                $start = $now->copy()->startOfYear();
                $end   = $now->copy()->endOfYear();
            } elseif ($range === 'custom') {
                // expects datetime-local: 2026-01-18T14:00
                if ($startAt && $endAt) {
                    $start = Carbon::createFromFormat('Y-m-d\TH:i', $startAt, $tz);
                    $end   = Carbon::createFromFormat('Y-m-d\TH:i', $endAt, $tz);
                }
            } elseif ($range === 'window') {
                // date + time window (e.g. 2026-01-18, 14:00 -> 16:00)
                if ($date) {
                    if ($from && $to) {
                        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$from}", $tz);
                        $end   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$to}", $tz);
                    } else {
                        // if only date, take the whole day
                        $start = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
                        $end   = Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay();
                    }
                }
            }
        } catch (\Throwable $e) {
            // invalid input -> ignore time filter
            $start = null;
            $end   = null;
        }

        // Normalize: if end < start, swap
        if ($start && $end && $end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        // convert to UTC for DB
        if ($start && $end) {
            return [$start->copy()->timezone('UTC'), $end->copy()->timezone('UTC')];
        }

        return [null, null];
    }
}
