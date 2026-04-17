<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfActingCoach
{
    public function handle(Request $request, Closure $next)
    {
        $active = strtolower((string) session('active_role', 'client'));

        // Only redirect if user is actually in coach mode
        if ($active !== 'coach') {
            return $next($request);
        }

        $routeName = optional($request->route())->getName() ?? '';

        // allow these even in coach mode
        $allow = [
            'coach.*',
            'role.switch',
            'logout',
            'me.timezone.update',
            'api.locale',
        ];

        foreach ($allow as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $routeName)) {
                return $next($request);
            }
        }

        // anything else -> force dashboard
        return redirect()->route('coach.home');
    }
}
