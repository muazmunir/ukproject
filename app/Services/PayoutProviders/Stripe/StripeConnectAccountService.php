<?php

namespace App\Services\PayoutProviders\Stripe;

use App\Models\CoachPayoutAccount;
use App\Models\CoachPayoutMethod;
use App\Models\CoachProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeConnectAccountService
{
    public function __construct(protected StripeClient $stripe)
    {
    }

    public static function make(): self
    {
        return new self(new StripeClient(config('services.stripe.secret')));
    }

    public function createOrGetExpressAccount(CoachProfile $coachProfile): CoachPayoutAccount
    {
        return DB::transaction(function () use ($coachProfile) {
            $existing = $coachProfile->payoutAccounts()
                ->where('provider', 'stripe')
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->provider_account_id) {
                return $existing;
            }

            $email = $coachProfile->user?->email;

            $stripeAccount = $this->stripe->accounts->create([
                'type' => 'express',
                'email' => $email,
                'metadata' => [
                    'coach_profile_id' => (string) $coachProfile->id,
                    'user_id' => (string) $coachProfile->user_id,
                    'provider' => 'stripe',
                ],
            ]);

            if (! $existing) {
                $coachProfile->payoutAccounts()
                    ->where('provider', 'stripe')
                    ->update(['is_default' => false]);

                $existing = new CoachPayoutAccount();
                $existing->coach_profile_id = $coachProfile->id;
                $existing->provider = 'stripe';
                $existing->is_default = true;
            }

            $existing->provider_account_id = $stripeAccount->id;
            $existing->status = 'onboarding_required';
            $existing->country = $stripeAccount->country ?: null;
            $existing->default_currency = $stripeAccount->default_currency
                ? strtoupper((string) $stripeAccount->default_currency)
                : ($existing->default_currency ?: 'USD');
            $existing->charges_enabled = (bool) ($stripeAccount->charges_enabled ?? false);
            $existing->payouts_enabled = (bool) ($stripeAccount->payouts_enabled ?? false);
            $existing->onboarding_started_at = $existing->onboarding_started_at ?: now();
            $existing->requirements_currently_due = array_values($stripeAccount->requirements->currently_due ?? []);
            $existing->requirements_eventually_due = array_values($stripeAccount->requirements->eventually_due ?? []);
            $existing->requirements_past_due = array_values($stripeAccount->requirements->past_due ?? []);
            $existing->capabilities = (array) ($stripeAccount->capabilities ?? []);
            $existing->raw_provider_payload = $stripeAccount->toArray();
            $existing->save();

            $coachProfile->forceFill([
                'preferred_payout_provider' => 'stripe',
            ])->save();

            Log::info('Stripe Express account created or reused', [
                'coach_profile_id' => $coachProfile->id,
                'coach_payout_account_id' => $existing->id,
                'provider_account_id' => $existing->provider_account_id,
            ]);

            return $existing;
        });
    }

    public function createOnboardingLink(CoachPayoutAccount $account): string
    {
        $refreshUrl = route('coach.payouts.settings');
        $returnUrl = route('coach.payouts.stripe.return');

        $link = $this->stripe->accountLinks->create([
            'account' => $account->provider_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return (string) $link->url;
    }

    public function syncStripeAccount(CoachPayoutAccount $account): CoachPayoutAccount
    {
        if (! $account->provider_account_id) {
            return $account;
        }

        return DB::transaction(function () use ($account) {
            /** @var Account $stripeAccount */
            $stripeAccount = $this->stripe->accounts->retrieve($account->provider_account_id, []);

            $payload = $stripeAccount->toArray();

            $currentlyDue = array_values($stripeAccount->requirements->currently_due ?? []);
            $pastDue = array_values($stripeAccount->requirements->past_due ?? []);
            $eventuallyDue = array_values($stripeAccount->requirements->eventually_due ?? []);
            $disabledReason = data_get($payload, 'requirements.disabled_reason');

            $account->status = $this->mapStatus(
                payoutsEnabled: (bool) ($stripeAccount->payouts_enabled ?? false),
                chargesEnabled: (bool) ($stripeAccount->charges_enabled ?? false),
                currentlyDue: $currentlyDue,
                pastDue: $pastDue,
                disabledReason: $disabledReason
            );

            $account->country = $stripeAccount->country ?: $account->country;
            $account->default_currency = $stripeAccount->default_currency
                ? strtoupper((string) $stripeAccount->default_currency)
                : ($account->default_currency ?: 'USD');
            $account->charges_enabled = (bool) ($stripeAccount->charges_enabled ?? false);
            $account->payouts_enabled = (bool) ($stripeAccount->payouts_enabled ?? false);
            $account->requirements_currently_due = $currentlyDue;
            $account->requirements_eventually_due = $eventuallyDue;
            $account->requirements_past_due = $pastDue;
            $account->capabilities = (array) ($stripeAccount->capabilities ?? []);
            $account->raw_provider_payload = $payload;

            if (empty($currentlyDue) && ! $account->onboarding_completed_at) {
                $account->onboarding_completed_at = now();
            }

            if ($account->payouts_enabled && ! $account->verified_at) {
                $account->verified_at = now();
            }

            $account->save();

            $this->syncExternalAccounts($account);

            if ($account->coachProfile) {
                $account->coachProfile->forceFill([
                    'preferred_payout_provider' => 'stripe',
                    'can_receive_payouts' => (bool) $account->payouts_enabled,
                ])->save();
            }

            Log::info('Stripe account synced', [
                'coach_payout_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
                'status' => $account->status,
                'payouts_enabled' => $account->payouts_enabled,
                'charges_enabled' => $account->charges_enabled,
            ]);

            return $account->fresh([
                'payoutMethods',
                'coachProfile',
            ]);
        });
    }

    protected function syncExternalAccounts(CoachPayoutAccount $account): void
    {
        try {
            $externalAccounts = $this->stripe->accounts->allExternalAccounts(
                $account->provider_account_id,
                ['limit' => 20]
            );
        } catch (ApiErrorException $e) {
            report($e);

            Log::warning('Stripe external account sync failed', [
                'coach_payout_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $seenIds = [];
        $defaultMethodId = null;

        foreach ($externalAccounts->data as $external) {
            $payload = $external->toArray();
            $externalId = $payload['id'] ?? null;

            if (! $externalId) {
                continue;
            }

            $seenIds[] = $externalId;

            $method = CoachPayoutMethod::firstOrNew([
                'coach_payout_account_id' => $account->id,
                'provider' => 'stripe',
                'provider_external_account_id' => $externalId,
            ]);

            $method->type = $payload['object'] ?? null; // bank_account / card
            $method->brand = $payload['brand'] ?? null;
            $method->bank_name = $payload['bank_name'] ?? null;
            $method->last4 = $payload['last4'] ?? null;
            $method->country = $payload['country'] ?? null;
            $method->currency = !empty($payload['currency'])
                ? strtoupper((string) $payload['currency'])
                : null;
            $method->is_default = (bool) ($payload['default_for_currency'] ?? false);
            $method->status = $this->mapMethodStatus($payload);
            $method->raw_provider_payload = $payload;
            $method->save();

            if ($method->is_default) {
                $defaultMethodId = $method->id;
            }
        }

        if (! empty($seenIds)) {
            CoachPayoutMethod::query()
                ->where('coach_payout_account_id', $account->id)
                ->where('provider', 'stripe')
                ->whereNotIn('provider_external_account_id', $seenIds)
                ->delete();
        }

        if ($defaultMethodId) {
            CoachPayoutMethod::query()
                ->where('coach_payout_account_id', $account->id)
                ->where('provider', 'stripe')
                ->where('id', '!=', $defaultMethodId)
                ->update(['is_default' => false]);
        } else {
            $firstActive = CoachPayoutMethod::query()
                ->where('coach_payout_account_id', $account->id)
                ->where('provider', 'stripe')
                ->orderByDesc('id')
                ->first();

            if ($firstActive) {
                CoachPayoutMethod::query()
                    ->where('coach_payout_account_id', $account->id)
                    ->where('provider', 'stripe')
                    ->update(['is_default' => false]);

                $firstActive->update(['is_default' => true]);
            }
        }
    }

    protected function mapStatus(
        bool $payoutsEnabled,
        bool $chargesEnabled,
        array $currentlyDue,
        array $pastDue,
        ?string $disabledReason
    ): string {
        if ($disabledReason) {
            return 'restricted';
        }

        if (! empty($pastDue)) {
            return 'restricted';
        }

        if ($payoutsEnabled) {
            return 'verified';
        }

        if (! empty($currentlyDue)) {
            return 'pending_verification';
        }

        if (! $chargesEnabled && ! $payoutsEnabled) {
            return 'onboarding_required';
        }

        return 'pending';
    }

    protected function mapMethodStatus(array $payload): string
    {
        if (($payload['object'] ?? null) === 'bank_account') {
            $status = strtolower((string) ($payload['status'] ?? 'new'));

            return match ($status) {
                'validated', 'verified' => 'active',
                'verification_failed', 'errored' => 'failed',
                // 'new' => 'active',
                default => 'active',
            };
        }

        return 'active';
    }
}