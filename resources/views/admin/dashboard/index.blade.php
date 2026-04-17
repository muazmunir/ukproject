{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.admin')
@section('title','Dashboard')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
@endpush

@section('content')

  {{-- ======================== KPI CARD (with its own filter) ======================== --}}
  <section class="card mb-4">
    <div class="card__head">
      <div>
        <div class="card__title">Overview KPIs</div>
        <div class="muted">Bookings, sales, users, etc.</div>
      </div>

      {{-- Filter ONLY for this KPI block --}}
      <form method="GET" class="range-form">
        <div class="range-control" data-prefix="kpi">
          <select name="kpi_preset" class="range-select">
            <option value="day"        {{ $kpiRange['preset'] === 'day' ? 'selected' : '' }}>Today</option>
            <option value="week"       {{ $kpiRange['preset'] === 'week' ? 'selected' : '' }}>This week</option>
            <option value="month"      {{ $kpiRange['preset'] === 'month' ? 'selected' : '' }}>This month</option>
            <option value="year"       {{ $kpiRange['preset'] === 'year' ? 'selected' : '' }}>This year</option>
            <option value="all"        {{ $kpiRange['preset'] === 'all' ? 'selected' : '' }}>All time</option>
            <option value="month_year" {{ $kpiRange['preset'] === 'month_year' ? 'selected' : '' }}>Month / Year</option>
            <option value="custom"     {{ $kpiRange['preset'] === 'custom' ? 'selected' : '' }}>Custom range</option>
          </select>

          {{-- Month / Year controls --}}
          <div class="range-extra range-extra--month-year" data-role="month-year">
            <input type="number"
                   name="kpi_month"
                   min="1" max="12"
                   value="{{ request('kpi_month', now()->month) }}"
                   class="range-input"
                   placeholder="MM">
            <input type="number"
                   name="kpi_year"
                   value="{{ request('kpi_year', now()->year) }}"
                   class="range-input"
                   placeholder="YYYY">
          </div>

          {{-- Custom from/to date --}}
          <div class="range-extra range-extra--custom" data-role="custom-range">
            <input type="date"
                   name="kpi_from"
                   value="{{ request('kpi_from') }}"
                   class="range-input">
            <input type="date"
                   name="kpi_to"
                   value="{{ request('kpi_to') }}"
                   class="range-input">
          </div>

          <button type="submit" class="btn btn-sm">Apply</button>
        </div>
        <div class="range-label">{{ $kpiRange['label'] }}</div>
      </form>
    </div>

    <div class="card__body">
      {{-- KPI CARDS --}}
      <div class="grid kpis">
        <div class="card">
          <div class="card__body">
            <div class="muted">Visitors</div>
            <div class="kpi"
                 id="activeVisitorsKpi"
                 data-url="{{ route('admin.metrics.active-visitors') }}">
              {{ $visitorsCount }}
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card__body">
            <div class="muted">Subscribers</div>
            {{-- <div class="kpi">{{ $subscribersCount }}</div> --}}
          </div>
        </div>
        <div class="card ok">
          <div class="card__body">
            <div class="muted">Sales</div>
            <div class="kpi">${{ number_format($totalSales, 2) }}</div>
          </div>
        </div>
        <div class="card">
          <div class="card__body">
            <div class="muted">Bookings</div>
            <div class="kpi">{{ $bookingsCount }}</div>
          </div>
        </div>
        <div class="card">
          <div class="card__body">
            <div class="muted">Coaches</div>
            <div class="kpi">{{ $vendorsCount }}</div>
          </div>
        </div>
        <div class="card">
          <div class="card__body">
            <div class="muted">Services</div>
            <div class="kpi">{{ $servicesCount }}</div>
          </div>
        </div>
        <div class="card">
          <div class="card__body">
            <div class="muted">Clients</div>
            <div class="kpi">{{ $clientsCount }}</div>
          </div>
        </div>
        <div class="card">
          <div class="card__body">
            <div class="muted">Categories</div>
            <div class="kpi">{{ $categoriesCount }}</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- ======================== ROW 1 — MAIN TRENDS ======================== --}}
  <div class="dashboard-grid mt">

    {{-- USER / BOOKINGS CHART (Line / Bar / Area) --}}
    

    {{-- SALES PERFORMANCE --}}
    <section class="card chart-lg" style="grid-column: span 5;">
      <div class="card__head">
        <div>
          <div class="card__title">Sales Performance</div>
          <div class="muted">Daily revenue in selected period</div>
        </div>

        <div class="d-flex flex-column align-items-end gap-1">
          <div class="d-flex gap-2 chart-controls">
            <select class="range-select" data-chart="sales-type">
              <option value="bar">Bar</option>
              <option value="line">Line</option>
            </select>
          </div>

          {{-- Filter for sales chart (salesRange) --}}
          <form method="GET" class="range-form mt-1">
            <div class="range-control" data-prefix="sales">
              <select name="sales_preset" class="range-select">
                <option value="day"        {{ $salesRange['preset'] === 'day' ? 'selected' : '' }}>Today</option>
                <option value="week"       {{ $salesRange['preset'] === 'week' ? 'selected' : '' }}>This week</option>
                <option value="month"      {{ $salesRange['preset'] === 'month' ? 'selected' : '' }}>This month</option>
                <option value="year"       {{ $salesRange['preset'] === 'year' ? 'selected' : '' }}>This year</option>
                <option value="all"        {{ $salesRange['preset'] === 'all' ? 'selected' : '' }}>All time</option>
                <option value="month_year" {{ $salesRange['preset'] === 'month_year' ? 'selected' : '' }}>Month / Year</option>
                <option value="custom"     {{ $salesRange['preset'] === 'custom' ? 'selected' : '' }}>Custom range</option>
              </select>

              <div class="range-extra range-extra--month-year" data-role="month-year">
                <input type="number" name="sales_month" min="1" max="12"
                       value="{{ request('sales_month', now()->month) }}"
                       class="range-input" placeholder="MM">
                <input type="number" name="sales_year"
                       value="{{ request('sales_year', now()->year) }}"
                       class="range-input" placeholder="YYYY">
              </div>

              <div class="range-extra range-extra--custom" data-role="custom-range">
                <input type="date" name="sales_from" value="{{ request('sales_from') }}" class="range-input">
                <input type="date" name="sales_to"   value="{{ request('sales_to') }}"   class="range-input">
              </div>

              <button type="submit" class="btn btn-sm">Apply</button>
            </div>
            <div class="range-label">{{ $salesRange['label'] }}</div>
          </form>

          <div class="d-flex gap-2 mt-1">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="salesChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="salesChart">Export PDF</button>
          </div>
        </div>
      </div>

      <div class="card__body">
        <div class="sales">${{ number_format($totalSales, 2) }}</div>
        <canvas id="salesChart" height="140"></canvas>
      </div>
    </section>

  </div>



  {{-- ======================== ROW 2 — MIDDLE CHARTS ======================== --}}
  <div class="dashboard-grid mt">

    {{-- BOOKINGS + REVENUE --}}
    <section class="card chart-md" style="grid-column: span 6;">
      <div class="card__head">
        <div>
          <div class="card__title">Bookings & Revenue</div>
          <div class="muted">Track bookings vs income</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="range-label small">Range: {{ $salesRange['label'] }}</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="bookingsRevenueChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="bookingsRevenueChart">Export PDF</button>
          </div>
        </div>
      </div>
      <div class="card__body">
        <canvas id="bookingsRevenueChart" height="150"></canvas>
      </div>
    </section>

    {{-- NEW USERS BREAKDOWN --}}
   

  </div>



  {{-- ======================== ROW 3 — SMALL CHARTS ======================== --}}
  <div class="dashboard-grid mt">

    {{-- CANCELLATIONS --}}
    <section class="card chart-sm" style="grid-column: span 4;">
      <div class="card__head">
        <div>
          <div class="card__title">Cancellations Over Time</div>
          <div class="muted">Daily cancelled bookings</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="range-label small">Range: {{ $salesRange['label'] }}</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="cancellationsChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="cancellationsChart">Export PDF</button>
          </div>
        </div>
      </div>
      <div class="card__body">
        <canvas id="cancellationsChart" height="140"></canvas>
      </div>
    </section>

    {{-- AVG BOOKING VALUE --}}
    

    {{-- BOOKINGS BY HOUR --}}
    <section class="card chart-sm" style="grid-column: span 4;">
      <div class="card__head">
        <div>
          <div class="card__title">Bookings by Hour</div>
          <div class="muted">Peak booking times</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="range-label small">Range: {{ $salesRange['label'] }}</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="bookingsByHourChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="bookingsByHourChart">Export PDF</button>
          </div>
        </div>
      </div>
      <div class="card__body">
        <canvas id="bookingsByHourChart" height="140"></canvas>
      </div>
    </section>

  </div>



  {{-- ======================== ROW 4 — DISTRIBUTIONS ======================== --}}
  <div class="dashboard-grid mt">

    {{-- OVERALL PIE CHART --}}
    <section class="card chart-lg" style="grid-column: span 6;">
      <div class="card__head">
        <div>
          <div class="card__title">Distribution Overview</div>
          <div class="muted">Users / bookings breakdown</div>
        </div>

        <div class="d-flex flex-column align-items-end gap-1">
          <div class="chart-controls">
            <select class="range-select" data-chart="comp-metric">
              <option value="roles">Clients vs Coaches</option>
              <option value="booking_status">Booking Status</option>
            </select>
          </div>
          <div class="d-flex gap-2 mt-1">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="compositionChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="compositionChart">Export PDF</button>
          </div>
        </div>
      </div>

      <div class="card__body">
        <canvas id="compositionChart" height="180"></canvas>
      </div>
    </section>

    {{-- PAYMENT METHODS --}}
    <section class="card chart-lg" style="grid-column: span 6;">
      <div class="card__head">
        <div>
          <div class="card__title">Revenue by Payment Method</div>
          <div class="muted">Compare method performance</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="range-label small">Range: {{ $salesRange['label'] }}</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="paymentMethodsChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="paymentMethodsChart">Export PDF</button>
          </div>
        </div>
      </div>
      <div class="card__body">
        <canvas id="paymentMethodsChart" height="180"></canvas>
      </div>
    </section>

  </div>



  {{-- ======================== ROW 5 — COACH REVENUE & PLATFORM FEES ======================== --}}
  <div class="dashboard-grid mt">

    {{-- COACH REVENUE (with / without platform) --}}
    <section class="card chart-md" style="grid-column: span 6;">
      <div class="card__head">
        <div>
          <div class="card__title">Revenue Earned by Coaches</div>
          <div class="muted">Toggle with / without platform charges</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="d-flex gap-2 chart-controls">
            <select class="range-select" data-chart="coach-revenue-mode">
              <option value="without_platform" selected>Without platform fee</option>
              <option value="with_platform">After platform fee</option>
            </select>
          </div>
          <div class="range-label small">Range: {{ $salesRange['label'] }}</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="coachRevenueChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="coachRevenueChart">Export PDF</button>
          </div>
        </div>
      </div>
      <div class="card__body">
        <canvas id="coachRevenueChart" height="160"></canvas>
      </div>
    </section>

    {{-- PLATFORM FEES OVER TIME --}}
    <section class="card chart-md" style="grid-column: span 6;">
      <div class="card__head">
        <div>
          <div class="card__title">Platform Fees Earned</div>
          <div class="muted">Total fees kept by ZAIVIAS</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
          <div class="range-label small">Range: {{ $salesRange['label'] }}</div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs chart-print" data-chart-target="platformFeesChart">Print</button>
            <button type="button" class="btn btn-xs chart-export" data-chart-target="platformFeesChart">Export PDF</button>
          </div>
        </div>
      </div>
      <div class="card__body">
        <canvas id="platformFeesChart" height="160"></canvas>
      </div>
    </section>

  </div>



  {{-- ======================== NEW USERS + TRANSACTIONS LISTS ======================== --}}
  <div class="dashboard-grid mt">

    {{-- NEW USERS CARD --}}
    <section class="card" style="grid-column: span 4;">

      <div class="card__head">
        <div class="card__title">New Users</div>

        {{-- Filter for New Users list --}}
        <form method="GET" class="range-form">
          <div class="range-control" data-prefix="new">
            <select name="new_preset" class="range-select">
              <option value="day"        {{ $newRange['preset'] === 'day' ? 'selected' : '' }}>Today</option>
              <option value="week"       {{ $newRange['preset'] === 'week' ? 'selected' : '' }}>This week</option>
              <option value="month"      {{ $newRange['preset'] === 'month' ? 'selected' : '' }}>This month</option>
              <option value="year"       {{ $newRange['preset'] === 'year' ? 'selected' : '' }}>This year</option>
              <option value="all"        {{ $newRange['preset'] === 'all' ? 'selected' : '' }}>All time</option>
              <option value="month_year" {{ $newRange['preset'] === 'month_year' ? 'selected' : '' }}>Month / Year</option>
              <option value="custom"     {{ $newRange['preset'] === 'custom' ? 'selected' : '' }}>Custom range</option>
            </select>

            <div class="range-extra range-extra--month-year" data-role="month-year">
              <input type="number"
                     name="new_month"
                     min="1" max="12"
                     value="{{ request('new_month', now()->month) }}"
                     class="range-input"
                     placeholder="MM">
              <input type="number"
                     name="new_year"
                     value="{{ request('new_year', now()->year) }}"
                     class="range-input"
                     placeholder="YYYY">
            </div>

            <div class="range-extra range-extra--custom" data-role="custom-range">
              <input type="date"
                     name="new_from"
                     value="{{ request('new_from') }}"
                     class="range-input">
              <input type="date"
                     name="new_to"
                     value="{{ request('new_to') }}"
                     class="range-input">
            </div>

            <button type="submit" class="btn btn-sm">Apply</button>
          </div>
          <div class="range-label">{{ $newRange['label'] }}</div>
        </form>

        {{-- Client / Coach toggle --}}
        <div class="toggle-group ms-2">
          <button type="button" class="toggle-btn active" data-target="clientsList">Clients</button>
          <button type="button" class="toggle-btn" data-target="coachesList">Coaches</button>
        </div>
      </div>

      {{-- CLIENTS LIST --}}
      <ul class="list" id="clientsList">
        @forelse ($latestClients as $user)
          @php
            $avatarUrl = null;

            if (!empty($user->avatar_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path)) {
                $avatarUrl = asset('storage/' . $user->avatar_path);
            } elseif (!empty($user->avatar_url)) {
                $avatarUrl = $user->avatar_url;
            }

            // Display name + fallback initial
            $displayName = trim(
                $user->name
                ?? (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            );

            $initial = $displayName !== ''
                ? mb_strtoupper(mb_substr($displayName, 0, 1))
                : '?';
          @endphp

          <li class="new-user-row">
            @if ($avatarUrl)
              <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="avatar-img-sm">
            @else
              <span class="avatar-sm-fallback">{{ $initial }}</span>
            @endif

            <span class="new-user-name">{{ $displayName }}</span>

            <span class="new-user-time">
              {{ $user->created_at->format('d M Y, H:i') }}
            </span>
          </li>

        @empty
          <li class="muted">No clients in this range.</li>
        @endforelse
        {{ $latestClients->links() }}
      </ul>

      {{-- COACHES LIST --}}
      <ul class="list d-none" id="coachesList">
        @forelse ($latestCoaches as $user)
          @php
            $avatarUrl = null;
            if (!empty($user->avatar_path)) {
                $avatarUrl = asset('storage/'.$user->avatar_path);
            } elseif (!empty($user->avatar_url)) {
                $avatarUrl = $user->avatar_url;
            }

            $displayName = trim(
                $user->name
                ?? (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            );

            $initial = $displayName !== ''
                ? mb_strtoupper(mb_substr($displayName, 0, 1))
                : '?';
          @endphp

          <li class="new-user-row">
            @if ($avatarUrl)
              <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="avatar-img-sm">
            @else
              <span class="avatar-sm-fallback">{{ $initial }}</span>
            @endif

            <span class="new-user-name">{{ $displayName }}</span>

            <span class="new-user-time">
              {{ $user->created_at->format('d M Y, H:i') }}
            </span>
          </li>
        @empty
          <li class="muted">No coaches in this range.</li>
        @endforelse

        {{ $latestCoaches->links() }}
      </ul>
    </section>

    {{-- TRANSACTION HISTORY CARD --}}
    <section class="card" style="grid-column: span 8;">

      <div class="card__head">
        <div class="card__title">Transaction History</div>

        {{-- Filter for transaction table --}}
        <form method="GET" class="range-form">
          <div class="range-control" data-prefix="tx">
            <select name="tx_preset" class="range-select">
              <option value="day"        {{ $txRange['preset'] === 'day' ? 'selected' : '' }}>Today</option>
              <option value="week"       {{ $txRange['preset'] === 'week' ? 'selected' : '' }}>This week</option>
              <option value="month"      {{ $txRange['preset'] === 'month' ? 'selected' : '' }}>This month</option>
              <option value="year"       {{ $txRange['preset'] === 'year' ? 'selected' : '' }}>This year</option>
              <option value="all"        {{ $txRange['preset'] === 'all' ? 'selected' : '' }}>All time</option>
              <option value="month_year" {{ $txRange['preset'] === 'month_year' ? 'selected' : '' }}>Month / Year</option>
              <option value="custom"     {{ $txRange['preset'] === 'custom' ? 'selected' : '' }}>Custom range</option>
            </select>

            <div class="range-extra range-extra--month-year" data-role="month-year">
              <input type="number"
                     name="tx_month"
                     min="1" max="12"
                     value="{{ request('tx_month', now()->month) }}"
                     class="range-input"
                     placeholder="MM">
              <input type="number"
                     name="tx_year"
                     value="{{ request('tx_year', now()->year) }}"
                     class="range-input"
                     placeholder="YYYY">
            </div>

            <div class="range-extra range-extra--custom" data-role="custom-range">
              <input type="date"
                     name="tx_from"
                     value="{{ request('tx_from') }}"
                     class="range-input">
              <input type="date"
                     name="tx_to"
                     value="{{ request('tx_to') }}"
                     class="range-input">
            </div>

            <button type="submit" class="btn btn-sm">Apply</button>
          </div>
          <div class="range-label">{{ $txRange['label'] }}</div>
        </form>

        <a class="btn" href="{{ url('admin/payments') }}">View All</a>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
          <tr>
            <th>Payment Number</th>
            <th>Date &amp; Time</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
          </thead>
          <tbody>
          @forelse($payments as $payment)
            <tr>
              <td>Payment #{{ $payment->id }}</td>
              <td>{{ $payment->created_at->format('M d, Y H:i') }}</td>
              <td>${{ number_format($payment->amount_total, 2) }}</td>
              <td>
                <span class="pill {{ $payment->status === 'completed' ? 'ok' : '' }}">
                  {{ ucfirst($payment->status) }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="muted text-center">No payments in this range.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>

  {{-- Expose chart data for JS --}}
  <script>
    window.dashboardChartData = @json($chartData);
  </script>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <script defer src="{{ asset('js/admin-dashboard.js') }}"></script>
@endpush
