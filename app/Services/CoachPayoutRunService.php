<?php

namespace App\Services;

use App\Models\CoachPayout;
use App\Models\CoachProfile;
use App\Models\PayoutRun;
use App\Services\PayoutProviders\PayoutProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CoachPayoutRunService
{
    public function __construct(
        private WalletService $walletService,
        private PayoutProviderRegistry $providerRegistry,
    ) {
    }

    public function run(array $options = []): PayoutRun
    {
        $provider = strtolower((string) ($options['provider'] ?? 'stripe'));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $scheduledFor = ! empty($options['date'])
            ? now()->parse($options['date'])
            : now();

        Log::info('Coach payout run started', [
            'provider' => $provider,
            'dry_run' => $dryRun,
            'scheduled_for' => (string) $scheduledFor,
            'options' => $options,
        ]);

        $run = PayoutRun::create([
            'provider' => $provider,
            'run_key' => $this->makeRunKey($provider, $scheduledFor),
            'scheduled_for' => $scheduledFor,
            'started_at' => now(),
            'status' => 'running',
            'notes' => $dryRun ? 'Dry run mode enabled.' : null,
        ]);

        $successCount = 0;
        $failedCount = 0;
        $totalAmount = 0;
        $totalCoaches = 0;

        $providerService = $this->providerRegistry->for($provider);

        $profiles = CoachProfile::query()
            ->with([
                'user',
                'payoutAccounts.payoutMethods',
            ])
            ->where('application_status', 'approved')
            ->where('can_receive_payouts', true)
            ->get();

        Log::info('Coach payout eligible profiles loaded', [
            'run_id' => $run->id,
            'provider' => $provider,
            'profile_count' => $profiles->count(),
        ]);

        foreach ($profiles as $coachProfile) {
            $user = $coachProfile->user;
            $payoutAccount = $this->resolvePayoutAccount($coachProfile, $provider);

            Log::info('Coach payout evaluating profile', [
                'run_id' => $run->id,
                'provider' => $provider,
                'coach_profile_id' => $coachProfile->id,
                'user_id' => $user?->id,
                'has_user' => (bool) $user,
                'has_payout_account' => (bool) $payoutAccount,
            ]);

            if (! $user || ! $payoutAccount) {
                Log::warning('Coach payout skipped: missing user or payout account', [
                    'run_id' => $run->id,
                    'provider' => $provider,
                    'coach_profile_id' => $coachProfile->id,
                    'user_id' => $user?->id,
                ]);
                continue;
            }

            if (! $providerService->payoutsEnabled($payoutAccount)) {
                Log::warning('Coach payout skipped: payouts not enabled', [
                    'run_id' => $run->id,
                    'provider' => $provider,
                    'coach_profile_id' => $coachProfile->id,
                    'user_id' => $user->id,
                    'payout_account_id' => $payoutAccount->id,
                ]);
                continue;
            }

            if ($payoutAccount->payoutMethods->isEmpty()) {
                Log::warning('Coach payout skipped: no payout methods', [
                    'run_id' => $run->id,
                    'provider' => $provider,
                    'coach_profile_id' => $coachProfile->id,
                    'user_id' => $user->id,
                    'payout_account_id' => $payoutAccount->id,
                ]);
                continue;
            }

            $currency = strtoupper($payoutAccount->default_currency ?: 'USD');

            try {
                $prepared = DB::transaction(function () use (
                    $run,
                    $provider,
                    $coachProfile,
                    $payoutAccount,
                    $user,
                    $currency,
                    $dryRun
                ) {
                    $existingOpen = CoachPayout::query()
                        ->where('coach_profile_id', $coachProfile->id)
                        ->where('provider', $provider)
                        ->whereIn('status', ['pending', 'processing', 'transfer_created', 'payout_pending'])
                        ->lockForUpdate()
                        ->exists();

                    if ($existingOpen) {
                        throw new \RuntimeException(sprintf(
                            'Open %s payout already exists for this coach.',
                            ucfirst($provider)
                        ));
                    }

                    $amountMinor = $this->currentWithdrawableBalance($coachProfile->user_id, $currency);

                    if ($amountMinor <= 0) {
                        return null;
                    }

                    $sourceCredits = $this->walletCreditSnapshot($coachProfile->user_id, $currency);

                    $payout = CoachPayout::create([
                        'payout_batch_id' => $run->id,
                        'coach_profile_id' => $coachProfile->id,
                        'coach_payout_account_id' => $payoutAccount->id,
                        'provider' => $provider,
                        'currency' => $currency,
                        'amount_minor' => $amountMinor,
                        'reservation_count' => 0,
                        'status' => $dryRun ? 'pending' : 'processing',
                        'meta' => [
                            'created_from' => 'wallet_withdrawable_balance',
                            'wallet_basis' => true,
                            'user_id' => $user->id,
                            'coach_profile_id' => $coachProfile->id,
                            'payout_run_id' => $run->id,
                            'provider' => $provider,
                            'dry_run' => $dryRun,
                            'balance_snapshot' => [
                                'currency' => $currency,
                                'withdrawable_balance_minor' => $amountMinor,
                            ],
                            'source_wallet_transaction_ids' => $sourceCredits,
                        ],
                    ]);

                    if (! $dryRun) {
                        $newBalance = $this->walletService->debit(
                            $coachProfile->user_id,
                            $amountMinor,
                            'coach_payout_withdrawal',
                            null,
                            null,
                            [
                                'provider' => $provider,
                                'coach_payout_id' => $payout->id,
                                'payout_run_id' => $run->id,
                                'wallet_basis' => true,
                                'source_wallet_transaction_ids' => $sourceCredits,
                            ],
                            $currency,
                            WalletService::BAL_WITHDRAW,
                            true
                        );

                        Log::info('Coach payout wallet debited', [
                            'run_id' => $run->id,
                            'provider' => $provider,
                            'coach_payout_id' => $payout->id,
                            'coach_profile_id' => $coachProfile->id,
                            'user_id' => $user->id,
                            'debited_minor' => $amountMinor,
                            'new_withdrawable_balance_minor' => $newBalance,
                            'currency' => $currency,
                        ]);
                    }

                    return [
                        'payout_id' => $payout->id,
                        'amount_minor' => $amountMinor,
                        'coach_user_id' => (int) $coachProfile->user_id,
                        'currency' => $currency,
                        'source_wallet_transaction_ids' => $sourceCredits,
                    ];
                });

                if (! $prepared) {
                    continue;
                }

                $payout = CoachPayout::with(['payoutAccount', 'coachProfile'])
                    ->findOrFail($prepared['payout_id']);

                if ($dryRun) {
                    $successCount++;
                    $totalAmount += (int) $prepared['amount_minor'];
                    $totalCoaches++;
                    continue;
                }

                $result = $providerService->sendPayout($payout);

                DB::transaction(function () use ($payout, $result, $prepared, $provider) {
                    if ($result['ok'] ?? false) {
                        $providerStatus = $result['status'] ?? 'paid';

                        $payout->update([
                            'status' => match ($providerStatus) {
                                'pending' => 'payout_pending',
                                'failed' => 'failed',
                                default => 'paid',
                            },
                            'provider_transfer_id' => $result['provider_transfer_id'] ?? null,
                            'provider_payout_id' => $result['provider_payout_id'] ?? null,
                            'provider_balance_txn_id' => $result['provider_balance_txn_id'] ?? null,
                            'paid_at' => $providerStatus === 'failed' ? null : now(),
                            'failed_at' => $providerStatus === 'failed' ? now() : null,
                            'failure_reason' => $result['failure_reason'] ?? null,
                            'meta' => array_merge((array) $payout->meta, [
                                'provider' => $provider,
                                'provider_response' => $result['raw'] ?? [],
                                'sent_at' => now()->toIso8601String(),
                            ]),
                        ]);
                    } else {
                        $payout->update([
                            'status' => 'failed',
                            'failed_at' => now(),
                            'failure_reason' => $result['failure_reason'] ?? ucfirst($provider) . ' payout failed.',
                            'meta' => array_merge((array) $payout->meta, [
                                'provider' => $provider,
                                'provider_response' => $result['raw'] ?? [],
                                'failed_at_iso' => now()->toIso8601String(),
                            ]),
                        ]);

                        $this->walletService->creditWithdrawable(
                            $prepared['coach_user_id'],
                            (int) $prepared['amount_minor'],
                            'coach_payout_reversal',
                            null,
                            null,
                            [
                                'provider' => $provider,
                                'coach_payout_id' => $payout->id,
                                'reason' => 'provider_payout_failed',
                                'wallet_basis' => true,
                                'source_wallet_transaction_ids' => $prepared['source_wallet_transaction_ids'] ?? [],
                            ],
                            $prepared['currency']
                        );
                    }
                });

                if ($result['ok'] ?? false) {
                    $successCount++;
                    $totalAmount += (int) $prepared['amount_minor'];
                    $totalCoaches++;
                } else {
                    $failedCount++;
                }
            } catch (Throwable $e) {
                Log::error('Coach payout batch failed with exception', [
                    'run_id' => $run->id,
                    'provider' => $provider,
                    'coach_profile_id' => $coachProfile->id ?? null,
                    'user_id' => $user->id ?? null,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $failedCount++;
                report($e);
            }
        }

        $run->update([
            'finished_at' => now(),
            'status' => $failedCount > 0
                ? ($successCount > 0 ? 'partial' : 'failed')
                : 'completed',
            'total_coaches' => $totalCoaches,
            'total_amount_minor' => $totalAmount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        Log::info('Coach payout run finished', [
            'run_id' => $run->id,
            'provider' => $provider,
            'status' => $run->fresh()->status,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_coaches' => $totalCoaches,
            'total_amount_minor' => $totalAmount,
        ]);

        return $run->fresh();
    }

    private function currentWithdrawableBalance(int $coachUserId, string $currency): int
    {
        $credits = (int) DB::table('wallet_transactions')
            ->where('user_id', $coachUserId)
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->where('currency', strtoupper($currency))
            ->where('type', 'credit')
            ->sum('amount_minor');

        $debits = (int) DB::table('wallet_transactions')
            ->where('user_id', $coachUserId)
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->where('currency', strtoupper($currency))
            ->where('type', 'debit')
            ->sum('amount_minor');

        return max(0, $credits - $debits);
    }

    private function walletCreditSnapshot(int $coachUserId, string $currency): array
    {
        return DB::table('wallet_transactions')
            ->where('user_id', $coachUserId)
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->where('currency', strtoupper($currency))
            ->where('type', 'credit')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function resolvePayoutAccount(CoachProfile $coachProfile, string $provider)
    {
        return $coachProfile->payoutAccounts->first(function ($account) use ($provider) {
            return strtolower((string) $account->provider) === strtolower($provider)
                && (bool) $account->is_default;
        });
    }

    private function makeRunKey(string $provider, $scheduledFor): string
    {
        return implode('-', [
            'payout-run',
            strtolower($provider),
            $scheduledFor->format('Ymd-His'),
            Str::lower(Str::random(6)),
        ]);
    }
}