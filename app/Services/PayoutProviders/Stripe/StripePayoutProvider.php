<?php

namespace App\Services\PayoutProviders\Stripe;

use App\Models\CoachPayout;
use App\Models\CoachPayoutAccount;
use App\Models\CoachProfile;
use App\Services\PayoutProviders\PayoutProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripePayoutProvider implements PayoutProviderInterface
{
    public function __construct(
        protected StripeConnectAccountService $accounts,
        protected StripeTransferClient $transfers,
    ) {
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createOrGetAccount(CoachProfile $coachProfile): CoachPayoutAccount
    {
        return $this->accounts->createOrGetExpressAccount($coachProfile);
    }

    public function createOnboardingLink(CoachPayoutAccount $account): string
    {
        return $this->accounts->createOnboardingLink($account);
    }

    public function syncAccount(CoachPayoutAccount $account): CoachPayoutAccount
    {
        return $this->accounts->syncStripeAccount($account);
    }

    public function payoutsEnabled(CoachPayoutAccount $account): bool
    {
        return (bool) $account->payouts_enabled
            && !empty($account->provider_account_id)
            && strtolower((string) $account->provider) === 'stripe';
    }

    public function sendPayout(CoachPayout $payout): array
    {
        try {
            $payout->loadMissing([
                'payoutAccount',
                'coachProfile.user',
            ]);

            $account = $payout->payoutAccount;

            if (! $account) {
                return $this->failedResponse('Stripe payout account was not found.');
            }

            if (strtolower((string) $account->provider) !== 'stripe') {
                return $this->failedResponse('Invalid payout provider for this payout.');
            }

            if (! $account->provider_account_id) {
                return $this->failedResponse('Missing Stripe connected account ID.');
            }

            if (! $account->payouts_enabled) {
                return $this->failedResponse('Stripe payouts are not enabled on this connected account.');
            }

            if ((int) $payout->amount_minor <= 0) {
                return $this->failedResponse('Invalid payout amount.');
            }

            $currency = strtoupper((string) ($payout->currency ?: $account->default_currency ?: 'USD'));

            Log::info('Stripe payout transfer starting', [
                'coach_payout_id' => $payout->id,
                'coach_profile_id' => $payout->coach_profile_id,
                'stripe_account_id' => $account->provider_account_id,
                'amount_minor' => (int) $payout->amount_minor,
                'currency' => $currency,
            ]);

            $transfer = $this->transfers->transferToConnectedAccount(
                stripeAccountId: $account->provider_account_id,
                amountMinor: (int) $payout->amount_minor,
                currency: $currency,
                transferGroup: 'coach_payout_' . $payout->id,
                metadata: [
                    'coach_payout_id' => (string) $payout->id,
                    'coach_profile_id' => (string) $payout->coach_profile_id,
                    'provider' => 'stripe',
                ],
            );

            Log::info('Stripe payout transfer created', [
                'coach_payout_id' => $payout->id,
                'provider_transfer_id' => $transfer['id'] ?? null,
                'provider_balance_txn_id' => $transfer['balance_transaction'] ?? null,
            ]);

            return [
                'ok' => true,
                'provider' => 'stripe',
                'provider_transfer_id' => $transfer['id'] ?? null,
                'provider_payout_id' => null,
                'provider_balance_txn_id' => $transfer['balance_transaction'] ?? null,
                'status' => 'paid',
                'failure_reason' => null,
                'raw' => $transfer,
            ];
        } catch (Throwable $e) {
            report($e);

            Log::error('Stripe payout transfer failed', [
                'coach_payout_id' => $payout->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return $this->failedResponse($e->getMessage());
        }
    }

    protected function failedResponse(string $message): array
    {
        return [
            'ok' => false,
            'provider' => 'stripe',
            'provider_transfer_id' => null,
            'provider_payout_id' => null,
            'provider_balance_txn_id' => null,
            'status' => 'failed',
            'failure_reason' => $message,
            'raw' => [],
        ];
    }
}