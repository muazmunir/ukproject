<?php
// app/Http/Middleware/UseUserTimezone.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UseUserTimezone
{
    public function handle(Request $request, Closure $next)
    {
        $tz = null;

        if ($request->user() && $request->user()->timezone) {
            $tz = $request->user()->timezone;
        } elseif (session()->has('guest_timezone')) {
            $tz = session('guest_timezone');
        }

        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            // Set PHP runtime TZ so Carbon/DateTime respect it
            @date_default_timezone_set($tz);
            config(['app.timezone' => $tz]); // optional but nice to keep consistent
        }

        return $next($request);
    }
}
