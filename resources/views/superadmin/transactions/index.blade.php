@extends('superadmin.layout')

@section('title', 'Payments & Transactions')

@php
  $money = function ($minor, $currency = 'USD') {
    $amount = ((int) $minor) / 100;
    return '$' . number_format($amount, 2) . ' ' . strtoupper($currency);
};

    $prettyMethod = function ($method) {
        return match ((string) $method) {
            'VISA' => 'Visa',
            'MASTERCARD' => 'Mastercard',
            'AMEX' => 'American Express',
            'DISCOVER' => 'Discover',
            'CARD' => 'Card',
            'KLARNA' => 'Klarna',
            'PAYPAL' => 'PayPal',
            'paypal' => 'PayPal',
            'PLATFORM_CREDIT' => 'Platform Credit',
            default => $method ?: '—',
        };
    };

    $badgeClass = function ($type, $value) {
        $value = strtolower((string) $value);

        if ($type === 'status') {
            return match ($value) {
                'succeeded', 'paid' => 'tx-badge tx-badge--success',
                'processing', 'pending', 'held', 'requires_payment', 'requires_payment_method' => 'tx-badge tx-badge--warning',
                'failed', 'cancelled', 'canceled' => 'tx-badge tx-badge--danger',
                default => 'tx-badge tx-badge--muted',
            };
        }

        if ($type === 'provider') {
            return match ($value) {
                'stripe' => 'tx-badge tx-badge--info',
                'paypal' => 'tx-badge tx-badge--primary',
                'wallet' => 'tx-badge tx-badge--purple',
                default => 'tx-badge tx-badge--muted',
            };
        }

        if ($type === 'funding') {
            return match ($value) {
                'wallet_only' => 'tx-badge tx-badge--purple',
                'mixed' => 'tx-badge tx-badge--warning',
                'external_only' => 'tx-badge tx-badge--info',
                default => 'tx-badge tx-badge--muted',
            };
        }

        return 'tx-badge tx-badge--muted';
    };
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/transactions.css') }}">
@endpush

