<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminActionLog;

class AdminSoftLockController extends Controller
{
    private function staffOrFail()
    {
        $u = auth()->user();
        abort_unless($u && in_array(strtolower((string)($u->role ?? '')), ['admin','manager','superadmin'], true), 403);
        return $u;
    }

    public function notice()
    {
        $this->staffOrFail();
        return view('admin.auth.locked_soft');
    }

    // JS calls this after 60s to lock immediately
    public function trigger(Request $request)
    {
        $admin = $this->staffOrFail();

        if (!session('admin_soft_locked')) {
            DB::transaction(function () use ($admin, $request) {
                $admin->forceFill([
                    'admin_soft_locked_at'   => now(),
                    'admin_soft_lock_count'  => ((int)($admin->admin_soft_lock_count ?? 0)) + 1,
                ])->save();

                AdminActionLog::create([
                    'admin_user_id' => (int)$admin->id,
                    'action'        => 'soft_locked',
                    'target_type'   => 'user',
                    'target_id'     => (int)$admin->id,
                    'meta'          => ['idle_seconds' => 60, 'source' => 'js'],
                    'ip'            => $request->ip(),
                    'user_agent'    => substr((string)$request->userAgent(), 0, 500),
                ]);
            });

            session(['admin_soft_locked' => true]);
        }

        return response()->json(['ok' => true]);
    }

   public function unlock(Request $request)
{
    $admin = $this->staffOrFail();

    $request->validate([
        'password' => ['required','string','min:6'],
    ]);

    if (!Hash::check((string)$request->password, (string)$admin->password)) {
        return back()->withErrors(['password' => 'Incorrect password.'])->withInput();
    }

    DB::transaction(function () use ($admin, $request) {
        AdminActionLog::create([
            'admin_user_id' => (int)$admin->id,
            'action'        => 'soft_unlocked',
            'target_type'   => 'user',
            'target_id'     => (int)$admin->id,
            'meta'          => ['source' => 'password'],
            'ip'            => $request->ip(),
            'user_agent'    => substr((string)$request->userAgent(), 0, 500),
        ]);

        $admin->forceFill(['admin_soft_locked_at' => null])->save();
    });

    session()->forget('admin_soft_locked');

    // ✅ use our own intended key (not polluted by ajax/polling)
    $url = (string) session('admin_intended_url', route('admin.dashboard'));

    // ✅ clear BOTH keys so nothing stale remains
    session()->forget('admin_intended_url');
    session()->forget('url.intended');

    // ✅ safety: never redirect to polling/lock endpoints
    if (
        !str_contains($url, '/admin') ||
        str_contains($url, '/admin/metrics') ||
        str_contains($url, '/admin/locked') ||
        str_contains($url, '/admin/soft-lock')
    ) {
        $url = route('admin.dashboard');
    }

    return redirect()->to($url);
}

}
