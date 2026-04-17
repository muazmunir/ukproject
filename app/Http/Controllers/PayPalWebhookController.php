<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use App\Services\PaymentStatusService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;


// use PayPalCheckoutSdk\Notifications\VerifyWebhookSignatureRequest;

class PayPalWebhookController extends Controller
{
    private PayPalHttpClient $client;

    public function __construct()
    {
        $clientId = config('services.paypal.client_id');
        $secret   = config('services.paypal.client_secret');
        $mode     = config('services.paypal.mode', 'sandbox');

        $env = $mode === 'live'
            ? new ProductionEnvironment($clientId, $secret)
            : new SandboxEnvironment($clientId, $secret);

        $this->client = new PayPalHttpClient($env);
    }

    public function handle(Request $request, PaymentStatusService $statusService)
    {
        $payload = $request->all();

        Log::info('paypal webhook received', [
            'headers'    => $request->headers->all(),
            'event_type' => $payload['event_type'] ?? null,
            'resource_id'=> $payload['resource']['id'] ?? null,
            'payload'    => $payload,
        ]);

        if (!$this->verifyWebhook($request)) {
            Log::warning('paypal webhook verification failed');
            return response()->json(['ok' => false], 400);
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $resource  = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];

        try {
            match ($eventType) {
                'CHECKOUT.ORDER.APPROVED'   => $this->handleOrderApproved($resource, $eventType, $statusService),
                'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource, $eventType, $statusService),
                'PAYMENT.CAPTURE.REFUNDED'  => $this->handleCaptureRefunded($resource, $eventType),
                'PAYMENT.CAPTURE.DENIED',
                'PAYMENT.CAPTURE.DECLINED',
                'PAYMENT.CAPTURE.PENDING',
                'CHECKOUT.ORDER.COMPLETED'  => $this->handleGenericEvent($resource, $eventType, $statusService),
                default                     => Log::info('paypal webhook ignored', ['event_type' => $eventType]),
            };

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('paypal webhook failed', [
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false], 500);
        }
    }

  private function verifyWebhook(Request $request): bool
{
    $webhookId = config('services.paypal.webhook_id');
    $clientId  = config('services.paypal.client_id');
    $secret    = config('services.paypal.client_secret');
    $mode      = config('services.paypal.mode', 'sandbox');

    if (!$webhookId || !$clientId || !$secret) {
        Log::warning('paypal webhook config missing');
        return false;
    }

    $baseUrl = $mode === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';

    $headers = [
        'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
        'cert_url'          => $request->header('PAYPAL-CERT-URL'),
        'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
        'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
        'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
    ];

    foreach ($headers as $key => $value) {
        if (!$value) {
            Log::warning('paypal webhook header missing', ['header' => $key]);
            return false;
        }
    }

    try {
        // 1) Get OAuth token
        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->post($baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (!$tokenResponse->successful()) {
            Log::error('paypal oauth failed', [
                'status' => $tokenResponse->status(),
                'body'   => $tokenResponse->body(),
            ]);
            return false;
        }

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            Log::error('paypal oauth token missing');
            return false;
        }

        // 2) Verify webhook signature
        $verifyBody = [
            'auth_algo'         => $headers['auth_algo'],
            'cert_url'          => $headers['cert_url'],
            'transmission_id'   => $headers['transmission_id'],
            'transmission_sig'  => $headers['transmission_sig'],
            'transmission_time' => $headers['transmission_time'],
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($request->getContent(), true),
        ];

        $verifyResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->post($baseUrl . '/v1/notifications/verify-webhook-signature', $verifyBody);

        if (!$verifyResponse->successful()) {
            Log::error('paypal verify request failed', [
                'status' => $verifyResponse->status(),
                'body'   => $verifyResponse->body(),
            ]);
            return false;
        }

        $status = strtoupper((string) $verifyResponse->json('verification_status'));

        Log::info('paypal webhook verify result', [
            'verification_status' => $status,
        ]);

        return $status === 'SUCCESS';
    } catch (\Throwable $e) {
        Log::error('paypal webhook verify exception', [
            'error' => $e->getMessage(),
        ]);

        return false;
    }
}
    private function handleOrderApproved(array $resource, string $eventType, PaymentStatusService $statusService): void
    {
        $orderId = (string) ($resource['id'] ?? '');
        if (!$orderId) {
            return;
        }

        DB::transaction(function () use ($orderId, $resource, $eventType, $statusService) {
            $pay = Payment::where('provider', 'paypal')
                ->where(function ($q) use ($orderId) {
                    $q->where('provider_payment_id', $orderId)
                      ->orWhere('provider_order_id', $orderId);
                })
                ->lockForUpdate()
                ->first();

            if (!$pay) {
                Log::warning('paypal order approved payment not found', [
                    'order_id' => $orderId,
                ]);
                return;
            }

            $pay->status             = $statusService->normalize('paypal', 'APPROVED');
            $pay->provider_status    = 'APPROVED';
            $pay->provider_order_id  = $orderId;
            $pay->last_webhook_event = $eventType;
            $pay->last_webhook_at    = now();
            $pay->meta = array_merge((array) $pay->meta, [
                'paypal_order_approved_resource' => $resource,
            ]);
            $pay->save();
        });
    }

