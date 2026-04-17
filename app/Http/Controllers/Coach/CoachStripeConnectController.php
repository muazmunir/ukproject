<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachPayoutMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class CoachStripeConnectController extends Controller
{
    protected function stripe(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    public function start(Request $request)
    {
        $u = $request->user();
        abort_unless(($u->is_coach ?? false), 403);
        abort_unless(($u->coach_verification_status ?? null) === 'approved', 403);

        return DB::transaction(function () use ($u) {

            // Find existing Stripe payout method for coach
            $pm = CoachPayoutMethod::where('coach_id', $u->id)
                ->where('type', 'stripe')
                ->lockForUpdate()
                ->first();

            $stripe = $this->stripe();

            $acctId = $pm->details['stripe_account_id'] ?? null;

            if (! $acctId) {
                // Create Express account (Stripe-hosted onboarding)
                $account = $stripe->accounts->create([
                    'type' => 'express',
                    // optional:
                    // 'country' => config('services.stripe.connect_country','US'),
                    'capabilities' => [
                        'transfers' => ['requested' => true],
                    ],
                ]);

                $acctId = $account->id;

                // Create or update payout method row
                if (! $pm) {
                    // make it default if none exist
                    $hasAny = CoachPayoutMethod::where('coach_id',$u->id)->exists();
                    if (! $hasAny) {
                        CoachPayoutMethod::where('coach_id',$u->id)->update(['is_default'=>false]);
                    }

                    $pm = CoachPayoutMethod::create([
                        'coach_id' => $u->id,
                        'type' => 'stripe',
                        'label' => 'Stripe',
                        'details' => ['stripe_account_id' => $acctId],
                        'status' => 'pending',
                        'is_default' => ! $hasAny,
                    ]);
                } else {
                    $pm->details = array_merge((array)$pm->details, ['stripe_account_id' => $acctId]);
                    $pm->status = 'pending';
                    $pm->save();
                }
            }

            // Create Stripe Account Link (onboarding URL)
            $link = $stripe->accountLinks->create([
                'account' => $acctId,
                'refresh_url' => route('coach.stripe.refresh'),
                'return_url'  => route('coach.stripe.return'),
                'type' => 'account_onboarding',
            ]);

            return redirect()->away($link->url);
        });
    }

    public function refresh()
    {
        // Stripe sends user here if they need to restart onboarding
        return redirect()->route('coach.stripe.connect');
    }

    public function return(Request $request)
    {
        // User finished onboarding (not a guarantee everything is verified yet)
        return redirect()->route('coach.withdraw.index')
            ->with('ok', __('Stripe onboarding completed. We will enable payouts once Stripe finishes verification.'));
    }
}
