<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAgentTimelineController extends Controller
{
    private const STATUSES = ['available','break','lunch','unavailable','tech_issues','logout'];

    public function index(Request $request)
    {
        $staff = User::query()
            ->select('id','first_name','last_name','email','role')
            ->whereIn('role', ['admin','manager'])
            ->orderBy('role')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('superadmin.support.agent-timeline', [
            'staff' => $staff,
            'statuses' => self::STATUSES,
        ]);
    }

    public function data(Request $request)
    {
        $request->validate([
            'user_id' => ['required','integer'],
            'date'    => ['required','date'],
            'start_hour' => ['nullable','integer','min:0','max:23'],
            'end_hour'   => ['nullable','integer','min:0','max:24'],
        ]);

        $userId = $request->integer('user_id');

        $date = Carbon::parse($request->date);
        $startHour = $request->integer('start_hour', 9);
        $endHour   = $request->integer('end_hour', 18);

        // day window (like schedule view)
        $windowStart = $date->copy()->startOfDay()->addHours($startHour);
        $windowEnd   = $date->copy()->startOfDay()->addHours($endHour);

        // grab overlapping logs
        $rows = DB::table('agent_status_logs')
            ->where('user_id', $userId)
            ->where(function ($w) use ($windowStart, $windowEnd) {
                $w->where('started_at', '<', $windowEnd)
                  ->where(function ($w2) use ($windowStart) {
                      $w2->whereNull('ended_at')->orWhere('ended_at', '>', $windowStart);
                  });
            })
            ->orderBy('started_at')
            ->get(['id','status','reason','started_at','ended_at']);

        // clamp + convert into segments
        $segments = [];
        foreach ($rows as $r) {
            $segStart = Carbon::parse($r->started_at);
            $segEnd = $r->ended_at ? Carbon::parse($r->ended_at) : Carbon::now();

            // clamp
            if ($segStart < $windowStart) $segStart = $windowStart->copy();
            if ($segEnd > $windowEnd) $segEnd = $windowEnd->copy();

            // ignore non-overlapping
            if ($segEnd <= $segStart) continue;

            $segments[] = [
                'status' => (string)$r->status,
                'reason' => $r->reason,
                'start'  => $segStart->toIso8601String(),
                'end'    => $segEnd->toIso8601String(),
                'startLabel' => $segStart->format('H:i'),
                'endLabel'   => $segEnd->format('H:i'),
                'minutes'    => $segEnd->diffInMinutes($segStart),
            ];
        }

        return response()->json([
            'window' => [
                'start' => $windowStart->toIso8601String(),
                'end'   => $windowEnd->toIso8601String(),
                'startHour' => $startHour,
                'endHour' => $endHour,
            ],
            'segments' => $segments,
        ]);
    }
}
