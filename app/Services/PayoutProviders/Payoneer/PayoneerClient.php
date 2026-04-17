<?php

namespace App\Services\PayoutProviders\Payoneer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PayoneerClient
{
    protected string $baseUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $partnerId;
    protected ?string $programId;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('payouts.providers.payoneer.base_url'), '/');
        $this->clientId = config('payouts.providers.payoneer.client_id');
        $this->clientSecret = config('payouts.providers.payoneer.client_secret');
        $this->partnerId = config('payouts.providers.payoneer.partner_id');
        $this->programId = config('payouts.providers.payoneer.program_id');
    }

    public function isConfigured(): bool
    {
        return filled($this->baseUrl)
            && filled($this->clientId)
            && filled($this->clientSecret);
    }

    public function accessToken(): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Payoneer is not configured yet.');
        }

        return Cache::remember('payoneer_access_token', now()->addMinutes(45), function () {
            $response = Http::asForm()
                ->acceptJson()
                ->post($this->baseUrl . '/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('Unable to fetch Payoneer access token: ' . $response->body());
            }

            return (string) data_get($response->json(), 'access_token');
        });
    }

    protected function request(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->withToken($this->accessToken())
            ->when($this->partnerId, fn (PendingRequest $r) => $r->withHeaders([
                'X-Partner-Id' => $this->partnerId,
            ]))
            ->when($this->programId, fn (PendingRequest $r) => $r->withHeaders([
                'X-Program-Id' => $this->programId,
            ]));
    }

    public function registerPayee(array $payload): array
    {
        $response = $this->request()->post($this->baseUrl . '/payees', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Payoneer payee registration failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getPayee(string $payeeId): array
    {
        $response = $this->request()->get($this->baseUrl . '/payees/' . urlencode($payeeId));

        if (!$response->successful()) {
            throw new RuntimeException('Unable to fetch Payoneer payee: ' . $response->body());
        }

        return $response->json();
    }

    public function createOnboardingLink(string $payeeId, array $payload = []): array
    {
        $response = $this->request()->post(
            $this->baseUrl . '/payees/' . urlencode($payeeId) . '/onboarding-links',
            $payload
        );

        if (!$response->successful()) {
            throw new RuntimeException('Unable to create Payoneer onboarding link: ' . $response->body());
        }

        return $response->json();
    }

    public function createPayout(array $payload): array
    {
        $response = $this->request()->post($this->baseUrl . '/payouts', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Payoneer payout creation failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getPayout(string $payoutId): array
    {
        $response = $this->request()->get($this->baseUrl . '/payouts/' . urlencode($payoutId));

        if (!$response->successful()) {
            throw new RuntimeException('Unable to fetch Payoneer payout: ' . $response->body());
        }

        return $response->json();
    }
}