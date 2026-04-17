<?php

namespace App\Services;

use Stripe\StripeClient;

class StripeConnectPayoutService
{
    public function __construct(protected StripeClient $stripe) {}

    public static function make(): self
    {
        return new self(new StripeClient(config('services.stripe.secret')));
    }

    public function transferToConnectedAccount(string $stripeAccountId, int $amountMinor, string $currency, string $transferGroup): array
    {
        $t = $this->stripe->transfers->create([
            'amount' => $amountMinor,
            'currency' => strtolower($currency),
            'destination' => $stripeAccountId,
            'transfer_group' => $transferGroup,
        ]);

        return $t->toArray();
    }
}
