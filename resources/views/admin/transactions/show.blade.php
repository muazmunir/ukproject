@extends('layouts.admin')

@section('title', 'Payment Audit')

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
                'succeeded', 'paid' => 'txd-badge txd-badge--success',
                'processing', 'pending', 'held', 'requires_payment', 'requires_payment_method' => 'txd-badge txd-badge--warning',
                'failed', 'cancelled', 'canceled' => 'txd-badge txd-badge--danger',
                default => 'txd-badge txd-badge--muted',
            };
        }

        if ($type === 'provider') {
            return match ($value) {
                'stripe' => 'txd-badge txd-badge--info',
                'paypal' => 'txd-badge txd-badge--dark',
                'wallet' => 'txd-badge txd-badge--purple',
                default => 'txd-badge txd-badge--muted',
            };
        }

        if ($type === 'funding') {
            return match ($value) {
                'wallet_only' => 'txd-badge txd-badge--purple',
                'mixed' => 'txd-badge txd-badge--warning',
                'external_only' => 'txd-badge txd-badge--info',
                default => 'txd-badge txd-badge--muted',
            };
        }

        return 'txd-badge txd-badge--muted';
    };

    $clientName = trim(
        (optional($reservation->client)->first_name ?? '') . ' ' . (optional($reservation->client)->last_name ?? '')
    ) ?: '—';

    $coachName = trim(
        (optional($reservation->coach)->first_name ?? '') . ' ' . (optional($reservation->coach)->last_name ?? '')
    ) ?: '—';

    $currency = strtoupper((string) ($summary['currency'] ?? $reservation->currency ?? 'USD'));
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/transaction-show.css') }}">
@endpush

