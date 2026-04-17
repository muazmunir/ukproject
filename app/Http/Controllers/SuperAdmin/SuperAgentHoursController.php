<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Users;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAgentHoursController extends Controller
{
    private const STATUSES = ['available','break','lunch','unavailable','tech_issues','logout'];

    public function index()
    {
        $staff = Users::query()
            ->select('id','first_name','last_name','email','role')
            ->whereIn('role', ['admin','manager'])
            ->orderBy('role')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('superadmin.support.agent-hours', [
            'staff' => $staff,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Aggregate hours for chart (stacked by status) for:
     * daily/weekly/monthly/yearly/lifetime
     */
    public function aggregate(Request $request)
    {
        $request->validate([
            'user_id' => ['nullable','integer'],
            'view'    => ['required','in:daily,weekly,monthly,yearly,lifetime'],
            'start'   => ['nullable','date'],
            'end'     => ['nullable','date'],
        ]);

        $userId = $request->integer('user_id');
        $view   = $request->string('view')->toString();

        // Range rules:
        // - lifetime: from earliest log to now
        // - others: need start/end (frontend will send)
        if ($view === 'lifetime') {
            $min = DB::table('agent_status_logs')->min('started_at');
            $rangeStart = $min ? Carbon::parse($min)->startOfDay() : now()->startOfDay();
            $rangeEnd = now();
        } else {
            $rangeStart = Carbon::parse($request->input('start', now()->toDateString()))->startOfDay();
            $rangeEnd   = Carbon::parse($request->input('end', now()->toDateString()))->endOfDay();
        }

        // bucket expression
        $bucketExpr = match ($view) {
            'daily'   => "DATE(GREATEST(l.started_at, ?))",
            'weekly'  => "YEARWEEK(DATE(GREATEST(l.started_at, ?)), 3)", // ISO week
            'monthly' => "DATE_FORMAT(DATE(GREATEST(l.started_at, ?)), '%Y-%m-01')",
            'yearly'  => "DATE_FORMAT(DATE(GREATEST(l.started_at, ?)), '%Y-01-01')",
            'lifetime'=> "DATE_FORMAT(DATE(GREATEST(l.started_at, ?)), '%Y-%m-01')", // lifetime chart monthly by default
        };

        $bindings = [
            $rangeStart->toDateTimeString(), // bucket param
            $rangeStart->toDateTimeString(), // clamp start
            $rangeEnd->toDateTimeString(),   // clamp end
        ];

        $q = DB::table('agent_status_logs as l')
            ->join('users as u','u.id','=','l.user_id')
            ->whereIn('u.role', ['admin','manager'])
            ->where(function ($w) use ($rangeStart, $rangeEnd) {
                $w->where('l.started_at', '<=', $rangeEnd)
                  ->where(function ($w2) use ($rangeStart) {
                      $w2->whereNull('l.ended_at')->orWhere('l.ended_at', '>=', $rangeStart);
                  });
            })
            ->selectRaw("
                {$bucketExpr} as bucket,
                l.status as status,
                SUM(
                    GREATEST(
                        0,
                        TIMESTAMPDIFF(
                            SECOND,
                            GREATEST(l.started_at, ?),
                            LEAST(COALESCE(l.ended_at, NOW()), ?)
                        )
                    )
                ) as seconds
            ", $bindings)
            ->groupBy('bucket','status')
            ->orderBy('bucket');

        if ($userId) $q->where('l.user_id', $userId);

        $rows = $q->get();

        // buckets
        $bucketSet = [];
        foreach ($rows as $r) $bucketSet[(string)$r->bucket] = true;
        $buckets = array_keys($bucketSet);

        if (!$buckets) {
            return response()->json([
                'range' => ['start'=>$rangeStart->toDateString(),'end'=>$rangeEnd->toDateString()],
                'labels' => [],
                'datasets' => [],
                'totals_by_status' => [],
            ]);
        }

        // labels (weekly formatting)
        $labels = array_map(function ($b) use ($view) {
            if ($view === 'weekly') {
                $year = substr($b, 0, 4);
                $week = substr($b, 4);
                return $year . '-W' . str_pad($week, 2, '0', STR_PAD_LEFT);
            }
            return (string)$b;
        }, $buckets);

        // init arrays
        $idx = array_flip($buckets);
        $dataByStatus = [];
        foreach (self::STATUSES as $st) {
            $dataByStatus[$st] = array_fill(0, count($buckets), 0.0);
        }
        $totalsByStatus = array_fill_keys(self::STATUSES, 0.0);

        foreach ($rows as $r) {
            $b = (string)$r->bucket;
            $st = (string)$r->status;
            if (!isset($dataByStatus[$st])) continue;

            $hours = round(((int)$r->seconds)/3600, 2);
            $dataByStatus[$st][$idx[$b]] = $hours;
            $totalsByStatus[$st] = round($totalsByStatus[$st] + $hours, 2);
        }

        // Stacked bar datasets (include logout as a dataset too — professional visibility)
        $datasets = [];
        foreach (self::STATUSES as $st) {
            $datasets[] = [
                'type' => 'bar',
                'label' => strtoupper(str_replace('_',' ', $st)),
                'data' => $dataByStatus[$st],
                'stack' => 'time',
            ];
        }

        // Total line (working time excluding logout)
        $totalWork = [];
        for ($i=0; $i<count($buckets); $i++) {
            $sum = 0.0;
            foreach (self::STATUSES as $st) {
                if ($st === 'logout') continue;
                $sum += (float)$dataByStatus[$st][$i];
            }
            $totalWork[] = round($sum, 2);
        }

        $datasets[] = [
            'type' => 'line',
            'label' => 'TOTAL WORK (hrs)',
            'data' => $totalWork,
            'tension' => 0.25,
            'pointRadius' => 2,
            'borderWidth' => 2,
            'yAxisID' => 'y',
        ];

        return response()->json([
            'range' => ['start'=>$rangeStart->toDateString(),'end'=>$rangeEnd->toDateString()],
            'labels' => $labels,
            'datasets' => $datasets,
            'totals_by_status' => $totalsByStatus,
        ]);
    }

    /**
     * Timeline segments for a date or custom period:
     * - If user_id present => single timeline + table
     * - If user_id empty   => roster timeline (rows per staff)
     */
    public function timeline(Request $request)
    {
        $request->validate([
            'user_id' => ['nullable','integer'],
            'start'   => ['required','date'],
            'end'     => ['required','date','after_or_equal:start'],
        ]);

        $userId = $request->integer('user_id');

        $rangeStart = Carbon::parse($request->start)->startOfDay();
        $rangeEnd   = Carbon::parse($request->end)->endOfDay();

        $q = DB::table('agent_status_logs as l')
            ->join('users as u','u.id','=','l.user_id')
            ->whereIn('u.role',['admin','manager'])
            ->where(function ($w) use ($rangeStart, $rangeEnd) {
                $w->where('l.started_at', '<=', $rangeEnd)
                  ->where(function ($w2) use ($rangeStart) {
                      $w2->whereNull('l.ended_at')->orWhere('l.ended_at', '>=', $rangeStart);
                  });
            })
            ->orderBy('u.role')
            ->orderBy('u.first_name')
            ->orderBy('l.started_at');

        if ($userId) $q->where('l.user_id', $userId);

        $rows = $q->get([
            'l.user_id','l.status','l.reason','l.started_at','l.ended_at',
            'u.first_name','u.last_name','u.email','u.role'
        ]);

        // staff map
        $staff = [];
        foreach ($rows as $r) {
            $staff[$r->user_id] = [
                'user_id' => (int)$r->user_id,
                'role' => (string)$r->role,
                'name' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')) ?: (string)$r->email,
                'email' => (string)$r->email,
            ];
        }

        // segments grouped by user
        $segmentsByUser = [];
        $table = [];

        foreach ($rows as $r) {
            $segStart = Carbon::parse($r->started_at);
            $segEnd = $r->ended_at ? Carbon::parse($r->ended_at) : now();

            if ($segStart < $rangeStart) $segStart = $rangeStart->copy();
            if ($segEnd > $rangeEnd) $segEnd = $rangeEnd->copy();
            if ($segEnd <= $segStart) continue;

            $seg = [
                'user_id' => (int)$r->user_id,
                'status' => (string)$r->status,
                'reason' => $r->reason,
                'start' => $segStart->toIso8601String(),
                'end' => $segEnd->toIso8601String(),
                'startLabel' => $segStart->format('Y-m-d H:i'),
                'endLabel'   => $segEnd->format('Y-m-d H:i'),
                'minutes' => $segEnd->diffInMinutes($segStart),
            ];

            $segmentsByUser[$r->user_id][] = $seg;

            if ($userId) {
                $table[] = [
                    'status' => $seg['status'],
                    'from' => $seg['startLabel'],
                    'to' => $seg['endLabel'],
                    'minutes' => $seg['minutes'],
                    'reason' => $seg['reason'],
                ];
            }
        }

        return response()->json([
            'mode' => $userId ? 'single' : 'roster',
            'range' => ['start'=>$rangeStart->toDateString(),'end'=>$rangeEnd->toDateString()],
            'staff' => array_values($staff),
            'segments' => $segmentsByUser,
            'table' => $table, // only filled for single
        ]);
    }
}
