<?php

namespace App\Services;

use RuntimeException;

class RefundSplitService
{
    /**
     * Returns:
     * [walletComponentMinor, externalComponentMinor, splitMeta]
     *
     * Important:
     * - walletComponentMinor = amount originally funded by wallet
     * - externalComponentMinor = amount originally funded by external provider
     *
     * Destination (wallet vs original payment) is handled later by RefundChoiceService.
     */
    public function computeRefundSplit(
        int $walletUsed,
        int $externalPaid,
        int $feesMinor,
        int $refundMinor,
        string $method,
        bool $feesRefundable = false
    ): array {
        $method = strtolower((string) $method);

        if (!in_array($method, ['wallet_credit', 'original_payment'], true)) {
            return [
                0,
                0,
                [
                    'type' => 'method_not_selected',
                    'method' => $method,
                ],
            ];
        }

        $walletUsed   = max(0, (int) $walletUsed);
        $externalPaid = max(0, (int) $externalPaid);
        $feesMinor    = max(0, (int) $feesMinor);
        $refundMinor  = max(0, (int) $refundMinor);

        if ($walletUsed === 0 && $externalPaid === 0) {
            return [
                0,
                0,
                [
                    'type' => 'no_funding',
                    'method' => $method,
                    'fees_refundable' => $feesRefundable,
                    'fees' => $feesMinor,
                    'refund_requested' => $refundMinor,
                ],
            ];
        }

        // -------------------------
        // Wallet-only
        // -------------------------
        if ($externalPaid <= 0 && $walletUsed > 0) {
            $maxRefundable = $feesRefundable
                ? $walletUsed
                : max(0, $walletUsed - min($feesMinor, $walletUsed));

            $originalRequestedRefundMinor = $refundMinor;
$refundMinor = min($refundMinor, $maxRefundable);


            return [
                $refundMinor,
                0,
                [
                    'type' => 'wallet_only',
                    'method' => $method,
                    'fees_refundable' => $feesRefundable,
                    'wallet_used' => $walletUsed,
                    'external_paid' => $externalPaid,
                    'fees' => $feesMinor,
                    'max_refundable' => $maxRefundable,
                    'refund_clamped' => $refundMinor,
                ],
            ];
        }

        // -------------------------
        // External-only
        // -------------------------
        if ($walletUsed <= 0 && $externalPaid > 0) {
            $maxRefundable = $feesRefundable
                ? $externalPaid
                : max(0, $externalPaid - min($feesMinor, $externalPaid));

            $refundMinor = min($refundMinor, $maxRefundable);

            return [
                0,
                $refundMinor,
                [
                    'type' => $method === 'wallet_credit'
                        ? 'external_only_to_wallet'
                        : 'external_only_to_original',
                    'method' => $method,
                    'fees_refundable' => $feesRefundable,
                    'wallet_used' => $walletUsed,
                    'external_paid' => $externalPaid,
                    'fees' => $feesMinor,
                    'max_refundable' => $maxRefundable,
                    'refund_clamped' => $refundMinor,
                ],
            ];
        }

        // -------------------------
        // Mixed
        // -------------------------
        $walletFeeNonRefundable = 0;
        $externalFeeNonRefundable = 0;

        if (!$feesRefundable && $feesMinor > 0) {
            $walletFeeNonRefundable = min($feesMinor, $walletUsed);
            $spill = max(0, $feesMinor - $walletFeeNonRefundable);
            $externalFeeNonRefundable = min($spill, $externalPaid);
        }

        $walletRefundableMax   = max(0, $walletUsed - $walletFeeNonRefundable);
        $externalRefundableMax = max(0, $externalPaid - $externalFeeNonRefundable);

        $maxRefundable = max(0, $walletRefundableMax + $externalRefundableMax);
$originalRequestedRefundMinor = $refundMinor;
$refundMinor = min($refundMinor, $maxRefundable);

        if ($method === 'wallet_credit') {
            $walletPart = min($refundMinor, $walletRefundableMax);
            $remaining  = max(0, $refundMinor - $walletPart);
            $externalPart = min($remaining, $externalRefundableMax);

            if (($walletPart + $externalPart) !== $refundMinor) {
                throw new RuntimeException('Refund split invariant failed for mixed wallet_credit.');
            }

            return [
                $walletPart,
                $externalPart,
                [
                    'type' => 'mixed_to_wallet',
                    'method' => $method,
                    'fees_refundable' => $feesRefundable,
                    'wallet_used' => $walletUsed,
                    'external_paid' => $externalPaid,
                    'fees' => $feesMinor,
                    'wallet_fee_non_refundable' => $walletFeeNonRefundable,
                    'external_fee_non_refundable' => $externalFeeNonRefundable,
                    'wallet_refundable_max' => $walletRefundableMax,
                    'external_refundable_max' => $externalRefundableMax,
                    'max_refundable' => $maxRefundable,
                    'refund_clamped' => $refundMinor,
                    'wallet_component_minor' => $walletPart,
                    'external_component_minor' => $externalPart,
                ],
            ];
        }

        $externalPart = min($refundMinor, $externalRefundableMax);
        $remaining    = max(0, $refundMinor - $externalPart);
        $walletPart   = min($remaining, $walletRefundableMax);

        if (($walletPart + $externalPart) !== $refundMinor) {
            throw new RuntimeException('Refund split invariant failed for mixed original_payment.');
        }

        return [
            $walletPart,
            $externalPart,
            [
                'type' => 'mixed_split_original_then_wallet',
                'method' => $method,
                'fees_refundable' => $feesRefundable,
                'wallet_used' => $walletUsed,
                'external_paid' => $externalPaid,
                'fees' => $feesMinor,
                'wallet_fee_non_refundable' => $walletFeeNonRefundable,
                'external_fee_non_refundable' => $externalFeeNonRefundable,
                'wallet_refundable_max' => $walletRefundableMax,
                'external_refundable_max' => $externalRefundableMax,
                'max_refundable' => $maxRefundable,
                'refund_clamped' => $refundMinor,
                'wallet_component_minor' => $walletPart,
                'external_component_minor' => $externalPart,
            ],
        ];
    }
}