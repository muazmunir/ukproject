<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCoachApproved
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u) return redirect()->route('login');

        if (!($u->is_coach ?? false)) {
            return redirect()->route('coach.apply');
        }

        if (($u->coach_verification_status ?? 'draft') !== 'approved') {
            return redirect()->route('coach.home');
        }

        return $next($request);
    }
}
