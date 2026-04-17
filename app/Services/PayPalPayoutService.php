<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayPalPayoutService
{
    public function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getPayoutBatch(string $batchId): array
{
    $resp = Http::withToken($this->token())
        ->get($this->baseUrl().'/v1/payments/payouts/'.$batchId);

    if (! $resp->ok()) {
        throw new \RuntimeException('PayPal payout lookup error: '.$resp->body());
    }

    return $resp->json();
}


    public function token(): string
    {
        $resp = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $resp->ok()) {
            throw new \RuntimeException('PayPal token error: '.$resp->body());
        }

        return (string)($resp->json('access_token'));
    }

    public function sendPayout(string $receiverEmail, int $amountMinor, string $currency, string $note, string $requestId): array
{
    $amount = number_format($amountMinor / 100, 2, '.', '');

    $resp = Http::withToken($this->token())
        ->withHeaders([
            'PayPal-Request-Id' => $requestId,
        ])
        ->post($this->baseUrl().'/v1/payments/payouts', [
            'sender_batch_header' => [
                'sender_batch_id' => $requestId,
                'email_subject' => 'You have a payout!',
                'email_message' => $note,
            ],
            'items' => [[
                'recipient_type' => 'EMAIL',
                'amount' => ['value' => $amount, 'currency' => $currency],
                'receiver' => $receiverEmail,
                'note' => $note,
                'sender_item_id' => $requestId,
            ]],
        ]);

    // ✅ accept 200/201/202 etc
    if (! $resp->successful()) {
        throw new \RuntimeException('PayPal payout error: '.$resp->status().' '.$resp->body());
    }

    return $resp->json();
}
}
