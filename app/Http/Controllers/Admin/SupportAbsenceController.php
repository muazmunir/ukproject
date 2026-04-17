<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentAbsenceRequest;
use App\Models\AgentAbsenceAudit;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AgentAbsenceRequestFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SupportAbsenceController extends Controller
{
    /* =========================
     | Role helpers
     * ========================= */
    private function role(): string
    {
        return strtolower(trim((string) auth()->user()->role));
    }

    private function isAdminRole(): bool
    {
        return $this->role() === 'admin';
    }

    private function isManagerRole(): bool
    {
        return $this->role() === 'manager';
    }

    private function adminOnly(): void
    {
        abort_unless($this->isAdminRole(), 403);
    }

    private function managerOnly(): void
    {
        abort_unless($this->isManagerRole(), 403);
    }

    /* =========================
     | Active absence check
     * ========================= */
 public static function hasActiveAbsence(Users $u): bool
{
    if (!$u->absence_status || !$u->absence_start_at || !$u->absence_end_at) {
        return false;
    }

    $now   = now()->utc();
    $start = Carbon::parse($u->absence_start_at)->utc();
    $end   = Carbon::parse($u->absence_end_at)->utc();

    return $now->between($start, $end);
}


    /* =========================
     | Audit logger (clean meta)
     * ========================= */
    private function logAudit(
        int $agentId,
        string $action,
        ?int $requestId = null,
        array $meta = [],
        $file = null
    ): void {
        $audit = AgentAbsenceAudit::create([
            'agent_id'   => $agentId,
            'actor_id'   => auth()->id(),
            'request_id' => $requestId,
            'action'     => $action,
            'meta'       => $meta,
            'ip'         => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        // Only admin request proof can attach file (manager decision must not)
        if ($file) {
            $path = $file->store('absence_audit', 'public');
            $audit->update([
                'file_disk' => 'public',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_mime' => $file->getClientMimeType(),
                'file_size' => (int) $file->getSize(),
            ]);
        }
    }

    /* =========================
     | Apply absence (system)
     * ========================= */
    private function applyAbsence(Users $agent, string $type, Carbon $start, Carbon $end): void
{
    $now = now()->utc();

    DB::table('agent_status_logs')
        ->where('user_id', $agent->id)
        ->whereNull('ended_at')
        ->update([
            'ended_at'   => $now,
            'updated_at' => $now,
        ]);

    $agent->forceFill([
        'absence_status'       => $type,
        'absence_start_at'     => $start->copy()->utc(),
        'absence_end_at'       => $end->copy()->utc(),
        'absence_set_by'       => auth()->id(),
        'absence_set_at'       => $now,
        'support_status'       => $type . '_absence',
        'support_status_since' => $now,
    ])->save();
}


    /* =========================
     | ADMIN: My Absence + Request
     * ========================= */
    public function my()
    {
        $this->adminOnly();

        $agent = auth()->user();
        $activeAbsence = self::hasActiveAbsence($agent);

       $requests = AgentAbsenceRequest::with(['files','decider'])->where('agent_id', $agent->id)->latest('id')->paginate(15);



        return view('admin.support_absence.my', compact('agent', 'activeAbsence', 'requests'));
    }

   

public function requestAbsence(Request $r)
{
    $this->adminOnly();

    $r->validate([
        'reason'    => ['required', 'string', 'max:190'],
        'comments'  => ['nullable', 'string', 'max:2000'],
        'start_at'  => ['required', 'date_format:Y-m-d\TH:i'],
        'end_at'    => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],

        // multiple proofs required
        'proofs'    => ['required', 'array', 'min:1'],
        'proofs.*'  => [
            'required',
            'file',
            'max:10240', // 10MB each
            'mimes:jpg,jpeg,png,webp,pdf,doc,docx'
        ],
    ]);

    $tz = auth()->user()->timezone ?: 'UTC';
    $start = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $r->start_at, $tz)->utc();
    $end   = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $r->end_at, $tz)->utc();

    // prevent overlap with existing applied absence window
    $me = auth()->user();
    if ($me->absence_status && $me->absence_start_at && $me->absence_end_at) {
        $overlap = $start->lt(Carbon::parse($me->absence_end_at)) && $end->gt(Carbon::parse($me->absence_start_at));
        if ($overlap) {
            return back()->with('error', 'You already have an absence window that overlaps with these dates.');
        }
    }

    DB::transaction(function () use ($r, $start, $end) {
        $req = AgentAbsenceRequest::create([
            'agent_id' => auth()->id(),
            'state'    => 'pending',
            'start_at' => $start,
            'end_at'   => $end,
            'reason'   => $r->reason,
            'comments' => $r->comments,
        ]);

        // store multiple files
        foreach ($r->file('proofs', []) as $file) {
            $path = $file->store('absence_requests', 'public');

            AgentAbsenceRequestFile::create([
                'request_id'    => $req->id,
                'disk'          => 'public',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => (int) $file->getSize(),
            ]);
        }

        // audit (no file in audit; files live in request_files table)
        $this->logAudit(auth()->id(), 'requested', $req->id, [
            'window_start' => $start->toDateTimeString(),
            'window_end'   => $end->toDateTimeString(),
            'reason'       => (string) $r->reason,
            'files_count'  => count($r->file('proofs', [])),
        ]);
    });

    return back()->with('success', 'Absence request submitted.');
}


    public function cancel(AgentAbsenceRequest $request)
    {
        $this->adminOnly();

        abort_unless((int)$request->agent_id === (int)auth()->id(), 403);
        abort_unless($request->state === 'pending', 422);

        $request->update([
            'state' => 'cancelled',
        ]);

        $this->logAudit(auth()->id(), 'cancelled', $request->id, [
            'window_start' => (string) $request->start_at,
            'window_end'   => (string) $request->end_at,
        ]);

        return back()->with('success', 'Request cancelled.');
    }

    /* =========================
     | MANAGER: Review + Decide
     * ========================= */
  public function review(Request $r)
{
    $this->managerOnly();

    $state = strtolower((string) $r->get('state', 'pending'));     // pending|approved|cancelled|all
    $range = strtolower((string) $r->get('range', 'month'));       // day|week|month|year|lifetime
    $q     = trim((string) $r->get('q', ''));
    $per   = (int) $r->get('per', 20);
    $per   = in_array($per, [10,20,30,50], true) ? $per : 20;

    // --- Range window (based on created_at for "requested in X range")
    $now = now()->utc();
    $from = null;

    switch ($range) {
        case 'day':
            $from = $now->copy()->startOfDay();
            break;
        case 'week':
            $from = $now->copy()->startOfWeek(); // Monday start by default (config)
            break;
        case 'month':
            $from = $now->copy()->startOfMonth();
            break;
        case 'year':
            $from = $now->copy()->startOfYear();
            break;
        case 'lifetime':
        default:
            $from = null;
            break;
    }

    // --- Base query (requests)
    $base = AgentAbsenceRequest::query()
        ->with(['agent','files','decider'])
        ->when($from, fn($qq) => $qq->where('created_at', '>=', $from));

    // --- State filter
    if (in_array($state, ['pending','approved','cancelled'], true)) {
        $base->where('state', $state);
    } else {
        $state = 'all';
    }

    // --- Search
    if ($q !== '') {
        $base->where(function ($qq) use ($q) {
            // search by numeric id: "#12" or "12"
            $id = ltrim($q, '#');
            if (ctype_digit($id)) {
                $qq->orWhere('id', (int) $id);
            }

            $qq->orWhere('reason', 'like', "%{$q}%")
               ->orWhere('comments', 'like', "%{$q}%")
               ->orWhereHas('agent', function ($a) use ($q) {
                    $a->where('username', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
               });
        });
    }

    $requests = $base->latest('id')->paginate($per)->appends($r->query());

    // --- Counts for segmented badges (same range + search optional?)
    // Professional approach: counts should ignore search (better as global),
    // but still respect RANGE so manager sees "in this range, how many".
    $countBase = AgentAbsenceRequest::query()
        ->when($from, fn($qq) => $qq->where('created_at', '>=', $from));

    $counts = [
        'pending'   => (clone $countBase)->where('state','pending')->count(),
        'approved'  => (clone $countBase)->where('state','approved')->count(),
        'cancelled' => (clone $countBase)->where('state','cancelled')->count(),
        'all'       => (clone $countBase)->count(),
    ];

    // --- Admins list (visibility)
    $agents = Users::query()
        ->where('role', 'admin')
        ->orderBy('username')
        ->get(['id','username','absence_status','absence_start_at','absence_end_at']);

    // --- Currently on leave NOW
    $nowUtc = now()->utc();
    $onLeave = Users::query()
        ->where('role', 'admin')
        ->whereNotNull('absence_status')
        ->whereNotNull('absence_start_at')
        ->whereNotNull('absence_end_at')
        ->where('absence_start_at', '<=', $nowUtc)
        ->where('absence_end_at', '>=', $nowUtc)
        ->orderBy('absence_end_at')
        ->get(['id','username','absence_status','absence_start_at','absence_end_at']);

    return view('admin.support_absence.review', compact(
        'requests','agents','onLeave',
        'state','range','q','per','counts'
    ));
}


    /**
     * Decide: authorized/unauthorized
     * Manager cannot upload any file here
     */
   public function decide(Request $r, AgentAbsenceRequest $request)
{
    $this->managerOnly();
    abort_unless($request->state === 'pending', 422);

    $r->validate([
        'decision' => ['required', 'in:approve,reject'],
        'note'     => ['nullable', 'string', 'max:255'],
    ]);

    $type = $r->decision === 'approve' ? 'authorized' : 'unauthorized';

    DB::transaction(function () use ($r, $request, $type) {
        $request->update([
            'state'         => 'approved',     // stays approved (processed)
            'type'          => $type,          // authorized / unauthorized
            'decided_by'    => auth()->id(),
            'decided_at'    => now(),
            'decision_note' => $r->note,
        ]);

        $agent = Users::findOrFail($request->agent_id);

        $this->applyAbsence(
            $agent,
            $type,
            Carbon::parse($request->start_at),
            Carbon::parse($request->end_at)
        );

        $this->logAudit($agent->id, 'applied', $request->id, [
            'type'         => $type,
            'window_start' => (string) $request->start_at,
            'window_end'   => (string) $request->end_at,
            'note'         => (string) $r->note,
        ]);
    });

    return back()->with('success', ucfirst($type).' absence applied.');
}


    /* =========================
     | ADMIN: My Absence Logs
     * ========================= */
    public function myLog()
    {
        $this->adminOnly();

        $me = auth()->user();

        $audits = AgentAbsenceAudit::query()
            ->with(['actor'])
            ->where('agent_id', $me->id)
            ->latest('id')
            ->paginate(30);

        return view('admin.support_absence.my_log', compact('audits', 'me'));
    }

    public function downloadMyFile(AgentAbsenceAudit $audit)
    {
        $this->adminOnly();

        abort_unless((int)$audit->agent_id === (int)auth()->id(), 403);
        abort_unless($audit->file_disk && $audit->file_path, 404);

        return Storage::disk($audit->file_disk)->download($audit->file_path, $audit->file_name ?? 'attachment');
    }


    public function downloadMyRequestFile(AgentAbsenceRequestFile $file)
{
    $this->adminOnly();

    $req = AgentAbsenceRequest::findOrFail($file->request_id);
    abort_unless((int)$req->agent_id === (int)auth()->id(), 403);

    return Storage::disk($file->disk)->download($file->path, $file->original_name ?? 'attachment');
}

public function downloadReviewRequestFile(AgentAbsenceRequestFile $file)
{
    $this->managerOnly();

    // manager can download any file for review
    return Storage::disk($file->disk)->download($file->path, $file->original_name ?? 'attachment');
}
}
