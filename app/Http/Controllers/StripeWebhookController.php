<?php

namespace App\Http\Controllers;

use App\Models\CoachPayoutAccount;
use App\Services\CoachPayoutWebhookService;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, CoachPayoutWebhookService $payoutWebhookService)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            report($e);
            return response('Invalid webhook payload.', 400);
        } catch (\Throwable $e) {
            report($e);
            return response('Webhook verification failed.', 400);
        }

        try {
            $type   = (string) ($event->type ?? '');
            $object = $event->data->object ?? null;

            switch ($type) {
                /*
                 |------------------------------------------------------------
                 | Connected account state
                 |------------------------------------------------------------
                 */
                case 'account.updated':
                    $this->handleAccountUpdated($object);
                    break;

                /*
                 |------------------------------------------------------------
                 | Platform -> connected account transfers
                 |------------------------------------------------------------
                 */
                case 'transfer.created':
                    $payoutWebhookService->handleTransferCreated($object);
                    break;

                case 'transfer.updated':
                    $payoutWebhookService->handleTransferUpdated($object);
                    break;

                case 'transfer.reversed':
                    $payoutWebhookService->handleTransferReversed($object);
                    break;

                /*
                 |------------------------------------------------------------
                 | Connected account bank payouts
                 |------------------------------------------------------------
                 */
                case 'payout.paid':
                    $payoutWebhookService->handlePayoutPaid($object);
                    break;

                case 'payout.failed':
                    $payoutWebhookService->handlePayoutFailed($object);
                    break;

                default:
                    // ignore unrelated Stripe events
                    break;
            }
        } catch (\Throwable $e) {
            report($e);
            return response('Webhook handling failed.', 500);
        }

        return response('OK', 200);
    }

    private function handleAccountUpdated(object $account): void
    {
        $accountId = (string) ($account->id ?? '');

        if ($accountId === '') {
            return;
        }

        $status = 'onboarding_required';

        $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);
        $currentlyDue   = (array) ($account->requirements->currently_due ?? []);
        $pastDue        = (array) ($account->requirements->past_due ?? []);

        if ($payoutsEnabled && empty($currentlyDue) && empty($pastDue)) {
            $status = 'verified';
        } elseif (!empty($pastDue)) {
            $status = 'restricted';
        } elseif (!empty($currentlyDue)) {
            $status = 'pending_verification';
        }

        $payoutAccount = CoachPayoutAccount::where('provider', 'stripe')
            ->where('provider_account_id', $accountId)
            ->first();

        if (!$payoutAccount) {
            return;
        }

        $payoutAccount->forceFill([
            'status' => $status,
            'country' => $account->country ?? $payoutAccount->country,
            'default_currency' => strtoupper((string) ($account->default_currency ?? $payoutAccount->default_currency ?? 'USD')),
            'charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'payouts_enabled' => $payoutsEnabled,
            'capabilities' => (array) ($account->capabilities ?? []),
            'requirements_currently_due' => $currentlyDue,
            'requirements_eventually_due' => (array) ($account->requirements->eventually_due ?? []),
            'requirements_past_due' => $pastDue,
            'raw_provider_payload' => method_exists($account, 'toArray') ? $account->toArray() : (array) $account,
            'verified_at' => $payoutsEnabled ? ($payoutAccount->verified_at ?? now()) : null,
            'onboarding_completed_at' => $payoutsEnabled ? ($payoutAccount->onboarding_completed_at ?? now()) : $payoutAccount->onboarding_completed_at,
        ])->save();

        $coachProfile = $payoutAccount->coachProfile;
        if ($coachProfile) {
            $coachProfile->forceFill([
                'can_receive_payouts' => $payoutsEnabled && $status === 'verified',
            ])->save();
        }
    }
}