    private function handleCaptureCompleted(array $resource, string $eventType, PaymentStatusService $statusService): void
    {
        $captureId = (string) ($resource['id'] ?? '');
        if (!$captureId) {
            return;
        }

        $amountMinor    = (int) round(((float) ($resource['amount']['value'] ?? 0)) * 100);
        $currency       = strtoupper((string) ($resource['amount']['currency_code'] ?? 'USD'));
        $providerStatus = (string) ($resource['status'] ?? 'COMPLETED');

        $invoiceId = (string) ($resource['invoice_id'] ?? '');
        $customId  = (string) ($resource['custom_id'] ?? '');
        $orderId   = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');

        DB::transaction(function () use (
            $captureId,
            $amountMinor,
            $currency,
            $providerStatus,
            $invoiceId,
            $customId,
            $orderId,
            $resource,
            $eventType,
            $statusService
        ) {
            $pay = null;

            if ($orderId) {
                $pay = Payment::where('provider', 'paypal')
                    ->where(function ($q) use ($orderId) {
                        $q->where('provider_payment_id', $orderId)
                          ->orWhere('provider_order_id', $orderId);
                    })
                    ->lockForUpdate()
                    ->first();
            }

            if (!$pay && $customId) {
                $reservation = Reservation::lockForUpdate()->find((int) $customId);
                if ($reservation) {
                    $pay = Payment::where('reservation_id', $reservation->id)
                        ->where('provider', 'paypal')
                        ->lockForUpdate()
                        ->latest('id')
                        ->first();
                }
            }

            if (!$pay && $invoiceId) {
                $reservation = Reservation::lockForUpdate()->find((int) $invoiceId);
                if ($reservation) {
                    $pay = Payment::where('reservation_id', $reservation->id)
                        ->where('provider', 'paypal')
                        ->lockForUpdate()
                        ->latest('id')
                        ->first();
                }
            }

            if (!$pay) {
                Log::warning('paypal capture completed payment not found', [
                    'capture_id' => $captureId,
                    'order_id'   => $orderId,
                    'invoice_id' => $invoiceId,
                    'custom_id'  => $customId,
                    'resource'   => $resource,
                ]);
                return;
            }

            $reservation = $pay->reservation_id
                ? Reservation::lockForUpdate()->find($pay->reservation_id)
                : null;

            $pay->status             = $statusService->normalize('paypal', $providerStatus);
            $pay->provider_status    = $providerStatus;
            $pay->provider_order_id  = $orderId ?: $pay->provider_order_id;
            $pay->provider_capture_id= $captureId;
            $pay->currency           = $currency;
            $pay->amount_total       = $amountMinor > 0 ? $amountMinor : $pay->amount_total;
            $pay->succeeded_at       = $pay->status === 'succeeded'
                ? ($pay->succeeded_at ?? now())
                : $pay->succeeded_at;
            $pay->last_webhook_event = $eventType;
            $pay->last_webhook_at    = now();
            $pay->meta = array_merge((array) $pay->meta, [
                'paypal_capture_completed_resource' => $resource,
            ]);
            $pay->save();

            if (!$reservation) {
                Log::warning('paypal capture completed reservation not found', [
                    'payment_id'  => $pay->id,
                    'capture_id'  => $captureId,
                    'order_id'    => $orderId,
                ]);
                return;
            }

            if ($pay->status === 'succeeded') {
                $this->finalizeReservationAfterSuccessfulPayment($reservation, $orderId);
            }
        });
    }

