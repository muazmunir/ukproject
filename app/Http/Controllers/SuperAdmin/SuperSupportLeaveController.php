<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AgentAbsenceRequest;
use App\Models\AgentAbsenceAudit;
use App\Models\AgentAbsenceRequestFile;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperSupportLeaveController extends Controller
{
    private const KINDS = ['absence','holiday'];

    private function role(): string
    {
        return strtolower(trim((string) auth()->user()->role));
    }

    private function superOnly(): void
    {
        abort_unless($this->role() === 'superadmin', 403);
    }

    /**
     * Schedule leave on user table (does NOT change support_status immediately).
     * IMPORTANT: rejected absence must NOT schedule anything.
     * Also reset return_required when a new schedule is applied.
     */
    private function scheduleLeave(Users $agent, string $kind, string $type, Carbon $startUtc, Carbon $endUtc): void
    {
        $now = now()->utc();

        $agent->forceFill([
            'absence_kind'            => $kind,   // absence|holiday
            'absence_status'          => $type,   // authorized
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

    /** List page */
    public function review(Request $r)
    {
        $this->superOnly();

        $state = strtolower((string) $r->get('state', 'pending'));
        $kind  = strtolower((string) $r->get('kind', 'all')); // absence|holiday|all
        $role  = strtolower((string) $r->get('role', 'all')); // admin|manager|all
        $q     = trim((string) $r->get('q', ''));
        $per   = (int) $r->get('per', 20);
        $per   = in_array($per, [10,20,30,50], true) ? $per : 20;

        $base = AgentAbsenceRequest::query()
            ->with(['agent','files','decider'])
            ->whereHas('agent', function ($a) use ($role) {
                $a->whereIn('role', ['manager','admin']);
                if (in_array($role, ['manager','admin'], true)) {
                    $a->where('role', $role);
                }
            });

        if (in_array($state, ['pending','approved','cancelled'], true)) {
            $base->where('state', $state);
        } else {
            $state = 'all';
        }

        if (in_array($kind, self::KINDS, true)) {
            $base->where('kind', $kind);
        } else {
            $kind = 'all';
        }

        if ($q !== '') {
            $base->where(function ($qq) use ($q) {
                $id = ltrim($q, '#');
                if (ctype_digit($id)) $qq->orWhere('id', (int) $id);

                $qq->orWhere('reason', 'like', "%{$q}%")
                   ->orWhere('comments', 'like', "%{$q}%")
                   ->orWhereHas('agent', function ($a) use ($q) {
                       $a->where('username','like',"%{$q}%")
                         ->orWhere('name','like',"%{$q}%");
                   });
            });
        }

        $requests = $base->latest('id')->paginate($per)->appends($r->query());

        $countsBase = AgentAbsenceRequest::query()
            ->whereHas('agent', fn($a) => $a->whereIn('role', ['manager','admin']));

        $counts = [
            'pending'   => (clone $countsBase)->where('state','pending')->count(),
            'approved'  => (clone $countsBase)->where('state','approved')->count(),
            'cancelled' => (clone $countsBase)->where('state','cancelled')->count(),
            'all'       => (clone $countsBase)->count(),
        ];

        return view('superadmin.support_leave.review', compact('requests','state','kind','role','q','per','counts'));
    }

    /** Details page */
    public function show($request)
    {
        $this->superOnly();

        $req = AgentAbsenceRequest::with(['agent','files','decider'])
            ->whereHas('agent', fn($a) => $a->whereIn('role', ['manager','admin']))
            ->findOrFail((int)$request);

        $agent = $req->agent;

        $audits = AgentAbsenceAudit::query()
            ->with('actor')
            ->where('request_id', $req->id)
            ->latest('id')
            ->take(20)
            ->get();

        return view('superadmin.support_leave.show', compact('req','agent','audits'));
    }

    /** Decide action (called from details page only) */
    public function decide(Request $r, $request)
    {
        $this->superOnly();

        $req = AgentAbsenceRequest::with('agent')->findOrFail((int)$request);
        abort_unless($req->state === 'pending', 422);

        $r->validate([
            'decision' => ['required','in:approve,reject'],
            'note'     => ['nullable','string','max:255'],
        ]);

        $kind = strtolower((string) ($req->kind ?? 'absence'));
        abort_unless(in_array($kind, self::KINDS, true), 422);

        $isApprove = $r->decision === 'approve';

        DB::transaction(function () use ($r, $req, $kind, $isApprove) {

            $agent = Users::findOrFail($req->agent_id);
            $agentRole = strtolower((string) $agent->role);

            abort_unless(in_array($agentRole, ['manager','admin'], true), 403);

            $start = Carbon::parse($req->start_at)->utc();
            $end   = Carbon::parse($req->end_at)->utc();

            // overlap protection vs users scheduled window
            if ($agent->absence_start_at && $agent->absence_end_at) {
                $s = Carbon::parse($agent->absence_start_at)->utc();
                $e = Carbon::parse($agent->absence_end_at)->utc();
                $overlap = $start->lt($e) && $end->gt($s);

                if ($overlap) {
                    $req->update([
                        'state'         => 'cancelled',
                        'type'          => 'conflict',
                        'decided_by'    => auth()->id(),
                        'decided_at'    => now(),
                        'decision_note' => trim(($r->note ? $r->note.' • ' : '').'Conflict: user already has an existing leave window.'),
                    ]);

                    $this->logAudit($agent->id, 'conflict', $req->id, [
                        'kind'         => $kind,
                        'window_start' => (string) $req->start_at,
                        'window_end'   => (string) $req->end_at,
                        'note'         => (string) $r->note,
                    ]);

                    return;
                }
            }

            // ===== HOLIDAY =====
            if ($kind === 'holiday') {

                $req->update([
                    'state'         => $isApprove ? 'approved' : 'cancelled',
                    'type'          => $isApprove ? 'authorized' : 'rejected',
                    'decided_by'    => auth()->id(),
                    'decided_at'    => now(),
                    'decision_note' => $r->note,
                ]);

                $this->logAudit($agent->id, $isApprove ? 'approved' : 'rejected', $req->id, [
                    'kind'         => 'holiday',
                    'type'         => $isApprove ? 'authorized' : 'rejected',
                    'window_start' => (string) $req->start_at,
                    'window_end'   => (string) $req->end_at,
                    'note'         => (string) $r->note,
                ]);

                if ($isApprove) {
                    $this->scheduleLeave($agent, 'holiday', 'authorized', $start, $end);

                    $this->logAudit($agent->id, 'scheduled', $req->id, [
                        'kind'         => 'holiday',
                        'type'         => 'authorized',
                        'window_start' => (string) $req->start_at,
                        'window_end'   => (string) $req->end_at,
                        'note'         => (string) $r->note,
                    ]);
                }

                return;
            }

            // ===== ABSENCE =====
            // ✅ Requirement: rejected absence should NOT set UA or schedule anything.
            $req->update([
                'state'         => $isApprove ? 'approved' : 'cancelled',
                'type'          => $isApprove ? 'authorized' : 'rejected',
                'decided_by'    => auth()->id(),
                'decided_at'    => now(),
                'decision_note' => $r->note,
            ]);

            $this->logAudit($agent->id, $isApprove ? 'approved' : 'rejected', $req->id, [
                'kind'         => 'absence',
                'type'         => $isApprove ? 'authorized' : 'rejected',
                'window_start' => (string) $req->start_at,
                'window_end'   => (string) $req->end_at,
                'note'         => (string) $r->note,
            ]);

            if ($isApprove) {
                $this->scheduleLeave($agent, 'absence', 'authorized', $start, $end);

                $this->logAudit($agent->id, 'scheduled', $req->id, [
                    'kind'         => 'absence',
                    'type'         => 'authorized',
                    'window_start' => (string) $req->start_at,
                    'window_end'   => (string) $req->end_at,
                    'note'         => (string) $r->note,
                ]);
            } else {
                // Defensive: if legacy data had scheduled an unauthorized window, clear it if it matches this request.
                if ($agent->absence_kind && $agent->absence_start_at && $agent->absence_end_at) {
                    $as = Carbon::parse($agent->absence_start_at)->utc();
                    $ae = Carbon::parse($agent->absence_end_at)->utc();
                    if (strtolower((string)$agent->absence_kind) === 'absence' && $as->eq($start) && $ae->eq($end)) {
                        $this->clearLeaveWindow($agent);
                    }
                }
            }
        });

        return redirect()
            ->route('superadmin.support.leave.show', (int) $req->id)
            ->with('success', 'Decision Saved.');
    }

    public function downloadReviewRequestFile($file)
    {
        $this->superOnly();

        $f = AgentAbsenceRequestFile::findOrFail((int)$file);
        return Storage::disk($f->disk)->download($f->path, $f->original_name ?? 'attachment');
    }
}