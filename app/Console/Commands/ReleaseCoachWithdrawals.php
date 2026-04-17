<?php

namespace App\Console\Commands;
use App\Models\Users;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\CoachWithdrawal;

class ReleaseCoachWithdrawals extends Command
{
    protected $signature = 'withdrawals:release';
    protected $description = 'Release due coach withdrawals (processing -> released)';

    public function handle(): int
    {
        $due = CoachWithdrawal::where('status','processing')
            ->whereNotNull('release_at')
            ->where('release_at','<=', now())
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($due as $w) {
          DB::transaction(function () use ($w) {
    $w = CoachWithdrawal::lockForUpdate()->find($w->id);
    if (! $w || $w->status !== 'processing') return;

    $user = Users::lockForUpdate()->find($w->coach_id);

   try {

    if ($w->method === 'stripe') {
        $acct = data_get($w->payout_details, 'stripe_account_id');
        if (! $acct) throw new \RuntimeException('Missing stripe_account_id');

        $res = \App\Services\StripeConnectPayoutService::make()
            ->transferToConnectedAccount($acct, (int)$w->amount_minor, $w->currency, 'withdrawal_'.$w->id);

        $w->provider_ref = $res['id'] ?? null;

        // ✅ Stripe is synchronous enough for your flow
        $w->status = 'released';
        $w->released_at = now();
        $w->save();
        return;
    }

    if ($w->method === 'paypal') {

        // ✅ ALWAYS define email from payout_details
        $email = data_get($w->payout_details, 'email');
        if (! $email) throw new \RuntimeException('Missing paypal email');

        // 1) create payout only once
        if (! $w->provider_ref) {

            $res = app(\App\Services\PayPalPayoutService::class)
                ->sendPayout($email, (int)$w->amount_minor, $w->currency, 'Coach withdrawal', 'wd_'.$w->id);

            $w->provider_ref = data_get($res, 'batch_header.payout_batch_id');
            $w->status = 'processing';
            $w->save();
            return; // ✅ IMPORTANT
        }

        // 2) poll status
        $batch = app(\App\Services\PayPalPayoutService::class)
            ->getPayoutBatch($w->provider_ref);

        $batchStatus = strtoupper((string) data_get($batch, 'batch_header.batch_status', 'PENDING'));

        // still waiting -> do NOTHING
        if (in_array($batchStatus, ['PENDING','PROCESSING'], true)) {
            return;
        }

        // success -> release
        if ($batchStatus === 'SUCCESS') {
            $w->status = 'released';
            $w->released_at = now();
            $w->save();
            return;
        }

        // failed/denied/etc
        throw new \RuntimeException('PayPal batch status: '.$batchStatus);
    }

    // If some unknown method, fail clearly
    throw new \RuntimeException('Unknown withdrawal method: '.$w->method);

} catch (\Throwable $e) {

    // ✅ Only refund if we truly failed (exception). Not on pending.
    $user->withdrawable_minor = (int)$user->withdrawable_minor + (int)$w->amount_minor;
    $user->save();

    $w->status = 'failed';
    $w->error = $e->getMessage();
    $w->save();
}

});

        }

        $this->info('Released: '.$due->count());
        return 0;
    }
}
