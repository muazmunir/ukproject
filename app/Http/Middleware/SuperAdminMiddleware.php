<?php
// app/Http/Middleware/SuperAdminMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (Auth::user()->role !== 'superadmin') {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
