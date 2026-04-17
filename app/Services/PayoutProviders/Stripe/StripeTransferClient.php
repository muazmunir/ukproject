<?php

namespace App\Services\PayoutProviders\Stripe;

use Stripe\StripeClient;

class StripeTransferClient
{
    public function __construct(protected StripeClient $stripe)
    {
    }

    public static function make(): self
    {
        return new self(new StripeClient(config('services.stripe.secret')));
    }

    public function transferToConnectedAccount(
        string $stripeAccountId,
        int $amountMinor,
        string $currency,
        string $transferGroup,
        array $metadata = []
    ): array {
        $transfer = $this->stripe->transfers->create(
            [
                'amount' => $amountMinor,
                'currency' => strtolower($currency),
                'destination' => $stripeAccountId,
                'transfer_group' => $transferGroup,
                'metadata' => $metadata,
            ],
            [
                'idempotency_key' => 'transfer_' . md5(
                    implode('|', [
                        $stripeAccountId,
                        $amountMinor,
                        strtoupper($currency),
                        $transferGroup,
                    ])
                ),
            ]
        );

        return $transfer->toArray();
    }
}