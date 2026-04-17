<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminNotHardLocked
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if ($u && in_array(strtolower((string)$u->role), ['admin','manager'], true)) {
            if ((bool)($u->is_locked ?? false)) {

                // ✅ For AJAX / polling / fetch calls: don't logout, just return signal
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'ok'     => false,
                        'locked' => true,
                        'message'=> 'Your account is locked.',
                    ], 423);
                }

                // ✅ For page navigation: show locked page (no logout event fired)
                return redirect()->route('admin.locked.notice');
            }
        }

        return $next($request);
    }
}
