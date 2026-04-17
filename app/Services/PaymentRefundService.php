<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use Stripe\StripeClient;

class PaymentRefundService
{
    /**
     * Refund back to ORIGINAL payment method.
     *
     * Returns:
     * [
     *   'ok' => bool,
     *   'provider' => 'stripe'|'paypal'|...,
     *   'provider_refund_id' => ?string,
     *   'provider_status' => ?string,
     *   'amount_minor' => int,
     *   'error' => ?string,
     *   'meta' => array,
     * ]
     */
    public function refundToOriginal(Payment $payment, int $amountMinor, string $reason = ''): array
    {
        $provider = strtolower((string) ($payment->provider ?? ''));

        $captured = (int) ($payment->amount_total ?? 0);
        $already  = (int) ($payment->refunded_minor ?? 0);
        $max      = max(0, $captured - $already);
        $amountMinor = min($amountMinor, $max);

        if ($amountMinor <= 0) {
            return [
                'ok' => true,
                'provider' => $provider,
                'provider_refund_id' => null,
                'provider_status' => 'NOOP',
                'amount_minor' => 0,
                'error' => null,
                'meta' => [
                    'reason' => 'nothing_refundable_remaining',
                    'captured_minor' => $captured,
                    'already_refunded_minor' => $already,
                    'max_refundable_minor' => $max,
                ],
            ];
        }

        try {
            return match ($provider) {
                'stripe' => $this->refundStripe($payment, $amountMinor, $reason),
                'paypal' => $this->refundPayPal($payment, $amountMinor, $reason),
                default  => [
                    'ok' => false,
                    'provider' => $provider,
                    'provider_refund_id' => null,
                    'provider_status' => null,
                    'amount_minor' => $amountMinor,
                    'error' => "Unsupported provider: {$provider}",
                    'meta' => [],
                ],
            };
        } catch (\Throwable $e) {
            Log::error('refundToOriginal failed', [
                'payment_id' => $payment->id,
                'provider'   => $provider,
                'amount'     => $amountMinor,
                'error'      => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'provider' => $provider,
                'provider_refund_id' => null,
                'provider_status' => null,
                'amount_minor' => $amountMinor,
                'error' => $e->getMessage(),
                'meta' => [],
            ];
        }
    }

    /**
     * STRIPE refund
     * Uses:
     * - provider_charge_id (preferred)
     * - provider_payment_id (fallback PaymentIntent id)
     */
    private function refundStripe(Payment $payment, int $amountMinor, string $reason): array
    {
        /**
         * Optional forced failure for testing:
         * set REFUND_FORCE_STRIPE_FAIL=true in .env when you want to simulate failure.
         */
        if ((bool) config('services.stripe.refund_force_fail', env('REFUND_FORCE_STRIPE_FAIL', false))) {
            return [
                'ok' => false,
                'provider' => 'stripe',
                'provider_refund_id' => null,
                'provider_status' => 'failed',
                'amount_minor' => $amountMinor,
                'error' => 'Forced Stripe refund failure for testing.',
                'meta' => [
                    'forced_failure' => true,
                ],
            ];
        }

        $chargeId = (string) ($payment->provider_charge_id ?? '');
        $piId     = (string) ($payment->provider_payment_id ?? '');

        if (!$chargeId && !$piId) {
            throw new RuntimeException('Stripe refund needs provider_charge_id or provider_payment_id (PaymentIntent id).');
        }

        $secret = config('services.stripe.secret') ?: env('STRIPE_SECRET');
        if (!$secret) {
            throw new RuntimeException('Missing Stripe secret key.');
        }

        $stripe = new StripeClient($secret);

        $payload = [
            'amount'   => $amountMinor,
            'reason'   => 'requested_by_customer',
            'metadata' => [
                'payment_id'      => (string) $payment->id,
                'reservation_id'  => (string) ($payment->reservation_id ?? ''),
                'reason'          => $reason,
            ],
        ];

        if ($chargeId) {
            $payload['charge'] = $chargeId;
        } else {
            $payload['payment_intent'] = $piId;
        }

        /**
         * Make idempotency specific enough to avoid collisions across distinct retries,
         * but stable enough for duplicate submits of the same request.
         */
        $idempotencyKey = implode(':', [
            'refund',
            'stripe',
            'payment',
            (string) $payment->id,
            'amount',
            (string) $amountMinor,
            'already',
            (string) ((int) ($payment->refunded_minor ?? 0)),
        ]);

        $refund = $stripe->refunds->create(
            $payload,
            ['idempotency_key' => $idempotencyKey]
        );

        $providerStatus = strtolower((string) ($refund->status ?? ''));

        return [
            'ok' => in_array($providerStatus, ['succeeded', 'pending', 'requires_action'], true),
            'provider' => 'stripe',
            'provider_refund_id' => !empty($refund->id) ? (string) $refund->id : null,
            'provider_status' => $providerStatus ?: null,
            'amount_minor' => $amountMinor,
            'error' => null,
            'meta' => [
                'idempotency_key'   => $idempotencyKey,
                'charge_id'         => $chargeId ?: null,
                'payment_intent_id' => $piId ?: null,
                'raw_refund'        => method_exists($refund, 'toArray')
                    ? $refund->toArray()
                    : json_decode(json_encode($refund), true),
            ],
        ];
    }

    /**
     * PAYPAL refund
     * Requires:
     * - provider_capture_id
     */
    private function refundPayPal(Payment $payment, int $amountMinor, string $reason): array
    {
        $captureId = (string) ($payment->provider_capture_id ?? '');
        if (!$captureId) {
            throw new RuntimeException('PayPal refund needs provider_capture_id on payments table.');
        }

        $clientId = config('services.paypal.client_id') ?: env('PAYPAL_CLIENT_ID');
        $secret   = config('services.paypal.client_secret') ?: env('PAYPAL_CLIENT_SECRET');
        $mode     = config('services.paypal.mode', env('PAYPAL_MODE', 'sandbox'));

        if (!$clientId || !$secret) {
            throw new RuntimeException('Missing PayPal credentials.');
        }

        $env = $mode === 'live'
            ? new ProductionEnvironment($clientId, $secret)
            : new SandboxEnvironment($clientId, $secret);

        $client = new PayPalHttpClient($env);

        $request = new CapturesRefundRequest($captureId);
        $request->prefer('return=representation');

        $currency = strtoupper((string) ($payment->currency ?? 'USD'));
        $value    = number_format($amountMinor / 100, 2, '.', '');

        $request->body = [
            'amount' => [
                'value'         => $value,
                'currency_code' => $currency,
            ],
            'note_to_payer' => $reason ? mb_substr($reason, 0, 200) : 'Refund',
        ];

        $response = $client->execute($request);
        $result   = $response->result ?? null;

        $providerRefundId = (string) ($result->id ?? null);
        $providerStatus   = strtolower((string) ($result->status ?? ''));

        return [
            'ok' => !empty($providerRefundId) && in_array($providerStatus, ['completed', 'pending'], true),
            'provider' => 'paypal',
            'provider_refund_id' => $providerRefundId ?: null,
            'provider_status' => $providerStatus ?: null,
            'amount_minor' => $amountMinor,
            'error' => null,
            'meta' => [
                'capture_id' => $captureId,
                'raw_refund' => json_decode(json_encode($result), true),
            ],
        ];
    }
}