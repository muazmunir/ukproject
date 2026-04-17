@extends('superadmin.layout')

@section('title', 'Business Analytics')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-analytics.css') }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Facades\Route;

   $money = function ($minor, $currency = 'USD') {
    $amount = ((int) $minor) / 100;
    return '$' . number_format($amount, 2) . ' ' . strtoupper($currency);
};

    $num = function ($value) {
        return number_format((float) $value);
    };

    $pctFmt = function ($value) {
        return number_format((float) $value, 2) . '%';
    };

    $labelize = function ($value) {
        return ucwords(str_replace('_', ' ', (string) $value));
    };

    $providerLabel = function ($value) {
        $v = strtolower((string) $value);

        return match ($v) {
            'stripe'        => 'Stripe',
            'paypal'        => 'PayPal',
            'wallet'        => 'Wallet',
            'wallet_only'   => 'Wallet Only',
            'mixed'         => 'Mixed Wallet + External',
            'external_only' => 'External Only',
            default         => ucwords(str_replace('_', ' ', (string) $value)),
        };
    };


        $reportTimezone = $reportTimezone ?? (auth()->user()?->timezone ?: config('app.timezone', 'UTC'));

    $displayDate = function ($value, $format = 'd M Y, h:i A') use ($reportTimezone) {
        if (!$value) {
            return '—';
        }

        return \Carbon\Carbon::parse($value)->setTimezone($reportTimezone)->format($format);
    };
    $statusBadge = function ($value) {
        $v = strtolower((string) $value);

        return match (true) {
            in_array($v, ['paid', 'completed', 'succeeded', 'verified', 'settled']) => 'is-success',
            in_array($v, ['processing', 'pending', 'payout_pending', 'transfer_created', 'partial', 'in_dispute']) => 'is-warning',
            in_array($v, ['failed', 'refunded', 'cancelled', 'canceled', 'reversed']) => 'is-danger',
            default => 'is-neutral',
        };
    };

       $safeRoute = function (string $name, array $params = [], string $fallback = '#') {
        return Route::has($name) ? route($name, $params) : $fallback;
    };

    /*
    |--------------------------------------------------------------------------
    | Superadmin Links
    |--------------------------------------------------------------------------
    | Important:
    | - Your superadmin bookings routes are commented in web.php
    | - Your superadmin refunds/payout detail routes are not shown in shared routes
    | - So we safely fallback to "#"
    */

   $reservationUrl = fn ($id) => Route::has('superadmin.bookings.show')
    ? route('superadmin.bookings.show', [$id])
    : '#';

    $transactionsIndexUrl = function (array $extra = []) {
        $query = [];

        $range = request('range', 'monthly');

        $query['period'] = match ($range) {
            'daily'    => 'daily',
            'weekly'   => 'weekly',
            'monthly'  => 'monthly',
            'yearly'   => 'yearly',
            'custom'   => 'custom',
            'lifetime' => 'all',
            default    => 'monthly',
        };

        if ($range === 'daily' && request('day')) {
            $query['day'] = request('day');
        }

        if ($range === 'weekly' && request('week_day')) {
            $query['week_date'] = request('week_day');
        }

        if ($range === 'monthly') {
            if (request('year')) {
                $query['year'] = request('year');
            }
            if (request('month')) {
                $month = str_pad((string) request('month'), 2, '0', STR_PAD_LEFT);
                $query['month'] = request('year', now()->year) . '-' . $month;
            }
        }

        if ($range === 'yearly' && request('year')) {
            $query['year'] = request('year');
        }

        if ($range === 'custom') {
            if (request('from')) {
                $query['from'] = request('from');
            }
            if (request('to')) {
                $query['to'] = request('to');
            }
        }

        $query = array_merge($query, $extra);

        return Route::has('superadmin.transactions.index')
            ? route('superadmin.transactions.index', $query)
            : '#';
    };

    $coachUrl = fn ($id) => $safeRoute('superadmin.coaches.stats', [$id], '#');
    $clientUrl = fn ($id) => $safeRoute('superadmin.clients.stats', [$id], '#');

    $payoutUrl = fn ($id) => $safeRoute('superadmin.payouts.show', [$id], '#');
    $refundUrl = fn ($id) => $safeRoute('superadmin.refunds.show', [$id], '#');

    $disputeUrl = fn ($id) => $safeRoute('superadmin.disputes.show', [$id], '#');

    $paymentMethodLabel = function ($value) {
        return match ((string) $value) {
            'VISA'            => 'Visa',
            'MASTERCARD'      => 'Mastercard',
            'AMEX'            => 'American Express',
            'DISCOVER'        => 'Discover',
            'CARD'            => 'Card',
            'KLARNA'          => 'Klarna',
            'paypal'          => 'PayPal',
            'PLATFORM_CREDIT' => 'Platform Credit',
            default           => ucwords(str_replace('_', ' ', (string) $value)),
        };
    };

    $currentTab = request('tab', 'overview');

    $tabs = [
        'overview'     => 'Overview',
        'business'     => 'Business Money',
        'traffic'      => 'Traffic & Conversion',
        'services'     => 'Services & Categories',
        'coaches'      => 'Coaches',
        'clients'      => 'Clients',
        'reviews'      => 'Reviews',
        'payments'     => 'Payments',
        'refunds'      => 'Refunds',
        'withdrawals'  => 'Withdrawals',
        'reservations' => 'Reservations',
        'disputes'     => 'Disputes',
    ];
@endphp

<div class="sa-page">
    <div class="sa-shell">

        {{-- =========================================================
            PAGE HEADER
        ========================================================== --}}
        <div class="sa-head">
            <div class="sa-head__left">
                <h1 class="sa-title">Business Analytics</h1>
                <div class="sa-period">{{ $periodLabel ?? 'All Time' }}</div>
            </div>

            <div class="sa-head__right">
                <a href="{{ route('superadmin.analytics.index') }}" class="sa-btn sa-btn--ghost">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Reset</span>
                </a>
            </div>
        </div>

        {{-- =========================================================
            FILTERS
        ========================================================== --}}
       {{-- =========================================================
    FILTERS
========================================================== --}}
<form method="GET" action="{{ route('superadmin.analytics.index') }}" class="sa-filters" id="analyticsFilterForm">
    <input type="hidden" name="tab" value="{{ $currentTab }}">

    <div class="sa-filterGrid">
        <div class="sa-field sa-field--search">
            <label>Search</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search Reservation, Coach, Client, Service, Refund, Payout...">
        </div>

        <div class="sa-field">
            <label>Range</label>
            <select name="range" id="analyticsRange">
                <option value="daily" {{ ($range ?? '') === 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ ($range ?? '') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="monthly" {{ ($range ?? '') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                <option value="yearly" {{ ($range ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                <option value="custom" {{ ($range ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                <option value="lifetime" {{ ($range ?? '') === 'lifetime' ? 'selected' : '' }}>All Time</option>
            </select>
        </div>

        {{-- Daily --}}
        <div class="sa-field js-range-field" id="dailyField">
            <label>Specific Day</label>
            <input
                type="date"
                name="day"
                id="day"
                value="{{ request('day', optional($dateFrom)->format('Y-m-d')) }}"
            >
        </div>

        {{-- Weekly base day --}}
        <div class="sa-field js-range-field" id="weeklyField">
            <label>Week Date</label>
            <input
    type="date"
    name="week_day"
    id="week_day"
    value="{{ request('week_day', optional($dateFrom)->format('Y-m-d')) }}"
>
        </div>

        {{-- Monthly --}}
        <div class="sa-field js-range-field" id="monthlyField">
            <label>Specific Month</label>
            <input
                type="month"
                id="month_picker"
                value="{{ request('year') && request('month') ? request('year') . '-' . str_pad(request('month'), 2, '0', STR_PAD_LEFT) : now()->format('Y-m') }}"
            >
            <input type="hidden" name="month" id="month_hidden" value="{{ request('month', now()->month) }}">
            <input type="hidden" name="year" id="month_year_hidden" value="{{ request('year', now()->year) }}">
        </div>

        {{-- Yearly --}}
        <div class="sa-field js-range-field" id="yearlyField">
            <label>Specific Year</label>
            <input
                type="number"
                name="year"
                id="year"
                min="2000"
                max="2100"
                value="{{ request('year', now()->year) }}"
            >
        </div>

        {{-- Custom --}}
        <div class="sa-field js-range-field" id="fromField">
            <label>From</label>
            <input type="date" name="from" id="from" value="{{ request('from', optional($dateFrom)->format('Y-m-d')) }}">
        </div>

        <div class="sa-field js-range-field" id="toField">
            <label>To</label>
            <input type="date" name="to" id="to" value="{{ request('to', optional($dateTo)->format('Y-m-d')) }}">
        </div>

        {{-- Time range --}}
        <div class="sa-field js-range-field" id="timeFromField">
            <label>Time From</label>
            <input type="time" name="time_from" id="time_from" value="{{ request('time_from') }}">
        </div>

        <div class="sa-field js-range-field" id="timeToField">
            <label>Time To</label>
            <input type="time" name="time_to" id="time_to" value="{{ request('time_to') }}">
        </div>

        <div class="sa-field">
            <label>Service</label>
            <select name="service">
                <option value="">All Services</option>
                @foreach($serviceOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($serviceFilter ?? '') === $option ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Category</label>
            <select name="category">
                <option value="">All Categories</option>
                @foreach($categoryOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($categoryFilter ?? '') === $option ? 'selected' : '' }}>
                        {{ str_replace('_', ' ', $option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Coach</label>
            <input type="text" name="coach" value="{{ $coachFilter }}" placeholder="Coach Name">
        </div>

        <div class="sa-field">
            <label>Client</label>
            <input type="text" name="client" value="{{ $clientFilter }}" placeholder="Client Name">
        </div>

        <div class="sa-field">
            <label>Provider</label>
            <select name="provider">
                <option value="">All Providers</option>
                @foreach($providerOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($paymentProvider ?? '') === $option ? 'selected' : '' }}>
                        {{ $providerLabel($option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Funding</label>
            <select name="funding_status">
                <option value="">All Funding</option>
                @foreach($fundingOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($fundingFilter ?? '') === $option ? 'selected' : '' }}>
                        {{ $providerLabel($option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Settlement</label>
            <select name="settlement_status">
                <option value="">All Settlement</option>
                @foreach($settlementOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($settlementFilter ?? '') === $option ? 'selected' : '' }}>
                        {{ $labelize($option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Reservation Status</label>
            <select name="reservation_status">
                <option value="">All Reservation Status</option>
                @foreach($reservationStatusOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($reservationStatus ?? '') === $option ? 'selected' : '' }}>
                        {{ $labelize($option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Payout Status</label>
            <select name="payout_status">
                <option value="">All Payout Status</option>
                @foreach($payoutStatusOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($payoutStatus ?? '') === $option ? 'selected' : '' }}>
                        {{ $labelize($option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Refund Status</label>
            <select name="refund_status">
                <option value="">All Refund Status</option>
                @foreach($refundStatusOptions ?? [] as $option)
                    <option value="{{ $option }}" {{ ($refundStatus ?? '') === $option ? 'selected' : '' }}>
                        {{ $labelize($option) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sa-field">
            <label>Sort</label>
            <select name="sort">
                <option value="desc" {{ ($sort ?? '') === 'desc' ? 'selected' : '' }}>High To Low</option>
                <option value="asc" {{ ($sort ?? '') === 'asc' ? 'selected' : '' }}>Low To High</option>
            </select>
        </div>

        <div class="sa-field">
            <label>Rating Sort</label>
            <select name="rating_sort">
                <option value="desc" {{ ($ratingSort ?? '') === 'desc' ? 'selected' : '' }}>High To Low</option>
                <option value="asc" {{ ($ratingSort ?? '') === 'asc' ? 'selected' : '' }}>Low To High</option>
            </select>
        </div>
    </div>

    <div class="sa-filterActions">
        <button type="submit" class="sa-btn sa-btn--primary">
            <i class="bi bi-funnel"></i>
            <span>Apply Filters</span>
        </button>

        <a href="{{ route('superadmin.analytics.index', ['tab' => $currentTab]) }}" class="sa-btn sa-btn--ghost">
            <i class="bi bi-x-circle"></i>
            <span>Clear Filters</span>
        </a>
    </div>
</form>

        {{-- =========================================================
            TABS
        ========================================================== --}}
        <div class="sa-tabs">
            @foreach($tabs as $key => $label)
                <a
                    href="{{ route('superadmin.analytics.index', array_merge(request()->query(), ['tab' => $key])) }}"
                    class="sa-tab {{ $currentTab === $key ? 'is-active' : '' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- =========================================================
            OVERVIEW
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'overview' ? 'is-active' : '' }}">
            <div class="sa-section">
                <div class="sa-section__head">
                    <h2>Business Money</h2>
                </div>

                <div class="sa-kpiGrid sa-kpiGrid--primary">
                    @foreach(($businessMoneyKpis ?? []) as $kpi)
                        <div class="sa-kpiCard {{ !empty($kpi['highlight']) ? 'is-highlight' : '' }}">
                            <div class="sa-kpiLabel">{{ $kpi['label'] ?? '—' }}</div>
                            <div class="sa-kpiValue">{{ $money($kpi['value_minor'] ?? 0) }}</div>
                            <div class="sa-kpiMeta">{{ $kpi['meta'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="sa-section">
                <div class="sa-section__head">
                    <h2>Core Performance</h2>
                </div>

                <div class="sa-kpiGrid">
                    <div class="sa-kpiCard">
                        <div class="sa-kpiLabel">Payments Count</div>
                        <div class="sa-kpiValue">{{ $num($paymentCount ?? 0) }}</div>
                    </div>
                    <div class="sa-kpiCard">
                        <div class="sa-kpiLabel">Reservations</div>
                        <div class="sa-kpiValue">{{ $num($reservationsCount ?? 0) }}</div>
                    </div>
                    <div class="sa-kpiCard">
                        <div class="sa-kpiLabel">Paid Reservations</div>
                        <div class="sa-kpiValue">{{ $num($paidReservationsCount ?? 0) }}</div>
                    </div>
                    <div class="sa-kpiCard">
                        <div class="sa-kpiLabel">Business Retention Rate</div>
                        <div class="sa-kpiValue">{{ $pctFmt($businessRetentionRate ?? 0) }}</div>
                    </div>
                    <div class="sa-kpiCard">
                        <div class="sa-kpiLabel">Refund Outflow</div>
                        <div class="sa-kpiValue">{{ $money($refundOutflowMinor ?? 0) }}</div>
                    </div>
                    <div class="sa-kpiCard">
                        <div class="sa-kpiLabel">Total Withdrawn</div>
                        <div class="sa-kpiValue">{{ $money($totalWithdrawnMinor ?? 0) }}</div>
                    </div>
                </div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Business Money Trend</h3></div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="businessMoneyTrendChart"></canvas></div>
                    <div class="sa-chartLegend" id="businessMoneyTrendLegend"></div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Business Money Split</h3></div>
                    <div class="sa-chartBox sa-chartBox--pie"><canvas id="businessMoneyPieChart"></canvas></div>
                    <div class="sa-chartLegend" id="businessMoneyPieLegend"></div>
                </div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Traffic & Conversion Trend</h3></div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="trafficTrendChart"></canvas></div>
                    <div class="sa-chartLegend" id="trafficTrendLegend"></div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Category Sales</h3></div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="categorySalesChart"></canvas></div>
                    <div class="sa-chartLegend" id="categorySalesLegend"></div>
                </div>
            </div>
        </div>

        {{-- =========================================================
            BUSINESS MONEY
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'business' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard is-highlight">
                    <div class="sa-kpiLabel">Gross Inflow</div>
                    <div class="sa-kpiValue">{{ $money($incomingOutgoing['gross_inflow_minor'] ?? 0) }}</div>
                </div>
                <div class="sa-kpiCard">
                    <div class="sa-kpiLabel">External Inflow</div>
                    <div class="sa-kpiValue">{{ $money($incomingOutgoing['external_inflow_minor'] ?? 0) }}</div>
                </div>
                <div class="sa-kpiCard">
                    <div class="sa-kpiLabel">Wallet Inflow</div>
                    <div class="sa-kpiValue">{{ $money($incomingOutgoing['wallet_inflow_minor'] ?? 0) }}</div>
                </div>
                <div class="sa-kpiCard">
                    <div class="sa-kpiLabel">Refund To Wallet</div>
                    <div class="sa-kpiValue">{{ $money($incomingOutgoing['refund_wallet_minor'] ?? 0) }}</div>
                </div>
                <div class="sa-kpiCard">
                    <div class="sa-kpiLabel">Refund To Original Method</div>
                    <div class="sa-kpiValue">{{ $money($incomingOutgoing['refund_original_minor'] ?? 0) }}</div>
                </div>
                <div class="sa-kpiCard is-highlight">
                    <div class="sa-kpiLabel">Platform Net Earned</div>
                    <div class="sa-kpiValue">{{ $money($incomingOutgoing['platform_net_earned_minor'] ?? 0) }}</div>
                </div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Payment Providers</h3></div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="providerBreakdownChart"></canvas></div>
                    <div class="sa-chartLegend" id="providerBreakdownLegend"></div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Funding Split</h3></div>
                    <div class="sa-chartBox sa-chartBox--pie"><canvas id="fundingPieChart"></canvas></div>
                    <div class="sa-chartLegend" id="fundingPieLegend"></div>
                </div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Settlement Split</h3></div>
                    <div class="sa-chartBox sa-chartBox--pie"><canvas id="settlementPieChart"></canvas></div>
                    <div class="sa-chartLegend" id="settlementPieLegend"></div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Refund Split</h3></div>
                    <div class="sa-chartBox sa-chartBox--pie"><canvas id="refundPieChart"></canvas></div>
                    <div class="sa-chartLegend" id="refundPieLegend"></div>
                </div>
            </div>

            <div class="sa-panel">
                <div class="sa-panel__head"><h3>Payment Method Breakdown</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Transactions</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                          @forelse($paymentMethodBreakdown ?? [] as $row)
    @php
        $methodValue = (string) ($row->payment_method ?? '');
        $methodLink = $transactionsIndexUrl([
            'method' => $methodValue,
        ]);
    @endphp
    <tr>
        <td>
            @if($methodLink !== '#')
                <a href="{{ $methodLink }}" class="sa-link">
                    {{ $paymentMethodLabel($methodValue ?: 'Unknown') }}
                </a>
            @else
                {{ $paymentMethodLabel($methodValue ?: 'Unknown') }}
            @endif
        </td>
        <td>{{ $num($row->tx_count ?? 0) }}</td>
        <td>{{ $money($row->amount_minor ?? 0) }}</td>
    </tr>
@empty
                                <tr><td colspan="3" class="sa-empty">No Data Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- =========================================================
            TRAFFIC
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'traffic' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Visitors</div><div class="sa-kpiValue">{{ $num($trafficAndConversion['visitors'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Site Visits</div><div class="sa-kpiValue">{{ $num($trafficAndConversion['site_visits'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Booking Page Visits</div><div class="sa-kpiValue">{{ $num($trafficAndConversion['booking_page_visits'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Checkout Started</div><div class="sa-kpiValue">{{ $num($trafficAndConversion['checkout_started'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Paid Bookings</div><div class="sa-kpiValue">{{ $num($trafficAndConversion['paid_bookings'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Visit To Paid Booking Rate</div><div class="sa-kpiValue">{{ $pctFmt($trafficAndConversion['visit_to_paid_booking_rate'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Visit To Signup Rate</div><div class="sa-kpiValue">{{ $pctFmt($trafficAndConversion['visit_to_signup_rate'] ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Signup To Verification Rate</div><div class="sa-kpiValue">{{ $pctFmt($trafficAndConversion['signup_to_verification_rate'] ?? 0) }}</div></div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Traffic & Signup Trend</h3></div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="signupTrafficChart"></canvas></div>
                    <div class="sa-chartLegend" id="signupTrafficLegend"></div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Booking Journey</h3></div>
                    <div class="sa-kpiGrid sa-kpiGrid--mini">
                        <div class="sa-kpiCard"><div class="sa-kpiLabel">Visit</div><div class="sa-kpiValue">{{ $num($siteVisitCount ?? 0) }}</div></div>
                        <div class="sa-kpiCard"><div class="sa-kpiLabel">Booking Page</div><div class="sa-kpiValue">{{ $num($bookingPageVisitCount ?? 0) }}</div></div>
                        <div class="sa-kpiCard"><div class="sa-kpiLabel">Checkout</div><div class="sa-kpiValue">{{ $num($checkoutStartedCount ?? 0) }}</div></div>
                        <div class="sa-kpiCard"><div class="sa-kpiLabel">Paid</div><div class="sa-kpiValue">{{ $num($bookingPaidEventCount ?? 0) }}</div></div>
                    </div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="funnelTrendChart"></canvas></div>
                    <div class="sa-chartLegend" id="funnelTrendLegend"></div>
                </div>
            </div>
        </div>

        {{-- =========================================================
            SERVICES
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'services' ? 'is-active' : '' }}">
            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Top Services People Are Purchasing</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Service</th>
                                    <th>Category</th>
                                    <th>Bookings</th>
                                    <th>Sales</th>
                                    <th>Platform Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topServices ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $row->service_title ?? '—' }}</td>
                                        <td>{{ str_replace('_', ' ', $row->category_name ?? 'Uncategorized') }}</td>
                                        <td>{{ $num($row->bookings_count ?? 0) }}</td>
                                        <td>{{ $money($row->sales_minor ?? 0) }}</td>
                                        <td>{{ $money($row->platform_earned_minor ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="sa-empty">No Data Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Category Sales</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Category</th>
                                    <th>Bookings</th>
                                    <th>Sales</th>
                                    <th>Platform Earned</th>
                                    <th>Coach Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryBreakdown ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ str_replace('_', ' ', $row->category_name ?? 'Uncategorized') }}</td>
                                        <td>{{ $num($row->bookings_count ?? 0) }}</td>
                                        <td>{{ $money($row->sales_minor ?? 0) }}</td>
                                        <td>{{ $money($row->platform_earned_minor ?? 0) }}</td>
                                        <td>{{ $money($row->coach_earned_minor ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="sa-empty">No Data Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="sa-panel">
                <div class="sa-panel__head"><h3>What Services Coaches Are Building</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Coach</th>
                                <th>Category</th>
                                <th>Services Built</th>
                                <th>Bookings</th>
                                <th>Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coachBuiltCategories ?? [] as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        @if(!empty($row->coach_id))
                                            <a href="{{ $coachUrl($row->coach_id) }}" class="sa-link">{{ $row->coach_name ?? 'Unknown Coach' }}</a>
                                        @else
                                            {{ $row->coach_name ?? 'Unknown Coach' }}
                                        @endif
                                    </td>
                                    <td>{{ str_replace('_', ' ', $row->category_name ?? 'Uncategorized') }}</td>
                                    <td>{{ $num($row->services_count ?? 0) }}</td>
                                    <td>{{ $num($row->bookings_count ?? 0) }}</td>
                                    <td>{{ $money($row->sales_minor ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="sa-empty">No Data Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- =========================================================
            COACHES
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'coaches' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Coach Released</div><div class="sa-kpiValue">{{ $money($coachReleasedMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Coach Compensation</div><div class="sa-kpiValue">{{ $money($coachCompMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Coach Penalty</div><div class="sa-kpiValue">{{ $money($coachPenaltyMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard is-highlight"><div class="sa-kpiLabel">Coach Net Benefit</div><div class="sa-kpiValue">{{ $money($coachNetBenefitMinor ?? 0) }}</div></div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Top Coaches</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Coach</th>
                                    <th>Bookings</th>
                                    <th>Sales</th>
                                    <th>Coach Net Benefit</th>
                                    <th>Platform Earned</th>
                                    <th>Rating</th>
                                    <th>Reviews</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topCoaches ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            @if(!empty($row->coach_id))
                                                <a href="{{ $coachUrl($row->coach_id) }}" class="sa-link">{{ $row->coach_name ?? 'Unknown Coach' }}</a>
                                            @else
                                                {{ $row->coach_name ?? 'Unknown Coach' }}
                                            @endif
                                        </td>
                                        <td>{{ $num($row->bookings_count ?? 0) }}</td>
                                        <td>{{ $money($row->sales_minor ?? 0) }}</td>
                                        <td>{{ $money($row->coach_net_benefit_minor ?? 0) }}</td>
                                        <td>{{ $money($row->platform_earned_minor ?? 0) }}</td>
                                        <td>{{ number_format((float) ($row->coach_rating_avg ?? 0), 2) }}</td>
                                        <td>{{ $num($row->coach_rating_count ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="sa-empty">No Data Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Lowest Rated Coaches</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Coach</th>
                                    <th>Average Rating</th>
                                    <th>Reviews</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($worstCoaches ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            @if(!empty($row->id))
                                                <a href="{{ $coachUrl($row->id) }}" class="sa-link">{{ $row->coach_name ?? 'Unknown Coach' }}</a>
                                            @else
                                                {{ $row->coach_name ?? 'Unknown Coach' }}
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) ($row->coach_rating_avg ?? 0), 2) }}</td>
                                        <td>{{ $num($row->coach_rating_count ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="sa-empty">No Data Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- =========================================================
            CLIENTS
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'clients' ? 'is-active' : '' }}">
            <div class="sa-panel">
                <div class="sa-panel__head"><h3>Top Clients</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Bookings</th>
                                <th>Total Spending</th>
                                <th>Service Value</th>
                                <th>Fee Value</th>
                                <th>Refunded</th>
                                <th>Client Penalty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topClients ?? [] as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        @if(!empty($row->client_id))
                                            <a href="{{ $clientUrl($row->client_id) }}" class="sa-link">{{ $row->client_name ?? 'Unknown Client' }}</a>
                                        @else
                                            {{ $row->client_name ?? 'Unknown Client' }}
                                        @endif
                                    </td>
                                    <td>{{ $num($row->bookings_count ?? 0) }}</td>
                                    <td>{{ $money($row->spending_minor ?? 0) }}</td>
                                    <td>{{ $money($row->service_minor ?? 0) }}</td>
                                    <td>{{ $money($row->fee_minor ?? 0) }}</td>
                                    <td>{{ $money($row->refunded_minor ?? 0) }}</td>
                                    <td>{{ $money($row->client_penalty_minor ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="sa-empty">No Data Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- =========================================================
            REVIEWS
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'reviews' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard"><div class="sa-kpiLabel">All Reviews</div><div class="sa-kpiValue">{{ $num($reviewCount ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Coach Reviews</div><div class="sa-kpiValue">{{ $num($coachReviewCount ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Average Coach Rating</div><div class="sa-kpiValue">{{ number_format((float) ($averageCoachRating ?? 0), 2) }}</div></div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Review Volume Trend</h3></div>
                    <div class="sa-chartBox sa-chartBox--lg"><canvas id="reviewTrendChart"></canvas></div>
                    <div class="sa-chartLegend" id="reviewTrendLegend"></div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Rating Breakdown</h3></div>
                    <div class="sa-chartBox sa-chartBox--pie"><canvas id="ratingBreakdownPieChart"></canvas></div>
                    <div class="sa-chartLegend" id="ratingBreakdownPieLegend"></div>
                </div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Top Rated Coaches</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Coach</th>
                                    <th>Average Rating</th>
                                    <th>Reviews</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topRatedCoaches ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><a href="{{ $coachUrl($row->id) }}" class="sa-link">{{ $row->coach_name ?? 'Unknown Coach' }}</a></td>
                                        <td>{{ number_format((float) ($row->coach_rating_avg ?? 0), 2) }}</td>
                                        <td>{{ $num($row->coach_rating_count ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="sa-empty">No Data Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Recent Reviews</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Reviewee</th>
                                    <th>Role</th>
                                    <th>Reservation</th>
                                    <th>Stars</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reviewRows ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ (($reviewRows->currentPage() - 1) * $reviewRows->perPage()) + $index + 1 }}</td>
                                        <td>
                                            @if(($row->reviewee_role ?? '') === 'coach' && !empty($row->reviewee_id))
                                                <a href="{{ $coachUrl($row->reviewee_id) }}" class="sa-link">{{ $row->reviewee_name ?? 'Unknown User' }}</a>
                                            @elseif(!empty($row->reviewee_id))
                                                <a href="{{ $clientUrl($row->reviewee_id) }}" class="sa-link">{{ $row->reviewee_name ?? 'Unknown User' }}</a>
                                            @else
                                                {{ $row->reviewee_name ?? 'Unknown User' }}
                                            @endif
                                        </td>
                                        <td>{{ $labelize($row->reviewee_role ?? '—') }}</td>
                                        <td>
                                            @if(!empty($row->reservation_id))
                                                <a href="{{ $reservationUrl($row->reservation_id) }}" class="sa-link">#{{ $row->reservation_id }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) ($row->stars ?? 0), 1) }}</td>
                                        <td>{{ $displayDate($row->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="sa-empty">No Reviews Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(isset($reviewRows) && method_exists($reviewRows, 'links'))
                        <div class="sa-pager">{{ $reviewRows->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- =========================================================
            PAYMENTS
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'payments' ? 'is-active' : '' }}">
            <div class="sa-panel">
                <div class="sa-panel__head"><h3>Payments</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reservation</th>
                                <th>Service</th>
                                <th>Client</th>
                                <th>Coach</th>
                                <th>Provider</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Amount</th>
                                {{-- <th>Refunded</th> --}}
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentRows ?? [] as $index => $row)
                                <tr>
                                    <td>{{ (($paymentRows->currentPage() - 1) * $paymentRows->perPage()) + $index + 1 }}</td>
                                    <td>@if(!empty($row->reservation_id))<a href="{{ $reservationUrl($row->reservation_id) }}" class="sa-link">#{{ $row->reservation_id }}</a>@else — @endif</td>
                                    <td>{{ $row->service_title ?? '—' }}</td>
                                    <td>{{ $row->client_name ?? '—' }}</td>
                                    <td>{{ $row->coach_name ?? '—' }}</td>
                                    <td>
    @php
        $providerValue = (string) ($row->provider ?? '');
        $providerLink = $transactionsIndexUrl([
            'provider' => $providerValue,
        ]);
    @endphp

    @if($providerLink !== '#' && $providerValue)
        <a href="{{ $providerLink }}" class="sa-link">
            {{ $providerLabel($providerValue) }}
        </a>
    @else
        {{ $providerLabel($providerValue ?: 'unknown') }}
    @endif
</td>
                                    <td>
    @php
        $methodValue = (string) ($row->method ?? '');
        $methodLink = $transactionsIndexUrl([
            'method' => $methodValue,
        ]);
    @endphp

    @if($methodLink !== '#' && $methodValue)
        <a href="{{ $methodLink }}" class="sa-link">
            {{ $paymentMethodLabel($methodValue) }}
        </a>
    @else
        {{ $paymentMethodLabel($methodValue ?: 'unknown') }}
    @endif
</td>
                                <td>
    @php
        $statusValue = (string) ($row->status ?? '');
        $statusLink = $transactionsIndexUrl([
            'status' => $statusValue,
        ]);
    @endphp

    @if($statusLink !== '#' && $statusValue)
        <a href="{{ $statusLink }}" class="sa-link">
            <span class="sa-badge {{ $statusBadge($statusValue) }}">
                {{ $labelize($statusValue) }}
            </span>
        </a>
    @else
        <span class="sa-badge {{ $statusBadge($statusValue) }}">
            {{ $labelize($statusValue ?: 'unknown') }}
        </span>
    @endif
</td>
                                    <td>{{ $money($row->amount_total ?? 0, $row->currency ?? 'USD') }}</td>
                                    {{-- <td>{{ $money($row->refunded_minor ?? 0, $row->currency ?? 'USD') }}</td> --}}
                                    <td>{{ $displayDate($row->succeeded_at ?? $row->created_at) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="sa-empty">No Payments Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($paymentRows) && method_exists($paymentRows, 'links'))
                    <div class="sa-pager">{{ $paymentRows->links() }}</div>
                @endif
            </div>
        </div>

        {{-- =========================================================
            REFUNDS
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'refunds' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Refunded</div><div class="sa-kpiValue">{{ $money($refundSucceededMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Partial Refund</div><div class="sa-kpiValue">{{ $money($refundPartialMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Processing Refund</div><div class="sa-kpiValue">{{ $money($refundProcessingMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard is-highlight"><div class="sa-kpiLabel">Refund Overall</div><div class="sa-kpiValue">{{ $money($refundOverallMinor ?? 0) }}</div></div>
            </div>

            <div class="sa-panel">
                <div class="sa-panel__head"><h3>Refund Records</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Refund</th>
                                <th>Reservation</th>
                                <th>Service</th>
                                <th>Client</th>
                                <th>Coach</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Refunded</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($refundRows ?? [] as $index => $row)
                                <tr>
                                    <td>{{ (($refundRows->currentPage() - 1) * $refundRows->perPage()) + $index + 1 }}</td>
                                    <td>@if($refundUrl($row->id) !== '#')<a href="{{ $refundUrl($row->id) }}" class="sa-link">#{{ $row->id }}</a>@else #{{ $row->id }} @endif</td>
                                    <td>@if(!empty($row->reservation_id))<a href="{{ $reservationUrl($row->reservation_id) }}" class="sa-link">#{{ $row->reservation_id }}</a>@else — @endif</td>
                                    <td>{{ $row->service_title ?? '—' }}</td>
                                    <td>{{ $row->client_name ?? '—' }}</td>
                                    <td>{{ $row->coach_name ?? '—' }}</td>
                                    <td>{{ $providerLabel($row->provider ?? 'unknown') }}</td>
                                    <td><span class="sa-badge {{ $statusBadge($row->status ?? '') }}">{{ $labelize($row->status ?? 'unknown') }}</span></td>
                                    <td>{{ $money($row->requested_amount_minor ?? 0, $row->currency ?? 'USD') }}</td>
                                    <td>{{ $money((($row->refunded_to_wallet_minor ?? 0) + ($row->refunded_to_original_minor ?? 0)), $row->currency ?? 'USD') }}</td>
                                    <td>{{ $displayDate($row->processed_at ?? $row->requested_at) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="sa-empty">No Refunds Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($refundRows) && method_exists($refundRows, 'links'))
                    <div class="sa-pager">{{ $refundRows->links() }}</div>
                @endif
            </div>
        </div>

        {{-- =========================================================
            WITHDRAWALS
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'withdrawals' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Withdrawals Count</div><div class="sa-kpiValue">{{ $num($withdrawalCount ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Paid Withdrawn</div><div class="sa-kpiValue">{{ $money($paidWithdrawnMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Pending Withdrawn</div><div class="sa-kpiValue">{{ $money($pendingWithdrawnMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Failed Withdrawn</div><div class="sa-kpiValue">{{ $money($failedWithdrawnMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard is-highlight"><div class="sa-kpiLabel">Total Withdrawn</div><div class="sa-kpiValue">{{ $money($totalWithdrawnMinor ?? 0) }}</div></div>
            </div>

            <div class="sa-grid sa-grid--2">
                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Withdrawals By Coach</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Coach</th>
                                    <th>Payout Count</th>
                                    <th>Withdrawn</th>
                                    <th>Paid</th>
                                    <th>Pending</th>
                                    <th>Failed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($withdrawalsByCoach ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            @if(!empty($row->coach_user_id))
                                                <a href="{{ $coachUrl($row->coach_user_id) }}" class="sa-link">{{ $row->coach_name ?? 'Unknown Coach' }}</a>
                                            @else
                                                {{ $row->coach_name ?? 'Unknown Coach' }}
                                            @endif
                                        </td>
                                        <td>{{ $num($row->payout_count ?? 0) }}</td>
                                        <td>{{ $money($row->withdrawn_minor ?? 0) }}</td>
                                        <td>{{ $money($row->paid_minor ?? 0) }}</td>
                                        <td>{{ $money($row->pending_minor ?? 0) }}</td>
                                        <td>{{ $money($row->failed_minor ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="sa-empty">No Withdrawal Data Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sa-panel">
                    <div class="sa-panel__head"><h3>Payout Records</h3></div>
                    <div class="sa-tableWrap">
                        <table class="sa-table sa-table--center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Payout</th>
                                    <th>Coach</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Reservations</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payoutRows ?? [] as $index => $row)
                                    <tr>
                                        <td>{{ (($payoutRows->currentPage() - 1) * $payoutRows->perPage()) + $index + 1 }}</td>
                                        <td>@if($payoutUrl($row->id) !== '#')<a href="{{ $payoutUrl($row->id) }}" class="sa-link">#{{ $row->id }}</a>@else #{{ $row->id }} @endif</td>
                                        <td>{{ $row->coach_name ?? 'Unknown Coach' }}</td>
                                        <td>{{ $providerLabel($row->provider ?? 'unknown') }}</td>
                                        <td><span class="sa-badge {{ $statusBadge($row->status ?? '') }}">{{ $labelize($row->status ?? 'unknown') }}</span></td>
                                        <td>{{ $money($row->amount_minor ?? 0, $row->currency ?? 'USD') }}</td>
                                        <td>{{ $num($row->reservation_count ?? 0) }}</td>
                                      <td>{{ $displayDate($row->paid_at ?? $row->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="sa-empty">No Payouts Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(isset($payoutRows) && method_exists($payoutRows, 'links'))
                        <div class="sa-pager">{{ $payoutRows->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- =========================================================
            RESERVATIONS
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'reservations' ? 'is-active' : '' }}">
            <div class="sa-kpiGrid">
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Completed</div><div class="sa-kpiValue">{{ $num($completedCount ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Cancelled</div><div class="sa-kpiValue">{{ $num($cancelledCount ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">No Show</div><div class="sa-kpiValue">{{ $num($noShowCount ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Service Total</div><div class="sa-kpiValue">{{ $money($reservationSubtotalMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Fees Total</div><div class="sa-kpiValue">{{ $money($reservationFeesMinor ?? 0) }}</div></div>
                <div class="sa-kpiCard"><div class="sa-kpiLabel">Reservation Total</div><div class="sa-kpiValue">{{ $money($reservationTotalMinor ?? 0) }}</div></div>
            </div>

            <div class="sa-panel">
                <div class="sa-panel__head"><h3>Reservation Records</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reservation</th>
                                <th>Service</th>
                                <th>Client</th>
                                <th>Coach</th>
                                <th>Status</th>
                                <th>Settlement</th>
                                <th>Funding</th>
                                <th>Total</th>
                                <th>Refunded</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reservationRows ?? [] as $index => $row)
                                <tr>
                                    <td>{{ (($reservationRows->currentPage() - 1) * $reservationRows->perPage()) + $index + 1 }}</td>
                                    <td><a href="{{ $reservationUrl($row->id) }}" class="sa-link">#{{ $row->id }}</a></td>
                                    <td>{{ $row->service_title ?? '—' }}</td>
                                    <td>@if(!empty($row->client_id))<a href="{{ $clientUrl($row->client_id) }}" class="sa-link">{{ $row->client_name ?? 'Unknown Client' }}</a>@else {{ $row->client_name ?? 'Unknown Client' }} @endif</td>
                                    <td>@if(!empty($row->coach_id))<a href="{{ $coachUrl($row->coach_id) }}" class="sa-link">{{ $row->coach_name ?? 'Unknown Coach' }}</a>@else {{ $row->coach_name ?? 'Unknown Coach' }} @endif</td>
                                    <td><span class="sa-badge {{ $statusBadge($row->status ?? '') }}">{{ $labelize($row->status ?? 'unknown') }}</span></td>
                                    <td><span class="sa-badge {{ $statusBadge($row->settlement_status ?? '') }}">{{ $labelize($row->settlement_status ?? 'unknown') }}</span></td>
                               <td>
    @php
        $fundingValue = (string) ($row->funding_status ?? '');
        $fundingLink = $transactionsIndexUrl([
            'funding_status' => $fundingValue,
        ]);
    @endphp

    @if($fundingLink !== '#' && $fundingValue)
        <a href="{{ $fundingLink }}" class="sa-link">
            {{ $providerLabel($fundingValue) }}
        </a>
    @else
        {{ $providerLabel($fundingValue ?: 'unknown') }}
    @endif
</td>
                                    <td>{{ $money($row->total_minor ?? 0, $row->currency ?? 'USD') }}</td>
                                    <td>{{ $money($row->refund_total_minor ?? 0, $row->currency ?? 'USD') }}</td>
                                  <td>{{ $displayDate($row->completed_at ?? $row->cancelled_at ?? $row->created_at) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="sa-empty">No Reservations Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($reservationRows) && method_exists($reservationRows, 'links'))
                    <div class="sa-pager">{{ $reservationRows->links() }}</div>
                @endif
            </div>
        </div>

        {{-- =========================================================
            DISPUTES
        ========================================================== --}}
        <div class="sa-tabPane {{ $currentTab === 'disputes' ? 'is-active' : '' }}">
            <div class="sa-panel">
                <div class="sa-panel__head"><h3>Dispute Records</h3></div>
                <div class="sa-tableWrap">
                    <table class="sa-table sa-table--center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reservation</th>
                                <th>Service</th>
                                <th>Client</th>
                                <th>Coach</th>
                                <th>Status</th>
                                <th>Settlement</th>
                                <th>Amount</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                       <tbody>
    @forelse($disputeRows ?? [] as $index => $row)
        <tr>
            <td>{{ (($disputeRows->currentPage() - 1) * $disputeRows->perPage()) + $index + 1 }}</td>

            <td>
                @if(!empty($row->reservation_id))
                    <a href="{{ $reservationUrl($row->reservation_id) }}" class="sa-link">#{{ $row->reservation_id }}</a>
                @else
                    —
                @endif
            </td>

            <td>{{ $row->service_title ?? '—' }}</td>
            <td>{{ $row->client_name ?? '—' }}</td>
            <td>{{ $row->coach_name ?? '—' }}</td>

            <td>
                <span class="sa-badge {{ $statusBadge($row->dispute_status ?? '') }}">
                    {{ $labelize($row->dispute_status ?? 'unknown') }}
                </span>
            </td>

            <td>
                <span class="sa-badge {{ $statusBadge($row->settlement_status ?? '') }}">
                    {{ $labelize($row->settlement_status ?? 'unknown') }}
                </span>
            </td>

            <td>{{ $money($row->total_minor ?? 0, $row->currency ?? 'USD') }}</td>

           <td>{{ $displayDate($row->dispute_last_event_at) }}</td>

            <td>
                <a href="{{ $disputeUrl($row->dispute_id ?? $row->id) }}" class="sa-link sa-link--action">View</a>
            </td>
        </tr>
    @empty
        <tr><td colspan="10" class="sa-empty">No Disputes Found</td></tr>
    @endforelse
</tbody>
                    </table>
                </div>

                @if(isset($disputeRows) && method_exists($disputeRows, 'links'))
                    <div class="sa-pager">{{ $disputeRows->links() }}</div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')


<script>
document.addEventListener('DOMContentLoaded', function () {
    const rangeEl = document.getElementById('analyticsRange');

    const dailyField = document.getElementById('dailyField');
    const weeklyField = document.getElementById('weeklyField');
    const monthlyField = document.getElementById('monthlyField');
    const yearlyField = document.getElementById('yearlyField');
    const fromField = document.getElementById('fromField');
    const toField = document.getElementById('toField');
    const timeFromField = document.getElementById('timeFromField');
    const timeToField = document.getElementById('timeToField');

    const monthPicker = document.getElementById('month_picker');
    const monthHidden = document.getElementById('month_hidden');
    const monthYearHidden = document.getElementById('month_year_hidden');

    function setFieldState(wrapper, show) {
        if (!wrapper) return;

        wrapper.style.display = show ? 'block' : 'none';

        wrapper.querySelectorAll('input, select').forEach(el => {
            el.disabled = !show;
        });
    }

    function hideAllRangeFields() {
        [
            dailyField,
            weeklyField,
            monthlyField,
            yearlyField,
            fromField,
            toField,
            timeFromField,
            timeToField
        ].forEach(el => setFieldState(el, false));
    }

    function toggleRangeFields() {
        if (!rangeEl) return;

        const range = rangeEl.value;
        hideAllRangeFields();

        if (range === 'daily') {
            setFieldState(dailyField, true);
            setFieldState(timeFromField, true);
            setFieldState(timeToField, true);
        } else if (range === 'weekly') {
            setFieldState(weeklyField, true);
            setFieldState(timeFromField, true);
            setFieldState(timeToField, true);
        } else if (range === 'monthly') {
            setFieldState(monthlyField, true);
            setFieldState(timeFromField, true);
            setFieldState(timeToField, true);
        } else if (range === 'yearly') {
            setFieldState(yearlyField, true);
            setFieldState(timeFromField, true);
            setFieldState(timeToField, true);
        } else if (range === 'custom') {
            setFieldState(fromField, true);
            setFieldState(toField, true);
            setFieldState(timeFromField, true);
            setFieldState(timeToField, true);
        }
    }

    if (monthPicker && monthHidden && monthYearHidden) {
        monthPicker.addEventListener('change', function () {
            const value = this.value;
            if (!value) return;

            const parts = value.split('-');
            if (parts.length === 2) {
                monthYearHidden.value = parts[0];
                monthHidden.value = parseInt(parts[1], 10);
            }
        });
    }

    if (rangeEl) {
        rangeEl.addEventListener('change', toggleRangeFields);
        toggleRangeFields();
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const moneyTick = (value) => {
        const v = Number(value || 0) / 100;
        return '$' + v.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    const numberTick = (value) => {
        return Number(value || 0).toLocaleString();
    };

    const percentOfTotal = (value, values) => {
        const total = (values || []).reduce((a, b) => a + Number(b || 0), 0);
        if (!total) return '0.00%';
        return ((Number(value || 0) / total) * 100).toFixed(2) + '%';
    };

    const createHtmlLegend = (containerId, labels, values, colors, formatter = null) => {
        const el = document.getElementById(containerId);
        if (!el) return;

        let html = '<div class="sa-legendList">';
        labels.forEach((label, i) => {
            const value = values[i] ?? 0;
            const displayValue = formatter ? formatter(value, values) : value;

            html += `
                <div class="sa-legendItem">
                    <span class="sa-legendSwatch" style="background:${colors[i] ?? '#cbd5e1'}"></span>
                    <span class="sa-legendLabel">${label}</span>
                    <span class="sa-legendValue">${displayValue}</span>
                </div>
            `;
        });
        html += '</div>';
        el.innerHTML = html;
    };

    const commonLegendLabels = {
        color: '#475569',
        boxWidth: 12,
        boxHeight: 12,
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 18,
        font: { size: 12, weight: '600' }
    };

    const gridColor = 'rgba(148, 163, 184, 0.16)';
    const tickColor = '#64748b';

  const rawLineLabels = @json($lineLabels ?? []);
const reportTimezone = @json($reportTimezone ?? 'UTC');
const bucketMode = @json($bucketMode ?? 'monthly');

function formatBucketLabel(label, mode, timezone) {
    if (!label) return label;

    try {
        let dt = null;

        if (mode === 'hourly') {
            dt = new Date(label.replace(' ', 'T') + ':00Z');
        } else if (mode === 'daily') {
            dt = new Date(label + 'T00:00:00Z');
        } else {
            dt = new Date(label + '-01T00:00:00Z');
        }

        if (isNaN(dt.getTime())) {
            return label;
        }

        if (mode === 'hourly') {
            return new Intl.DateTimeFormat('en-GB', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(dt);
        }

        if (mode === 'daily') {
            return new Intl.DateTimeFormat('en-GB', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(dt);
        }

        return new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            year: 'numeric',
            month: 'short'
        }).format(dt);
    } catch (e) {
        return label;
    }
}

const lineLabels = Array.isArray(rawLineLabels)
    ? rawLineLabels.map(label => formatBucketLabel(label, bucketMode, reportTimezone))
    : [];
    const lineInflow            = @json($lineInflow ?? []);
    const lineRefundCash        = @json($lineRefundCash ?? []);
    const linePlatform          = @json($linePlatform ?? []);
    const lineWithdrawals       = @json($lineWithdrawals ?? []);
    const lineVisits            = @json($lineVisits ?? []);
    const lineSignups           = @json($lineSignups ?? []);
    const lineSignupVerified    = @json($lineSignupVerified ?? []);
    const lineBookingPageVisits = @json($lineBookingPageVisits ?? []);
    const lineCoachApplications = @json($lineCoachApplications ?? []);
    const lineReviewCounts      = @json($lineReviewCounts ?? []);

    const pieBusinessMoneyLabels = @json($pieBusinessMoneyLabels ?? []);
    const pieBusinessMoneyValues = @json($pieBusinessMoneyValues ?? []);
    const pieFundingLabels       = @json($pieFundingLabels ?? []);
    const pieFundingValues       = @json($pieFundingValues ?? []);
    const pieSettlementLabels    = @json($pieSettlementLabels ?? []);
    const pieSettlementValues    = @json($pieSettlementValues ?? []);
    const pieRefundLabels        = @json($pieRefundLabels ?? []);
    const pieRefundValues        = @json($pieRefundValues ?? []);

    const providerLabels = @json($barProviderLabels ?? []);
    const providerValues = @json($barProviderValues ?? []);
    const categoryLabels = @json($barCategoryLabels ?? []);
    const categorySales  = @json($barCategorySales ?? []);

    const ratingLabels = @json(collect($ratingBreakdown ?? [])->pluck('stars')->map(fn($v) => $v . ' Star')->values());
    const ratingValues = @json(collect($ratingBreakdown ?? [])->pluck('review_count')->values());

    const brandBlue   = '#2563eb';
    const brandPurple = '#7c3aed';
    const brandGreen  = '#16a34a';
    const brandGray   = '#64748b';
    const brandOrange = '#f97316';
    const brandRed    = '#dc2626';
    const brandYellow = '#eab308';

    const pieColors1 = ['#2563eb', '#7c3aed', '#dc2626', '#16a34a'];
    const pieColors2 = ['#2563eb', '#7c3aed', '#64748b', '#16a34a', '#f97316', '#eab308'];
    const pieColors3 = ['#2563eb', '#7c3aed', '#16a34a', '#64748b', '#f97316', '#dc2626'];

    const baseLineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: true, position: 'bottom', labels: commonLegendLabels },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const label = ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                        if ((ctx.dataset.yAxisID || 'y') === 'money') {
                            return label + moneyTick(ctx.parsed.y);
                        }
                        return label + numberTick(ctx.parsed.y);
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: { color: tickColor, maxRotation: 0, autoSkip: true },
                grid: { color: 'transparent' }
            },
            money: {
                position: 'left',
                ticks: { color: tickColor, callback: moneyTick },
                grid: { color: gridColor }
            },
            y: {
                position: 'left',
                ticks: { color: tickColor, callback: numberTick },
                grid: { color: gridColor }
            }
        }
    };

    const baseBarOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'bottom', labels: commonLegendLabels },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const label = ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                        return label + moneyTick(ctx.parsed.y);
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: tickColor,
                    autoSkip: false,
                    callback: function(value) {
                        const label = this.getLabelForValue(value);
                        return String(label).length > 16 ? String(label).slice(0, 16) + '…' : label;
                    }
                },
                grid: { display: false }
            },
            y: {
                ticks: { color: tickColor, callback: moneyTick },
                grid: { color: gridColor }
            }
        }
    };

    const basePieOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'bottom', labels: commonLegendLabels },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const label = ctx.label || '';
                        const value = ctx.parsed || 0;
                        return label + ': ' + moneyTick(value) + ' (' + percentOfTotal(value, ctx.dataset.data) + ')';
                    }
                }
            }
        }
    };

    const ratingPieOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'bottom', labels: commonLegendLabels },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const label = ctx.label || '';
                        const value = ctx.parsed || 0;
                        return label + ': ' + numberTick(value) + ' (' + percentOfTotal(value, ctx.dataset.data) + ')';
                    }
                }
            }
        }
    };

    const makeChart = (id, config) => {
        const node = document.getElementById(id);
        if (!node) return null;
        return new Chart(node, config);
    };

    makeChart('businessMoneyTrendChart', {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [
                { label: 'Gross Inflow', data: lineInflow, borderColor: brandBlue, backgroundColor: 'rgba(37,99,235,.12)', yAxisID: 'money', tension: .35, fill: false },
                { label: 'Refund Outflow', data: lineRefundCash, borderColor: brandRed, backgroundColor: 'rgba(220,38,38,.12)', yAxisID: 'money', tension: .35, fill: false },
                { label: 'Platform Earned', data: linePlatform, borderColor: brandGreen, backgroundColor: 'rgba(22,163,74,.12)', yAxisID: 'money', tension: .35, fill: false },
                { label: 'Withdrawals', data: lineWithdrawals, borderColor: brandPurple, backgroundColor: 'rgba(124,58,237,.12)', yAxisID: 'money', tension: .35, fill: false }
            ]
        },
        options: baseLineOptions
    });

    createHtmlLegend('businessMoneyTrendLegend',
        ['Gross Inflow', 'Refund Outflow', 'Platform Earned', 'Withdrawals'],
        [
            lineInflow.reduce((a,b)=>a+Number(b||0),0),
            lineRefundCash.reduce((a,b)=>a+Number(b||0),0),
            linePlatform.reduce((a,b)=>a+Number(b||0),0),
            lineWithdrawals.reduce((a,b)=>a+Number(b||0),0)
        ],
        [brandBlue, brandRed, brandGreen, brandPurple],
        (value) => moneyTick(value)
    );

    makeChart('businessMoneyPieChart', {
        type: 'doughnut',
        data: {
            labels: pieBusinessMoneyLabels,
            datasets: [{ data: pieBusinessMoneyValues, backgroundColor: pieColors1, borderWidth: 0 }]
        },
        options: basePieOptions
    });

    createHtmlLegend('businessMoneyPieLegend', pieBusinessMoneyLabels, pieBusinessMoneyValues, pieColors1,
        (value, values) => moneyTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('providerBreakdownChart', {
        type: 'bar',
        data: {
            labels: providerLabels,
            datasets: [{ label: 'Amount', data: providerValues, backgroundColor: [brandBlue, brandPurple, brandGray, brandGreen, brandOrange, brandYellow] }]
        },
        options: baseBarOptions
    });

    createHtmlLegend('providerBreakdownLegend', providerLabels, providerValues, [brandBlue, brandPurple, brandGray, brandGreen, brandOrange, brandYellow],
        (value, values) => moneyTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('categorySalesChart', {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [{ label: 'Category Sales', data: categorySales, backgroundColor: brandBlue }]
        },
        options: baseBarOptions
    });

    createHtmlLegend('categorySalesLegend', categoryLabels, categorySales, categoryLabels.map(() => brandBlue),
        (value, values) => moneyTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('fundingPieChart', {
        type: 'doughnut',
        data: {
            labels: pieFundingLabels,
            datasets: [{ data: pieFundingValues, backgroundColor: pieColors2, borderWidth: 0 }]
        },
        options: basePieOptions
    });

    createHtmlLegend('fundingPieLegend', pieFundingLabels, pieFundingValues, pieColors2,
        (value, values) => moneyTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('settlementPieChart', {
        type: 'doughnut',
        data: {
            labels: pieSettlementLabels,
            datasets: [{ data: pieSettlementValues, backgroundColor: pieColors3, borderWidth: 0 }]
        },
        options: basePieOptions
    });

    createHtmlLegend('settlementPieLegend', pieSettlementLabels, pieSettlementValues, pieColors3,
        (value, values) => moneyTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('refundPieChart', {
        type: 'doughnut',
        data: {
            labels: pieRefundLabels,
            datasets: [{ data: pieRefundValues, backgroundColor: [brandPurple, brandGray], borderWidth: 0 }]
        },
        options: basePieOptions
    });

    createHtmlLegend('refundPieLegend', pieRefundLabels, pieRefundValues, [brandPurple, brandGray],
        (value, values) => moneyTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('trafficTrendChart', {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [
                { label: 'Visitors', data: lineVisits, borderColor: brandBlue, backgroundColor: 'rgba(37,99,235,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Signups', data: lineSignups, borderColor: brandPurple, backgroundColor: 'rgba(124,58,237,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Booking Page Visits', data: lineBookingPageVisits, borderColor: brandGray, backgroundColor: 'rgba(100,116,139,.12)', yAxisID: 'y', tension: .35 }
            ]
        },
        options: baseLineOptions
    });

    createHtmlLegend('trafficTrendLegend',
        ['Visitors', 'Signups', 'Booking Page Visits'],
        [
            lineVisits.reduce((a,b)=>a+Number(b||0),0),
            lineSignups.reduce((a,b)=>a+Number(b||0),0),
            lineBookingPageVisits.reduce((a,b)=>a+Number(b||0),0)
        ],
        [brandBlue, brandPurple, brandGray],
        (value) => numberTick(value)
    );

    makeChart('signupTrafficChart', {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [
                { label: 'Visitors', data: lineVisits, borderColor: brandBlue, backgroundColor: 'rgba(37,99,235,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Signup Created', data: lineSignups, borderColor: brandPurple, backgroundColor: 'rgba(124,58,237,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Signup Verified', data: lineSignupVerified, borderColor: brandGreen, backgroundColor: 'rgba(22,163,74,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Coach Applications', data: lineCoachApplications, borderColor: brandGray, backgroundColor: 'rgba(100,116,139,.12)', yAxisID: 'y', tension: .35 }
            ]
        },
        options: baseLineOptions
    });

    createHtmlLegend('signupTrafficLegend',
        ['Visitors', 'Signup Created', 'Signup Verified', 'Coach Applications'],
        [
            lineVisits.reduce((a,b)=>a+Number(b||0),0),
            lineSignups.reduce((a,b)=>a+Number(b||0),0),
            lineSignupVerified.reduce((a,b)=>a+Number(b||0),0),
            lineCoachApplications.reduce((a,b)=>a+Number(b||0),0)
        ],
        [brandBlue, brandPurple, brandGreen, brandGray],
        (value) => numberTick(value)
    );

    makeChart('funnelTrendChart', {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [
                { label: 'Booking Page Visits', data: lineBookingPageVisits, borderColor: brandBlue, backgroundColor: 'rgba(37,99,235,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Signups', data: lineSignups, borderColor: brandPurple, backgroundColor: 'rgba(124,58,237,.12)', yAxisID: 'y', tension: .35 },
                { label: 'Signup Verified', data: lineSignupVerified, borderColor: brandGray, backgroundColor: 'rgba(100,116,139,.12)', yAxisID: 'y', tension: .35 }
            ]
        },
        options: baseLineOptions
    });

    createHtmlLegend('funnelTrendLegend',
        ['Booking Page Visits', 'Checkout Started', 'Paid Bookings'],
        [
            {{ (int)($bookingPageVisitCount ?? 0) }},
            {{ (int)($checkoutStartedCount ?? 0) }},
            {{ (int)($bookingPaidEventCount ?? 0) }}
        ],
        [brandBlue, brandPurple, brandGray],
        (value, values) => numberTick(value) + ' • ' + percentOfTotal(value, values)
    );

    makeChart('reviewTrendChart', {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [
                { label: 'Review Count', data: lineReviewCounts, borderColor: brandPurple, backgroundColor: 'rgba(124,58,237,.12)', yAxisID: 'y', tension: .35 }
            ]
        },
        options: baseLineOptions
    });

    createHtmlLegend('reviewTrendLegend', ['Review Count'], [lineReviewCounts.reduce((a,b)=>a+Number(b||0),0)], [brandPurple],
        (value) => numberTick(value)
    );

    makeChart('ratingBreakdownPieChart', {
        type: 'doughnut',
        data: {
            labels: ratingLabels,
            datasets: [{ data: ratingValues, backgroundColor: [brandGreen, brandBlue, brandPurple, brandOrange, brandGray], borderWidth: 0 }]
        },
        options: ratingPieOptions
    });

    createHtmlLegend('ratingBreakdownPieLegend', ratingLabels, ratingValues, [brandGreen, brandBlue, brandPurple, brandOrange, brandGray],
        (value, values) => numberTick(value) + ' • ' + percentOfTotal(value, values)
    );
})();
</script>
@endpush