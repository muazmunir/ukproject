@extends('layouts.role-dashboard')
@section('title', __('Payouts'))

@php
    $accountBadgeClass = match($accountStatus ?? 'not_connected') {
        'verified' => 'cw-badge--success',
        'restricted', 'disabled', 'failed' => 'cw-badge--danger',
        'pending_verification', 'onboarding_required', 'pending' => 'cw-badge--warning',
        default => 'cw-badge--muted',
    };

    $readinessLabel = $systemReady ? __('Ready') : __('Setup required');

    $defaultMethodStatus = strtolower((string) ($defaultMethod->status ?? 'active'));
    $defaultMethodStatusClass = match($defaultMethodStatus) {
        'active' => 'cw-badge--success',
        'failed' => 'cw-badge--danger',
        'pending' => 'cw-badge--warning',
        default => 'cw-badge--muted',
    };

    $defaultMethodStatusLabel = ucfirst(str_replace('_', ' ', $defaultMethodStatus));
@endphp

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/coach-payouts.css') }}">

<div class="cw-page">
    <div class="container py-4 py-lg-5">
        <div class="cw-wrap">

            @if(session('ok'))
                <div class="alert alert-success cw-alert">{{ session('ok') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger cw-alert">{{ session('error') }}</div>
            @endif

            @if(session('info'))
                <div class="alert alert-info cw-alert">{{ session('info') }}</div>
            @endif

            <div class="cw-header">
                <div>
                    <h1 class="cw-title">{{ __('Payouts') }}</h1>
                    <p class="cw-subtitle text-capitalize">{{ __('Manage your earnings and payouts') }}</p>
                </div>

                <div class="cw-header__actions">
                    @if($account)
                        <form method="POST" action="{{ route('coach.payouts.refresh') }}">
                            @csrf
                            <button type="submit" class="cw-btn cw-btn--light">
                                {{ __('Refresh') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('coach.payouts.start') }}">
                            @csrf
                            <button type="submit" class="cw-btn cw-btn--primary">
                                {{ __('Connect Stripe') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="cw-top-grid">
                <section class="cw-card cw-card--balance">
                    <div class="cw-card__label">{{ __('Available for Payout') }}</div>

                    <div class="cw-balance-shell">
                        <div class="cw-balance-center">
                            <div class="cw-balance-amount">
                                ${{ number_format($availableBalance, 2) }}
                                <span>{{ strtoupper($currency ?? 'USD') }}</span>
                            </div>

                            <div class="cw-balance-state {{ $systemReady ? 'is-ready' : 'is-pending' }}">
                                <span class="cw-balance-state__dot"></span>
                                {{ $readinessLabel }}
                            </div>

                            <div class="cw-balance-meta text-capitalize">
                                {{ __('Payments are released daily') }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="cw-card cw-card--method">
                    <div class="cw-card__label">{{ __('Payout Method') }}</div>

                    @if($defaultMethod)
                        <div class="cw-method-clean">
                            <div class="cw-method-clean__main">
                                <div class="cw-method-clean__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3.5" y="6.5" width="17" height="11" rx="2"></rect>
                                        <path d="M3.5 10.5h17"></path>
                                    </svg>
                                </div>

                                <div class="cw-method-clean__copy">
                                    <div class="cw-method-clean__top">
                                        <h3>
                                            {{ strtolower((string) ($defaultMethod->type ?? 'bank_account')) === 'card' ? __('Card') : __('Bank Account') }}
                                        </h3>

                                        @if(!empty($defaultMethod->last4))
                                            <span class="cw-method-clean__last4">•••• {{ $defaultMethod->last4 }}</span>
                                        @endif
                                    </div>

                                    <div class="cw-method-clean__meta">
                                        <span>{{ $defaultMethod->bank_name ?: __('Stripe payout method') }}</span>

                                        @if(!empty($defaultMethod->country))
                                            <span>{{ strtoupper($defaultMethod->country) }}</span>
                                        @endif

                                        @if(!empty($defaultMethod->currency))
                                            <span>{{ strtoupper($defaultMethod->currency) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="cw-method-clean__badges">
                                <span class="cw-badge {{ $defaultMethodStatusClass }}">{{ $defaultMethodStatusLabel }}</span>
                                <span class="cw-badge cw-badge--dark">{{ __('Default') }}</span>
                            </div>
                        </div>
                    @else
                        <div class="cw-empty-inline">
                            {{ __('No Stripe payout method available yet.') }}
                        </div>
                    @endif
                </section>

                <section class="cw-card cw-card--status">
                    <div class="cw-card__label">{{ __('Account Status') }}</div>

                    <div class="cw-status-list">
                        <div class="cw-status-item">
                            <span>{{ __('Coach Status') }}</span>
                            <strong class="cw-text-success">
                                {{ ucfirst(str_replace('_', ' ', $coachProfile->application_status ?? 'draft')) }}
                            </strong>
                        </div>

                        <div class="cw-status-item">
                            <span>{{ __('Payout Readiness') }}</span>
                            <strong class="{{ $systemReady ? 'cw-text-success' : 'cw-text-warning' }}">
                                {{ $readinessLabel }}
                            </strong>
                        </div>

                        <div class="cw-status-item">
                            <span>{{ __('Total Paid Out') }}</span>
                            <strong>${{ number_format($totalPaidOut, 2) }} {{ strtoupper($currency ?? 'USD') }}</strong>
                        </div>

                        <div class="cw-status-item">
                            <span>{{ __('Last Payout') }}</span>
                            <strong>
                                @if($latestPayout)
                                    ${{ number_format(($latestPayout->amount_minor ?? 0) / 100, 2) }} USD
                                    <small>{{ $latestPayout->created_at?->format('M d, Y') }}</small>
                                @else
                                    —
                                @endif
                            </strong>
                        </div>
                    </div>
                </section>
            </div>

            <div class="cw-lower-grid">
                <section class="cw-mini-card cw-mini-card--equal">
                    <div class="cw-mini-card__label">{{ __('Balances') }}</div>

                    <div class="cw-balance-grid-sm cw-balance-grid-sm--single">
                        <div class="cw-balance-box-sm cw-balance-box-sm--center">
                            <div class="cw-balance-box-sm__label">{{ __('Total Paid Out') }}</div>
                            <div class="cw-balance-box-sm__value">${{ number_format($totalPaidOut, 2) }} USD</div>
                        </div>
                    </div>
                </section>

                <section class="cw-mini-card cw-mini-card--equal">
                    <div class="cw-mini-card__label">{{ __('Quick Actions') }}</div>

                    <div class="cw-actions-grid cw-actions-grid--three">
                        @if($account)
                            <form method="POST" action="{{ route('coach.payouts.start') }}">
                                @csrf
                                <button type="submit" class="cw-action-tile">
                                    <span class="cw-action-tile__icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                            <path d="M3 10h18"></path>
                                        </svg>
                                    </span>
                                    <span class="cw-action-tile__text">{{ __('Update Your Bank Details') }}</span>
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('coach.payouts.start') }}">
                                @csrf
                                <button type="submit" class="cw-action-tile">
                                    <span class="cw-action-tile__icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 5v14"></path>
                                            <path d="M5 12h14"></path>
                                        </svg>
                                    </span>
                                    <span class="cw-action-tile__text">{{ __('Update Your Bank Details') }}</span>
                                </button>
                            </form>
                        @endif

                        <a href="#payout-history" class="cw-action-tile">
                            <span class="cw-action-tile__icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3v18h18"></path>
                                    <path d="M8 14l3-3 2 2 5-6"></path>
                                </svg>
                            </span>
                            <span class="cw-action-tile__text">{{ __('Payout History') }}</span>
                        </a>

                        <a href="{{ route('coach.payouts.settings') }}" class="cw-action-tile">
                            <span class="cw-action-tile__icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.21 7.1a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01A1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </span>
                            <span class="cw-action-tile__text">{{ __('Settings') }}</span>
                        </a>
                    </div>
                </section>
            </div>

            @if(!empty($requirementsDue) || !empty($pastDueRequirements))
                <section class="cw-note">
                    <div class="cw-note__title">{{ __('Action required') }}</div>
                    <ul class="cw-note__list">
                        @foreach(array_slice(array_merge($pastDueRequirements, $requirementsDue), 0, 8) as $item)
                            <li>{{ ucfirst(str_replace('_', ' ', $item)) }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="cw-panel" id="payout-history">
                <div class="cw-panel-head">
                    <div>
                        <h2 class="cw-panel-title">{{ __('Payout History') }}</h2>
                    </div>

                    <form method="GET" action="{{ route('coach.withdraw.index') }}" class="cw-table-filters">
                        <select name="status" class="cw-select" onchange="this.form.submit()">
                            <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>{{ __('All Statuses') }}</option>
                            <option value="paid" {{ $selectedStatus === 'paid' ? 'selected' : '' }}>{{ __('Paid') }}</option>
                            <option value="processing" {{ $selectedStatus === 'processing' ? 'selected' : '' }}>{{ __('Processing') }}</option>
                            <option value="payout_pending" {{ $selectedStatus === 'payout_pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                            <option value="failed" {{ $selectedStatus === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                        </select>

                        <select name="range" class="cw-select" onchange="this.form.submit()">
                            <option value="7days" {{ $range === '7days' ? 'selected' : '' }}>{{ __('Last 7 Days') }}</option>
                            <option value="30days" {{ $range === '30days' ? 'selected' : '' }}>{{ __('Last 30 Days') }}</option>
                            <option value="90days" {{ $range === '90days' ? 'selected' : '' }}>{{ __('Last 90 Days') }}</option>
                            <option value="year" {{ $range === 'year' ? 'selected' : '' }}>{{ __('Last 1 Year') }}</option>
                            <option value="all" {{ $range === 'all' ? 'selected' : '' }}>{{ __('All Time') }}</option>
                        </select>
                    </form>
                </div>

                @if($payouts->count() === 0)
                    <div class="cw-empty">
                        <h4>{{ __('No payouts found') }}</h4>
                        <p>{{ __('No Stripe payout records matched your current filters.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table cw-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Method') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Reference') }}</th>
                                    <th class="text-end">{{ __('Receipt') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payouts as $payout)
                                    @php
                                        $status = strtolower((string) ($payout->status ?? 'pending'));

                                        $badgeClass = match($status) {
                                            'paid' => 'cw-badge--success',
                                            'failed', 'reversed' => 'cw-badge--danger',
                                            'processing', 'payout_pending', 'transfer_created', 'pending' => 'cw-badge--warning',
                                            default => 'cw-badge--muted',
                                        };
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="cw-main">{{ $payout->created_at?->format('M d, Y') }}</div>
                                            <div class="cw-sub">{{ $payout->created_at?->format('h:i A') }}</div>
                                        </td>

                                        <td>
                                            <div class="cw-main">
                                                ${{ number_format(($payout->amount_minor ?? 0) / 100, 2) }}
                                                <span class="cw-currency">{{ strtoupper($payout->currency ?? 'USD') }}</span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="cw-main">{{ __('Bank') }}</div>
                                            <div class="cw-sub">
                                                @if($defaultMethod && !empty($defaultMethod->last4))
                                                    {{ $defaultMethod->bank_name ?: __('Bank') }} •••• {{ $defaultMethod->last4 }}
                                                @else
                                                    {{ __('Connected payout method') }}
                                                @endif
                                            </div>
                                        </td>

                                        <td>
                                            <span class="cw-badge {{ $badgeClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </span>

                                            @if($payout->failure_reason)
                                                <div class="cw-error">{{ $payout->failure_reason }}</div>
                                            @endif
                                        </td>

                                        <td>
                                            <span class="cw-ref">
                                                {{ $payout->provider_payout_id ?: ($payout->provider_transfer_id ?: '—') }}
                                            </span>
                                        </td>

                                        <td class="text-end">
                                            <div class="cw-receipt-actions">
                                                <a href="{{ route('coach.withdraw.receipt', $payout) }}" class="cw-link-btn">
                                                    {{ __('View') }}
                                                </a>
                                                <a href="{{ route('coach.withdraw.receipt.download', $payout) }}" class="cw-link-btn">
                                                    {{ __('Download') }}
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="cw-pagination">
                        {{ $payouts->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection