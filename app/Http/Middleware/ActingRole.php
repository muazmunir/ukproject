<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActingRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login');

        $role = strtolower($role);

        // Client routes: always set client
        if ($role === 'client') {
            session(['active_role' => 'client']);
            return $next($request);
        }

        // Coach routes:
        // Only allow session role=coach when approved
        $isApprovedCoach =
            ($user->is_coach ?? false) &&
            ((string)($user->coach_verification_status ?? 'draft') === 'approved');

        session(['active_role' => $isApprovedCoach ? 'coach' : 'client']);

        return $next($request);
    }
}
