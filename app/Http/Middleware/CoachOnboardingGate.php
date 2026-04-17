<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CoachOnboardingGate
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $user->loadMissing('coachProfile');

        $route = optional($request->route())->getName();

        $isCoach = (bool) ($user->is_coach ?? false);
        $status  = (string) ($user->coachProfile?->application_status ?? 'draft');

        if (!$isCoach) {
            return $next($request);
        }

        if ($route === 'coach.application.review' && $status === 'approved') {
            session(['active_role' => 'coach']);
            return redirect()->route('coach.home');
        }

        if ($route === 'coach.application.review' && $status !== 'submitted') {
            session(['active_role' => 'client']);
            return redirect()->route('coach.application.show');
        }

        if ($status === 'submitted') {
            session(['active_role' => 'client']);

            if ($route !== 'coach.application.review') {
                return redirect()->route('coach.application.review');
            }

            return $next($request);
        }

        if ($status === 'approved') {
            return $next($request);
        }

        session(['active_role' => 'client']);
        return $next($request);
    }
}