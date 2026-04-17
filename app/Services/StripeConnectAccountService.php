<?php

namespace App\Services;

use App\Models\CoachPayoutAccount;
use App\Models\CoachPayoutMethod;
use App\Models\CoachProfile;
use Illuminate\Support\Arr;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeConnectAccountService
{
    protected function stripe(): StripeClient
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        return new StripeClient($secret);
    }

    /**
     * Create or return the coach's default Stripe Express account.
     */
    public function createOrGetExpressAccount(CoachProfile $coachProfile): CoachPayoutAccount
    {
        $existing = $coachProfile->payoutAccounts()
            ->where('provider', 'stripe')
            ->where('is_default', true)
            ->first();

        if ($existing && filled($existing->provider_account_id)) {
            return $existing;
        }

        $user = $coachProfile->user;

        $account = $this->stripe()->accounts->create([
            'type' => 'express',
            'email' => $user->email,
            'business_type' => 'individual',
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
                'coach_profile_id' => (string) $coachProfile->id,
            ],
        ]);

        return CoachPayoutAccount::updateOrCreate(
            [
                'coach_profile_id' => $coachProfile->id,
                'provider' => 'stripe',
                'is_default' => true,
            ],
            [
                'provider_account_id' => $account->id,
                'status' => $this->determineStripeStatus($account),
                'country' => $account->country ?? null,
                'default_currency' => strtoupper((string) ($account->default_currency ?? 'USD')),
                'charges_enabled' => (bool) ($account->charges_enabled ?? false),
                'payouts_enabled' => (bool) ($account->payouts_enabled ?? false),
                'capabilities' => (array) ($account->capabilities ?? []),
                'requirements_currently_due' => (array) data_get($account, 'requirements.currently_due', []),
                'requirements_eventually_due' => (array) data_get($account, 'requirements.eventually_due', []),
                'requirements_past_due' => (array) data_get($account, 'requirements.past_due', []),
                'raw_provider_payload' => method_exists($account, 'toArray') ? $account->toArray() : [],
                'onboarding_started_at' => now(),
                'onboarding_completed_at' => null,
                'verified_at' => null,
            ]
        );
    }

    /**
     * Create a Stripe onboarding link for the coach.
     */
    public function createOnboardingLink(CoachPayoutAccount $payoutAccount): string
    {
        if (blank($payoutAccount->provider_account_id)) {
            throw new RuntimeException('Stripe provider account ID is missing.');
        }

        $link = $this->stripe()->accountLinks->create([
            'account' => $payoutAccount->provider_account_id,
            'refresh_url' => route('coach.payouts.stripe.refresh'),
            'return_url' => route('coach.payouts.stripe.return'),
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    /**
     * Pull fresh account data from Stripe and sync to local DB.
     */
    public function syncStripeAccount(CoachPayoutAccount $payoutAccount): CoachPayoutAccount
    {
        if (blank($payoutAccount->provider_account_id)) {
            throw new RuntimeException('Stripe provider account ID is missing.');
        }

        $account = $this->stripe()->accounts->retrieve($payoutAccount->provider_account_id, []);

        $currentlyDue = (array) data_get($account, 'requirements.currently_due', []);
        $eventuallyDue = (array) data_get($account, 'requirements.eventually_due', []);
        $pastDue = (array) data_get($account, 'requirements.past_due', []);
        $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);
        $chargesEnabled = (bool) ($account->charges_enabled ?? false);

        $status = $this->determineStripeStatus($account);
        $isVerified = $status === 'verified' && $payoutsEnabled;

        $payoutAccount->forceFill([
            'status' => $status,
            'country' => $account->country ?? $payoutAccount->country,
            'default_currency' => strtoupper((string) ($account->default_currency ?? $payoutAccount->default_currency ?? 'USD')),
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'capabilities' => (array) ($account->capabilities ?? []),
            'requirements_currently_due' => $currentlyDue,
            'requirements_eventually_due' => $eventuallyDue,
            'requirements_past_due' => $pastDue,
            'raw_provider_payload' => method_exists($account, 'toArray') ? $account->toArray() : [],
            'onboarding_completed_at' => empty($currentlyDue) ? ($payoutAccount->onboarding_completed_at ?? now()) : null,
            'verified_at' => $isVerified ? ($payoutAccount->verified_at ?? now()) : null,
        ])->save();

        $this->syncExternalAccounts($payoutAccount);
        $this->syncCoachPayoutFlags($payoutAccount);

        return $payoutAccount->fresh(['payoutMethods']);
    }

    /**
     * Sync Stripe external bank accounts into local payout methods.
     */
    public function syncExternalAccounts(CoachPayoutAccount $payoutAccount): void
    {
        if (blank($payoutAccount->provider_account_id)) {
            return;
        }

        $externalAccounts = $this->stripe()->accounts->allExternalAccounts(
            $payoutAccount->provider_account_id,
            ['object' => 'bank_account']
        );

        $seenExternalIds = [];

        $payoutAccount->payoutMethods()->update([
            'is_default' => false,
        ]);

        foreach ($externalAccounts->data as $externalAccount) {
            $externalId = $externalAccount->id ?? null;

            if (!$externalId) {
                continue;
            }

            $seenExternalIds[] = $externalId;

            CoachPayoutMethod::updateOrCreate(
                [
                    'coach_payout_account_id' => $payoutAccount->id,
                    'provider' => 'stripe',
                    'provider_external_account_id' => $externalId,
                ],
                [
                    'type' => $externalAccount->object ?? 'bank_account',
                    'brand' => $externalAccount->brand ?? null,
                    'bank_name' => $externalAccount->bank_name ?? null,
                    'last4' => $externalAccount->last4 ?? null,
                    'country' => $externalAccount->country ?? null,
                    'currency' => strtoupper((string) ($externalAccount->currency ?? 'USD')),
                    'is_default' => (bool) ($externalAccount->default_for_currency ?? false),
                    'status' => $this->determineExternalAccountStatus($externalAccount),
                    'raw_provider_payload' => method_exists($externalAccount, 'toArray')
                        ? $externalAccount->toArray()
                        : [],
                ]
            );
        }

        if (!empty($seenExternalIds)) {
            $payoutAccount->payoutMethods()
                ->where('provider', 'stripe')
                ->whereNotIn('provider_external_account_id', $seenExternalIds)
                ->delete();
        }
    }

    /**
     * Update coach-level payout readiness flags.
     */
    protected function syncCoachPayoutFlags(CoachPayoutAccount $payoutAccount): void
    {
        $coachProfile = $payoutAccount->coachProfile;

        if (!$coachProfile) {
            return;
        }

        $isReady = $payoutAccount->status === 'verified'
            && (bool) $payoutAccount->payouts_enabled;

        $coachProfile->forceFill([
            'can_receive_payouts' => $isReady,
            'can_accept_bookings' => $isReady,
        ])->save();
    }

    /**
     * Determine normalized local status from Stripe account data.
     */
    protected function determineStripeStatus(object $account): string
    {
        $currentlyDue = (array) data_get($account, 'requirements.currently_due', []);
        $pastDue = (array) data_get($account, 'requirements.past_due', []);
        $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);
        $chargesEnabled = (bool) ($account->charges_enabled ?? false);

        if (!empty($pastDue)) {
            return 'restricted';
        }

        if ($payoutsEnabled && empty($currentlyDue)) {
            return 'verified';
        }

        if (!$chargesEnabled && !$payoutsEnabled && !empty($currentlyDue)) {
            return 'onboarding_required';
        }

        return 'pending_verification';
    }

    /**
     * Determine a normalized status for external payout methods.
     */
    protected function determineExternalAccountStatus(object $externalAccount): string
    {
        $status = (string) ($externalAccount->status ?? '');

        if ($status !== '') {
            return $status;
        }

        return 'active';
    }
}