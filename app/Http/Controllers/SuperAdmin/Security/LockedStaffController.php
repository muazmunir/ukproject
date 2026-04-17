<?php

namespace App\Http\Controllers\SuperAdmin\Security;

use App\Http\Controllers\Controller;
use App\Models\Users;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;

class LockedStaffController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $role = (string)$request->query('role', 'all');

        $staff = Users::query()
            ->whereIn('role', ['admin','manager'])
            ->when($role !== 'all', fn($qq) => $qq->where('role', $role))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_locked')
            ->orderByDesc('locked_at')
            ->paginate(12)
            ->withQueryString();

        return view('superadmin.security.locked_staff', compact('staff','q','role'));
    }

   public function lock(Request $request, Users $user, AdminAuditService $audit)
{
    abort_unless(in_array($user->role, ['admin','manager'], true), 404);

    $validated = $request->validate([
        'reason' => ['required', 'string', 'max:255'],
    ], [
        'reason.required' => 'Please provide a reason before locking this account.',
    ]);

    $reason = trim($validated['reason']);

    $user->forceFill([
        'is_locked' => true,
        'locked_at' => now(),
        'locked_reason' => $reason,
        'locked_by' => (int) $request->user()->id,
    ])->save();

    $audit->log('hard_locked', $user->id, Users::class, [
        'reason' => $reason,
        'by' => 'superadmin_manual',
    ], (int) $request->user()->id);

    return back()->with('ok', 'Admin Account Locked.');
}
    public function unlock(Request $request, Users $user, AdminAuditService $audit)
    {
        abort_unless(in_array($user->role, ['admin','manager'], true), 404);

        $user->forceFill([
            'is_locked' => false,
            'locked_at' => null,
            'locked_reason' => null,
            'locked_by' => (int)$request->user()->id, // keep who performed last lock action
        ])->save();

        $audit->log('hard_unlocked', $user->id, Users::class, [
            'by' => 'superadmin_manual',
        ], (int)$request->user()->id);

        return back()->with('ok', 'Admin Account Unlocked.');
    }
}
