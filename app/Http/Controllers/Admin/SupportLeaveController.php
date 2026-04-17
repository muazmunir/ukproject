<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentAbsenceRequest;
use App\Models\AgentAbsenceAudit;
use App\Models\AgentAbsenceRequestFile;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SupportLeaveController extends Controller
{
    private const KINDS = ['absence','holiday'];

    /* =========================
     | Roles
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

    private function adminOrManagerOnly(): void
    {
        abort_unless(in_array($this->role(), ['admin','manager'], true), 403);
    }

    private function managerOnly(): void
    {
        abort_unless($this->isManagerRole(), 403);
    }

    /* =========================
     | Leave State Helpers
     * ========================= */

    /**
     * Active only inside window: start <= now < end  (end EXCLUSIVE)
     */
    public static function hasActiveLeave(Users $u): bool
    {
        if (!$u->absence_kind || !$u->absence_status || !$u->absence_start_at || !$u->absence_end_at) {
            return false;
        }

        $now   = now()->utc();
        $start = Carbon::parse($u->absence_start_at)->utc();
        $end   = Carbon::parse($u->absence_end_at)->utc();

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    public static function hasPendingRequest(int $agentId): bool
    {
        return AgentAbsenceRequest::where('agent_id', $agentId)
            ->where('state', 'pending')
            ->exists();
    }

    private function logAudit(int $agentId, string $action, ?int $requestId = null, array $meta = [], $file = null): void
    {
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

    /**
     * Schedule leave on the user (does NOT change support_status immediately).
     * IMPORTANT: Rejections MUST NOT schedule anything on user table.
     *
     * Also ensure we reset any return_required flags when a NEW window is scheduled.
     */
    private function scheduleLeave(
        Users $agent,
        string $kind,
        string $type,
        Carbon $startUtc,
        Carbon $endUtc
    ): void {
        $now = now()->utc();

        $agent->forceFill([
            'absence_kind'            => $kind,   // absence|holiday
            'absence_status'          => $type,   // authorized|unauthorized (but we will only schedule authorized/holiday in this controller)
            'absence_start_at'        => $startUtc->copy()->utc(),
            'absence_end_at'          => $endUtc->copy()->utc(),
            'absence_set_by'          => auth()->id(),
            'absence_set_at'          => $now,

            // reset return required for a fresh schedule
            'absence_return_required' => false,
            'absence_return_since'    => null,
        ])->save();
    }

    private function clearLeaveWindow(Users $agent): void
    {
        $agent->forceFill([
            'absence_kind'            => null,
            'absence_status'          => null,
            'absence_start_at'        => null,
            'absence_end_at'          => null,
            'absence_return_required' => false,
            'absence_return_since'    => null,
        ])->save();
    }

    /* =========================
     | My (Admin + Manager)
     * ========================= */
    public function my()
    {
        $this->adminOrManagerOnly();

        $agent = auth()->user();

        $activeLeave = self::hasActiveLeave($agent);
        $hasPending  = self::hasPendingRequest((int) $agent->id);

        $requests = AgentAbsenceRequest::with(['files','decider'])
            ->where('agent_id', $agent->id)
            ->latest('id')
            ->paginate(15);

        return view('admin.support_leave.my', compact('agent','activeLeave','hasPending','requests'));
    }

    public function show($request)
    {
        $this->managerOnly();

        $req = AgentAbsenceRequest::with(['agent','files','decider'])
            ->whereHas('agent', fn($a) => $a->where('role', 'admin')) // manager reviews admins only
            ->findOrFail((int) $request);

        $agent = $req->agent;

        $audits = AgentAbsenceAudit::query()
            ->with('actor')
            ->where('request_id', $req->id)
            ->latest('id')
            ->take(20)
            ->get();

        return view('admin.support_leave.show', compact('req','agent','audits'));
    }

    public function requestLeave(Request $r)
    {
        $this->adminOrManagerOnly();

        // RULE: Block creating a new request while any pending exists
        if (self::hasPendingRequest((int) auth()->id())) {
            return back()->with('error', 'You already have a pending request. Please wait for approval or rejection.');
        }

        $r->validate([
            'kind'     => ['required','in:absence,holiday'],
            'reason'   => ['required', 'string', 'max:190'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at'   => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],

            'proofs'   => ['required', 'array', 'min:1'],
            'proofs.*' => ['required','file','max:10240','mimes:jpg,jpeg,png,webp,pdf,doc,docx'],
        ]);

        $kind = strtolower($r->kind);
        abort_unless(in_array($kind, self::KINDS, true), 422);

        $tz = auth()->user()->timezone ?: 'UTC';
        $start = Carbon::createFromFormat('Y-m-d\TH:i', $r->start_at, $tz)->utc();
        $end   = Carbon::createFromFormat('Y-m-d\TH:i', $r->end_at, $tz)->utc();

        $me = auth()->user();

        // Overlap protection vs current user's existing scheduled window
        if ($me->absence_start_at && $me->absence_end_at) {
            $s = Carbon::parse($me->absence_start_at)->utc();
            $e = Carbon::parse($me->absence_end_at)->utc();
            $overlap = $start->lt($e) && $end->gt($s);
            if ($overlap) {
                return back()->with('error', 'You already have a leave window that overlaps with these dates.');
            }
        }

        DB::transaction(function () use ($r, $start, $end, $kind) {

            $req = AgentAbsenceRequest::create([
                'agent_id' => auth()->id(),
                'kind'     => $kind,
                'state'    => 'pending',
                'start_at' => $start,
                'end_at'   => $end,
                'reason'   => $r->reason,
                'comments' => $r->comments,
            ]);

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

            $this->logAudit(auth()->id(), 'requested', $req->id, [
                'kind'         => $kind,
                'window_start' => $start->toDateTimeString(),
                'window_end'   => $end->toDateTimeString(),
                'reason'       => (string) $r->reason,
                'files_count'  => count($r->file('proofs', [])),
            ]);
        });

        return back()->with('success', ucfirst($kind).' request submitted.');
    }

    public function cancel(AgentAbsenceRequest $request)
    {
        $this->adminOrManagerOnly();

        abort_unless((int)$request->agent_id === (int)auth()->id(), 403);
        abort_unless($request->state === 'pending', 422);

        $request->update(['state' => 'cancelled']);

        $this->logAudit(auth()->id(), 'cancelled', $request->id, [
            'kind'         => (string) $request->kind,
            'window_start' => (string) $request->start_at,
            'window_end'   => (string) $request->end_at,
        ]);

        return back()->with('success', 'Request cancelled.');
    }

    /* =========================
     | Manager Review (Admins only)
     * ========================= */
    public function review(Request $r)
    {
        $this->managerOnly();

        $state = strtolower((string) $r->get('state', 'pending'));
        $kind  = strtolower((string) $r->get('kind', 'all')); // absence|holiday|all
        $q     = trim((string) $r->get('q', ''));
        $per   = (int) $r->get('per', 20);
        $per   = in_array($per, [10,20,30,50], true) ? $per : 20;

        $base = AgentAbsenceRequest::query()
            ->with(['agent','files','decider'])
            ->whereHas('agent', fn($a) => $a->where('role', 'admin'));

        if (in_array($state, ['pending','approved','cancelled'], true)) {
            $base->where('state', $state);
        } else {
            $state = 'all';
        }

        if (in_array($kind, ['absence','holiday'], true)) {
            $base->where('kind', $kind);
        } else {
            $kind = 'all';
        }

        if ($q !== '') {
            $base->where(function ($qq) use ($q) {
                $id = ltrim($q, '#');
                if (ctype_digit($id)) $qq->orWhere('id', (int)$id);

                $qq->orWhere('reason', 'like', "%{$q}%")
                    ->orWhere('comments', 'like', "%{$q}%")
                    ->orWhereHas('agent', function ($a) use ($q) {
                        $a->where('username','like',"%{$q}%")->orWhere('name','like',"%{$q}%");
                    });
            });
        }

        $requests = $base->latest('id')->paginate($per)->appends($r->query());

        $countsBase = AgentAbsenceRequest::query()
            ->whereHas('agent', fn($a) => $a->where('role', 'admin'));

        $counts = [
            'pending'   => (clone $countsBase)->where('state','pending')->count(),
            'approved'  => (clone $countsBase)->where('state','approved')->count(),
            'cancelled' => (clone $countsBase)->where('state','cancelled')->count(),
            'all'       => (clone $countsBase)->count(),
        ];

        return view('admin.support_leave.review', compact('requests','state','kind','q','per','counts'));
    }

    public function decide(Request $r, AgentAbsenceRequest $request)
    {
        $this->managerOnly();
        abort_unless($request->state === 'pending', 422);

        $r->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);

        $kind = strtolower((string) ($request->kind ?? 'absence'));
        $isApprove = $r->decision === 'approve';

        DB::transaction(function () use ($r, $request, $kind, $isApprove) {

            $agent = Users::findOrFail($request->agent_id);
            $start = Carbon::parse($request->start_at)->utc();
            $end   = Carbon::parse($request->end_at)->utc();

            // overlap guard vs existing leave window on user
            if ($agent->absence_start_at && $agent->absence_end_at) {
                $s = Carbon::parse($agent->absence_start_at)->utc();
                $e = Carbon::parse($agent->absence_end_at)->utc();
                $overlap = $start->lt($e) && $end->gt($s);

                if ($overlap) {
                    $request->update([
                        'state'         => 'cancelled',
                        'type'          => 'conflict',
                        'decided_by'    => auth()->id(),
                        'decided_at'    => now(),
                        'decision_note' => trim(($r->note ? $r->note.' • ' : '').'Conflict: agent already has an existing leave window.'),
                    ]);

                    $this->logAudit($agent->id, 'conflict', $request->id, [
                        'kind'         => $kind,
                        'window_start' => (string) $request->start_at,
                        'window_end'   => (string) $request->end_at,
                        'note'         => (string) $r->note,
                    ]);

                    return;
                }
            }

            // ========= Holiday =========
            if ($kind === 'holiday') {

                $request->update([
                    'state'         => $isApprove ? 'approved' : 'cancelled',
                    'type'          => $isApprove ? 'authorized' : 'rejected', // keep your type usage
                    'decided_by'    => auth()->id(),
                    'decided_at'    => now(),
                    'decision_note' => $r->note,
                ]);

                $this->logAudit($agent->id, $isApprove ? 'approved' : 'rejected', $request->id, [
                    'kind'         => 'holiday',
                    'type'         => $isApprove ? 'authorized' : null,
                    'window_start' => (string) $request->start_at,
                    'window_end'   => (string) $request->end_at,
                    'note'         => (string) $r->note,
                ]);

                // ✅ IMPORTANT: only schedule on APPROVE. Reject should NOT schedule anything.
                if ($isApprove) {
                    $this->scheduleLeave($agent, 'holiday', 'authorized', $start, $end);

                    $this->logAudit($agent->id, 'scheduled', $request->id, [
                        'kind'         => 'holiday',
                        'type'         => 'authorized',
                        'window_start' => (string) $request->start_at,
                        'window_end'   => (string) $request->end_at,
                    ]);
                }

                return;
            }

            // ========= Absence =========
            // ✅ Requirement: if absence is rejected, DO NOT change status to unauthorized_absence.
            // So we DO NOT schedule anything on reject.
            $type = $isApprove ? 'authorized' : 'rejected';

            $request->update([
                'state'         => $isApprove ? 'approved' : 'cancelled',
                'type'          => $type,
                'decided_by'    => auth()->id(),
                'decided_at'    => now(),
                'decision_note' => $r->note,
            ]);

            $this->logAudit($agent->id, $isApprove ? 'approved' : 'rejected', $request->id, [
                'kind'         => 'absence',
                'type'         => $isApprove ? 'authorized' : 'rejected',
                'window_start' => (string) $request->start_at,
                'window_end'   => (string) $request->end_at,
                'note'         => (string) $r->note,
            ]);

            if ($isApprove) {
                $this->scheduleLeave($agent, 'absence', 'authorized', $start, $end);

                $this->logAudit($agent->id, 'scheduled', $request->id, [
                    'kind'         => 'absence',
                    'type'         => 'authorized',
                    'window_start' => (string) $request->start_at,
                    'window_end'   => (string) $request->end_at,
                ]);
            } else {
                // Defensive: if anything was previously scheduled for some reason, clear it
                // (optional, but prevents legacy corruption)
                // Only clear if it matches this request window exactly
                if ($agent->absence_kind && $agent->absence_start_at && $agent->absence_end_at) {
                    $as = Carbon::parse($agent->absence_start_at)->utc();
                    $ae = Carbon::parse($agent->absence_end_at)->utc();
                    if ($as->eq($start) && $ae->eq($end) && strtolower((string)$agent->absence_kind) === 'absence') {
                        $this->clearLeaveWindow($agent);
                    }
                }
            }
        });

        return back()->with('success', ($kind === 'holiday') ? 'Holiday request processed.' : 'Absence request processed.');
    }

    /* =========================
     | My log + downloads
     * ========================= */
    public function myLog()
    {
        $this->adminOrManagerOnly();

        $me = auth()->user();

        $audits = AgentAbsenceAudit::query()
            ->with(['actor'])
            ->where('agent_id', $me->id)
            ->latest('id')
            ->paginate(30);

        return view('admin.support_leave.my_log', compact('audits','me'));
    }

    public function downloadMyFile(AgentAbsenceAudit $audit)
    {
        $this->adminOrManagerOnly();

        abort_unless((int)$audit->agent_id === (int)auth()->id(), 403);
        abort_unless($audit->file_disk && $audit->file_path, 404);

        return Storage::disk($audit->file_disk)->download($audit->file_path, $audit->file_name ?? 'attachment');
    }

    public function downloadMyRequestFile(AgentAbsenceRequestFile $file)
    {
        $this->adminOrManagerOnly();

        $req = AgentAbsenceRequest::findOrFail($file->request_id);
        abort_unless((int)$req->agent_id === (int)auth()->id(), 403);

        return Storage::disk($file->disk)->download($file->path, $file->original_name ?? 'attachment');
    }

    public function downloadReviewRequestFile(AgentAbsenceRequestFile $file)
    {
        $this->managerOnly();
        return Storage::disk($file->disk)->download($file->path, $file->original_name ?? 'attachment');
    }
}