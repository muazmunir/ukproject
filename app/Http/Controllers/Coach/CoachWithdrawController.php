<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachPayout;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class CoachWithdrawController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->loadMissing([
            'coachProfile.payouts',
            'coachProfile.payoutAccounts.payoutMethods',
            'coachProfile.defaultPayoutAccount.payoutMethods',
        ]);

        $coachProfile = $user->coachProfile;

        if (! $coachProfile) {
            abort(403);
        }

        $account = $coachProfile->payoutAccounts
            ->first(fn ($item) => strtolower((string) $item->provider) === 'stripe' && (bool) $item->is_default);

        if (! $account && $coachProfile->defaultPayoutAccount && strtolower((string) $coachProfile->defaultPayoutAccount->provider) === 'stripe') {
            $account = $coachProfile->defaultPayoutAccount;
        }

        $currency = strtoupper((string) ($account?->default_currency ?? 'USD'));

        $withdrawableMinor = $this->balanceMinor($user->id, 'withdrawable', $currency);
        $availableBalance = $withdrawableMinor / 100;

        $selectedStatus = strtolower((string) $request->query('status', 'all'));
        $range = strtolower((string) $request->query('range', '30days'));

        $payoutsQuery = $coachProfile->payouts()
            ->where('provider', 'stripe')
            ->latest();

        if ($selectedStatus !== 'all' && $selectedStatus !== '') {
            $payoutsQuery->where('status', $selectedStatus);
        }

        $this->applyQuickDateFilters($range, $payoutsQuery);

        $payouts = $payoutsQuery
            ->paginate(10)
            ->appends($request->query());

        $accountStatus = strtolower((string) ($account?->status ?? 'not_connected'));
        $accountStatusLabel = ucfirst(str_replace('_', ' ', $accountStatus));
        $payoutsEnabled = (bool) ($account?->payouts_enabled ?? false);
        $requirementsDue = array_values((array) ($account?->requirements_currently_due ?? []));
        $pastDueRequirements = array_values((array) ($account?->requirements_past_due ?? []));
        $hasPayoutMethod = (bool) ($account && $account->payoutMethods && $account->payoutMethods->count() > 0);

        $systemReady = $coachProfile->application_status === 'approved'
            && (bool) $coachProfile->can_receive_payouts
            && $account
            && $payoutsEnabled
            && $hasPayoutMethod;

        $latestPayout = $coachProfile->payouts()
            ->where('provider', 'stripe')
            ->latest()
            ->first();

        $totalPaidOutMinor = (int) $coachProfile->payouts()
            ->where('provider', 'stripe')
            ->whereIn('status', ['paid', 'processing', 'payout_pending', 'transfer_created'])
            ->sum('amount_minor');

        $totalPaidOut = $totalPaidOutMinor / 100;

        $successfulPayoutMinor = (int) $coachProfile->payouts()
            ->where('provider', 'stripe')
            ->where('status', 'paid')
            ->sum('amount_minor');

        $successfulPayoutTotal = $successfulPayoutMinor / 100;

        $pendingPayoutMinor = (int) $coachProfile->payouts()
            ->where('provider', 'stripe')
            ->whereIn('status', ['processing', 'payout_pending', 'transfer_created', 'pending'])
            ->sum('amount_minor');

        $pendingPayoutTotal = $pendingPayoutMinor / 100;

        $nextAutomaticPayoutAt = now()->copy()->setTime(3, 0);
        if (now()->greaterThan($nextAutomaticPayoutAt)) {
            $nextAutomaticPayoutAt->addDay();
        }

        $methods = $account?->payoutMethods ?? collect();
        $defaultMethod = $methods->firstWhere('is_default', true) ?? $methods->first();

        return view('coach.withdraw.index', compact(
            'user',
            'coachProfile',
            'account',
            'payouts',
            'availableBalance',
            'accountStatus',
            'accountStatusLabel',
            'payoutsEnabled',
            'requirementsDue',
            'pastDueRequirements',
            'hasPayoutMethod',
            'systemReady',
            'latestPayout',
            'totalPaidOut',
            'successfulPayoutTotal',
            'pendingPayoutTotal',
            'nextAutomaticPayoutAt',
            'defaultMethod',
            'currency',
            'selectedStatus',
            'range',
            'methods'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user()->loadMissing([
            'coachProfile.payoutAccounts.payoutMethods',
            'coachProfile.defaultPayoutAccount.payoutMethods',
        ]);

        $coachProfile = $user->coachProfile;

        if (! $coachProfile) {
            abort(403);
        }

        $account = $coachProfile->payoutAccounts
            ->first(fn ($item) => strtolower((string) $item->provider) === 'stripe' && (bool) $item->is_default);

        if (! $account && $coachProfile->defaultPayoutAccount && strtolower((string) $coachProfile->defaultPayoutAccount->provider) === 'stripe') {
            $account = $coachProfile->defaultPayoutAccount;
        }

        $currency = strtoupper((string) ($account?->default_currency ?? 'USD'));
        $availableMinor = $this->balanceMinor($user->id, 'withdrawable', $currency);

        if ($availableMinor <= 0) {
            return redirect()
                ->route('coach.withdraw.index')
                ->with('info', __('No withdrawable balance is available right now.'));
        }

        if ($coachProfile->application_status !== 'approved') {
            return redirect()
                ->route('coach.withdraw.index')
                ->with('error', __('Your coach application must be approved before withdrawals can be processed.'));
        }

        if (! $account) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', __('Please connect your Stripe account first.'));
        }

        if (! (bool) $account->payouts_enabled) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', __('Stripe payouts are not enabled yet. Please complete your Stripe setup first.'));
        }

        if (! $account->payoutMethods || $account->payoutMethods->count() === 0) {
            return redirect()
                ->route('coach.payouts.settings')
                ->with('error', __('Please add a payout method in Stripe before requesting a payout.'));
        }

        return redirect()
            ->route('coach.withdraw.index')
            ->with('info', __('Your payout will be included in the next Stripe payout run.'));
    }

    public function receipt(Request $request, CoachPayout $payout)
    {
        $user = $request->user();

        abort_unless(
            $payout->coachProfile && (int) $payout->coachProfile->user_id === (int) $user->id,
            403
        );

        $payout->loadMissing(['coachProfile.user', 'payoutAccount']);

        return view('coach.withdraw.receipt', compact('payout'));
    }

    public function downloadReceipt(Request $request, CoachPayout $payout)
    {
        $user = $request->user();

        abort_unless(
            $payout->coachProfile && (int) $payout->coachProfile->user_id === (int) $user->id,
            403
        );

        $payout->loadMissing(['coachProfile.user', 'payoutAccount']);

        $html = View::make('coach.withdraw.receipt', compact('payout'))->render();
        $filename = 'payout-receipt-' . ($payout->id ?? 'receipt') . '.html';

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function applyQuickDateFilters(string $range, $query): void
    {
        switch ($range) {
            case '7days':
                $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
                break;

            case '30days':
                $query->where('created_at', '>=', now()->subDays(30)->startOfDay());
                break;

            case '90days':
                $query->where('created_at', '>=', now()->subDays(90)->startOfDay());
                break;

            case 'year':
                $query->where('created_at', '>=', now()->subYear()->startOfDay());
                break;

            case 'all':
            default:
                break;
        }
    }

    private function balanceMinor(int $userId, string $balanceType, string $currency = 'USD'): int
    {
        $currency = strtoupper($currency);

        $credits = (int) WalletTransaction::query()
            ->where('user_id', $userId)
            ->where('balance_type', $balanceType)
            ->where('currency', $currency)
            ->where('type', 'credit')
            ->sum('amount_minor');

        $debits = (int) WalletTransaction::query()
            ->where('user_id', $userId)
            ->where('balance_type', $balanceType)
            ->where('currency', $currency)
            ->where('type', 'debit')
            ->sum('amount_minor');

        return max(0, $credits - $debits);
    }
}