<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Services\PayoutProviders\PayoutProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CoachPayoutController extends Controller
{
    private const PROVIDER = 'stripe';

    public function settings(Request $request)
    {
        $coachProfile = $this->getCoachProfileOrAbort($request);

        $coachProfile->load([
            'defaultPayoutAccount.payoutMethods',
            'payoutAccounts.payoutMethods',
        ]);

        $stripeAccount = $coachProfile->payoutAccounts
            ->first(fn ($account) => strtolower((string) $account->provider) === self::PROVIDER && (bool) $account->is_default);

        return view('coach.payout-settings', [
            'coachProfile' => $coachProfile,
            'stripeAccount' => $stripeAccount,
        ]);
    }

    public function start(Request $request, PayoutProviderRegistry $registry): RedirectResponse
    {
        $coachProfile = $this->getCoachProfileOrAbort($request);

        if ($coachProfile->application_status !== 'approved') {
            return redirect()
                ->route('coach.application.review')
                ->with('error', __('Your coach application must be approved before payout onboarding.'));
        }

        $coachProfile->forceFill([
            'preferred_payout_provider' => self::PROVIDER,
        ])->save();

        $service = $registry->for(self::PROVIDER);
        $payoutAccount = $service->createOrGetAccount($coachProfile);
        $url = $service->createOnboardingLink($payoutAccount);

        return redirect()->away($url);
    }

    public function refresh(Request $request, PayoutProviderRegistry $registry): RedirectResponse
    {
        $coachProfile = $this->getCoachProfileOrAbort($request);

        if ($coachProfile->application_status !== 'approved') {
            return redirect()
                ->route('coach.application.review')
                ->with('error', __('Your coach application must be approved before managing payouts.'));
        }

        $payoutAccount = $coachProfile->payoutAccounts()
            ->where('provider', self::PROVIDER)
            ->where('is_default', true)
            ->first();

        if (! $payoutAccount) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', __('No Stripe payout account was found. Please connect Stripe first.'));
        }

        $registry->for(self::PROVIDER)->syncAccount($payoutAccount);

        return redirect()
            ->route('coach.payouts.settings')
            ->with('ok', __('Your Stripe Connect account status has been refreshed.'));
    }

    public function providerReturn(Request $request, PayoutProviderRegistry $registry): RedirectResponse
    {
        $coachProfile = $this->getCoachProfileOrAbort($request);

        $payoutAccount = $coachProfile->payoutAccounts()
            ->where('provider', self::PROVIDER)
            ->where('is_default', true)
            ->first();

        if ($payoutAccount) {
            $registry->for(self::PROVIDER)->syncAccount($payoutAccount);
        }

        $coachProfile->forceFill([
            'preferred_payout_provider' => self::PROVIDER,
        ])->save();

        return redirect()
            ->route('coach.payouts.settings')
            ->with('ok', __('Your Stripe Connect details have been updated successfully.'));
    }

    public function setDefaultMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'method_id' => ['required', 'integer'],
        ]);

        $coachProfile = $this->getCoachProfileOrAbort($request);

        $account = $coachProfile->payoutAccounts()
            ->where('provider', self::PROVIDER)
            ->where('is_default', true)
            ->first();

        if (! $account) {
            return redirect()
                ->route('coach.withdraw.index')
                ->with('error', __('No Stripe payout account found.'));
        }

        $method = $account->payoutMethods()
            ->where('id', $validated['method_id'])
            ->first();

        if (! $method) {
            return redirect()
                ->route('coach.withdraw.index')
                ->with('error', __('Selected payout method was not found.'));
        }

        $account->payoutMethods()->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return redirect()
            ->route('coach.withdraw.index')
            ->with('ok', __('Default payout method updated successfully.'));
    }

    private function getCoachProfileOrAbort(Request $request): CoachProfile
    {
        $coachProfile = $request->user()?->coachProfile;

        if (! $coachProfile) {
            throw new HttpException(403);
        }

        return $coachProfile;
    }
}