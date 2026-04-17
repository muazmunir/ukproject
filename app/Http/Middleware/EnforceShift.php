<?php
// app/Http/Middleware/EnforceShift.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Shift;

class EnforceShift
{
  public function handle(Request $request, Closure $next)
  {
    $u = $request->user();

    // superadmin not restricted
    if ($u && strtolower((string)$u->role) === 'superadmin') {
      return $next($request);
    }

    if ($u && !Shift::isWorkingNow($u)) {
      // professional response: either 403 or redirect with message
      if ($request->expectsJson()) {
        return response()->json([
          'message' => 'You are outside your assigned working hours.'
        ], 403);
      }
      return redirect()->back()->with('error', 'You are outside your assigned working hours.');
    }

    return $next($request);
  }
}
