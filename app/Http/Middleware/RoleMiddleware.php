<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
   public function handle(Request $request, Closure $next, ...$roles)
{
    $user = $request->user( );
    if (! $user) abort(401);

    // if you have actingAs(), prefer it
    foreach ($roles as $role) {
        if (method_exists($user, 'actingAs') && $user->actingAs($role)) {
            return $next($request);
        }
    }

    // fallback to column if no actingAs method
    if (in_array($user->role, $roles, true)) {
        return $next($request);
    }

    abort(403);
}

}
