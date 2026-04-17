<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CoachKycOverlayGate
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $activeRole = strtolower((string) session('active_role', 'client'));

        // Only apply when browsing coach area in coach mode
        if (! $user || $activeRole !== 'coach') {
            return $next($request);
        }

        // Default: no overlay
        $state = null;

        // If submitted and pending/rejected => overlay
        if ($user->coach_kyc_submitted) {
            if ($user->coach_verification_status === 'pending') {
                $state = 'pending';
            } elseif ($user->coach_verification_status === 'rejected') {
                $state = 'rejected';
            }
        }

        view()->share('coachKycState', $state);

        return $next($request);
    }
}