@section('content')
<div class="txd-page">
    <div class="txd-header">
        <div>
            <div class="txd-breadcrumbs">
                <a href="{{ route('admin.transactions.index') }}">Transactions</a>
                <span>/</span>
                <span>Payment Audit</span>
            </div>

            <h1 class="txd-title">Payment Audit</h1>
            <p class="txd-subtitle">
                Payment trace, provider references, funding breakdown, and collection record for reservation #{{ $reservation->id }}.
            </p>
        </div>

        <div class="txd-header-actions">
            <a href="{{ route('admin.transactions.index') }}" class="txd-btn txd-btn--secondary">
                Back to Transactions
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="txd-alert txd-alert--success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="txd-alert txd-alert--danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="txd-summary-grid">
        <div class="txd-card txd-card--hero">
            <div class="txd-card__head">
                <h2>Payment Summary</h2>
                <p>Core payment information for legal, finance, and audit review.</p>
            </div>

            <div class="txd-kpis">
                <div class="txd-kpi">
                    <span class="txd-kpi__label">Total Paid</span>
                    <strong class="txd-kpi__value">{{ $money($summary['total_minor'] ?? 0, $currency) }}</strong>
                </div>

                <div class="txd-kpi">
                    <span class="txd-kpi__label">Client Fee</span>
                    <strong class="txd-kpi__value">{{ $money($summary['fees_minor'] ?? 0, $currency) }}</strong>
                </div>

                <div class="txd-kpi">
                    <span class="txd-kpi__label">Wallet Used</span>
                    <strong class="txd-kpi__value">{{ $money($summary['wallet_platform_credit_used_minor'] ?? 0, $currency) }}</strong>
                </div>

                <div class="txd-kpi">
                    <span class="txd-kpi__label">Gateway Paid</span>
                    <strong class="txd-kpi__value">{{ $money($summary['payable_minor'] ?? 0, $currency) }}</strong>
                </div>
            </div>
        </div>

        <div class="txd-card">
            <div class="txd-card__head">
                <h2>Payment Status</h2>
                <p>Collection status and funding source.</p>
            </div>

            <div class="txd-info-list">
                <div class="txd-info-row">
                    <span>Reservation ID</span>
                    <strong>#{{ $reservation->id }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Currency</span>
                    <strong>{{ $currency }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Provider</span>
                    <strong>{{ ucfirst($summary['provider'] ?? $reservation->provider ?? '—') }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Payment Status</span>
                    <div>
                        <span class="{{ $badgeClass('status', $summary['payment_status'] ?? $reservation->payment_status) }}">
                            {{ ucwords(str_replace('_', ' ', $summary['payment_status'] ?? $reservation->payment_status ?? 'unknown')) }}
                        </span>
                    </div>
                </div>

                <div class="txd-info-row">
                    <span>Funding Type</span>
                    <div>
                        <span class="{{ $badgeClass('funding', $summary['funding_status'] ?? $reservation->funding_status) }}">
                            {{ ucwords(str_replace('_', ' ', $summary['funding_status'] ?? $reservation->funding_status ?? 'unknown')) }}
                        </span>
                    </div>
                </div>

                <div class="txd-info-row">
                    <span>Payment Intent ID</span>
                    <strong class="txd-code">{{ $summary['payment_intent_id'] ?: '—' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="txd-grid-2">
        <div class="txd-card">
            <div class="txd-card__head">
                <h2>Funding Breakdown</h2>
                <p>How this reservation payment was funded.</p>
            </div>

            <div class="txd-breakdown">
                <div class="txd-breakdown__item">
                    <div>
                        <span class="txd-breakdown__label">Service Subtotal</span>
                        <strong>{{ $money($summary['subtotal_minor'] ?? 0, $currency) }}</strong>
                    </div>
                </div>

                <div class="txd-breakdown__item">
                    <div>
                        <span class="txd-breakdown__label">Client Fee</span>
                        <strong>{{ $money($summary['fees_minor'] ?? 0, $currency) }}</strong>
                    </div>
                </div>

                <div class="txd-breakdown__item">
                    <div>
                        <span class="txd-breakdown__label">Wallet Contribution</span>
                        <strong>{{ $money($summary['wallet_platform_credit_used_minor'] ?? 0, $currency) }}</strong>
                    </div>
                </div>

                <div class="txd-breakdown__item">
                    <div>
                        <span class="txd-breakdown__label">External Contribution</span>
                        <strong>{{ $money($summary['payable_minor'] ?? 0, $currency) }}</strong>
                    </div>
                </div>

                <div class="txd-breakdown__item txd-breakdown__item--total">
                    <div>
                        <span class="txd-breakdown__label">Total</span>
                        <strong>{{ $money($summary['total_minor'] ?? 0, $currency) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="txd-card">
            <div class="txd-card__head">
                <h2>Reservation Snapshot</h2>
                <p>Light contextual data only.</p>
            </div>

            <div class="txd-info-list">
                <div class="txd-info-row">
                    <span>Service</span>
                    <strong>{{ optional($reservation->service)->title ?? ($reservation->service_title_snapshot ?? '—') }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Package</span>
                    <strong>{{ $reservation->package_name_snapshot ?? '—' }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Customer</span>
                    <strong>{{ $clientName }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Customer Email</span>
                    <strong>{{ optional($reservation->client)->email ?? '—' }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Coach</span>
                    <strong>{{ $coachName }}</strong>
                </div>

                <div class="txd-info-row">
                    <span>Booked At</span>
                    <strong>{{ optional($reservation->booked_at)->format('d M Y, h:i A') ?? '—' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="txd-card">
        <div class="txd-card__head">
            <h2>Payment Records</h2>
            <p>Provider-level records saved against this reservation.</p>
        </div>

        @if($payments->count())
            <div class="txd-table-wrap">
                <table class="txd-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Provider References</th>
                            <th>Receipt</th>
                            <th>Created</th>
                            <th>Succeeded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            @php
                                $paymentCurrency = strtoupper((string) ($payment->currency ?: $currency));
                            @endphp
                            <tr>
                                <td>
                                    <span class="{{ $badgeClass('provider', $payment->provider) }}">
                                        {{ ucfirst($payment->provider ?: 'Unknown') }}
                                    </span>
                                </td>

                                <td>
                                    <div class="txd-stack">
                                        <strong>{{ $prettyMethod($payment->method) }}</strong>
                                    </div>
                                </td>

                                <td>
                                    <span class="{{ $badgeClass('status', $payment->status) }}">
                                        {{ ucwords(str_replace('_', ' ', $payment->status ?: 'unknown')) }}
                                    </span>
                                </td>

                                <td>
                                    <strong>{{ $money($payment->amount_total ?? 0, $paymentCurrency) }}</strong>
                                </td>

                                <td>
                                    <div class="txd-stack txd-stack--refs">
                                        @if($payment->provider_payment_id)
                                            <span><strong>Payment:</strong> {{ $payment->provider_payment_id }}</span>
                                        @endif

                                        @if($payment->provider_order_id)
                                            <span><strong>Order:</strong> {{ $payment->provider_order_id }}</span>
                                        @endif

                                        @if($payment->provider_charge_id)
                                            <span><strong>Charge:</strong> {{ $payment->provider_charge_id }}</span>
                                        @endif

                                        @if($payment->provider_capture_id)
                                            <span><strong>Capture:</strong> {{ $payment->provider_capture_id }}</span>
                                        @endif

                                        @if($payment->provider_refund_id)
                                            <span><strong>Refund Ref:</strong> {{ $payment->provider_refund_id }}</span>
                                        @endif

                                        @if(
                                            !$payment->provider_payment_id &&
                                            !$payment->provider_order_id &&
                                            !$payment->provider_charge_id &&
                                            !$payment->provider_capture_id &&
                                            !$payment->provider_refund_id
                                        )
                                            <span>—</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @if($payment->receipt_url)
                                        <a href="{{ $payment->receipt_url }}" target="_blank" class="txd-link">
                                            Open Receipt
                                        </a>
                                    @else
                                        <span class="txd-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="txd-stack">
                                        <strong>{{ optional($payment->created_at)->format('d M Y') ?: '—' }}</strong>
                                        <span>{{ optional($payment->created_at)->format('h:i A') ?: '—' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="txd-stack">
                                        <strong>{{ optional($payment->succeeded_at)->format('d M Y') ?: '—' }}</strong>
                                        <span>{{ optional($payment->succeeded_at)->format('h:i A') ?: '—' }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="txd-empty">
                <div class="txd-empty__icon">💳</div>
                <h3>No payment records found</h3>
                <p>No provider payment rows are currently attached to this reservation.</p>
            </div>
        @endif
    </div>

    <div class="txd-card">
        <div class="txd-card__head">
            <h2>Payment Timeline</h2>
            <p>High-level collection milestones.</p>
        </div>

        <div class="txd-timeline">
            <div class="txd-timeline__item">
                <div class="txd-timeline__dot"></div>
                <div class="txd-timeline__content">
                    <strong>Reservation Created</strong>
                    <span>{{ optional($reservation->created_at)->format('d M Y, h:i A') ?? '—' }}</span>
                </div>
            </div>

            <div class="txd-timeline__item">
                <div class="txd-timeline__dot"></div>
                <div class="txd-timeline__content">
                    <strong>Payment Booked</strong>
                    <span>{{ optional($reservation->booked_at)->format('d M Y, h:i A') ?? '—' }}</span>
                </div>
            </div>

            <div class="txd-timeline__item">
                <div class="txd-timeline__dot"></div>
                <div class="txd-timeline__content">
                    <strong>Latest Successful Provider Record</strong>
                    <span>
                        {{
                            optional(
                                $payments->whereIn('status', ['succeeded', 'paid'])->sortByDesc('succeeded_at')->first()
                            ?->succeeded_at
                            )->format('d M Y, h:i A') ?? '—'
                        }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection