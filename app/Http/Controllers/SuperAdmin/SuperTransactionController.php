<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperTransactionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $paymentsBase = Payment::query()
            ->with([
                'reservation.service:id,title',
                'reservation.client:id,first_name,last_name,email',
                'reservation.coach:id,first_name,last_name,email',
            ])
            ->leftJoin('reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->select([
                'payments.*',
                'reservations.funding_status',
                'reservations.payment_status as reservation_payment_status',
                'reservations.service_title_snapshot',
                'reservations.package_name_snapshot',
                'reservations.wallet_platform_credit_used_minor',
                'reservations.payable_minor',
                'reservations.subtotal_minor',
                'reservations.fees_minor',
                'reservations.total_minor',
                'reservations.currency as reservation_currency',
            ]);

        $paymentsBase = $this->applyDateFilter($paymentsBase, $filters);
        $paymentsBase = $this->applyTransactionFilters($paymentsBase, $request);

        $payments = (clone $paymentsBase)
            ->orderByDesc('payments.created_at')
            ->paginate(20)
            ->withQueryString();

        $kpis = $this->buildKpis($filters, $request);

        $filterOptions = [
            'periods' => [
                'daily'   => 'Daily',
                'weekly'  => 'Weekly',
                'monthly' => 'Monthly',
                'yearly'  => 'Yearly',
                'custom'  => 'Custom',
                'all'     => 'All Time',
            ],
            'providers' => [
                'stripe' => 'Stripe',
                'paypal' => 'PayPal',
                'wallet' => 'Wallet',
            ],
            'statuses' => [
                'succeeded'               => 'Succeeded',
                'paid'                    => 'Paid',
                'processing'              => 'Processing',
                'pending'                 => 'Pending',
                'held'                    => 'Held',
                'requires_payment'        => 'Requires Payment',
                'requires_payment_method' => 'Requires Payment Method',
                'failed'                  => 'Failed',
                'cancelled'               => 'Cancelled',
                'canceled'                => 'Canceled',
            ],
            'methods' => [
                'VISA'            => 'Visa',
                'MASTERCARD'      => 'Mastercard',
                'AMEX'            => 'American Express',
                'DISCOVER'        => 'Discover',
                'CARD'            => 'Card',
                'KLARNA'          => 'Klarna',
                'PAYPAL'          => 'PayPal',
                'paypal'          => 'PayPal',
                'PLATFORM_CREDIT' => 'Platform Credit',
            ],
            'funding_statuses' => [
                'wallet_only'   => 'Wallet Only',
                'mixed'         => 'Mixed',
                'external_only' => 'External Only',
            ],
        ];

        return view('superadmin.transactions.index', [
            'payments'      => $payments,
            'kpis'          => $kpis,
            'filters'       => $filters,
            'filterOptions' => $filterOptions,
        ]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'service',
            'client',
            'coach',
            'slots',
        ]);

        $payments = Payment::query()
            ->where('reservation_id', $reservation->id)
            ->orderByDesc('created_at')
            ->get();

        $summary = [
            'subtotal_minor'                    => (int) ($reservation->subtotal_minor ?? 0),
            'fees_minor'                        => (int) ($reservation->fees_minor ?? 0),
            'total_minor'                       => (int) ($reservation->total_minor ?? 0),
            'wallet_platform_credit_used_minor' => (int) ($reservation->wallet_platform_credit_used_minor ?? 0),
            'payable_minor'                     => (int) ($reservation->payable_minor ?? 0),
            'currency'                          => strtoupper((string) ($reservation->currency ?? 'USD')),
            'funding_status'                    => (string) ($reservation->funding_status ?? 'external_only'),
            'payment_status'                    => (string) ($reservation->payment_status ?? 'requires_payment'),
            'provider'                          => (string) ($reservation->provider ?? '-'),
            'payment_intent_id'                 => (string) ($reservation->payment_intent_id ?? ''),
        ];

        return view('superadmin.transactions.show', [
            'reservation' => $reservation,
            'payments'    => $payments,
            'summary'     => $summary,
        ]);
    }

    /**
     * Recompute only payment/compliance-facing amounts.
     * This intentionally avoids coach earnings / payout logic.
     */
    public function recompute(Reservation $reservation)
    {
        DB::transaction(function () use ($reservation) {
            $reservation->refresh();

            $payments = Payment::query()
                ->where('reservation_id', $reservation->id)
                ->lockForUpdate()
                ->get();

            $subtotalMinor = (int) ($reservation->subtotal_minor ?? 0);
            $feesMinor     = (int) ($reservation->fees_minor ?? 0);
            $currency      = strtoupper((string) ($reservation->currency ?? 'USD'));

            foreach ($payments as $payment) {
                $provider = strtolower((string) ($payment->provider ?? ''));

                $payment->update([
                    'service_subtotal_minor' => $subtotalMinor,
                    'client_fee_minor'       => $feesMinor,
                    'platform_fee'           => $feesMinor,
                    'currency'               => $payment->currency ?: $currency,
                    // payout / earnings fields intentionally untouched or should be deprecated
                ]);

                // Optional cleanup if your columns still exist and you want them zeroed:
                if (Schema::hasColumn('payments', 'coach_fee_minor')) {
                    $payment->coach_fee_minor = 0;
                }

                if (Schema::hasColumn('payments', 'coach_earnings')) {
                    $payment->coach_earnings = 0;
                }

                $payment->save();
            }
        });

        return redirect()
            ->route('superadmin.transactions.show', $reservation->id)
            ->with('success', 'Transaction amounts were recomputed successfully.');
    }

    private function buildKpis(array $filters, Request $request): array
    {
        $paymentsQuery = Payment::query()
            ->leftJoin('reservations', 'reservations.id', '=', 'payments.reservation_id');

        $paymentsQuery = $this->applyDateFilter($paymentsQuery, $filters);
        $paymentsQuery = $this->applyTransactionFilters($paymentsQuery, $request, true);

        $reservationsQuery = Reservation::query();
        $reservationsQuery = $this->applyReservationDateFilter($reservationsQuery, $filters);

        $successfulStatuses = ['succeeded', 'paid'];
        $failedStatuses = ['failed', 'cancelled', 'canceled'];
        $processingStatuses = ['processing', 'pending', 'held', 'requires_payment', 'requires_payment_method'];

        $grossProcessedMinor = (clone $paymentsQuery)
            ->whereIn('payments.status', $successfulStatuses)
            ->sum('payments.amount_total');

        $walletVolumeMinor = (clone $paymentsQuery)
            ->where('payments.provider', 'wallet')
            ->whereIn('payments.status', $successfulStatuses)
            ->sum('payments.amount_total');

        $externalVolumeMinor = (clone $paymentsQuery)
            ->whereIn('payments.provider', ['stripe', 'paypal'])
            ->whereIn('payments.status', $successfulStatuses)
            ->sum('payments.amount_total');

        $clientFeesCollectedMinor = (clone $paymentsQuery)
            ->whereIn('payments.status', $successfulStatuses)
            ->sum(DB::raw('COALESCE(payments.client_fee_minor, 0)'));

        $walletCreditsAppliedMinor = (clone $reservationsQuery)
            ->whereIn('payment_status', ['paid', 'requires_payment'])
            ->sum('wallet_platform_credit_used_minor');

        $gatewayPayableMinor = (clone $reservationsQuery)
            ->whereIn('payment_status', ['paid', 'requires_payment'])
            ->sum('payable_minor');

        $successfulTransactions = (clone $paymentsQuery)
            ->whereIn('payments.status', $successfulStatuses)
            ->count();

        $failedTransactions = (clone $paymentsQuery)
            ->whereIn('payments.status', $failedStatuses)
            ->count();

        $processingTransactions = (clone $paymentsQuery)
            ->whereIn('payments.status', $processingStatuses)
            ->count();

        $totalTransactions = (clone $paymentsQuery)->count();

        $totalReservations = (clone $reservationsQuery)->count();
        $walletOnlyReservations = (clone $reservationsQuery)->where('funding_status', 'wallet_only')->count();
        $mixedReservations = (clone $reservationsQuery)->where('funding_status', 'mixed')->count();
        $externalOnlyReservations = (clone $reservationsQuery)->where('funding_status', 'external_only')->count();

        return [
            'gross_processed_minor'        => (int) $grossProcessedMinor,
            'wallet_volume_minor'          => (int) $walletVolumeMinor,
            'external_volume_minor'        => (int) $externalVolumeMinor,
            'client_fees_collected_minor'  => (int) $clientFeesCollectedMinor,
            'wallet_credits_applied_minor' => (int) $walletCreditsAppliedMinor,
            'gateway_payable_minor'        => (int) $gatewayPayableMinor,
            'successful_transactions'      => (int) $successfulTransactions,
            'failed_transactions'          => (int) $failedTransactions,
            'processing_transactions'      => (int) $processingTransactions,
            'total_transactions'           => (int) $totalTransactions,
            'total_reservations'           => (int) $totalReservations,
            'wallet_only_reservations'     => (int) $walletOnlyReservations,
            'mixed_reservations'           => (int) $mixedReservations,
            'external_only_reservations'   => (int) $externalOnlyReservations,
        ];
    }

    private function applyTransactionFilters($query, Request $request, bool $strictTableAliases = false)
    {
        $paymentTable = 'payments';
        $reservationTable = 'reservations';

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($q) use ($search, $paymentTable, $reservationTable) {
                $q->where("{$paymentTable}.provider_payment_id", 'like', "%{$search}%")
                    ->orWhere("{$paymentTable}.provider_order_id", 'like', "%{$search}%")
                    ->orWhere("{$paymentTable}.provider_charge_id", 'like', "%{$search}%")
                    ->orWhere("{$paymentTable}.provider_capture_id", 'like', "%{$search}%")
                    ->orWhere("{$paymentTable}.method", 'like', "%{$search}%")
                    ->orWhere("{$paymentTable}.currency", 'like', "%{$search}%")
                    ->orWhere("{$paymentTable}.reservation_id", 'like', "%{$search}%")
                    ->orWhere("{$reservationTable}.service_title_snapshot", 'like', "%{$search}%")
                    ->orWhere("{$reservationTable}.package_name_snapshot", 'like', "%{$search}%");
            });
        }

        if ($provider = $request->string('provider')->toString()) {
            $query->where("{$paymentTable}.provider", $provider);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where("{$paymentTable}.status", $status);
        }

        if ($fundingStatus = $request->string('funding_status')->toString()) {
            $query->where("{$reservationTable}.funding_status", $fundingStatus);
        }

        if ($method = trim((string) $request->string('method'))) {
            $query->where("{$paymentTable}.method", $method);
        }

        return $query;
    }

    private function resolveFilters(Request $request): array
    {
        $period = (string) $request->input('period', 'monthly');

        if (! in_array($period, ['daily', 'weekly', 'monthly', 'yearly', 'custom', 'all'], true)) {
            $period = 'monthly';
        }

        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $day = $request->input('day') ?: $now->toDateString();
        $month = $request->input('month') ?: $now->format('Y-m');
        $year = (int) ($request->input('year') ?: $now->year);
        $weekDate = $request->input('week_date') ?: $now->toDateString();
        $from = $request->input('from');
        $to = $request->input('to');

        $start = null;
        $end = null;
        $label = 'All Time';

        switch ($period) {
            case 'daily':
                $start = Carbon::parse($day, $tz)->startOfDay();
                $end   = Carbon::parse($day, $tz)->endOfDay();
                $label = 'Daily';
                break;

            case 'weekly':
                $base  = Carbon::parse($weekDate, $tz);
                $start = $base->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $end   = $base->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                $label = 'Weekly';
                break;

            case 'monthly':
                $base  = Carbon::createFromFormat('Y-m', $month, $tz);
                $start = $base->copy()->startOfMonth()->startOfDay();
                $end   = $base->copy()->endOfMonth()->endOfDay();
                $label = 'Monthly';
                break;

            case 'yearly':
                $base  = Carbon::create($year, 1, 1, 0, 0, 0, $tz);
                $start = $base->copy()->startOfYear()->startOfDay();
                $end   = $base->copy()->endOfYear()->endOfDay();
                $label = 'Yearly';
                break;

            case 'custom':
                $start = $from ? Carbon::parse($from, $tz)->startOfDay() : null;
                $end   = $to ? Carbon::parse($to, $tz)->endOfDay() : null;
                $label = 'Custom';
                break;

            case 'all':
            default:
                $label = 'All Time';
                break;
        }

        return [
            'period'    => $period,
            'label'     => $label,
            'day'       => $day,
            'week_date' => $weekDate,
            'month'     => $month,
            'year'      => $year,
            'from'      => $from,
            'to'        => $to,
            'start'     => $start,
            'end'       => $end,
            'timezone'  => $tz,
        ];
    }

    private function applyDateFilter($query, array $filters)
    {
        if ($filters['start'] && $filters['end']) {
            $query->whereBetween('payments.created_at', [
                $filters['start']->copy()->utc(),
                $filters['end']->copy()->utc(),
            ]);
        } elseif ($filters['start']) {
            $query->where('payments.created_at', '>=', $filters['start']->copy()->utc());
        } elseif ($filters['end']) {
            $query->where('payments.created_at', '<=', $filters['end']->copy()->utc());
        }

        return $query;
    }

    private function applyReservationDateFilter($query, array $filters)
    {
        if ($filters['start'] && $filters['end']) {
            $query->whereBetween('created_at', [
                $filters['start']->copy()->utc(),
                $filters['end']->copy()->utc(),
            ]);
        } elseif ($filters['start']) {
            $query->where('created_at', '>=', $filters['start']->copy()->utc());
        } elseif ($filters['end']) {
            $query->where('created_at', '<=', $filters['end']->copy()->utc());
        }

        return $query;
    }
}