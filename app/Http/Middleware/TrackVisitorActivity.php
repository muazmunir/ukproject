<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TrackVisitorActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Don’t track admin panel hits as “visitors” if you don’t want
        if ($request->is('admin/*')) {
            return $response;
        }

        $now = Carbon::now();

        // 1) Get or set visitor_id cookie
        $cookieName  = 'za_visitor';
        $visitorId   = $request->cookie($cookieName);

        if (!$visitorId) {
            $visitorId = (string) Str::uuid();
            // Set cookie for 1 year
            $response->headers->setCookie(
                cookie($cookieName, $visitorId, 60 * 24 * 365)
            );
        }

        // 2) Basic data
        $user   = $request->user();
        $ip     = $request->ip();
        $agent  = substr($request->userAgent() ?? '', 0, 255);
        $path   = substr($request->path(), 0, 255);

        // 3) Find or create visit row
        $visit = Visit::where('visitor_id', $visitorId)->first();

        if (!$visit) {
            $visit = Visit::create([
                'visitor_id'   => $visitorId,
                'user_id'      => $user?->id,
                'ip'           => $ip,
                'user_agent'   => $agent,
                'path'         => $path,
                'first_seen_at'=> $now,
                'last_seen_at' => $now,
            ]);
        } else {
            $visit->update([
                'user_id'      => $user?->id ?? $visit->user_id,
                'ip'           => $ip,
                'user_agent'   => $agent,
                'path'         => $path,
                'last_seen_at' => $now,
            ]);
        }

        return $response;
    }
}
