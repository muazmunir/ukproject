<?php

namespace App\Services\PayoutProviders\Payoneer;

use App\Models\CoachPayout;
use App\Models\CoachPayoutAccount;
use App\Models\CoachPayoutMethod;
use App\Models\CoachProfile;
use App\Services\PayoutProviders\PayoutProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PayoneerPayoutProvider implements PayoutProviderInterface
{
    public function __construct(
        protected PayoneerClient $client,
    ) {}

    public function name(): string
    {
        return 'payoneer';
    }

    public function createOrGetAccount(CoachProfile $coachProfile): CoachPayoutAccount
    {
        return DB::transaction(function () use ($coachProfile) {
            $account = $coachProfile->payoutAccounts()
                ->where('provider', 'payoneer')
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if ($account && $account->provider_account_id) {
                return $account;
            }

            if (!$this->client->isConfigured()) {
                if (!$account) {
                    $account = new CoachPayoutAccount();
                    $account->coach_profile_id = $coachProfile->id;
                    $account->provider = 'payoneer';
                    $account->is_default = true;
                }

                $account->status = 'onboarding_required';
                $account->onboarding_started_at = $account->onboarding_started_at ?: now();
                $account->save();

                return $account;
            }

            $user = $coachProfile->user;

            $payload = [
                'client_reference_id' => 'coach_profile_' . $coachProfile->id,
                'email' => $user?->email,
                'first_name' => $user?->first_name ?? Str::before((string) $user?->name, ' '),
                'last_name' => $user?->last_name ?? Str::after((string) $user?->name, ' '),
            ];

            $response = $this->client->registerPayee($payload);

            if (!$account) {
                $account = new CoachPayoutAccount();
                $account->coach_profile_id = $coachProfile->id;
                $account->provider = 'payoneer';
                $account->is_default = true;
            }

            $account->provider_account_id = data_get($response, 'id')
                ?? data_get($response, 'payee_id')
                ?? data_get($response, 'payeeId');

            $account->status = 'onboarding_required';
            $account->charges_enabled = false;
            $account->payouts_enabled = false;
            $account->onboarding_started_at = $account->onboarding_started_at ?: now();
            $account->raw_provider_payload = $response;
            $account->save();

            return $account;
        });
    }

    public function createOnboardingLink(CoachPayoutAccount $account): string
    {
        if (!$this->client->isConfigured()) {
            return route('coach.payouts.settings');
        }

        $response = $this->client->createOnboardingLink($account->provider_account_id, [
            'return_url' => route('coach.payouts.return', ['provider' => 'payoneer']),
        ]);

        return (string) (
            data_get($response, 'url')
            ?? data_get($response, 'onboarding_url')
            ?? route('coach.payouts.settings')
        );
    }

    public function syncAccount(CoachPayoutAccount $account): CoachPayoutAccount
    {
        if (!$account->provider_account_id || !$this->client->isConfigured()) {
            return $account;
        }

        return DB::transaction(function () use ($account) {
            $response = $this->client->getPayee($account->provider_account_id);

            $status = strtolower((string) (
                data_get($response, 'status')
                ?? data_get($response, 'payee_status')
                ?? 'pending'
            ));

            $mappedStatus = match ($status) {
                'approved', 'active', 'verified' => 'verified',
                'pending', 'submitted', 'under_review' => 'pending_verification',
                'restricted', 'blocked' => 'restricted',
                'disabled', 'rejected' => 'disabled',
                default => 'onboarding_required',
            };

            $account->status = $mappedStatus;
            $account->country = data_get($response, 'country', $account->country);
            $account->default_currency = data_get($response, 'currency', $account->default_currency);
            $account->charges_enabled = false;
            $account->payouts_enabled = in_array($mappedStatus, ['verified'], true);
            $account->requirements_currently_due = data_get($response, 'requirements.currently_due', []);
            $account->requirements_eventually_due = data_get($response, 'requirements.eventually_due', []);
            $account->requirements_past_due = data_get($response, 'requirements.past_due', []);
            $account->capabilities = (array) data_get($response, 'capabilities', []);
            $account->raw_provider_payload = $response;

            if ($mappedStatus !== 'onboarding_required' && !$account->onboarding_completed_at) {
                $account->onboarding_completed_at = now();
            }

            if ($mappedStatus === 'verified' && !$account->verified_at) {
                $account->verified_at = now();
            }

            $account->save();

            $this->syncMethodsFromPayload($account, $response);

            return $account->fresh(['payoutMethods']);
        });
    }

    protected function syncMethodsFromPayload(CoachPayoutAccount $account, array $payload): void
    {
        $methods = data_get($payload, 'payout_methods', []);
        if (!is_array($methods)) {
            return;
        }

        $seen = [];

        foreach ($methods as $row) {
            $externalId = data_get($row, 'id') ?? data_get($row, 'method_id');
            if (!$externalId) {
                continue;
            }

            $seen[] = $externalId;

            $method = CoachPayoutMethod::firstOrNew([
                'coach_payout_account_id' => $account->id,
                'provider' => 'payoneer',
                'provider_external_account_id' => $externalId,
            ]);

            $method->type = data_get($row, 'type', 'bank_account');
            $method->brand = data_get($row, 'brand');
            $method->bank_name = data_get($row, 'bank_name');
            $method->last4 = data_get($row, 'last4');
            $method->country = data_get($row, 'country');
            $method->currency = data_get($row, 'currency');
            $method->is_default = (bool) data_get($row, 'is_default', false);
            $method->status = strtolower((string) data_get($row, 'status', 'active'));
            $method->raw_provider_payload = $row;
            $method->save();
        }

        if (!empty($seen)) {
            CoachPayoutMethod::query()
                ->where('coach_payout_account_id', $account->id)
                ->where('provider', 'payoneer')
                ->whereNotIn('provider_external_account_id', $seen)
                ->delete();
        }
    }

    public function payoutsEnabled(CoachPayoutAccount $account): bool
    {
        return (bool) $account->payouts_enabled;
    }

    public function sendPayout(CoachPayout $payout): array
    {
        try {
            $payout->loadMissing('payoutAccount', 'coachProfile');

            $account = $payout->payoutAccount;

            if (!$account || !$account->provider_account_id) {
                return [
                    'ok' => false,
                    'provider' => 'payoneer',
                    'provider_transfer_id' => null,
                    'provider_payout_id' => null,
                    'provider_balance_txn_id' => null,
                    'status' => 'failed',
                    'failure_reason' => 'Missing Payoneer payee account.',
                    'raw' => [],
                ];
            }

            if (!$this->client->isConfigured()) {
                return [
                    'ok' => false,
                    'provider' => 'payoneer',
                    'provider_transfer_id' => null,
                    'provider_payout_id' => null,
                    'provider_balance_txn_id' => null,
                    'status' => 'failed',
                    'failure_reason' => 'Payoneer is not configured yet.',
                    'raw' => [],
                ];
            }

            $payload = [
                'client_reference_id' => 'coach_payout_' . $payout->id,
                'payee_id' => $account->provider_account_id,
                'amount' => [
                    'value' => (int) $payout->amount_minor,
                    'currency' => strtoupper((string) $payout->currency),
                ],
                'description' => 'Coach payout #' . $payout->id,
                'metadata' => [
                    'coach_profile_id' => (string) $payout->coach_profile_id,
                    'coach_payout_id' => (string) $payout->id,
                ],
            ];

            $response = $this->client->createPayout($payload);

            return [
                'ok' => true,
                'provider' => 'payoneer',
                'provider_transfer_id' => data_get($response, 'transfer_id'),
                'provider_payout_id' => data_get($response, 'id')
                    ?? data_get($response, 'payout_id'),
                'provider_balance_txn_id' => null,
                'status' => 'paid',
                'failure_reason' => null,
                'raw' => $response,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'provider' => 'payoneer',
                'provider_transfer_id' => null,
                'provider_payout_id' => null,
                'provider_balance_txn_id' => null,
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'raw' => [],
            ];
        }
    }
}