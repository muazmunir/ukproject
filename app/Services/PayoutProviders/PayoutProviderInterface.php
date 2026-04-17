<?php

namespace App\Services\PayoutProviders;

use App\Models\CoachProfile;
use App\Models\CoachPayout;
use App\Models\CoachPayoutAccount;

interface PayoutProviderInterface
{
    public function name(): string;

    public function createOrGetAccount(CoachProfile $coachProfile): CoachPayoutAccount;

    public function createOnboardingLink(CoachPayoutAccount $account): string;

    public function syncAccount(CoachPayoutAccount $account): CoachPayoutAccount;

    public function payoutsEnabled(CoachPayoutAccount $account): bool;

    /**
     * Send payout externally.
     *
     * Expected return shape:
     * [
     *   'ok' => bool,
     *   'provider' => 'stripe'|'payoneer',
     *   'provider_transfer_id' => ?string,
     *   'provider_payout_id' => ?string,
     *   'provider_balance_txn_id' => ?string,
     *   'status' => 'paid'|'pending'|'failed',
     *   'failure_reason' => ?string,
     *   'raw' => array,
     * ]
     */
    public function sendPayout(CoachPayout $payout): array;
}