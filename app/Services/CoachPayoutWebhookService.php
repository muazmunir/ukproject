<?php

namespace App\Services;

use App\Models\CoachPayout;
use Illuminate\Support\Facades\DB;

class CoachPayoutWebhookService
{
    public function __construct(
        private WalletService $walletService
    ) {
    }

    public function handleTransferCreated(object $transfer): void
    {
        $payout = $this->findPayoutFromTransfer($transfer);
        if (!$payout) {
            return;
        }

        $payout->forceFill([
            'provider_transfer_id' => $transfer->id,
            'provider_balance_txn_id' => $transfer->balance_transaction ?? $payout->provider_balance_txn_id,
            'status' => 'transfer_created',
            'meta' => array_merge((array) $payout->meta, [
                'last_webhook' => 'transfer.created',
                'last_webhook_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public function handleTransferUpdated(object $transfer): void
    {
        $payout = $this->findPayoutFromTransfer($transfer);
        if (!$payout) {
            return;
        }

        $payout->forceFill([
            'provider_transfer_id' => $transfer->id,
            'provider_balance_txn_id' => $transfer->balance_transaction ?? $payout->provider_balance_txn_id,
            'meta' => array_merge((array) $payout->meta, [
                'last_webhook' => 'transfer.updated',
                'last_webhook_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public function handlePayoutPaid(object $payoutObject): void
    {
        $payout = $this->findPayoutFromStripePayout($payoutObject);
        if (!$payout) {
            return;
        }

        $payout->forceFill([
            'provider_payout_id' => $payoutObject->id,
            'status' => 'paid',
            'paid_at' => now(),
            'meta' => array_merge((array) $payout->meta, [
                'last_webhook' => 'payout.paid',
                'last_webhook_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    public function handlePayoutFailed(object $payoutObject): void
    {
        $payout = $this->findPayoutFromStripePayout($payoutObject);
        if (!$payout) {
            return;
        }

        DB::transaction(function () use ($payout, $payoutObject) {
            if (!in_array($payout->status, ['paid', 'failed', 'reversed'], true)) {
                $this->walletService->credit(
                    userId: $payout->coachProfile->user_id,
                    amountMinor: (int) $payout->amount_minor,
                    reason: 'coach_payout_failed_return',
                    reservationId: null,
                    paymentId: null,
                    meta: [
                        'coach_payout_id' => $payout->id,
                        'provider_payout_id' => $payoutObject->id,
                    ],
                    currency: $payout->currency ?: 'USD',
                    balanceType: WalletService::BAL_WITHDRAW,
                );
            }

            $payout->forceFill([
                'provider_payout_id' => $payoutObject->id,
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $payoutObject->failure_message ?? 'Stripe payout failed.',
                'meta' => array_merge((array) $payout->meta, [
                    'last_webhook' => 'payout.failed',
                    'last_webhook_at' => now()->toIso8601String(),
                ]),
            ])->save();
        });
    }

    public function handleTransferReversed(object $transfer): void
    {
        $payout = $this->findPayoutFromTransfer($transfer);
        if (!$payout) {
            return;
        }

        DB::transaction(function () use ($payout, $transfer) {
            if (!in_array($payout->status, ['reversed', 'failed'], true)) {
                $this->walletService->credit(
                    userId: $payout->coachProfile->user_id,
                    amountMinor: (int) $payout->amount_minor,
                    reason: 'coach_payout_reversed_return',
                    reservationId: null,
                    paymentId: null,
                    meta: [
                        'coach_payout_id' => $payout->id,
                        'provider_transfer_id' => $transfer->id,
                    ],
                    currency: $payout->currency ?: 'USD',
                    balanceType: WalletService::BAL_WITHDRAW,
                );
            }

            $payout->forceFill([
                'status' => 'reversed',
                'failed_at' => now(),
                'failure_reason' => 'Stripe transfer was reversed.',
                'meta' => array_merge((array) $payout->meta, [
                    'last_webhook' => 'transfer.reversed',
                    'last_webhook_at' => now()->toIso8601String(),
                ]),
            ])->save();
        });
    }

    private function findPayoutFromTransfer(object $transfer): ?CoachPayout
    {
        $metaPayoutId = data_get($transfer, 'metadata.coach_payout_id');

        if ($metaPayoutId) {
            return CoachPayout::with('coachProfile')->find($metaPayoutId);
        }

        return CoachPayout::with('coachProfile')
            ->where('provider_transfer_id', $transfer->id)
            ->first();
    }

    private function findPayoutFromStripePayout(object $stripePayout): ?CoachPayout
    {
        return CoachPayout::with('coachProfile')
            ->where('provider_payout_id', $stripePayout->id)
            ->first();
    }
}