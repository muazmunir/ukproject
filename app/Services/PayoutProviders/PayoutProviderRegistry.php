<?php

namespace App\Services\PayoutProviders;

use App\Services\PayoutProviders\Payoneer\PayoneerPayoutProvider;
use App\Services\PayoutProviders\Stripe\StripePayoutProvider;
use InvalidArgumentException;

class PayoutProviderRegistry
{
    public function for(string $provider): PayoutProviderInterface
    {
        return match (strtolower(trim($provider))) {
            'stripe' => app(StripePayoutProvider::class),
            'payoneer' => app(PayoneerPayoutProvider::class),
            default => throw new InvalidArgumentException("Unsupported payout provider [{$provider}]"),
        };
    }

    public function supported(): array
    {
        return ['stripe', 'payoneer'];
    }
}