<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CoachLock
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $user->loadMissing('coachProfile');

        if (!($user->is_coach ?? false)) {
            session(['active_role' => 'client']);
            return redirect()->route('coach.apply');
        }

        $coachProfile = $user->coachProfile;
        $status = (string) ($coachProfile?->application_status ?? 'draft');

        if ($status === 'approved') {
            session(['active_role' => 'coach']);
            return $next($request);
        }

        session(['active_role' => 'client']);

        if ($status === 'submitted') {
            return redirect()->route('coach.application.review');
        }

        return redirect()->route('coach.application.show');
    }
}