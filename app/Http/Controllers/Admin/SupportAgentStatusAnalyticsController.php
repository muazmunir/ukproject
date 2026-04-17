<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupportAgentStatusAnalyticsService;
use App\Models\Users;
use Illuminate\Http\Request;

class SupportAgentStatusAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $role = strtolower(trim((string) ($u->role ?? '')));

        abort_unless(in_array($role, ['admin','manager','superadmin'], true), 403);

        // Who are we viewing?
        $targetId = (int) ($request->input('user_id') ?: $u->id);

        // Admin can only see self; manager/superadmin can see others
        if ($targetId !== (int) $u->id) {
            abort_unless(in_array($role, ['manager','superadmin'], true), 403);
        }

        $target = Users::query()
            ->select('id','first_name','last_name','email','timezone','role')
            ->findOrFail($targetId);

        $staff = collect();

        if (in_array($role, ['manager','superadmin'], true)) {
            $staff = Users::query()
                ->select('id','first_name','last_name','email','timezone','role')
                ->whereIn('role', ['admin','manager','superadmin'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        return view('admin.support.status_analytics.index', [
            'target' => $target,
            'staff'  => $staff,
        ]);
    }

    public function data(Request $request, SupportAgentStatusAnalyticsService $svc)
    {
        $viewer = $request->user();
        $role = strtolower(trim((string) ($viewer->role ?? '')));

        abort_unless(in_array($role, ['admin','manager','superadmin'], true), 403);

        // ✅ Range now supports calendar-like navigation
        $range = strtolower((string) $request->input('range', 'weekly'));
        if (!in_array($range, ['daily','weekly','monthly','yearly','lifetime','custom'], true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid range'], 422);
        }

        // Who are we viewing?
        $targetId = (int) ($request->input('user_id') ?: $viewer->id);

        if ($targetId !== (int) $viewer->id) {
            abort_unless(in_array($role, ['manager','superadmin'], true), 403);
        }

        $target = Users::query()
            ->select('id','timezone','shift_enabled','shift_start','shift_end','shift_days','role')
            ->findOrFail($targetId);

        // Timezone mode: viewer | target | custom
        $tzMode = strtolower((string) $request->input('tz_mode', 'target'));
        if (!in_array($tzMode, ['viewer','target','custom'], true)) {
            $tzMode = 'target';
        }

        $viewerTz = $viewer->timezone ?: 'UTC';
        $targetTz = $target->timezone ?: 'UTC';

        $displayTz = match ($tzMode) {
            'viewer' => $viewerTz,
            'target' => $targetTz,
            'custom' => (string) ($request->input('tz') ?: $viewerTz),
            default  => $targetTz,
        };

        // Validate timezone string (prevents exceptions)
        try {
            new \DateTimeZone($displayTz);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Invalid timezone'], 422);
        }

        // ✅ Calendar navigation inputs
        // anchor = the date you are viewing (used for day/week/month/year ranges)
        $anchor = $request->input('anchor'); // YYYY-MM-DD
        // custom range
        $from = $request->input('from');     // YYYY-MM-DD
        $to   = $request->input('to');       // YYYY-MM-DD

        // ✅ Basic validation for dates (safe + no exceptions)
        if ($range !== 'lifetime' && $anchor) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$anchor)) {
                return response()->json(['ok' => false, 'message' => 'Invalid anchor date'], 422);
            }
        }

        if ($range === 'custom') {
            if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$from)) {
                return response()->json(['ok' => false, 'message' => 'Invalid from date'], 422);
            }
            if ($to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$to)) {
                return response()->json(['ok' => false, 'message' => 'Invalid to date'], 422);
            }
        }

        // ✅ build payload (service must support anchor/custom params)
        $payload = $svc->buildForUser(
            $target->id,
            $range,
            $displayTz,
            $anchor ? (string)$anchor : null,
            $from ? (string)$from : null,
            $to ? (string)$to : null
        );

        // ===============================
        // WORKING HOURS (SHIFT) FOR UI
        // ===============================
        $dayMap = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];

        $shiftEnabled = (bool) ($target->shift_enabled ?? false);
        $shiftStart   = $target->shift_start;
        $shiftEnd     = $target->shift_end;

        $days = $target->shift_days ?: [];
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($days)) $days = [];

        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        if ($shiftEnabled) {
            $ss = $shiftStart ? substr((string)$shiftStart, 0, 5) : '—';
            $se = $shiftEnd   ? substr((string)$shiftEnd,   0, 5) : '—';
            $d  = $days
                ? implode(', ', array_map(fn($x) => $dayMap[$x] ?? (string)$x, $days))
                : '—';

            $shiftLabel = "{$d} • {$ss}–{$se}";
        } else {
            $shiftLabel = 'Shift disabled';
        }

        return response()->json([
            'ok'         => true,
            'range'      => $range,
            'user_id'    => $targetId,
            'tz_mode'    => $tzMode,
            'display_tz' => $displayTz,

            // ✅ navigation echo back (helpful for UI)
            'anchor' => $anchor,
            'custom' => [
                'from' => $from,
                'to'   => $to,
            ],

            // ✅ SHIFT DATA
            'shift' => [
                'enabled' => $shiftEnabled,
                'start'   => $shiftStart ? substr((string)$shiftStart, 0, 5) : null,
                'end'     => $shiftEnd   ? substr((string)$shiftEnd,   0, 5) : null,
                'days'    => $days,
                'label'   => $shiftLabel,
            ],
        ] + $payload);
    }
}
