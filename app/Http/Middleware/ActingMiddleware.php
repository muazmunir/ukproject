<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActingMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredRole)
    {
        $user = $request->user();
        if (! $user) abort(401);

        $requiredRole = strtolower(trim($requiredRole));
        if (!in_array($requiredRole, ['client', 'coach'], true)) {
            abort(500, 'Invalid acting role middleware configuration.');
        }

        // Active role from session
        $active = strtolower((string) session('active_role', ''));

        // Default to client
        if (!in_array($active, ['client', 'coach'], true)) {
            $active = 'client';
            session(['active_role' => $active]);
        }

        // ✅ If user is not a coach, they cannot act as coach
        if ($active === 'coach' && !($user->is_coach ?? false)) {
            $active = 'client';
            session(['active_role' => $active]);
        }

        // Required role checks
        if ($requiredRole === 'client') {
            if ($active !== 'client') {
                abort(403, 'Switch to Client account to access this page.');
            }
            return $next($request);
        }

        // requiredRole === 'coach'
        if (!($user->is_coach ?? false)) {
            abort(403, 'Coach account required.');
        }

        if ($active !== 'coach') {
            abort(403, 'Switch to Coach account to access this page.');
        }

        // ✅ DO NOT check is_approved here anymore.
        // KYC overlay / lock will handle pending/rejected.

        return $next($request);
    }
}
