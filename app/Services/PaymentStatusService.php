<?php

namespace App\Services;

class PaymentStatusService
{
    public function normalize(string $provider, ?string $providerStatus): string
    {
        $provider = strtolower(trim($provider));
        $status   = strtoupper(trim((string) $providerStatus));

        return match ($provider) {
            'paypal' => $this->normalizePayPal($status),
            'stripe' => $this->normalizeStripe(strtolower($providerStatus ?? '')),
            'wallet' => $this->normalizeWallet(strtolower($providerStatus ?? '')),
            default  => 'pending',
        };
    }

    private function normalizePayPal(string $status): string
    {
        return match ($status) {
            'CREATED'   => 'pending',
            'SAVED'     => 'pending',
            'APPROVED'  => 'processing',
            'PAYER_ACTION_REQUIRED' => 'processing',
            'COMPLETED' => 'succeeded',
            'VOIDED', 'DENIED', 'FAILED' => 'failed',
            'CANCELLED', 'CANCELED' => 'cancelled',
            default => 'pending',
        };
    }

    private function normalizeStripe(string $status): string
    {
        return match ($status) {
            'succeeded' => 'succeeded',
            'processing' => 'processing',
            'requires_action',
            'requires_confirmation',
            'requires_capture' => 'processing',
            'requires_payment_method',
            'canceled' => 'failed',
            default => 'pending',
        };
    }

    private function normalizeWallet(string $status): string
    {
        return match ($status) {
            'held' => 'pending',
            'succeeded' => 'succeeded',
            'cancelled', 'canceled' => 'cancelled',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}