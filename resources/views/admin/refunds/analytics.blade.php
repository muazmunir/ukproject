@extends('layouts.admin')
@section('title', 'Refund & Agent Analytics')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-refund-analytics.css') }}">
@endpush

@section('content')
<section class="refund-analytics-page">
    <div class="page-top">
        <div>
            <h1>Refund & Agent Analytics</h1>
            <div class="muted">Selected Period: {{ $periodLabel }}</div>
        </div>
    </div>

    <section class="panel">
        <form method="get" class="filters">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="filter-group">
                <label class="px-2">Range</label>
                <select name="range" onchange="toggleDateFields(this.value)">
                    <option value="lifetime" @selected($range==='lifetime')>All Time</option>
                    <option value="daily" @selected($range==='daily')>Day</option>
                    <option value="weekly" @selected($range==='weekly')>Week</option>
                    <option value="monthly" @selected($range==='monthly')>Month</option>
                    <option value="yearly" @selected($range==='yearly')>Year</option>
                    <option value="custom" @selected($range==='custom')>Custom</option>
                </select>
            </div>

            <div class="filter-group js-day" style="{{ $range==='daily' ? '' : 'display:none' }}">
                <label>Day</label>
                <input type="date" name="day" value="{{ request('day') }}">
            </div>

            <div class="filter-group js-month" style="{{ $range==='monthly' ? '' : 'display:none' }}">
                <label>Month</label>
                <select name="month">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int)request('month', now()->month) === $m)>
                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            <div class="filter-group js-year" style="{{ in_array($range,['monthly','yearly'],true) ? '' : 'display:none' }}">
                <label>Year</label>
                <select name="year">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" @selected((int)request('year', now()->year) === $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div class="filter-group js-from" style="{{ $range==='custom' ? '' : 'display:none' }}">
                <label>From</label>
                <input type="date" name="from" value="{{ request('from') }}">
            </div>

            <div class="filter-group js-to" style="{{ $range==='custom' ? '' : 'display:none' }}">
                <label>To</label>
                <input type="date" name="to" value="{{ request('to') }}">
            </div>

            <div class="filter-group">
                <label class="px-2">Agent</label>
                <select name="staff_id">
                    <option value="">All Agents</option>
                    @foreach($staffOptions as $staff)
                        @php
                            $staffName = trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')) ?: ($staff->username ?: $staff->email);
                        @endphp
                        <option value="{{ $staff->id }}" @selected((int)$staffId === (int)$staff->id)>
                           {{ $staffName }} ({{ $staff->role_label }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="px-1">Refund Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="succeeded" @selected($status==='succeeded')>Succeeded</option>
                    <option value="partial" @selected($status==='partial')>Partial</option>
                    <option value="failed" @selected($status==='failed')>Failed</option>
                    <option value="processing" @selected($status==='processing')>Processing</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="px-1">Destination</label>
                <select name="destination">
                    <option value="">All</option>
                    <option value="wallet" @selected($destination==='wallet')>Client Wallet</option>
                    <option value="original" @selected($destination==='original')>Original Payment Method</option>
                    <option value="mixed" @selected($destination==='mixed')>Mixed</option>
                </select>
            </div>

            <div class="filter-group search">
                <label class="px-2">Search</label>
                <input
                    type="search"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Refund #, Dispute #, Reservation #, Client, Coach, Service..."
                >
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">Apply</button>
                <a href="{{ route('admin.refunds.analytics', ['tab' => $tab]) }}" class="btn-filter btn-filter--light">Reset</a>
            </div>
        </form>
    </section>

    <div class="tabbar">
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'overview']) }}" class="tablink {{ $tab==='overview' ? 'active' : '' }}">Overview</a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'cancellation_refunds']) }}" class="tablink {{ $tab==='cancellation_refunds' ? 'active' : '' }}">Cancellation Refunds</a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'dispute_refunds']) }}" class="tablink {{ $tab==='dispute_refunds' ? 'active' : '' }}">Dispute Refunds</a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'coach_payouts']) }}" class="tablink {{ $tab==='coach_payouts' ? 'active' : '' }}">Coach Payouts</a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'agent_performance']) }}" class="tablink {{ $tab==='agent_performance' ? 'active' : '' }}">Agent Performance</a>
    </div>

    @if($tab === 'overview')
        <div class="stats-grid">
            <div class="metric-card">
                <span class="metric-label">Total Refunds</span>
                <strong>{{ number_format($allRefundCount) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">Total Refund Amount</span>
                <strong>{{ $fmt($allRefundMinor) }}</strong>
            </div>

            <div class="metric-card">
                <span class="metric-label">Cancellation Refunds</span>
                <strong>{{ number_format($cancellationRefundCount) }}</strong>
                <small>{{ $fmt($cancellationRefundMinor) }}</small>
            </div>

            <div class="metric-card">
                <span class="metric-label">Dispute Refunds</span>
                <strong>{{ number_format($disputeRefundCount) }}</strong>
                <small>{{ $fmt($disputeRefundMinor) }}</small>
            </div>

            <div class="metric-card money">
                <span class="metric-label">Refunded to Client Wallet</span>
                <strong>{{ $fmt($walletRefundMinor) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">Refunded to Original Method</span>
                <strong>{{ $fmt($originalRefundMinor) }}</strong>
            </div>

            <div class="metric-card">
                <span class="metric-label">Processing Refunds</span>
                <strong>{{ number_format($processingRefundCount) }}</strong>
            </div>

            <div class="metric-card ">
                <span class="metric-label">Failed Refunds</span>
                <strong>{{ number_format($failedRefundCount) }}</strong>
            </div>

            <div class="metric-card">
                <span class="metric-label">Average Refund</span>
                <strong>{{ $fmt($avgRefundMinor) }}</strong>
            </div>

            <div class="metric-card">
                <span class="metric-label">Coach Payout Decisions</span>
                <strong>{{ number_format($coachPayoutCount) }}</strong>
                <small>{{ $fmt($coachPayoutMinor) }} total</small>
            </div>
        </div>

        <section class="panel">
            <div class="panel__head">
                <h3>Manager Summary</h3>
                <span class="muted">This Overview Seperates Cancellation Refunds From Dispute Refunds, Allowing Managers To Clearly Review Refund Patterns.</span>
            </div>

            <div class="analytics-table-card">
                <div class="table-wrap">
                    <table class="analytics-table analytics-table--summary">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Count</th>
                                <th>Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Cancellation Refunds</td>
                                <td>{{ number_format($cancellationRefundCount) }}</td>
                                <td class="text-money">{{ $fmt($cancellationRefundMinor) }}</td>
                                <td>Refunds created because a reservation was cancelled.</td>
                            </tr>
                            <tr>
                                <td>Dispute Refunds</td>
                                <td>{{ number_format($disputeRefundCount) }}</td>
                                <td class="text-money">{{ $fmt($disputeRefundMinor) }}</td>
                                <td>Refunds created after dispute handling and agent intervention.</td>
                            </tr>
                            <tr>
                                <td>Coach Payout Decisions</td>
                                <td>{{ number_format($coachPayoutCount) }}</td>
                                <td class="text-money">{{ $fmt($coachPayoutMinor) }}</td>
                                <td>Cases where the final decision was to pay the coach.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    @if($tab === 'cancellation_refunds')
        <div class="stats-grid">
            <div class="metric-card">
                <span class="metric-label">Cancellation Refund Count</span>
                <strong>{{ number_format($cancellationRefundCount) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">Cancellation Refund Amount</span>
                <strong>{{ $fmt($cancellationRefundMinor) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">To Client Wallet</span>
                <strong>{{ $fmt($cancellationWalletMinor) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">To Original Method</span>
                <strong>{{ $fmt($cancellationOriginalMinor) }}</strong>
            </div>
        </div>

        <section class="panel">
            <div class="panel__head">
                <h3>Cancellation Refund Logs</h3>
                <span class="muted">Only refunds created because of reservation cancellation. This excludes dispute refund decisions.</span>
            </div>

            <div class="analytics-table-card">
                <div class="table-wrap">
                   <table class="analytics-table analytics-table--logs analytics-table--fit">
                        <thead>
                            <tr>
                                <th class="col-id">Refund #</th>
                                <th class="col-id">Reservation #</th>
                                <th class="col-name">Resolved By</th>
                                <th class="col-type">Handler Type</th>
                                <th class="col-name">Client</th>
                                <th class="col-name">Coach</th>
                                <th class="col-service">Service</th>
                                <th class="col-badge">Decision</th>
                                <th class="col-badge">Status</th>
                                <th class="col-badge">Funds Sent To</th>
                                <th class="col-money">Total</th>
                                <th class="col-date">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cancellationRefundRows as $row)
                                <tr>
                                    <td class="fw-700">#{{ $row->id }}</td>
                                    <td class="fw-700">#{{ $row->reservation_id }}</td>
                                    <td>{{ $row->issued_by_name }}</td>
                                    <td>
                                        <span class="table-badge table-badge--gray">
                                            {{ str_replace('_', ' ', $row->issued_by_type) }}
                                        </span>
                                    </td>
                                    <td>{{ $row->reservation->client->username ?? $row->reservation->client->email ?? '—' }}</td>
                                    <td>{{ $row->reservation->coach->username ?? $row->reservation->coach->email ?? '—' }}</td>
                                    <td>{{ $row->reservation->service->title ?? '—' }}</td>
                                    <td>
                                        <span class="table-badge table-badge--blue text-capitalize">
                                            {{ $row->decision_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge {{ refundStatusBadgeClass($row->status) }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge table-badge--slate text-capitalize">
                                            {{ $row->funds_destination_label }}
                                        </span>
                                    </td>
                                    <td class="text-money">{{ $fmt((int) $row->actual_amount_minor) }}</td>
                                    <td>{{ optional($row->event_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="empty-cell">No cancellation refund records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pager">
                {{ $cancellationRefundRows->links() }}
            </div>
        </section>
    @endif

    @if($tab === 'dispute_refunds')
        <div class="stats-grid">
            <div class="metric-card">
                <span class="metric-label">Dispute Refund Count</span>
                <strong>{{ number_format($disputeRefundCount) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">Dispute Refund Amount</span>
                <strong>{{ $fmt($disputeRefundMinor) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">To Client Wallet</span>
                <strong>{{ $fmt($disputeWalletMinor) }}</strong>
            </div>

            <div class="metric-card money">
                <span class="metric-label">To Original Method</span>
                <strong>{{ $fmt($disputeOriginalMinor) }}</strong>
            </div>
        </div>

        <section class="panel">
            <div class="panel__head">
                <h3>Dispute Refund Logs</h3>
                <span class="muted text-capitalize">Only refunds linked to dispute outcomes such as full refund or service-only refund.</span>
            </div>

            <div class="analytics-table-card">
                <div class="table-wrap">
                    <table class="analytics-table analytics-table--logs analytics-table--fit">
                        <thead>
                            <tr>
                                <th class="col-id">Refund #</th>
                                <th class="col-id">Reservation #</th>
                                <th class="col-name">Issued By</th>
                                <th class="col-type">Issuer Type</th>
                                <th class="col-name">Client</th>
                                <th class="col-name">Coach</th>
                                <th class="col-service">Service</th>
                                <th class="col-badge">Decision</th>
                                <th class="col-badge">Status</th>
                                <th class="col-badge">Funds Sent To</th>
                                <th class="col-money">Total</th>
                                <th class="col-date">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($disputeRefundRows as $row)
                                <tr>
                                    <td class="fw-700">#{{ $row->id }}</td>
                                    <td class="fw-700">#{{ $row->reservation_id }}</td>
                                    <td>{{ $row->issued_by_name }}</td>
                                    <td>
                                        <span class="table-badge table-badge--gray">
                                            {{ str_replace('_', ' ', $row->issued_by_type) }}
                                        </span>
                                    </td>
                                    <td>{{ $row->reservation->client->username ?? $row->reservation->client->email ?? '—' }}</td>
                                    <td>{{ $row->reservation->coach->username ?? $row->reservation->coach->email ?? '—' }}</td>
                                    <td>{{ $row->reservation->service->title ?? '—' }}</td>
                                    <td>
                                        <span class="table-badge table-badge--blue text-capitalize">
                                            {{ $row->decision_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge {{ refundStatusBadgeClass($row->status) }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge table-badge--slate text-capitalize">
                                            {{ $row->funds_destination_label }}
                                        </span>
                                    </td>
                                    <td class="text-money">{{ $fmt((int) $row->actual_amount_minor) }}</td>
                                    <td>{{ optional($row->event_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="empty-cell">No dispute refund records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pager">
                {{ $disputeRefundRows->links() }}
            </div>
        </section>
    @endif

    @if($tab === 'coach_payouts')
        <div class="stats-grid">
            <div class="metric-card">
                <span class="metric-label">Coach Payout Decisions</span>
                <strong>{{ number_format($coachPayoutCount) }}</strong>
            </div>

            <div class="metric-card ">
                <span class="metric-label">Coach Payout Amount</span>
                <strong>{{ $fmt($coachPayoutMinor) }}</strong>
            </div>
        </div>

        <section class="panel">
            <div class="panel__head">
                <h3>Coach Payout Decision Logs</h3>
                <span class="muted text-capitalize">Final dispute decisions where the money was awarded to the coach.</span>
            </div>

            <div class="analytics-table-card">
                <div class="table-wrap">
                <table class="analytics-table analytics-table--logs analytics-table--fit">
                        <thead>
                            <tr>
                                <th class="col-id">Dispute #</th>
                                <th class="col-id">Reservation #</th>
                                <th class="col-name">Decided By</th>
                                <th class="col-name">Client</th>
                                <th class="col-name">Coach</th>
                                <th class="col-service">Service</th>
                                <th class="col-badge">Decision</th>
                                <th class="col-badge">Destination</th>
                                <th class="col-money">Amount</th>
                                <th class="col-date">Created On</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coachPayoutRows as $row)
                                <tr>
                                    <td class="fw-700">#{{ $row->id }}</td>
                                    <td class="fw-700">#{{ $row->reservation_id }}</td>
                                    <td>{{ $row->decided_by_name }}</td>
                                    <td>{{ $row->reservation->client->username ?? $row->reservation->client->email ?? '—' }}</td>
                                    <td>{{ $row->reservation->coach->username ?? $row->reservation->coach->email ?? '—' }}</td>
                                    <td>{{ $row->reservation->service->title ?? '—' }}</td>
                                    <td>
                                        <span class="table-badge table-badge--green text-capitalize">
                                            {{ $row->decision_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge table-badge--slate text-capitalize">
                                            {{ $row->funds_destination_label }}
                                        </span>
                                    </td>
                                    <td class="text-money">{{ $fmt((int) ($row->reservation->coach_earned_minor ?? 0)) }}</td>
                                    <td>{{ optional($row->event_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="empty-cell">No coach payout decisions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pager">
                {{ $coachPayoutRows->links() }}
            </div>
        </section>
    @endif

    @if($tab === 'agent_performance')
        <div class="stats-grid">
            <div class="metric-card">
                <span class="metric-label">Tracked Agents</span>
                <strong>{{ number_format($agentPerformance->count()) }}</strong>
            </div>

            <div class="metric-card">
                <span class="metric-label">Highest Refund Count</span>
                <strong>{{ $topRefundAgent['name'] ?? '—' }}</strong>
                <small>{{ isset($topRefundAgent['refunds_count']) ? number_format($topRefundAgent['refunds_count']) . ' Refunds' : '—' }}</small>
            </div>

            <div class="metric-card money">
                <span class="metric-label">Highest Refund Amount</span>
                <strong>{{ $topRefundAmountAgent['name'] ?? '—' }}</strong>
                <small>{{ isset($topRefundAmountAgent['refunds_minor']) ? $fmt((int) $topRefundAmountAgent['refunds_minor']) : '—' }}</small>
            </div>
        </div>

        <section class="panel">
            <div class="panel__head">
                <h3>Agent Performance Summary</h3>
                <span class="muted text-capitalize">Use this section to identify agents issuing too many refunds or handling high-value dispute outcomes.</span>
            </div>

            <div class="analytics-table-card">
                <div class="table-wrap">
                    <table class="analytics-table analytics-table--summary">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Role</th>
                                <th>Total Refunds</th>
                                <th>Total Refund Amount</th>
                                <th>Cancellation Refunds</th>
                                <th>Dispute Refunds</th>
                                <th>Coach Payout Decisions</th>
                                <th>Coach Payout Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agentPerformance as $row)
                                <tr>
                                    <td class="fw-700">{{ $row['name'] }}</td>
                                    <td>
                                        <span class="table-badge table-badge--gray">
                                            {{ $row['role_label'] }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($row['refunds_count']) }}</td>
                                    <td class="text-money">{{ $fmt((int) $row['refunds_minor']) }}</td>
                                    <td>
                                        {{ number_format($row['cancellation_count']) }}
                                        <br>
                                        <small>{{ $fmt((int) $row['cancellation_minor']) }}</small>
                                    </td>
                                    <td>
                                        {{ number_format($row['dispute_refund_count']) }}
                                        <br>
                                        <small>{{ $fmt((int) $row['dispute_refund_minor']) }}</small>
                                    </td>
                                    <td>{{ number_format($row['payout_count']) }}</td>
                                    <td class="text-money">{{ $fmt((int) $row['payout_minor']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="empty-cell">No agent performance data available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="padding-top: 12px;">
              
            </div>
        </section>
    @endif
</section>
@endsection

@push('scripts')
<script>
    function toggleDateFields(range) {
        document.querySelector('.js-day').style.display   = range === 'daily' ? '' : 'none';
        document.querySelector('.js-month').style.display = range === 'monthly' ? '' : 'none';
        document.querySelector('.js-year').style.display  = ['monthly', 'yearly'].includes(range) ? '' : 'none';
        document.querySelector('.js-from').style.display  = range === 'custom' ? '' : 'none';
        document.querySelector('.js-to').style.display    = range === 'custom' ? '' : 'none';
    }
</script>
@endpush

@php
    /**
     * Optional helper if you don't already have something similar.
     * If Blade helper functions are not allowed in your setup,
     * move this logic to a global helper or replace inline with @php blocks.
     */
    function refundStatusBadgeClass($status) {
        return match(strtolower((string)$status)) {
            'succeeded', 'completed', 'paid', 'refunded' => 'table-badge--green',
            'partial', 'partially refunded'              => 'table-badge--amber',
            'failed', 'cancelled', 'canceled'            => 'table-badge--red',
            'processing', 'pending'                      => 'table-badge--blue',
            default                                      => 'table-badge--gray',
        };
    }
@endphp