<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOrManager
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (! $user) {
            return $this->toAdminLogin($request);
        }

        // ✅ Disabled staff
        if (! (bool)($user->is_active ?? true)) {

            // AJAX/polling: don't logout (prevents Logout listener spam)
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'disabled' => true,
                    'message' => 'Your account is disabled.',
                ], 423);
            }

            // real navigation: logout and redirect
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => __('Your account is disabled.')]);
        }

        $role = strtolower((string)($user->role ?? ''));
        if (!in_array($role, ['admin','manager'], true)) {
            abort(403, __('Unauthorized.'));
        }

        return $next($request);
    }

    private function toAdminLogin(Request $request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return redirect()->route('admin.login');
    }
}
