<?php

namespace App\Http\Controllers\SuperAdmin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminSecurityEvent;
use Illuminate\Http\Request;

class SecurityEventsController extends Controller
{
    public function index(Request $request)
    {
        $status = (string)$request->query('status', 'open');

        $events = AdminSecurityEvent::query()
            ->with(['admin','reviewer'])
            ->when(in_array($status, ['open','reviewed','closed'], true), fn($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.security.events', compact('events','status'));
    }

    public function markReviewed(Request $request, AdminSecurityEvent $event)
    {
        $event->forceFill([
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => (int)$request->user()->id,
        ])->save();

        return back()->with('ok', 'Event marked as reviewed.');
    }
}