@section('content')
<div class="tx-page">
    <div class="tx-page__header">
        <div>
            <h1 class="tx-page__title">Payments & Transactions</h1>
            <p class="tx-page__subtitle text-capitalize">
                Monitor payment collections, funding mix, payment methods, provider activity, and transaction audit history.
            </p>
        </div>

        <div class="tx-page__header-meta">
            <div class="tx-period-chip">
                <span class="tx-period-chip__label">Period</span>
                <strong>{{ $filters['label'] ?? 'All Time' }}</strong>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="tx-alert tx-alert--success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="tx-alert tx-alert--danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="tx-kpis">
        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Gross Processed</span>
            <strong class="tx-kpi-card__value">{{ $money($kpis['gross_processed_minor'] ?? 0) }}</strong>
        </div>

        {{-- <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Client Fees Collected</span>
            <strong class="tx-kpi-card__value">{{ $money($kpis['client_fees_collected_minor'] ?? 0) }}</strong>
        </div> --}}

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Wallet Credits Applied</span>
            <strong class="tx-kpi-card__value">{{ $money($kpis['wallet_credits_applied_minor'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Gateway Payable</span>
            <strong class="tx-kpi-card__value">{{ $money($kpis['gateway_payable_minor'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Wallet Volume</span>
            <strong class="tx-kpi-card__value">{{ $money($kpis['wallet_volume_minor'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">External Volume</span>
            <strong class="tx-kpi-card__value">{{ $money($kpis['external_volume_minor'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Successful</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['successful_transactions'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Processing / Pending</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['processing_transactions'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Failed / Cancelled</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['failed_transactions'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Total Transactions</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['total_transactions'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Wallet-Only Reservations</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['wallet_only_reservations'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">Mixed Reservations</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['mixed_reservations'] ?? 0) }}</strong>
        </div>

        <div class="tx-kpi-card">
            <span class="tx-kpi-card__label">External-Only Reservations</span>
            <strong class="tx-kpi-card__value">{{ number_format($kpis['external_only_reservations'] ?? 0) }}</strong>
        </div>
    </div>

    <div class="tx-panel">
        <div class="tx-panel__head">
            <h2>Filters</h2>
            <p class=" text-capitalize">Refine payment analytics by date range, provider, method, status, and funding type.</p>
        </div>

        <form method="GET" action="{{ route('superadmin.transactions.index') }}" class="tx-filters">
            <div class="tx-form-grid">
                <div class="tx-field">
                    <label for="period">Period</label>
                    <select name="period" id="period" class="tx-control" data-period-switcher>
                        @foreach(($filterOptions['periods'] ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['period'] ?? 'monthly') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tx-field tx-period-field" data-period-target="daily">
                    <label for="day">Select Day</label>
                    <input
                        type="date"
                        name="day"
                        id="day"
                        class="tx-control"
                        value="{{ $filters['day'] ?? '' }}"
                    >
                </div>

                <div class="tx-field tx-period-field" data-period-target="weekly">
                    <label for="week_date">Week Reference Date</label>
                    <input
                        type="date"
                        name="week_date"
                        id="week_date"
                        class="tx-control"
                        value="{{ $filters['week_date'] ?? '' }}"
                    >
                </div>

                <div class="tx-field tx-period-field" data-period-target="monthly">
                    <label for="month">Select Month</label>
                    <input
                        type="month"
                        name="month"
                        id="month"
                        class="tx-control"
                        value="{{ $filters['month'] ?? '' }}"
                    >
                </div>

                <div class="tx-field tx-period-field" data-period-target="yearly">
                    <label for="year">Select Year</label>
                    <input
                        type="number"
                        name="year"
                        id="year"
                        class="tx-control"
                        min="2020"
                        max="{{ now()->year + 10 }}"
                        value="{{ $filters['year'] ?? now()->year }}"
                    >
                </div>

                <div class="tx-field tx-period-field" data-period-target="custom">
                    <label for="from">From</label>
                    <input
                        type="date"
                        name="from"
                        id="from"
                        class="tx-control"
                        value="{{ $filters['from'] ?? '' }}"
                    >
                </div>

                <div class="tx-field tx-period-field" data-period-target="custom">
                    <label for="to">To</label>
                    <input
                        type="date"
                        name="to"
                        id="to"
                        class="tx-control"
                        value="{{ $filters['to'] ?? '' }}"
                    >
                </div>

                <div class="tx-field">
                    <label for="provider">Provider</label>
                    <select name="provider" id="provider" class="tx-control">
                        <option value="">All Providers</option>
                        @foreach(($filterOptions['providers'] ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(request('provider') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tx-field">
                    <label for="method">Method</label>
                    <select name="method" id="method" class="tx-control">
                        <option value="">All Methods</option>
                        @foreach(($filterOptions['methods'] ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(request('method') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tx-field">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="tx-control">
                        <option value="">All Statuses</option>
                        @foreach(($filterOptions['statuses'] ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tx-field">
                    <label for="funding_status">Funding Type</label>
                    <select name="funding_status" id="funding_status" class="tx-control">
                        <option value="">All Funding Types</option>
                        @foreach(($filterOptions['funding_statuses'] ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(request('funding_status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tx-field tx-field--wide">
                    <label for="q">Search</label>
                    <input
                        type="text"
                        name="q"
                        id="q"
                        class="tx-control"
                        placeholder="Search by reservation ID, payment intent/order/charge/capture ID, method, or service title..."
                        value="{{ request('q') }}"
                    >
                </div>
            </div>

            <div class="tx-filter-actions">
                <button type="submit" class="tx-btn tx-btn--primary">Apply Filters</button>
                <a href="{{ route('superadmin.transactions.index') }}" class="tx-btn tx-btn--secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="tx-panel">
        <div class="tx-panel__head tx-panel__head--split">
            <div>
                <h2>Transactions</h2>
                <p>
                    Showing {{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }}
                    Of {{ $payments->total() }} Results
                </p>
            </div>
        </div>

        @if($payments->count())
            <div class="tx-table-wrap">
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>Reservation</th>
                            <th>Service</th>
                            <th>Customer</th>
                            <th>Provider</th>
                            <th>Method</th>
                            <th>Status</th>
                            {{-- <th>Funding</th> --}}
                            <th class="text-right">Amount</th>
                            {{-- <th class="text-right">Client Fee</th> --}}
                            <th>Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            @php
                                $reservation = $payment->reservation;
                                $currency = strtoupper((string) ($payment->currency ?: 'USD'));
                            @endphp
                            <tr>
                                <td>
                                    <div class="tx-cell-stack">
                                       @if($payment->reservation_id)
    <a href="{{ route('superadmin.bookings.show', $payment->reservation_id) }}" class="tx-link">
        #{{ $payment->reservation_id }}
    </a>
@else
    <span>—</span>
@endif
                                        <span>{{ $payment->provider_payment_id ?: 'No provider payment ID' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="tx-cell-stack">
                                        <strong>{{ $reservation->service_title_snapshot ?? optional($reservation?->service)->title ?? '—' }}</strong>
                                        <span>{{ $reservation->package_name_snapshot ?? '—' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="tx-cell-stack">
                                        <strong>{{ optional($reservation?->client)->name ?? '—' }}</strong>
                                        <span>{{ optional($reservation?->client)->email ?? 'No customer email' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <span class="{{ $badgeClass('provider', $payment->provider) }}">
                                        {{ ucfirst($payment->provider ?: 'Unknown') }}
                                    </span>
                                </td>

                                <td>
                                    <div class="tx-cell-stack">
                                        <strong>{{ $prettyMethod($payment->method) }}</strong>
                                        @if($payment->provider_order_id)
                                            <span>Order: {{ $payment->provider_order_id }}</span>
                                        @elseif($payment->provider_charge_id)
                                            <span>Charge: {{ $payment->provider_charge_id }}</span>
                                        @elseif($payment->provider_capture_id)
                                            <span>Capture: {{ $payment->provider_capture_id }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <span class="{{ $badgeClass('status', $payment->status) }}">
                                        {{ str_replace('_', ' ', ucfirst($payment->status ?: 'unknown')) }}
                                    </span>
                                </td>

                                {{-- <td>
                                    <span class="{{ $badgeClass('funding', $payment->funding_status ?? $reservation?->funding_status) }}">
                                        {{ ucwords(str_replace('_', ' ', $payment->funding_status ?? $reservation?->funding_status ?? 'unknown')) }}
                                    </span>
                                </td> --}}

                                <td class="text-right">
                                    <strong>{{ $money($payment->amount_total ?? 0, $currency) }}</strong>
                                </td>

                                {{-- <td class="text-right">
                                    {{ $money($payment->client_fee_minor ?? 0, $currency) }}
                                </td> --}}

                                <td>
                                    <div class="tx-cell-stack">
                                        <strong>{{ optional($payment->created_at)->format('d M Y') }}</strong>
                                        <span>{{ optional($payment->created_at)->format('h:i A') }}</span>
                                    </div>
                                </td>

                                <td class="text-center">
                                    @if($payment->reservation_id)
                                        <a href="{{ route('superadmin.transactions.show', $payment->reservation_id) }}" class="tx-btn tx-btn--table">
                                            View
                                        </a>
                                    @else
                                        <span class="tx-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="tx-pagination">
                {{ $payments->links() }}
            </div>
        @else
            <div class="tx-empty">
                <div class="tx-empty__icon text-capitalize">💳</div>
                <h3>No Transactions Found</h3>
                <p class=" text-capitalize">Try adjusting your filters, date range, provider, payment method, funding type, or status.</p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const periodSelect = document.querySelector('[data-period-switcher]');
        const periodFields = document.querySelectorAll('.tx-period-field');

        function syncPeriodFields() {
            const active = periodSelect ? periodSelect.value : 'monthly';

            periodFields.forEach((field) => {
                const target = field.getAttribute('data-period-target');
                field.style.display = target === active ? '' : 'none';
            });
        }

        if (periodSelect) {
            syncPeriodFields();
            periodSelect.addEventListener('change', syncPeriodFields);
        }
    })();
</script>
@endpush