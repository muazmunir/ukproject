<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CoachLockMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Only run inside coach routes
        if (! $request->routeIs('coach.*')) {
            return $next($request);
        }

        // Must have coach capability
        $isCoach = (bool) ($user->is_coach ?? false);

        if (! $isCoach) {
            // keep Fiverr-style: bounce to apply or client
            session(['active_role' => 'client']);
            return redirect()->route('coach.apply'); // or client.home if you prefer
        }

        // Entering coach area => set mode
        session(['active_role' => 'coach']);

        // ✅ DO NOT redirect based on KYC status here
        return $next($request);
    }
}
