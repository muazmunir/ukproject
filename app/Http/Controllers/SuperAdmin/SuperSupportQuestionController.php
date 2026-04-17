<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperSupportQuestionController extends Controller
{
   public function index(Request $request)
{
    $q   = $request->q;
    $per = (int)($request->per ?? 15);

    $mode = $request->filter_mode ?? 'lifetime';

    $day       = $request->day;
    $weekDay   = $request->week_day;
    $month     = $request->month;
    $year      = $request->year;
    $dateFrom  = $request->date_from;
    $dateTo    = $request->date_to;

    $rangeFrom = null;
    $rangeTo   = null;

    try {
        if ($mode === 'daily' && $day) {
            $rangeFrom = Carbon::parse($day)->startOfDay();
            $rangeTo   = Carbon::parse($day)->endOfDay();
        }

        if ($mode === 'weekly' && $weekDay) {
            $d = Carbon::parse($weekDay);
            $rangeFrom = $d->startOfWeek()->startOfDay();
            $rangeTo   = $d->endOfWeek()->endOfDay();
        }

        if ($mode === 'monthly' && $month) {
            $d = Carbon::createFromFormat('Y-m', $month);
            $rangeFrom = $d->copy()->startOfMonth()->startOfDay();
            $rangeTo   = $d->copy()->endOfMonth()->endOfDay();
        }

        if ($mode === 'yearly' && $year) {
            $d = Carbon::createFromFormat('Y', $year);
            $rangeFrom = $d->copy()->startOfYear()->startOfDay();
            $rangeTo   = $d->copy()->endOfYear()->endOfDay();
        }

        if ($mode === 'custom' && ($dateFrom || $dateTo)) {
            if ($dateFrom) $rangeFrom = Carbon::parse($dateFrom)->startOfDay();
            if ($dateTo)   $rangeTo   = Carbon::parse($dateTo)->endOfDay();
        }

        if ($mode === 'lifetime') {
            $rangeFrom = null;
            $rangeTo   = null;
        }
    } catch (\Throwable $e) {
        $mode = 'lifetime';
        $rangeFrom = $rangeTo = null;
    }

    // Base range is driven by created_at (raised time)
    $base = SupportQuestion::query()
        ->when($rangeFrom, fn($qb) => $qb->where('created_at', '>=', $rangeFrom))
        ->when($rangeTo, fn($qb) => $qb->where('created_at', '<=', $rangeTo));

    // ✅ KPIs
    $total       = (clone $base)->count();
    $takenCount  = (clone $base)->whereNotNull('taken_at')->count();
    $answeredCnt = (clone $base)->whereNotNull('answered_at')->count();
    $openCount   = (clone $base)->where('status', 'open')->count();
    $takenOnly   = (clone $base)->where('status', 'taken')->count();
    $closedCount = (clone $base)->where('status', 'closed')->count();

    // MySQL avg seconds (NULL-safe)
    $avgTakeSec = (clone $base)
        ->whereNotNull('taken_at')
        ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, taken_at)) as v')
        ->value('v');

    $avgAnswerSec = (clone $base)
        ->whereNotNull('taken_at')
        ->whereNotNull('answered_at')
        ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, taken_at, answered_at)) as v')
        ->value('v');

    $avgCloseSec = (clone $base)
        ->whereNotNull('closed_at')
        ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as v')
        ->value('v');

    $analytics = [
        'total'        => $total,
        'open'         => $openCount,
        'taken'        => $takenOnly,
        'answered'     => $closedCount, // UI label
        'taken_count'  => $takenCount,
        'answered_cnt' => $answeredCnt,
        'avg_take_sec'   => (int) round($avgTakeSec ?? 0),
        'avg_answer_sec' => (int) round($avgAnswerSec ?? 0),
        'avg_close_sec'  => (int) round($avgCloseSec ?? 0),
    ];

    // ✅ Top Admins (Raised)
    $topAdmins = (clone $base)
        ->select('asked_by_admin_id', DB::raw('COUNT(*) as cnt'))
        ->whereNotNull('asked_by_admin_id')
        ->groupBy('asked_by_admin_id')
        ->orderByDesc('cnt')
        ->with('askedBy:id,first_name,last_name,email,username')
        ->limit(8)
        ->get();

    // ✅ Top Managers (Answered/Closed)
    $topManagers = (clone $base)
        ->select('assigned_manager_id', DB::raw('COUNT(*) as cnt'))
        ->whereNotNull('assigned_manager_id')
        ->whereNotNull('answered_at')
        ->groupBy('assigned_manager_id')
        ->orderByDesc('cnt')
        ->with('assignedManager:id,first_name,last_name,email,username')
        ->limit(8)
        ->get();

    // ✅ Items list
    $items = (clone $base)
        ->with(['askedBy','assignedManager'])
        ->when($q, function($qb) use ($q){
            $qb->where(function($w) use ($q){
                $w->where('question','like',"%{$q}%")
                  ->orWhere('id', $q);
            });
        })
        ->orderByDesc('updated_at')
        ->paginate($per)
        ->appends($request->query());

    return view('superadmin.support.questions.index', [
        'items'       => $items,
        'analytics'   => $analytics,
        'topAdmins'   => $topAdmins,
        'topManagers' => $topManagers,

        'q'          => $q,
        'per'        => $per,
        'filterMode' => $mode,
        'day'        => $day,
        'weekDay'    => $weekDay,
        'month'      => $month,
        'year'       => $year,
        'dateFrom'   => $dateFrom,
        'dateTo'     => $dateTo,
    ]);
}



    public function show(SupportQuestion $question)
    {
        // View only: show full question + timeline
        $question->load(['askedBy','assignedManager','messages.sender']);

        return view('superadmin.support.questions.show', [
            'question' => $question,
        ]);
    }
}
