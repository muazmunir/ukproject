<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCoachPayoutReady
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $user->loadMissing([
            'coachProfile.payoutAccounts.payoutMethods',
            'coachProfile.defaultPayoutAccount.payoutMethods',
        ]);

        if (!($user->is_coach ?? false)) {
            return redirect()->route('coach.apply');
        }

        $coachProfile = $user->coachProfile;

        if (!$coachProfile) {
            return redirect()->route('coach.application.show');
        }

        if ($coachProfile->application_status !== 'approved') {
            return redirect()->route('coach.application.review');
        }

        // Always allow payout routes themselves
        if ($request->routeIs('coach.payouts.*')) {
            return $next($request);
        }

        $preferredProvider = strtolower(
            $coachProfile->preferred_payout_provider
            ?? $coachProfile->defaultPayoutAccount?->provider
            ?? 'stripe'
        );

        $account = $coachProfile->payoutAccounts
            ->first(function ($item) use ($preferredProvider) {
                return strtolower((string) $item->provider) === $preferredProvider
                    && (bool) $item->is_default;
            });

        if (!$account) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', 'Please connect a payout account before continuing.');
        }

        if (!$account->payouts_enabled) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', 'Please complete payout verification before continuing.');
        }

        if ($account->payoutMethods->isEmpty()) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', 'Please add a payout method before continuing.');
        }

        return $next($request);
    }
}