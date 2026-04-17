@extends('layouts.admin')

@section('title', 'Bookings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/superadmin-bookings.css') }}">
@endpush

@section('content')
    @php
        $money = function ($minor, $currency = 'USD') {
            $amount = ((int) $minor) / 100;
            return '$' . number_format($amount, 2) . ' ' . strtoupper($currency);
        };

        $labelize = function ($value) {
            return ucwords(str_replace('_', ' ', (string) $value));
        };

        $fullName = function ($user, $fallback = '—') {
            if (!$user) {
                return $fallback;
            }

            $name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
            return $name !== '' ? $name : ($user->email ?? $fallback);
        };

        $pillClass = function ($type, $value) {
            $v = strtolower((string) $value);

            return match ($type) {
                'booking' => match (true) {
                    in_array($v, ['completed', 'booked']) => 'ok',
                    in_array($v, ['cancelled', 'canceled']) => 'danger',
                    in_array($v, ['no_show', 'no_show_client', 'no_show_coach', 'no_show_both']) => 'warn',
                    default => 'neutral',
                },
                'payment' => match (true) {
                    in_array($v, ['paid', 'succeeded']) => 'ok',
                    in_array($v, ['processing', 'pending', 'requires_payment']) => 'warn',
                    in_array($v, ['failed', 'cancelled', 'canceled']) => 'danger',
                    default => 'neutral',
                },
                'settlement' => match (true) {
                    in_array($v, ['paid']) => 'ok',
                    in_array($v, ['refunded']) => 'danger',
                    in_array($v, ['refunded_partial', 'refund_pending']) => 'warn',
                    in_array($v, ['in_dispute']) => 'danger',
                    in_array($v, ['cancelled', 'canceled']) => 'danger',
                    default => 'neutral',
                },
                'refund' => match (true) {
                    in_array($v, ['succeeded']) => 'ok',
                    in_array($v, ['partial', 'processing', 'pending_choice']) => 'warn',
                    in_array($v, ['failed']) => 'danger',
                    default => 'neutral',
                },
                'funding' => match (true) {
                    $v === 'external_only' => 'ok',
                    $v === 'mixed' => 'warn',
                    $v === 'wallet_only' => 'neutral',
                    default => 'neutral',
                },
                default => 'neutral',
            };
        };

        $tabs = $filterOptions['tabs'] ?? [];
        $ranges = $filterOptions['ranges'] ?? [];
        $selectedRange = $filters['range'] ?? 'lifetime';
        $selectedYear = $filters['year'] ?? now()->year;
        $selectedMonth = $filters['month'] ?? now()->month;
        $selectedDay = $filters['day'] ?? '';
        $periodLabel = $filters['period_label'] ?? 'All time';

        $yearOptions = range(now()->year, now()->year - 7);
        $monthOptions = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    @endphp

    <div class="ab-page">
        <div class="ab-shell">

            <div class="ab-head">
                <div class="ab-head__content">
                    <h1 class="ab-title text-center">Bookings</h1>
                    <p class="ab-sub text-center">
                        Operational view of bookings with search, filters, statuses, refunds, disputes, and payment context.
                    </p>
                    <div class="ab-head__period text-center mt-3">
                        <span class="ab-pill neutral">
                            Period: {{ $periodLabel }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="ab-card">
                <div class="ab-cardHead">
                    <div>
                        <h2 class="ab-cardTitle">Filters</h2>
                        <p class="ab-cardSub">
                            Narrow booking records by lifecycle, service, coach, client, payment, settlement, refund,
                            dispute, funding, amount, and reporting period.
                        </p>
                    </div>
                </div>

                <form class="ab-filters" method="GET" action="{{ route('admin.bookings.index') }}">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <div class="ab-filterGrid">
                        <div class="ab-field">
                            <label>Range</label>
                            <select class="ab-input" name="range" id="ab-range-select">
                                @foreach ($ranges as $value => $label)
                                    <option value="{{ $value }}" {{ $selectedRange === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field ab-range-field" data-range-visible="daily">
                            <label>Day</label>
                            <input class="ab-input" type="date" name="day" value="{{ $selectedDay }}">
                        </div>

                        <div class="ab-field ab-range-field" data-range-visible="monthly,yearly">
                            <label>Year</label>
                            <select class="ab-input" name="year">
                                @foreach ($yearOptions as $year)
                                    <option value="{{ $year }}" {{ (int) $selectedYear === (int) $year ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field ab-range-field" data-range-visible="monthly">
                            <label>Month</label>
                            <select class="ab-input" name="month">
                                @foreach ($monthOptions as $value => $label)
                                    <option value="{{ $value }}" {{ (int) $selectedMonth === (int) $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field ab-range-field" data-range-visible="custom">
                            <label>From</label>
                            <input class="ab-input" type="date" name="from"
                                value="{{ optional($filters['date_from'] ?? null)->format('Y-m-d') }}">
                        </div>

                        <div class="ab-field ab-range-field" data-range-visible="custom">
                            <label>To</label>
                            <input class="ab-input" type="date" name="to"
                                value="{{ optional($filters['date_to'] ?? null)->format('Y-m-d') }}">
                        </div>

                        <div class="ab-field ab-field--wide">
                            <label>Search</label>
                            <input class="ab-input" type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Booking ID, service, client, coach, refund, dispute...">
                        </div>

                        <div class="ab-field">
                            <label>Service</label>
                            <input class="ab-input" type="text" name="service"
                                value="{{ $filters['service'] ?? '' }}" placeholder="Service Title">
                        </div>

                        <div class="ab-field">
                            <label>Coach</label>
                            <input class="ab-input" type="text" name="coach"
                                value="{{ $filters['coach'] ?? '' }}" placeholder="Coach name or email">
                        </div>

                        <div class="ab-field">
                            <label>Client</label>
                            <input class="ab-input" type="text" name="client"
                                value="{{ $filters['client'] ?? '' }}" placeholder="Client name or email">
                        </div>

                        <div class="ab-field">
                            <label>Booking Status</label>
                            <select class="ab-input" name="status">
                                <option value="">All</option>
                                @foreach ($filterOptions['statuses'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Payment Status</label>
                            <select class="ab-input" name="payment_status">
                                <option value="">All</option>
                                @foreach ($filterOptions['payment_statuses'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['payment_status'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Settlement Status</label>
                            <select class="ab-input" name="settlement_status">
                                <option value="">All</option>
                                @foreach ($filterOptions['settlement_statuses'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['settlement_status'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Refund Status</label>
                            <select class="ab-input" name="refund_status">
                                <option value="">All</option>
                                @foreach ($filterOptions['refund_statuses'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['refund_status'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Funding Type</label>
                            <select class="ab-input" name="funding_status">
                                <option value="">All</option>
                                @foreach ($filterOptions['funding_statuses'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['funding_status'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Provider</label>
                            <select class="ab-input" name="provider">
                                <option value="">All</option>
                                @foreach ($filterOptions['providers'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['provider'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Method</label>
                            <select class="ab-input" name="method">
                                <option value="">All</option>
                                @foreach ($filterOptions['methods'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['method'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Dispute Side</label>
                            <select class="ab-input" name="dispute_side">
                                <option value="">All</option>
                                @foreach ($filterOptions['dispute_sides'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['dispute_side'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Dispute Result</label>
                            <select class="ab-input" name="dispute_result">
                                <option value="">All</option>
                                @foreach ($filterOptions['dispute_results'] ?? [] as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['dispute_result'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ab-field">
                            <label>Min Amount</label>
                            <input class="ab-input" type="number" step="0.01" min="0" name="amount_from"
                                value="{{ request('amount_from') }}" placeholder="0.00">
                        </div>

                        <div class="ab-field">
                            <label>Max Amount</label>
                            <input class="ab-input" type="number" step="0.01" min="0" name="amount_to"
                                value="{{ request('amount_to') }}" placeholder="0.00">
                        </div>
                    </div>

                    <div class="ab-filterActions">
                        <button class="ab-btn ab-btn--primary" type="submit">
                            <i class="bi bi-funnel"></i>
                            <span>Apply Filters</span>
                        </button>

                        <a class="ab-btn ab-btn--ghost" href="{{ route('admin.bookings.index', ['tab' => $tab]) }}">
                            <i class="bi bi-x-circle"></i>
                            <span>Clear Filters</span>
                        </a>
                    </div>
                </form>
            </div>

            <div class="ab-tabs">
                @foreach ($tabs as $key => $label)
                    <a class="ab-tab {{ $tab === $key ? 'is-active' : '' }}"
                        href="{{ route('admin.bookings.index', array_filter(array_merge(request()->query(), ['tab' => $key]))) }}">
                        <span>{{ $label }}</span>
                        <span class="ab-count">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            <div class="ab-card">
                <div class="ab-cardHead ab-cardHead--split">
                    <div>
                        <h2 class="ab-cardTitle">Booking Records</h2>
                        <p class="ab-cardSub">
                            Table-focused operational booking list for admin users.
                        </p>
                    </div>

                    <div class="ab-cardMeta">
                        {{ $rows->total() }} result{{ $rows->total() === 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="ab-tableWrap">
                    <table class="ab-table">
                        <thead>
                            <tr>
                                <th>Sl#</th>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Coach</th>
                                <th>Service</th>
                                <th>Money</th>
                                <th>Lifecycle</th>
                                <th>Funding</th>
                                <th class="ab-right">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($rows as $i => $r)
                                @php
                                    $bookingNo = 'BK-' . str_pad((string) $r->id, 8, '0', STR_PAD_LEFT);
                                    $currency = strtoupper((string) ($r->currency ?? 'USD'));
                                    $clientName = $fullName($r->client, 'Client #' . $r->client_id);
                                    $coachModel = $r->coach ?? $r->service?->coach;
                                    $coachName = $fullName($coachModel, 'Coach #' . ($r->coach_id ?? $r->service?->coach_id));
                                    $serviceTitle = $r->service?->title ?? ($r->service_title_snapshot ?? '—');
                                    $packageTitle = $r->package?->name ?? ($r->package_name_snapshot ?? '—');

                                    $lifecycleValue = $r->status ?? 'pending';
                                    $lifecycleType = 'booking';

                                    if (!empty($r->settlement_status) && $r->settlement_status !== 'pending') {
                                        $lifecycleValue = $r->settlement_status;
                                        $lifecycleType = 'settlement';
                                    } elseif (!empty($r->payment_status) && $r->payment_status !== 'requires_payment') {
                                        $lifecycleValue = $r->payment_status;
                                        $lifecycleType = 'payment';
                                    }

                                    $fundingValue = $r->funding_status ?? 'external_only';
                                @endphp

                                <tr>
                                    <td>{{ $rows->firstItem() + $i }}</td>

                                    <td>
                                        <div class="ab-stack">
                                            <strong class="ab-mono">{{ $bookingNo }}</strong>
                                            <span>#{{ $r->id }}</span>
                                            <span>{{ optional($r->created_at)->format('d M Y, h:i A') ?: '—' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ab-stack">
                                            <strong>{{ $clientName }}</strong>
                                            <span>{{ $r->client->email ?? '—' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ab-stack">
                                            <strong>{{ $coachName }}</strong>
                                            <span>{{ $coachModel->email ?? '—' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ab-stack">
                                            <strong>{{ $serviceTitle }}</strong>
                                            <span>{{ $packageTitle }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <strong class="ab-moneyCell__value">
                                            {{ $money($r->total_minor ?? 0, $currency) }}
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="ab-pill {{ $pillClass($lifecycleType, $lifecycleValue) }}">
                                            {{ $labelize($lifecycleValue) }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ab-pill {{ $pillClass('funding', $fundingValue) }}">
                                            {{ $labelize($fundingValue) }}
                                        </span>
                                    </td>

                                    <td class="ab-right">
                                        <a class="ab-iconBtn" href="{{ route('admin.bookings.show', $r->id) }}"
                                            title="View Details">
                                            <i class="bi bi-info-lg"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="ab-empty">No bookings found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (isset($rows) && method_exists($rows, 'links'))
                    <div class="ab-pager">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const rangeSelect = document.getElementById('ab-range-select');
            const rangeFields = document.querySelectorAll('.ab-range-field');

            const updateRangeFields = () => {
                const selected = rangeSelect ? rangeSelect.value : 'lifetime';

                rangeFields.forEach((field) => {
                    const visibleFor = (field.dataset.rangeVisible || '')
                        .split(',')
                        .map(v => v.trim())
                        .filter(Boolean);

                    const show = visibleFor.includes(selected);
                    field.style.display = show ? '' : 'none';
                });
            };

            if (rangeSelect) {
                rangeSelect.addEventListener('change', updateRangeFields);
                updateRangeFields();
            }
        })();
    </script>
@endpush