    private function handleCaptureRefunded(array $resource, string $eventType): void
    {
        $captureId = (string) ($resource['id'] ?? '');
        if (!$captureId) {
            return;
        }

        $amountMinor = (int) round(((float) ($resource['amount']['value'] ?? 0)) * 100);
        $currency    = strtoupper((string) ($resource['amount']['currency_code'] ?? 'USD'));

        DB::transaction(function () use ($captureId, $amountMinor, $currency, $resource, $eventType) {
            $pay = Payment::where('provider', 'paypal')
                ->where('provider_capture_id', $captureId)
                ->lockForUpdate()
                ->first();

            if (!$pay) {
                Log::warning('paypal refund payment not found', [
                    'capture_id' => $captureId,
                ]);
                return;
            }

            $pay->refunded_minor      = (int) ($pay->refunded_minor ?? 0) + $amountMinor;
            $pay->refund_status       = 'succeeded';
            $pay->refunded_at         = $pay->refunded_at ?? now();
            $pay->last_webhook_event  = $eventType;
            $pay->last_webhook_at     = now();

            $pay->meta = array_merge((array) $pay->meta, [
                'paypal_capture_refunded_resource' => $resource,
                'paypal_refund_currency'           => $currency,
            ]);

            $pay->save();
        });
    }

    private function handleGenericEvent(array $resource, string $eventType, PaymentStatusService $statusService): void
    {
        Log::info('paypal generic webhook handled', [
            'event_type'  => $eventType,
            'resource_id' => $resource['id'] ?? null,
            'status'      => $resource['status'] ?? null,
        ]);
    }

    private function finalizeReservationAfterSuccessfulPayment(Reservation $reservation, ?string $orderId = null): void
    {
        $holdId = (int) ($reservation->wallet_hold_tx_id ?? 0);

        if ($holdId <= 0) {
            $holdTx = WalletTransaction::where('reservation_id', $reservation->id)
                ->where('balance_type', WalletService::BAL_PLATFORM)
                ->where('type', 'debit')
                ->where('status', 'hold')
                ->lockForUpdate()
                ->first();

            $holdId = (int) ($holdTx?->id ?? 0);
        }

        if ($holdId > 0) {
            app(WalletService::class)->postHold($holdId);

            Payment::where('reservation_id', $reservation->id)
                ->where('provider', 'wallet')
                ->where('status', 'pending')
                ->where('provider_status', 'HELD')
                ->update([
                    'status'          => 'succeeded',
                    'provider_status' => 'POSTED',
                    'succeeded_at'    => now(),
                ]);
        }

        $reservation->update([
            'status'            => 'booked',
            'payment_status'    => 'paid',
            'provider'          => 'paypal',
            'payment_intent_id' => $orderId ?: $reservation->payment_intent_id,
            'booked_at'         => $reservation->booked_at ?? now(),
            'wallet_hold_tx_id' => null,
        ]);
    }
}