<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
class EnsureActiveRole
{
    public function handle($request, Closure $next, string $role)
    {
        $active = strtolower((string) session('active_role', 'client'));
        $role   = strtolower(trim($role));

        if ($active !== $role) {
            abort(403, 'Invalid active role.');
        }

        return $next($request);
    }
}

