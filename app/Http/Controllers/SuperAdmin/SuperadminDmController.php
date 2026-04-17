<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StaffDmThread;
use Illuminate\Http\Request;

class SuperadminDmController extends Controller
{
    private function ensureSuperadmin(): void
    {
        $role = strtolower((string) auth()->user()->role);
        abort_unless(in_array($role, ['superadmin','super_admin'], true), 403);
    }

    public function index(Request $r)
    {
        $this->ensureSuperadmin();

        $q = StaffDmThread::query()
            ->with(['manager', 'agent'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        // Filter: status (active/archived)
        if (($status = $r->get('status')) === 'active') {
            $q->where('is_active', true);
        } elseif ($status === 'archived') {
            $q->where('is_active', false);
        }

        // Filter: manager
        if ($mid = (int) $r->get('manager_id')) {
            $q->where('manager_id', $mid);
        }

        // Filter: agent
        if ($aid = (int) $r->get('agent_id')) {
            $q->where('agent_id', $aid);
        }

        // Search: manager/agent name/username (basic)
        if ($s = trim((string) $r->get('q'))) {
            $q->where(function ($qq) use ($s) {
                $qq->whereHas('manager', fn($m) => $m->where('username','like',"%{$s}%")->orWhere('name','like',"%{$s}%"))
                   ->orWhereHas('agent', fn($a) => $a->where('username','like',"%{$s}%")->orWhere('name','like',"%{$s}%"));
            });
        }

        $threads = $q->paginate(25)->appends($r->query());

        // Auto-open first thread
        $active = $threads->first();

        return view('superadmin.dm.index', compact('threads','active'));
    }

    public function show(Request $r, StaffDmThread $thread)
    {
        $this->ensureSuperadmin();

        $thread->load(['manager','agent']);

        // Build same list on left (keeps filters applied)
        $q = StaffDmThread::query()
            ->with(['manager','agent'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if (($status = $r->get('status')) === 'active') {
            $q->where('is_active', true);
        } elseif ($status === 'archived') {
            $q->where('is_active', false);
        }
        if ($mid = (int) $r->get('manager_id')) $q->where('manager_id', $mid);
        if ($aid = (int) $r->get('agent_id')) $q->where('agent_id', $aid);
        if ($s = trim((string) $r->get('q'))) {
            $q->where(function ($qq) use ($s) {
                $qq->whereHas('manager', fn($m) => $m->where('username','like',"%{$s}%")->orWhere('name','like',"%{$s}%"))
                   ->orWhereHas('agent', fn($a) => $a->where('username','like',"%{$s}%")->orWhere('name','like',"%{$s}%"));
            });
        }

        $threads = $q->paginate(25)->appends($r->query());

        // Messages (audit view)
        $messages = $thread->messages()
            ->with('sender')
            ->latest('id')
            ->take(100)
            ->get()
            ->reverse()
            ->values();

        return view('superadmin.dm.show', compact('thread','threads','messages'));
    }
}
