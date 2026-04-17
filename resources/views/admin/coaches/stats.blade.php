@extends('layouts.admin')
@section('title', 'Coach Analytics')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-coach-stats.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/coach.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/coach_dashboard.css') }}">
@endpush

@section('content')
@php
  $coachName = trim(($coach->first_name ?? '').' '.($coach->last_name ?? '')) ?: ($coach->username ?? 'Coach');
  $avatar = !empty($coach->avatar_path) ? asset('storage/'.$coach->avatar_path) : null;
  $letter = strtoupper(mb_substr($coachName, 0, 1));

  $active = strtolower($range ?? request('range','lifetime'));
  $selectedYear  = (int) request('year', now()->year);
  $selectedMonth = (int) request('month', now()->month);
  $selectedDay   = request('day', now()->format('Y-m-d'));

  $showDay    = ($active === 'daily');
  $showYear   = in_array($active, ['yearly','monthly'], true);
  $showMonth  = ($active === 'monthly');
  $showCustom = ($active === 'custom') || request()->hasAny(['from','to']);
@endphp

<section class="stats-page">
  <div class="top-card">
    <div class="top-head">
      <div class="identity-wrap">
        <a href="{{ route('admin.coaches.index') }}" class="back-link my-3">
          <i class="bi bi-arrow-left"></i> Back to Coaches
        </a>

        <div class="identity-card">
          <div class="avatar-wrap">
            @if($avatar)
              <img src="{{ $avatar }}" alt="{{ $coachName }}" class="avatar-img">
            @else
              <div class="avatar-fallback">{{ $letter }}</div>
            @endif
          </div>

          <div class="identity-meta">
            <h1>{{ $coachName }}</h1>
            <div class="muted">{{ $coach->email ?? '—' }}</div>
            <div class="muted">Selected Period: {{ $periodLabel }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter bar like coach dashboard --}}
  <section class="panel">
    <div class="zv-filterbar">
      <form method="GET" action="{{ route('admin.coaches.stats', $coach->id) }}" class="zv-filterform" id="js-admin-coach-filter">
        <div class="zv-pillgroup" role="group" aria-label="Date filters">
          <button type="button" name="range" value="daily" class="zv-pill {{ $active==='daily' ? 'is-active' : '' }}">Today</button>
          <button type="button" name="range" value="weekly" class="zv-pill {{ $active==='weekly' ? 'is-active' : '' }}">This Week</button>
          <button type="button" name="range" value="monthly" class="zv-pill {{ $active==='monthly' ? 'is-active' : '' }}">This Month</button>
          <button type="button" name="range" value="yearly" class="zv-pill {{ $active==='yearly' ? 'is-active' : '' }}">This Year</button>
          <button type="button" name="range" value="lifetime" class="zv-pill {{ in_array($active,['lifetime','all'],true) ? 'is-active' : '' }}">All Time</button>
          <button type="button" name="range" value="custom" class="zv-pill {{ $active==='custom' ? 'is-active' : '' }}">Custom</button>
        </div>

        <input type="hidden" name="range" value="{{ $active }}">

        <div class="zv-range">
          <label class="zv-range-field js-day" style="{{ $showDay ? '' : 'display:none' }}">
            <span>Day</span>
            <input type="date" name="day" value="{{ $selectedDay }}">
          </label>

          <label class="zv-range-field js-year" style="{{ $showYear ? '' : 'display:none' }}">
            <span>Year</span>
            <select name="year">
              @for($y = now()->year; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" {{ $selectedYear === $y ? 'selected' : '' }}>{{ $y }}</option>
              @endfor
            </select>
          </label>

          <label class="zv-range-field js-month" style="{{ $showMonth ? '' : 'display:none' }}">
            <span>Month</span>
            <select name="month">
              @for($m=1;$m<=12;$m++)
                <option value="{{ $m }}" {{ $selectedMonth === $m ? 'selected' : '' }}>
                  {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                </option>
              @endfor
            </select>
          </label>

          <label class="zv-range-field js-from" style="{{ $showCustom ? '' : 'display:none' }}">
            <span>From</span>
            <input type="date" name="from" value="{{ request('from') }}">
          </label>

          <label class="zv-range-field js-to" style="{{ $showCustom ? '' : 'display:none' }}">
            <span>To</span>
            <input type="date" name="to" value="{{ request('to') }}">
          </label>

          <button type="submit" class="btn-filter">Apply</button>

          <a href="{{ route('admin.coaches.stats', $coach->id) }}"
             class="zv-linkbtn"
             id="js-admin-coach-reset"
             style="{{ ($active !== 'lifetime' || request()->hasAny(['from','to','year','month','day'])) ? '' : 'display:none' }}">
            Clear Filter
          </a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel__head">
      <h3>Selected Period Summary</h3>
      <span class="muted text-capitalize">Everything below follows the selected top filter</span>
    </div>

    <div class="kpi-layout">

      {{-- 1. Activity Overview --}}
      <div class="kpi-block">
        <div class="kpi-block__head">
          <h4>Activity Overview</h4>
        </div>
        <div class="kpi-cards kpi-cards--4">
          <div class="metric-card">
            <span class="metric-label">Bookings Created</span>
            <strong>{{ number_format($bookingsCreatedCount) }}</strong>
            <small>{{ $fmt($bookedGmvMinor) }}</small>
          </div>

          <div class="metric-card">
            <span class="metric-label">Bookings Completed</span>
            <strong>{{ number_format($completedCount) }}</strong>
            <small>{{ $fmt($completedGmvMinor) }}</small>
          </div>

          <div class="metric-card">
            <span class="metric-label">Bookings Cancelled</span>
            <strong>{{ number_format($cancelledCount) }}</strong>
            <small>{{ $fmt($cancelledGmvMinor) }}</small>
          </div>

          <div class="metric-card">
            <span class="metric-label">Total No Show</span>
            <strong>{{ number_format($totalNoShowCount) }}</strong>
          </div>
        </div>
      </div>

      {{-- 2. Exceptions & Outcomes --}}
      <div class="kpi-block">
        <div class="kpi-block__head">
          <h4>Exceptions &amp; Outcomes</h4>
        </div>
        <div class="kpi-cards kpi-cards--3">
          <div class="metric-card">
            <span class="metric-label"> Completed Sessions Refunded</span>
            <strong>{{ number_format($completedRefundedCount) }}</strong>
            <small>{{ $fmt($completedRefundedGmvMinor) }}</small>
          </div>

          <div class="metric-card">
            <span class="metric-label">Coach Cancelled</span>
            <strong>{{ number_format($coachCancelledCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Client Cancelled</span>
            <strong>{{ number_format($clientCancelledCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Both No Show</span>
            <strong>{{ number_format($bothNoShowCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Client No Show</span>
            <strong>{{ number_format($clientNoShowCount) }}</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Coach No Show</span>
            <strong>{{ number_format($coachNoShowCount) }}</strong>
          </div>
        </div>
      </div>

      {{-- 3. Performance Rates --}}
      <div class="kpi-block">
        <div class="kpi-block__head">
          <h4>Performance Rates</h4>
        </div>
        <div class="kpi-cards kpi-cards--2">
          <div class="metric-card">
            <span class="metric-label">Completion Rate</span>
            <strong>{{ number_format($completionRate, 2) }}%</strong>
          </div>

          <div class="metric-card">
            <span class="metric-label">Cancellation Rate</span>
            <strong>{{ number_format($cancellationRate, 2) }}%</strong>
          </div>
        </div>
      </div>

      {{-- 4. Financial Summary --}}
     {{-- 4. Financial Summary --}}
<div class="kpi-block">
  <div class="kpi-block__head">
    <h4>Financial Summary</h4>
  </div>

  <div class="kpi-cards kpi-cards--5">

    <div class="metric-card money">
      <span class="metric-label">Coach Gross</span>
      <strong>{{ $fmt($coachGrossMinor) }}</strong>
    </div>

    <div class="metric-card money">
      <span class="metric-label">Coach Commission</span>
      <strong>{{ $fmt($coachCommissionMinor) }}</strong>
    </div>

    <div class="metric-card money">
      <span class="metric-label">Coach Paid Net</span>
      <strong>{{ $fmt($coachPaidNetMinor) }}</strong>
    </div>

    <div class="metric-card money">
      <span class="metric-label">Coach Net</span>
      <strong>{{ $fmt($coachNetMinor) }}</strong>
    </div>

    <div class="metric-card money">
      <span class="metric-label">Final Payout</span>
      <strong>{{ $fmt($coachFinalImpactMinor) }}</strong>
    </div>

  </div>
</div>
      {{-- 5. Adjustments + Disputes same line --}}
      <div class="kpi-row-2">
        <div class="kpi-block">
          <div class="kpi-block__head">
            <h4>Adjustments</h4>
          </div>
          <div class="kpi-cards kpi-cards--2">
            <div class="metric-card money">
              <span class="metric-label">Coach Compensation</span>
              <strong>{{ $fmt($coachCompMinor) }}</strong>
            </div>

            <div class="metric-card money dangerish">
              <span class="metric-label">Coach Penalties</span>
              <strong>{{ $fmt($coachPenaltiesMinor) }}</strong>
            </div>
          </div>
        </div>

        <div class="kpi-block">
          <div class="kpi-block__head">
            <h4>Disputes</h4>
          </div>
          <div class="kpi-cards kpi-cards--3">
            <div class="metric-card">
              <span class="metric-label">Coach Raised Dispute</span>
              <strong>{{ number_format($coachDisputesRaisedCount) }}</strong>
            </div>

            <div class="metric-card">
              <span class="metric-label">Client Raised Dispute</span>
              <strong>{{ number_format($clientDisputesAgainstCoachCount) }}</strong>
            </div>

            <div class="metric-card">
              <span class="metric-label">Open Disputes</span>
              <strong>{{ number_format($openDisputesCount) }}</strong>
            </div>

            <div class="metric-card">
              <span class="metric-label">Resolved</span>
              <strong>{{ number_format($resolvedDisputesCount) }}</strong>
            </div>

            <div class="metric-card">
              <span class="metric-label">Rejected</span>
              <strong>{{ number_format($rejectedDisputesCount) }}</strong>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <section class="panel">
    <div class="panel__head">
      <h3>Reservation Activity</h3>
      
    </div>

    @if($rows->count())
      <div class="zv-card">
        <div class="zv-card-head">
          <div class="zv-card-title">Earnings & Activity</div>
        </div>

        <div class="zv-tablewrap">
          <table class="zv-table zv-table--responsive">
            <thead>
              <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Status</th>
                <th>Booking Value</th>
                <th>Adjustment</th>
                <th>Service Fee</th>
                <th>Net Pay</th>
              </tr>
            </thead>

            <tbody>
              @php
                $label = fn($s) => $s ? ucwords(str_replace('_',' ', strtolower(trim((string)$s)))) : '—';

                $statusLabel = function($s) use ($label) {
                  $s = strtolower(trim((string)$s));
                  return match($s) {
                    'no_show' => 'No Show',
                    default => $label($s),
                  };
                };

                $settleLabel = function($s) use ($label) {
                  $s = strtolower(trim((string)$s));
                  return match($s) {
                    'refund_pending'   => 'Refund Pending',
                    'refunded_partial' => 'Partially Refunded',
                    'in_dispute'       => 'In Dispute',
                    'pending'          => 'Pending Payout',
                    default            => $label($s),
                  };
                };
              @endphp

              @foreach($rows as $r)
                @php
                  $rowDate = $r->completed_at ?? $r->cancelled_at ?? $r->created_at;

                  $status = strtolower((string) $r->status);
                  $settle = strtolower((string) $r->settlement_status);

                  $booked = (int) ($r->subtotal_minor ?? 0);
                  $paidNet = (int) ($r->coach_earned_minor ?? $r->coach_net_minor ?? 0);
                  $penalty = (int) ($r->coach_penalty_minor ?? 0);
                  $comp    = (int) ($r->coach_comp_minor ?? 0);

                  $adjustmentMinor = (int) ($comp - $penalty);

                  $serviceFeeMinor = ($settle === 'paid')
                      ? max(0, $booked - $paidNet)
                      : null;

                  $netPayMinor = (int) ($paidNet + $comp - $penalty);

                  $badgeClass = 'zv-badge';
                  if ($settle === 'paid') {
                    $badgeClass .= ' zv-badge-success';
                  } elseif ($settle === 'pending') {
                    $badgeClass .= ' zv-badge-warning';
                  } elseif (in_array($settle, ['refund_pending','refunded','refunded_partial'])) {
                    $badgeClass .= ' zv-badge-danger';
                  } elseif ($settle === 'in_dispute') {
                    $badgeClass .= ' zv-badge-info';
                  }
                @endphp

                <tr>
                  <td>#{{ $r->id }}</td>

                  <td>
                    {{ optional($rowDate)->format('Y-m-d') ?? '-' }}

                    @if($r->completed_at)
                      <div class="zv-muted">Completed</div>
                    @elseif($r->cancelled_at)
                      <div class="zv-muted">Cancelled</div>
                    @else
                      <div class="zv-muted">Created</div>
                    @endif
                  </td>

                  <td>
                    <div class="d-flex flex-column gap-1 align-items-center">
                      <div class="text-capitalize">{{ $statusLabel($status) }}</div>
                      <span class="{{ $badgeClass }}">{{ $settleLabel($settle) }}</span>
                    </div>
                  </td>

                  <td>
                    {{ $fmt($booked) }}
                  </td>

                  <td>
                    @if($adjustmentMinor > 0)
                      <span class="text-success">+{{ $fmt($adjustmentMinor) }}</span>
                      <div class="zv-muted">Compensation</div>
                    @elseif($adjustmentMinor < 0)
                      <span class="text-danger">{{ $fmt($adjustmentMinor) }}</span>
                      <div class="zv-muted">Penalty</div>
                    @else
                      —
                    @endif
                  </td>

                  <td>
                    @if($settle === 'paid')
                      {{ $fmt((int) $serviceFeeMinor) }}
                    @else
                      <span class="zv-muted">—</span>
                    @endif
                  </td>

                  <td>
                    @if($settle === 'paid')
                      <strong>{{ $fmt($netPayMinor) }}</strong>
                      @if($adjustmentMinor !== 0)
                        <div class="zv-muted">Net ± adjustment</div>
                      @else
                        <div class="zv-muted">Paid Net</div>
                      @endif
                    @else
                      @if(($comp - $penalty) !== 0)
                        <span class="{{ $netPayMinor < 0 ? 'text-danger' : 'text-success' }}">
                          {{ $fmt($netPayMinor) }}
                        </span>
                        <div class="zv-muted">Adjustment Only</div>
                      @else
                        <span class="zv-muted">—</span>
                      @endif
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="zv-pager">
          {{ $rows->withQueryString()->links() }}
        </div>
      </div>
    @else
      <div class="zv-empty-hero zv-empty-hero-compact">
        <div class="zv-empty-emoji">📊</div>
        <div class="zv-empty-title text-capitalize">No data for this period</div>
        <div class="zv-empty-sub text-capitalize">Try a different range or remove date filters.</div>
      </div>
    @endif
  </section>
</section>
@endsection

@push('scripts')
<script>
(function () {
  const form = document.getElementById('js-admin-coach-filter');
  if (!form) return;

  const pills = form.querySelectorAll('.zv-pill[name="range"]');
  const rangeInput = form.querySelector('input[type="hidden"][name="range"]');

  const dayField   = form.querySelector('.js-day');
  const yearField  = form.querySelector('.js-year');
  const monthField = form.querySelector('.js-month');
  const fromField  = form.querySelector('.js-from');
  const toField    = form.querySelector('.js-to');

  function setActivePill(value) {
    pills.forEach(btn => btn.classList.toggle('is-active', btn.value === value));
  }

  function showFields(range) {
    const showDay   = (range === 'daily');
    const showYear  = (range === 'yearly' || range === 'monthly');
    const showMonth = (range === 'monthly');
    const showCust  = (range === 'custom');

    if (dayField)   dayField.style.display   = showDay ? '' : 'none';
    if (yearField)  yearField.style.display  = showYear ? '' : 'none';
    if (monthField) monthField.style.display = showMonth ? '' : 'none';
    if (fromField)  fromField.style.display  = showCust ? '' : 'none';
    if (toField)    toField.style.display    = showCust ? '' : 'none';
  }

  pills.forEach(btn => {
    btn.addEventListener('click', () => {
      const range = btn.value.toLowerCase();
      rangeInput.value = range;
      setActivePill(range);
      showFields(range);

      if (range !== 'daily') {
        const day = form.querySelector('input[name="day"]');
        if (day) day.value = '';
      }

      if (range !== 'custom') {
        const from = form.querySelector('input[name="from"]');
        const to   = form.querySelector('input[name="to"]');
        if (from) from.value = '';
        if (to) to.value = '';
      }

      form.submit();
    });
  });

  form.querySelector('select[name="year"]')?.addEventListener('change', () => form.submit());
  form.querySelector('select[name="month"]')?.addEventListener('change', () => form.submit());
  form.querySelector('input[name="day"]')?.addEventListener('change', () => form.submit());
  form.querySelector('input[name="from"]')?.addEventListener('change', () => form.submit());
  form.querySelector('input[name="to"]')?.addEventListener('change', () => form.submit());

  const initRange = (rangeInput.value || 'lifetime').toLowerCase();
  showFields(initRange);
})();
</script>
@endpush