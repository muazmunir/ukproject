<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfAdminAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        // If logged in AND staff => don't allow admin login page; go to admin dashboard
        if ($u && in_array(strtolower((string)($u->role ?? '')), ['admin','manager','super_admin'], true)) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
