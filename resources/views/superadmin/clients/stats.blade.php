@extends('superadmin.layout')
@section('title', 'Client Analytics')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-client-stats.css') }}">
@endpush

@section('content')
@php
  $clientName = trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: ($client->username ?? 'Client');
  $avatar = !empty($client->avatar_path) ? asset('storage/'.$client->avatar_path) : null;
  $letter = strtoupper(mb_substr($clientName, 0, 1));

 
@endphp

<section class="client-stats-page">
  <div class="top-card">
    <div class="top-head">
      <div class="identity-wrap">
        <a href="{{ route('superadmin.clients.index') }}" class="back-link">
          <i class="bi bi-arrow-left"></i> Back to Clients
        </a>

        <div class="identity-card">
          <div class="avatar-wrap">
            @if($avatar)
              <img src="{{ $avatar }}" alt="{{ $clientName }}" class="avatar-img">
            @else
              <div class="avatar-fallback">{{ $letter }}</div>
            @endif
          </div>

          <div class="identity-meta">
            <h1>{{ $clientName }}</h1>
            <div class="muted">{{ $client->email ?? '—' }}</div>
            <div class="muted">Selected Period: {{ $periodLabel }}</div>
          </div>
        </div>
      </div>

      <form method="get" class="filters">
        <div class="filter-group">
          <label>Range</label>
          <select name="range" onchange="toggleDateFields(this.value)">
            <option value="lifetime" @selected($range==='lifetime')>All Time</option>
            <option value="daily" @selected($range==='daily')>Today</option>
            <option value="weekly" @selected($range==='weekly')>This Week</option>
            <option value="monthly" @selected($range==='monthly')>This Month</option>
            <option value="yearly" @selected($range==='yearly')>This Year</option>
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

        <div class="filter-actions">
          <button type="submit" class="btn-filter">Apply</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ANALYTICS BLOCKS --}}
  <section class="analytics-board">

    <div class="analytics-row">
      <div class="analytics-section">
        <div class="section-title">Activity Overview</div>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card">
            <span class="metric-label">Total Spend</span>
            <strong>{{ $fmt($totalSpendMinor) }}</strong>
            <small>{{ number_format($paidBookingsCount) }} paid bookings</small>
          </div>

          <div class="metric-card">
            <span class="metric-label">Average Booking Value</span>
            <strong>{{ $fmt($avgBookingValueMinor) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Completed Bookings</span>
            <strong>{{ number_format($completedBookingsCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Cancelled Bookings</span>
            <strong>{{ number_format($cancelledBookingsCount) }}</strong>
          </div>
        </div>
      </div>

      <div class="analytics-section">
        <div class="section-title">Payment Breakdown</div>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card money">
            <span class="metric-label">Paid for Services</span>
            <strong>{{ $fmt($totalServiceSpendMinor) }}</strong>
            <small>Subtotal before platform fees</small>
          </div>

          <div class="metric-card money">
            <span class="metric-label">Platform Fee Charged</span>
            <strong>{{ $fmt($totalPlatformPaidMinor) }}</strong>
          </div>

          <div class="metric-card money">
            <span class="metric-label">Platform Fee Refunded</span>
            <strong>{{ $fmt($totalPlatformFeeRefundedMinor) }}</strong>
          </div>

          <div class="metric-card money">
            <span class="metric-label">Net Platform Fee Kept</span>
            <strong>{{ $fmt($totalNetPlatformPaidMinor) }}</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="analytics-row">
      <div class="analytics-section">
        <div class="section-title">Refunds</div>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card money">
            <span class="metric-label">Refunded to Client</span>
            <strong>{{ $fmt($totalRefundedMinor) }}</strong>
            <small>
              {{ number_format($refundSucceededCount) }} successful /
              {{ number_format($refundPartialCount) }} partial
            </small>
          </div>

          <div class="metric-card money">
            <span class="metric-label">Wallet Refunds</span>
            <strong>{{ $fmt($walletRefundedMinor) }}</strong>
          </div>

          <div class="metric-card money">
            <span class="metric-label">Original Refund</span>
            <strong>{{ $fmt($externalRefundedMinor) }}</strong>
          </div>
        </div>
      </div>

      <div class="analytics-section">
        <div class="section-title">Client Outcomes</div>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card ">
            <span class="metric-label">Lost by Client</span>
            <strong>{{ $fmt($clientLostMinor) }}</strong>
            <small>Based on client penalties</small>
          </div>

          <div class="metric-card">
            <span class="metric-label">Refund Attempts</span>
            <strong>{{ number_format($refundsCount) }}</strong>
            <small>
              {{ number_format($refundProcessingCount) }} processing /
              {{ number_format($refundFailedCount) }} failed
            </small>
          </div>
        </div>
      </div>
    </div>

    <div class="analytics-row">
      <div class="analytics-section">
        <div class="section-title">Dispute Activity</div>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card">
            <span class="metric-label">Dispute Raised by Client</span>
            <strong>{{ number_format($clientDisputesCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Dispute Raised by Coach</span>
            <strong>{{ number_format($coachDisputesCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Open Disputes</span>
            <strong>{{ number_format($openDisputesCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Resolved Disputes</span>
            <strong>{{ number_format($resolvedDisputesCount) }}</strong>
          </div>
        </div>
      </div>

      <div class="analytics-section">
        <div class="section-title">Dispute Results</div>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card ">
            <span class="metric-label">Client Dispute Wins</span>
            <strong>{{ number_format($clientDisputeWinsCount) }}</strong>
          </div>

          <div class="metric-card ">
            <span class="metric-label">Client Dispute Losses</span>
            <strong>{{ number_format($clientDisputeLossesCount) }}</strong>
          </div>

          <div class="metric-card ">
            <span class="metric-label">Coach Dispute Wins</span>
            <strong>{{ number_format($coachDisputeWinsCount) }}</strong>
          </div>

          <div class="metric-card ">
            <span class="metric-label">Coach Dispute Losses</span>
            <strong>{{ number_format($coachDisputeLossesCount) }}</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="analytics-row analytics-row--single">
      <div class="analytics-section">
        <div class="section-title text-center">Dispute Outcomes</div>
        <div class="stats-grid stats-grid--2 stats-grid--center-one">
  <div class="metric-card money">
    <span class="metric-label">Recovered Via Client Wins</span>
    <strong>{{ $fmt($clientDisputeRefundedMinor) }}</strong>
  </div>
</div>
      </div>
    </div>
  </section>

  {{-- TABLES --}}
 <section class="panel">
  <div class="panel__head">
    <h3 class="text-center">Reservation History</h3>
    {{-- <span class="muted">Reservation activity in selected period</span> --}}
  </div>

  <div class="table-wrap table-wrap--soft">
    <table class="stats-table clean-table clean-table--equal clean-table--soft">
      <thead>
       <tr>
  <th>ID</th>
  <th>Coach</th>
  <th>Service</th>
  <th>Status</th>
  <th>Payment</th>
  <th>Settlement</th>
  <th>Service Paid</th>
  <th>Service Fee</th>
  <th>Total</th>
  <th>Penalty</th>
  {{-- <th>Refund Total</th> --}}
  <th>Created On</th>
</tr>
      </thead>
      <tbody>
        @forelse($reservationRows as $row)
         @php
  $paymentStatus = strtolower((string) $row->payment_status);
  $settlementStatus = strtolower((string) $row->settlement_status);

  $paymentClass = match($paymentStatus) {
    'paid' => 'pill pill--success',
    'refund pending', 'refund_pending' => 'pill pill--warning',
    'refunded' => 'pill pill--danger',
    'partially refunded', 'partially_refunded', 'partial_refund', 'refund_partial', 'refunded_partial' => 'pill pill--danger',
    default => 'pill pill--neutral',
  };

  $paymentLabel = match($paymentStatus) {
    'paid' => 'Paid',
    'refund pending', 'refund_pending' => 'Refund Pending',
    'refunded' => 'Refunded',
    'partially refunded', 'partially_refunded', 'partial_refund', 'refund_partial', 'refunded_partial' => 'Partially Refunded',
    default => ucwords(str_replace('_', ' ', (string) $row->payment_status)),
  };

  $settlementClass = match($settlementStatus) {
    'paid' => 'status-pill soft status-pill--success',
    'refund pending', 'refund_pending' => 'status-pill soft status-pill--warning',
    'refunded' => 'status-pill soft status-pill--danger',
    'partially refunded', 'partially_refunded', 'partial_refund', 'refund_partial', 'refunded_partial' => 'status-pill soft status-pill--danger',
    default => 'status-pill soft',
  };

  $settlementLabel = match($settlementStatus) {
    'paid' => 'Paid',
    'refund pending', 'refund_pending' => 'Refund Pending',
    'refunded' => 'Refunded',
    'partially refunded', 'partially_refunded', 'partial_refund', 'refund_partial', 'refunded_partial' => 'Partially Refunded',
    default => ucwords(str_replace('_', ' ', (string) $row->settlement_status)),
  };
@endphp

          <tr>
            <td>
              <div class="td-stack">
               <div class="td-main td-main--strong">
  <a href="{{ route('superadmin.bookings.show', $row->id) }}">
    #{{ $row->id }}
  </a>
</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $row->coach->username ?? $row->coach->email ?? '—' }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $row->service->title ?? '—' }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ ucfirst($row->status ?? '—') }}</div>
                @if(!empty($row->payment_status))
                  <div class="td-sub">
                    <span class="{{ $paymentClass }}">{{ $paymentLabel }}</span>
                  </div>
                @endif
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main text-capitalize">{{ $row->payment_status ?? '—' }}</div>
              </div>
            </td>

            <td>
  <div class="td-stack">
    <div class="td-main">
      <span class="{{ $settlementClass }}">{{ $settlementLabel }}</span>
    </div>
  </div>
</td>
           

<td>
  <div class="td-stack">
    <div class="td-main ">{{ $fmt((int) ($row->subtotal_minor ?? 0)) }}</div>
  </div>
</td>

<td>
  <div class="td-stack">
    <div class="td-main ">{{ $fmt((int) ($row->fees_minor ?? 0)) }}</div>
  </div>
</td>

 <td>
  <div class="td-stack">
    <div class="td-main money-neutral">{{ $fmt((int) $row->total_minor) }}</div>
  </div>
</td>

<td>
  <div class="td-stack">
    <div class="td-main money-negative">{{ $fmt((int) $row->client_penalty_minor) }}</div>
  </div>
</td>

{{-- <td>
  <div class="td-stack">
    <div class="td-main money-positive">{{ $fmt((int) $row->refund_total_minor) }}</div>
  </div>
</td> --}}
            <td>
              <div class="td-stack">
                <div class="td-main">{{ optional($row->created_at)->format('Y-m-d H:i') }}</div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="empty-cell text-capitalize">No reservation activity found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">
    {{ $reservationRows->links() }}
  </div>
</section>

 <section class="panel">
  <div class="panel__head">
    <h3>Refund History</h3>
    {{-- <span class="muted">Refund records in selected period</span> --}}
  </div>

  <div class="table-wrap table-wrap--soft">
    <table class="stats-table clean-table clean-table--equal clean-table--soft">
      <thead>
        <tr>
          <th>ID</th>
          <th>Service ID</th>
          <th>Coach</th>
          <th>Method</th>
          <th>Status</th>
          <th>Refunded To Wallet</th>
          <th>Refunded To Original</th>
          <th>Actual Total</th>
          <th>Requested On</th>
          <th>Processed On</th>
        </tr>
      </thead>
      <tbody>
        @forelse($refundRows as $refund)
          @php
            $refundClass = match(strtolower((string) $refund->status)) {
              'paid' => 'pill pill--success',
              'refund pending', 'refund_pending', 'processing' => 'pill pill--warning',
              'refunded', 'succeeded' => 'pill pill--success',
              'partially refunded', 'partial', 'partially_refunded' => 'pill pill--danger',
              'failed' => 'pill pill--danger',
              default => 'pill pill--neutral',
            };
            $refundMethodLabel = match(strtolower((string) $refund->method)) {
  'wallet_credit' => 'Wallet Credit',
  'original_payment' => 'Original Source',
  default => $refund->method ? ucwords(str_replace('_', ' ', (string) $refund->method)) : '—',
};

            $refundLabel = match(strtolower((string) $refund->status)) {
              'paid' => 'Paid',
              'refund pending', 'refund_pending' => 'Refund Pending',
              'processing' => 'Processing',
              'refunded', 'succeeded' => 'Refunded',
              'partial', 'partially refunded', 'partially_refunded' => 'Partially Refunded',
              'failed' => 'Failed',
              default => ucwords(str_replace('_', ' ', (string) $refund->status)),
            };
          @endphp

          <tr>
            <td>
              <div class="td-stack">
                <div class="td-main td-main--strong">#{{ $refund->id }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $refund->reservation->service->id ?? '—' }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $refund->reservation->coach->username ?? $refund->reservation->coach->email ?? '—' }}</div>
              </div>
            </td>

            

            <td>
              <div class="td-stack">
              <div class="td-main">{{ $refundMethodLabel }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">
                  <span class="{{ $refundClass }}">{{ $refundLabel }}</span>
                </div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main money-positive">{{ $fmt((int) $refund->refunded_to_wallet_minor) }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main money-positive">{{ $fmt((int) $refund->refunded_to_original_minor) }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main money-neutral">{{ $fmt((int) $refund->actual_amount_minor) }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ optional($refund->requested_at)->format('Y-m-d H:i') }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ optional($refund->processed_at)->format('Y-m-d H:i') ?: '—' }}</div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="empty-cell text-capitalize">No refunds found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">
    {{ $refundRows->links() }}
  </div>
</section>

 <section class="panel">
  <div class="panel__head">
    <h3>Dispute History</h3>
    {{-- <span class="muted">All disputes related to this client in selected period</span> --}}
  </div>

  <div class="table-wrap table-wrap--soft">
    <table class="stats-table clean-table clean-table--equal clean-table--soft">
      <thead>
        <tr>
          <th>ID</th>
          <th>Booking ID</th>
          <th>Coach</th>
          <th>Service</th>
          <th>Raised By</th>
          <th>Status</th>
          <th>Decision</th>
          <th>Resolved By</th>
          <th>Opened</th>
          <th>Resolved</th>
        </tr>
      </thead>
      <tbody>
        @forelse($disputeRows as $dispute)
          @php
            $raisedByRole = match(strtolower((string) $dispute->opened_by_role)) {
              'client' => 'Client',
              'coach' => 'Coach',
              default => ucfirst((string) $dispute->opened_by_role),
            };

            $decisionLabel = match(strtolower((string) $dispute->decision_action)) {
  'refund_service' => 'Refund Service',
  'refund_full' => 'Refund Full',
  'pay_coach' => 'Pay Coach',
  default => $dispute->decision_action
    ? ucwords(str_replace('_', ' ', (string) $dispute->decision_action))
    : '—',
};

            $raisedByUser = $dispute->opener->username
              ?? $dispute->opener->email
              ?? '—';

            $resolvedByUser = $dispute->resolvedBy->username
              ?? $dispute->resolvedBy->email
              ?? '—';

            $disputeClass = match(strtolower((string) $dispute->status_label)) {
              'open' => 'pill pill--warning',
              'resolved' => 'pill pill--success',
              default => 'pill pill--neutral',
            };
          @endphp

          <tr>
            <td>
              <div class="td-stack">
                <div class="td-main td-main--strong">
  <a href="{{ route('superadmin.disputes.show', $dispute->id) }}">
    #{{ $dispute->id }}
  </a>
</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">#{{ $dispute->reservation->id ?? '—' }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $dispute->reservation->coach->username ?? $dispute->reservation->coach->email ?? '—' }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $dispute->reservation->service->title ?? '—' }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $raisedByRole }}</div>
                <div class="td-sub">{{ $raisedByUser }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">
                  <span class="{{ $disputeClass }}">{{ $dispute->status_label ?? '—' }}</span>
                </div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $decisionLabel }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ $resolvedByUser }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ optional($dispute->created_at)->format('Y-m-d H:i') }}</div>
              </div>
            </td>

            <td>
              <div class="td-stack">
                <div class="td-main">{{ optional($dispute->resolved_at)->format('Y-m-d H:i') ?: '—' }}</div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="empty-cell text-capitalize">No disputes found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">
    {{ $disputeRows->links() }}
  </div>
</section>
</section>
@endsection

@push('scripts')
<script>
  function toggleDateFields(range) {
    document.querySelector('.js-day').style.display   = range === 'daily' ? '' : 'none';
    document.querySelector('.js-month').style.display = range === 'monthly' ? '' : 'none';
    document.querySelector('.js-year').style.display  = ['monthly','yearly'].includes(range) ? '' : 'none';
    document.querySelector('.js-from').style.display  = range === 'custom' ? '' : 'none';
    document.querySelector('.js-to').style.display    = range === 'custom' ? '' : 'none';
  }
</script>
@endpush