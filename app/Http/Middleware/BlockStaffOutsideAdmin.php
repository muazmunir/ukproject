<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockStaffOutsideAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        // Only care when logged in as staff
        if ($u && in_array(strtolower((string)($u->role ?? '')), ['admin','manager','super_admin'], true)) {

            // Allow ONLY admin + superadmin areas
            if ($request->is('admin/*') || $request->is('admin') || $request->is('superadmin/*') || $request->is('superadmin')) {
                return $next($request);
            }

            // Block everything else
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
