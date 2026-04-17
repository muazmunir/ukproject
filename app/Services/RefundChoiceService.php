<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundChoiceService
{
    public function process(Reservation $reservation, string $method, ?int $byUserId = null): array
    {
        $method = strtolower(trim($method));
        if (!in_array($method, ['wallet_credit', 'original_payment'], true)) {
            $method = 'wallet_credit';
        }

        // -----------------------------
        // phase 1: prepare refund record + split
        // -----------------------------
        $prepared = DB::transaction(function () use ($reservation, $method, $byUserId) {
            $res = Reservation::lockForUpdate()
                ->with(['payments', 'refunds', 'latestRefund'])
                ->find($reservation->id);

            if (!$res) {
                return ['ok' => false, 'message' => 'Reservation not found'];
            }

            $state = strtolower((string) ($res->refund_status ?? 'none'));
            if (!in_array($state, ['pending_choice', 'failed', 'partial'], true)) {
                return ['ok' => false, 'message' => "Refund cannot be processed from status: {$state}"];
            }

            if (in_array(strtolower((string) ($res->payout_status ?? '')), ['queued', 'sent', 'paid'], true)) {
    return ['ok' => false, 'message' => 'Refund cannot be processed after payout has already been queued or sent.'];
}

            /** @var Payment|null $payment */
            $payment = $res->payments
                ->whereIn('provider', ['stripe', 'paypal'])
                ->where('status', 'succeeded')
                ->sortByDesc('id')
                ->first();

            $clientId  = (int) $res->client_id;
            $paymentId = $payment?->id;
            $provider  = $payment?->provider;
            $currency  = $res->currency ?? ($payment?->currency ?? 'USD');

            $walletUsedMinor   = (int) ($res->wallet_platform_credit_used_minor ?? 0);
            $externalPaidMinor = (int) $res->payments
                ->whereIn('provider', ['stripe', 'paypal'])
                ->where('status', 'succeeded')
                ->sum('amount_total');
            $feesMinor = (int) ($res->fees_minor ?? 0);

            if ($externalPaidMinor <= 0 && $walletUsedMinor > 0 && $method === 'original_payment') {
                return [
                    'ok' => false,
                    'message' => 'This booking was paid fully with wallet credit, so it can only be refunded to wallet.',
                ];
            }

            $cancelledBy = strtolower((string) ($res->cancelled_by ?? ''));

            $feesRefundable =
                in_array($cancelledBy, ['coach', 'admin', 'system'], true)
                || (
                    (int) ($res->platform_earned_minor ?? 0) === 0
                    && (int) ($res->refund_total_minor ?? 0) === (int) ($res->total_minor ?? 0)
                );

            $requestedRefundMinor = 0;
            $walletPart = 0;
            $externalPart = 0;
            $splitMeta = [];

            /** @var Refund|null $latestRefund */
            $latestRefund = $res->latestRefund ?? $res->refunds->sortByDesc('id')->first();

            // Retry path: retry only the unresolved amount from the latest refund
            if ($latestRefund && in_array($state, ['partial', 'failed'], true)) {
                $remainingWalletMinor = in_array($latestRefund->wallet_status, ['succeeded', 'not_applicable'], true)
                    ? 0
                    : (int) ($latestRefund->wallet_amount_minor ?? 0);

                $remainingExternalMinor = in_array($latestRefund->external_status, ['succeeded', 'not_applicable'], true)
                    ? 0
                    : (int) ($latestRefund->external_amount_minor ?? 0);

                $requestedRefundMinor = $remainingWalletMinor + $remainingExternalMinor;

                if ($requestedRefundMinor <= 0) {
                    return ['ok' => false, 'message' => 'No refundable amount remains to retry'];
                }

                // Retry unresolved only
                $walletPart = $remainingWalletMinor;
                $externalPart = $remainingExternalMinor;

                $splitMeta = [
                    'type' => 'retry_remaining_only',
                    'method' => $method,
                    'retry_from_refund_id' => $latestRefund->id,
                    'remaining_wallet_minor' => $remainingWalletMinor,
                    'remaining_external_minor' => $remainingExternalMinor,
                    'fees_refundable' => $feesRefundable,
                    'cancelled_by' => $res->cancelled_by,
                ];
            } else {
                // First-time / pending-choice flow
                $requestedRefundMinor = (int) ($res->refund_total_minor ?? 0);

                if ($requestedRefundMinor <= 0) {
                    return ['ok' => false, 'message' => 'No refundable amount'];
                }

                [$walletPart, $externalPart, $splitMeta] = app(RefundSplitService::class)->computeRefundSplit(
                    $walletUsedMinor,
                    $externalPaidMinor,
                    $feesMinor,
                    $requestedRefundMinor,
                    $method,
                    $feesRefundable
                );
            }

            if ($method === 'original_payment' && $externalPart <= 0 && $walletPart > 0) {
                return [
                    'ok' => false,
                    'message' => 'No remaining external amount is available to refund back to the original payment method.',
                ];
            }

            $actualRefundMinor = (int) ($walletPart + $externalPart);

            if ($actualRefundMinor <= 0) {
                $res->refund_status = 'failed';
                $res->refund_error = 'Nothing refundable after applying rules.';
                $res->settlement_status = 'refund_pending';
                $res->save();

                return ['ok' => false, 'message' => 'Nothing refundable after applying rules.'];
            }

            if ($externalPart > 0 && !$payment && $method === 'original_payment') {
                $res->refund_status = 'failed';
                $res->refund_error = 'Missing payment record for external refund.';
                $res->settlement_status = 'refund_pending';
                $res->save();

                return ['ok' => false, 'message' => 'Missing payment record for external refund'];
            }

            // Always create a NEW refund row per attempt
            $refund = new Refund();

            $previousAttemptsCount = $res->refunds()->count();
            $attemptNo = $previousAttemptsCount + 1;

            $refund->fill([
                'reservation_id'         => $res->id,
                'payment_id'             => $paymentId,
                'requested_by_user_id'   => $byUserId,
                'provider'               => $provider,
                'method'                 => $method,
                'requested_amount_minor' => $requestedRefundMinor,
                'actual_amount_minor'    => $actualRefundMinor,
                'wallet_amount_minor'    => $walletPart,
                'external_amount_minor'  => $externalPart,
                'currency'               => $currency,
                'status'                 => 'processing',
                'wallet_status'          => $walletPart > 0 ? 'pending' : 'not_applicable',
                'external_status'        => $externalPart > 0 ? 'pending' : 'not_applicable',
                'provider_order_id'      => $payment?->provider_order_id,
                'provider_capture_id'    => $payment?->provider_capture_id,
                'failure_reason'         => null,
                'requested_at'           => now(),
                'processed_at'           => null,
               'meta'                   => [
    'attempt_no'           => $attemptNo,
    'split'                => $splitMeta,
    'fees_refundable'      => $feesRefundable,
    'cancelled_by'         => $res->cancelled_by,
    'requested_refund'     => $requestedRefundMinor,
    'actual_refund'        => $actualRefundMinor,
    'wallet_used_minor'    => $walletUsedMinor,
    'refunded_to_wallet_minor'   => 0,
    'refunded_to_original_minor' => 0,
    'external_paid_minor'  => $externalPaidMinor,
    'platform_fee_refund_requested_minor' => (int) ($res->platform_fee_refund_requested_minor ?? 0),
    'retry'                => $latestRefund && in_array($state, ['partial', 'failed'], true),
    'retry_from_refund_id' => $latestRefund && in_array($state, ['partial', 'failed'], true)
        ? $latestRefund->id
        : null,
],
            ]);

            $refund->idempotency_key = 'refund:res:' . $res->id . ':attempt:' . $attemptNo . ':' . (string) Str::ulid();
            $refund->save();

            // reservation = summary/mirror only (attempt state for now)
          $res->refund_method = $method;
$res->refund_status = 'processing';
$res->refund_error = null;
$res->refund_requested_at = $res->refund_requested_at ?? now();
$res->settlement_status = 'refund_pending';
$res->earnings_status = 'blocked';
$res->payout_status = 'blocked';
$res->earnings_released_at = null;
$res->coach_payout_id = null;
$res->payout_queued_at = null;
$res->payout_sent_at = null;
$res->payout_provider = null;
$res->provider_transfer_id = null;
$res->provider_payout_id = null;
$res->save();

            return [
                'ok'             => true,
                'refund_id'      => $refund->id,
                'reservation_id' => $res->id,
                'payment_id'     => $paymentId,
                'client_id'      => $clientId,
            ];
        });

        if (!($prepared['ok'] ?? false)) {
            return $prepared;
        }

        // -----------------------------
        // phase 2: process refund outside long DB transaction
        // -----------------------------
        return $this->executePreparedRefund(
            (int) $prepared['refund_id'],
            (int) $prepared['reservation_id'],
            (int) $prepared['client_id']
        );
    }

    private function executePreparedRefund(int $refundId, int $reservationId, int $clientId): array
    {
        /** @var Refund|null $refund */
        $refund = Refund::with(['payment', 'reservation'])->find($refundId);
        if (!$refund) {
            return ['ok' => false, 'message' => 'Refund record not found'];
        }

        $res = $refund->reservation;
        if (!$res) {
            return ['ok' => false, 'message' => 'Reservation not found'];
        }

        $payment  = $refund->payment;
        $currency = $refund->currency ?? 'USD';

        // -----------------------------
        // 1) wallet-funded part always goes back to wallet
        // -----------------------------
        if ($refund->wallet_status === 'pending' && (int) $refund->wallet_amount_minor > 0) {
            try {
                $walletTxn = app(WalletService::class)->creditPlatform(
                    $clientId,
                    (int) $refund->wallet_amount_minor,
                    'refund_processed_wallet',
                    $res->id,
                    $payment?->id,
                    [
                        'refund_id'     => $refund->id,
                        'refund_method' => $refund->method,
                    ],
                    $currency
                );

                DB::transaction(function () use ($refund, $walletTxn) {
                    $freshRefund = Refund::lockForUpdate()->find($refund->id);
                    if ($freshRefund && $freshRefund->wallet_status === 'pending') {
                        $meta = (array) $freshRefund->meta;
                        $meta['wallet_credit'] = [
                            'transaction_id' => is_object($walletTxn) ? ($walletTxn->id ?? null) : null,
                            'status'         => 'succeeded',
                            'processed_at'   => now()->toDateTimeString(),
                        ];

                        $freshRefund->wallet_status = 'succeeded';
                        $freshRefund->meta = $meta;
                        $freshRefund->save();
                    }
                });
            } catch (\Throwable $e) {
                DB::transaction(function () use ($refund, $e) {
                    $freshRefund = Refund::lockForUpdate()->find($refund->id);
                    if ($freshRefund) {
                        $meta = (array) $freshRefund->meta;
                        $meta['wallet_credit'] = [
                            'status'       => 'failed',
                            'error'        => $e->getMessage(),
                            'processed_at' => now()->toDateTimeString(),
                        ];

                        $freshRefund->wallet_status = 'failed';
                        $freshRefund->failure_reason = 'Wallet refund failed: ' . $e->getMessage();
                        $freshRefund->meta = $meta;
                        $freshRefund->save();
                    }
                });
            }
        }

        // -----------------------------
        // 2) external-funded part
        // -----------------------------
        if ($refund->external_status === 'pending' && (int) $refund->external_amount_minor > 0) {
            if ($refund->method === 'wallet_credit') {
                try {
                    $walletTxn = app(WalletService::class)->creditPlatform(
                        $clientId,
                        (int) $refund->external_amount_minor,
                        'refund_processed_wallet_external_to_wallet',
                        $res->id,
                        $payment?->id,
                        [
                            'refund_id' => $refund->id,
                            'note'      => 'External portion credited to wallet by user choice',
                        ],
                        $currency
                    );

                    DB::transaction(function () use ($refund, $walletTxn) {
                        $freshRefund = Refund::lockForUpdate()->find($refund->id);
                        if ($freshRefund && $freshRefund->external_status === 'pending') {
                            $meta = (array) $freshRefund->meta;
                            $meta['external_wallet_credit'] = [
                                'transaction_id' => is_object($walletTxn) ? ($walletTxn->id ?? null) : null,
                                'status'         => 'succeeded',
                                'processed_at'   => now()->toDateTimeString(),
                            ];

                            $freshRefund->external_status = 'succeeded';
                            $freshRefund->meta = $meta;
                            $freshRefund->save();
                        }
                    });
                } catch (\Throwable $e) {
                    DB::transaction(function () use ($refund, $e) {
                        $freshRefund = Refund::lockForUpdate()->find($refund->id);
                        if ($freshRefund) {
                            $meta = (array) $freshRefund->meta;
                            $meta['external_wallet_credit'] = [
                                'status'       => 'failed',
                                'error'        => $e->getMessage(),
                                'processed_at' => now()->toDateTimeString(),
                            ];

                            $freshRefund->external_status = 'failed';
                            $freshRefund->failure_reason = 'Wallet crediting of external refund failed: ' . $e->getMessage();
                            $freshRefund->meta = $meta;
                            $freshRefund->save();
                        }
                    });
                }
            } else {
                if (!$payment) {
                    DB::transaction(function () use ($refund) {
                        $freshRefund = Refund::lockForUpdate()->find($refund->id);
                        if ($freshRefund) {
                            $freshRefund->external_status = 'failed';
                            $freshRefund->failure_reason = 'Original payment refund failed: Missing external payment.';
                            $freshRefund->save();
                        }
                    });
                } else {
                    $result = app(PaymentRefundService::class)->refundToOriginal(
                        $payment,
                        (int) $refund->external_amount_minor,
                        'reservation_refund'
                    );

                    $ok               = (bool) ($result['ok'] ?? false);
                    $providerRefundId = (string) ($result['provider_refund_id'] ?? '');
                    $providerStatus   = (string) ($result['provider_status'] ?? '');
                    $err              = (string) ($result['error'] ?? '');
                    $refundMeta       = (array) ($result['meta'] ?? []);

                    DB::transaction(function () use (
                        $refund,
                        $payment,
                        $ok,
                        $providerRefundId,
                        $providerStatus,
                        $refundMeta,
                        $err
                    ) {
                        $freshRefund = Refund::lockForUpdate()->find($refund->id);
                        $freshPayment = Payment::lockForUpdate()->find($payment->id);

                        if (!$freshRefund || !$freshPayment) {
                            return;
                        }

                        $meta = (array) $freshRefund->meta;
                        $meta['provider_refund_status'] = $providerStatus ?: null;
                        $meta['provider_refund_meta'] = $refundMeta;

                        if ($ok) {
                            $normalizedExternalStatus = in_array($providerStatus, ['pending', 'requires_action'], true)
                                ? 'pending'
                                : 'succeeded';

                            $freshRefund->external_status = $normalizedExternalStatus;
                            $freshRefund->provider_refund_id = $providerRefundId ?: $freshRefund->provider_refund_id;
                            $freshRefund->meta = $meta;

                            $freshPayment->provider_refund_id = $providerRefundId ?: $freshPayment->provider_refund_id;

                            if ($normalizedExternalStatus === 'succeeded') {
                                $freshPayment->refunded_minor = (int) ($freshPayment->refunded_minor ?? 0)
                                    + (int) $freshRefund->external_amount_minor;
                                $freshPayment->refund_status = 'succeeded';
                                $freshPayment->refunded_at = $freshPayment->refunded_at ?? now();
                                $freshPayment->markRefundAggregateStatus();
                            } else {
                                $freshPayment->refund_status = 'pending';
                            }

                            $freshPayment->save();
                        } else {
                            $freshRefund->external_status = 'failed';
                            $freshRefund->failure_reason = 'Original payment refund failed: ' . ($err ?: 'Refund failed');
                            $freshRefund->meta = $meta;

                            $freshPayment->refund_status = 'failed';
                            $freshPayment->save();
                        }

                        $freshRefund->save();
                    });
                }
            }
        }

        // -----------------------------
        // 3) finalize overall state
        // -----------------------------
        return DB::transaction(function () use ($refundId, $reservationId) {
            $refund = Refund::lockForUpdate()->find($refundId);
            $res = Reservation::lockForUpdate()->find($reservationId);

            if (!$refund || !$res) {
                return ['ok' => false, 'message' => 'Refund finalization failed'];
            }

            $walletSuccess   = in_array($refund->wallet_status, ['succeeded', 'not_applicable'], true);
            $externalSuccess = in_array($refund->external_status, ['succeeded', 'not_applicable'], true);

            $hasPending = in_array($refund->wallet_status, ['pending'], true)
                || in_array($refund->external_status, ['pending'], true);

            $hasFailure = in_array($refund->wallet_status, ['failed'], true)
                || in_array($refund->external_status, ['failed'], true);

$succeededWalletMinor = $refund->wallet_status === 'succeeded'
    ? (int) $refund->wallet_amount_minor
    : 0;

$succeededExternalMinor = $refund->external_status === 'succeeded'
    ? (int) $refund->external_amount_minor
    : 0;

$refundedToWalletMinor = $refund->method === 'wallet_credit'
    ? ($succeededWalletMinor + $succeededExternalMinor)
    : $succeededWalletMinor;

$refundedToOriginalMinor = $refund->method === 'original_payment'
    ? $succeededExternalMinor
    : 0;

$succeededTotalMinor = $succeededWalletMinor + $succeededExternalMinor;

// actual platform fee refunded in this completed attempt
$requestedPlatformFeeRefundMinor = (int) ($res->platform_fee_refund_requested_minor ?? 0);

// clamp fee refund to amount actually refunded
$actualPlatformFeeRefundedMinor = min($requestedPlatformFeeRefundMinor, $succeededTotalMinor);

            $succeededTotalMinor = $succeededWalletMinor + $succeededExternalMinor;

          if ($walletSuccess && $externalSuccess) {
    $refund->status = 'succeeded';
    $refund->processed_at = now();

    $res->refund_status = 'succeeded';
    $res->refund_processed_at = now();
    $res->refund_error = null;

    $res->refund_wallet_minor = $succeededWalletMinor;
    $res->refund_external_minor = $succeededExternalMinor;
    $res->refund_total_minor = $succeededTotalMinor;

    // final actual fee refunded
    $res->platform_fee_refunded_minor = $actualPlatformFeeRefundedMinor;

    $totalMinor = (int) ($res->total_minor ?? 0);
    $isFullRefund = $succeededTotalMinor > 0 && $totalMinor > 0 && $succeededTotalMinor === $totalMinor;

    $refund->refunded_to_wallet_minor = $refundedToWalletMinor;
    $refund->refunded_to_original_minor = $refundedToOriginalMinor;
    $res->settlement_status = $isFullRefund ? 'refunded' : 'refunded_partial';
$res->earnings_status = 'refunded';
$res->payout_status = 'blocked';
$res->earnings_released_at = null;
$res->coach_payout_id = null;
$res->payout_queued_at = null;
$res->payout_sent_at = null;
$res->payout_provider = null;
$res->provider_transfer_id = null;
$res->provider_payout_id = null;

    $refund->save();
    $res->save();

                return [
                    'ok'      => true,
                    'message' => $this->successMessage($res, $refund),
                ];
            }

           if ($hasPending) {
    $refund->status = 'processing';

    $res->refund_status = 'processing';
    $res->refund_error = null;
    $res->settlement_status = 'refund_pending';

    $res->refund_wallet_minor = $succeededWalletMinor;
    $res->refund_external_minor = $succeededExternalMinor;
    $res->refund_total_minor = $succeededTotalMinor;

    // whatever fee portion has actually succeeded so far
    $res->platform_fee_refunded_minor = $actualPlatformFeeRefundedMinor;

    $refund->refunded_to_wallet_minor = $refundedToWalletMinor;
    $refund->refunded_to_original_minor = $refundedToOriginalMinor;


    $res->earnings_status = 'blocked';
$res->payout_status = 'blocked';
$res->earnings_released_at = null;
$res->coach_payout_id = null;
$res->payout_queued_at = null;
$res->payout_sent_at = null;
$res->payout_provider = null;
$res->provider_transfer_id = null;
$res->provider_payout_id = null;
    $refund->save();
    $res->save();

                return [
                    'ok'      => true,
                    'message' => 'Refund is being processed.',
                    'pending' => true,
                ];
            }

if ($hasFailure && ($walletSuccess || $externalSuccess)) {
    $refund->status = 'partial';
    $refund->processed_at = now();

    $res->refund_status = 'partial';
    $res->refund_error = $refund->failure_reason;
    $res->settlement_status = 'refund_pending';

    $res->refund_wallet_minor = $succeededWalletMinor;
    $res->refund_external_minor = $succeededExternalMinor;
    $res->refund_total_minor = $succeededTotalMinor;

    // partial real fee refund so far
    $res->platform_fee_refunded_minor = $actualPlatformFeeRefundedMinor;

    $refund->refunded_to_wallet_minor = $refundedToWalletMinor;
    $refund->refunded_to_original_minor = $refundedToOriginalMinor;

$res->earnings_status = 'blocked';
$res->payout_status = 'blocked';
$res->earnings_released_at = null;
$res->coach_payout_id = null;
$res->payout_queued_at = null;
$res->payout_sent_at = null;
$res->payout_provider = null;
$res->provider_transfer_id = null;
$res->provider_payout_id = null;

    $refund->save();
    $res->save();

                return [
                    'ok'      => false,
                    'message' => 'Refund partially completed. Manual review or retry is needed.',
                    'partial' => true,
                ];
            }

           $refund->status = 'failed';
$refund->processed_at = now();

$res->refund_status = 'failed';
$res->refund_error = $refund->failure_reason ?: 'Refund failed';
$res->settlement_status = 'refund_pending';

$res->refund_wallet_minor = 0;
$res->refund_external_minor = 0;
$res->refund_total_minor = 0;

// no actual fee refunded if whole attempt failed
$res->platform_fee_refunded_minor = 0;

$refund->refunded_to_wallet_minor = 0;
$refund->refunded_to_original_minor = 0;


$res->earnings_status = 'blocked';
$res->payout_status = 'blocked';
$res->earnings_released_at = null;
$res->coach_payout_id = null;
$res->payout_queued_at = null;
$res->payout_sent_at = null;
$res->payout_provider = null;
$res->provider_transfer_id = null;
$res->provider_payout_id = null;

$refund->save();
$res->save();

            return [
                'ok'      => false,
                'message' => 'Refund failed',
                'error'   => $res->refund_error,
            ];
        });
    }

    private function successMessage(Reservation $res, Refund $refund): string
    {
        $walletSucceeded = $refund->wallet_status === 'succeeded';
        $externalSucceeded = $refund->external_status === 'succeeded';

        $walletMinor = $walletSucceeded ? (int) $refund->wallet_amount_minor : 0;
        $externalMinor = $externalSucceeded ? (int) $refund->external_amount_minor : 0;

        if ($walletMinor > 0 && $externalMinor > 0) {
            return $refund->method === 'original_payment'
                ? 'Refunded: wallet-funded portion returned to wallet, remaining amount refunded to original payment method.'
                : 'Refunded To Wallet Successfully.';
        }

        if ($walletMinor > 0 && $externalMinor <= 0) {
            return 'Refunded To Wallet Successfully.';
        }

        if ($externalMinor > 0 && $walletMinor <= 0) {
            return $refund->method === 'original_payment'
                ? 'Refunded to original payment method successfully.'
                : 'Refunded To Wallet Successfully.';
        }

        return 'Refund processed.';
    }
}