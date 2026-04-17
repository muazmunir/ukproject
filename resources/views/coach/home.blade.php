@extends('layouts.role-dashboard')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/coach.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/coach_dashboard.css') }}">
@endpush

@section('role-content')
<div class="zv-panel">

  <div class="zv-tabs" role="tablist" aria-label="Coach dashboard tabs">
    <h4 class="mb-0 py-2 px-1">Overview</h4>
  </div>

  <div class="zv-filterbar">
    <form method="GET" action="{{ route('coach.home') }}" class="zv-filterform" id="js-dashboard-filter">
      @php
        $active = strtolower(request('range', 'lifetime'));

        $selectedYear  = (int) request('year', now()->year);
        $selectedMonth = (int) request('month', now()->month);
        $selectedDay   = request('day', now()->format('Y-m-d'));

        $selectedFrom = request('from');
        $selectedTo   = request('to');

        $showDay    = ($active === 'daily');
        $showYear   = in_array($active, ['yearly', 'monthly'], true);
        $showMonth  = ($active === 'monthly');
        $showCustom = ($active === 'custom') || request()->hasAny(['from', 'to']);
      @endphp

      <div class="zv-pillgroup" role="group" aria-label="Date filters">
        <button type="button" name="range" value="daily"
          class="zv-pill {{ $active === 'daily' ? 'is-active' : '' }}">Daily</button>

        <button type="button" name="range" value="weekly"
          class="zv-pill {{ $active === 'weekly' ? 'is-active' : '' }}">Weekly</button>

        <button type="button" name="range" value="monthly"
          class="zv-pill {{ $active === 'monthly' ? 'is-active' : '' }}">Monthly</button>

        <button type="button" name="range" value="yearly"
          class="zv-pill {{ $active === 'yearly' ? 'is-active' : '' }}">Yearly</button>

        <button type="button" name="range" value="lifetime"
          class="zv-pill {{ in_array($active, ['lifetime', 'all'], true) ? 'is-active' : '' }}">All Time</button>

        <button type="button" name="range" value="custom"
          class="zv-pill {{ $active === 'custom' ? 'is-active' : '' }}">Custom</button>
      </div>

      <input type="hidden" name="range" value="{{ $active }}">

      <div class="zv-range">
        {{-- Daily --}}
        <label class="zv-range-field" id="js-day-field" style="{{ $showDay ? '' : 'display:none' }}">
          <span>Day</span>
          <input
            type="text"
            name="day"
            id="js-day-input"
            class="zv-date-input"
            value="{{ $selectedDay }}"
            placeholder="Select day"
            autocomplete="off">
        </label>

        {{-- Year custom dropdown --}}
        <div class="zv-range-field zv-custom-pick" id="js-year-field" style="{{ $showYear ? '' : 'display:none' }}">
          <span>Year</span>

          <input type="hidden" name="year" id="js-year-input" value="{{ $selectedYear }}">

          <button type="button" class="zv-custom-trigger" id="js-year-trigger" aria-expanded="false">
            <span id="js-year-trigger-text">{{ $selectedYear }}</span>
            <span class="zv-custom-caret">▾</span>
          </button>

          <div class="zv-custom-menu" id="js-year-menu">
            @for($y = now()->year; $y >= now()->year - 5; $y--)
              <button
                type="button"
                class="zv-custom-option {{ $selectedYear === $y ? 'is-selected' : '' }}"
                data-type="year"
                data-value="{{ $y }}">
                {{ $y }}
              </button>
            @endfor
          </div>
        </div>

        {{-- Month custom dropdown --}}
        <div class="zv-range-field zv-custom-pick" id="js-month-field" style="{{ $showMonth ? '' : 'display:none' }}">
          <span>Month</span>

          <input type="hidden" name="month" id="js-month-input" value="{{ $selectedMonth }}">

          <button type="button" class="zv-custom-trigger" id="js-month-trigger" aria-expanded="false">
            <span id="js-month-trigger-text">{{ \Carbon\Carbon::create()->month($selectedMonth)->format('F') }}</span>
            <span class="zv-custom-caret">▾</span>
          </button>

          <div class="zv-custom-menu zv-custom-menu-months" id="js-month-menu">
            @for($m = 1; $m <= 12; $m++)
              <button
                type="button"
                class="zv-custom-option {{ $selectedMonth === $m ? 'is-selected' : '' }}"
                data-type="month"
                data-value="{{ $m }}"
                data-label="{{ \Carbon\Carbon::create()->month($m)->format('F') }}">
                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
              </button>
            @endfor
          </div>
        </div>

        {{-- Custom from --}}
        <label class="zv-range-field" id="js-from-field" style="{{ $showCustom ? '' : 'display:none' }}">
          <span>From</span>
          <input
            type="text"
            name="from"
            id="js-from-input"
            class="zv-date-input"
            value="{{ $selectedFrom }}"
            placeholder="Select from date"
            autocomplete="off">
        </label>

        {{-- Custom to --}}
        <label class="zv-range-field" id="js-to-field" style="{{ $showCustom ? '' : 'display:none' }}">
          <span>To</span>
          <input
            type="text"
            name="to"
            id="js-to-input"
            class="zv-date-input"
            value="{{ $selectedTo }}"
            placeholder="Select to date"
            autocomplete="off">
        </label>

        {{-- Clear filter stays in same row --}}
        <div class="zv-range-action">
          <a href="{{ route('coach.home') }}"
             class="zv-linkbtn"
             id="js-dashboard-reset"
             style="{{ ($active !== 'lifetime' || request()->hasAny(['from','to','year','month','day'])) ? '' : 'display:none' }}">
            Clear Filter
          </a>
        </div>
      </div>
    </form>

    <div id="js-dashboard-meta">
      @include('coach.partials.dashboard_meta', ['currency' => $currency, 'periodLabel' => $periodLabel])
    </div>
  </div>

  <div id="js-dashboard-tiles">
    @include('coach.partials.dashboard_tiles', compact(
      'coachGrossMinor',
      'coachCommissionMinor',
      'coachNetMinor',
      'coachPenaltiesMinor',
      'coachCompMinor',
      'coachFinalImpactMinor',
      'profileViews',
      'bookingPageVisits',
      'enquiries',
      'bookingsCount',
      'convViewsToBookings',
      'convEnquiryToBooking',
      'fmt'
    ))
  </div>

  <div class="zv-card zv-charts" id="js-dashboard-charts">
    <div class="zv-charts-head">
      <h5 class="mb-0">Analytics</h5>
      <div class="zv-charts-subtext">
        <span id="js-chart-period" class="text-capitalize">{{ $periodLabel ?? 'All time' }}</span>
      </div>
    </div>

    <div class="zv-charts-grid">
      <div class="zv-chartbox">
        <canvas id="js-line-chart"></canvas>
      </div>

      <div class="zv-chartbox">
        <canvas id="js-bar-chart"></canvas>
      </div>

      <div class="zv-chartbox">
        <canvas id="js-pie-chart"></canvas>
        <div id="js-pie-legend" class="zv-pie-legend"></div>
      </div>
    </div>
  </div>

  <div class="mt-4" id="js-dashboard-table">
    @include('coach.partials.dashboard_table', compact('rows','fmt'))
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function () {
  const form = document.getElementById('js-dashboard-filter');
  const metaWrap  = document.getElementById('js-dashboard-meta');
  const tilesWrap = document.getElementById('js-dashboard-tiles');
  const tableWrap = document.getElementById('js-dashboard-table');
  const resetBtn  = document.getElementById('js-dashboard-reset');

  const periodText = document.getElementById('js-chart-period');

  const lineCanvas = document.getElementById('js-line-chart');
  const barCanvas  = document.getElementById('js-bar-chart');
  const pieCanvas  = document.getElementById('js-pie-chart');

  if (!form || !metaWrap || !tilesWrap || !tableWrap) return;

  const pills = form.querySelectorAll('.zv-pill[name="range"]');
  const rangeInput = form.querySelector('input[type="hidden"][name="range"]');

  const dayField       = document.getElementById('js-day-field');
  const yearFieldWrap  = document.getElementById('js-year-field');
  const monthFieldWrap = document.getElementById('js-month-field');
  const fromField      = document.getElementById('js-from-field');
  const toField        = document.getElementById('js-to-field');

  const dayInput   = document.getElementById('js-day-input');
  const fromInput  = document.getElementById('js-from-input');
  const toInput    = document.getElementById('js-to-input');
  let dayPicker = null;
let fromPicker = null;
let toPicker = null;

  const yearInput       = document.getElementById('js-year-input');
  const monthInput      = document.getElementById('js-month-input');
  const yearTrigger     = document.getElementById('js-year-trigger');
  const monthTrigger    = document.getElementById('js-month-trigger');
  const yearTriggerText = document.getElementById('js-year-trigger-text');
  const monthTriggerText= document.getElementById('js-month-trigger-text');
  const yearMenu        = document.getElementById('js-year-menu');
  const monthMenu       = document.getElementById('js-month-menu');

  function fmtMinor(minor) {
    const n = (Number(minor || 0) / 100).toFixed(2);
    return '$' + n + ' USD';
  }

  function isMobile() {
    return window.innerWidth <= 390;
  }

  function isTablet() {
    return window.innerWidth <= 768;
  }

  function getChartSizing() {
    const mobile = isMobile();
    const tablet = isTablet();

    return {
      mobile,
      tablet,
      legendFontSize: mobile ? 10 : 12,
      tickFontSize: mobile ? 10 : 12,
      tooltipFontSize: mobile ? 10 : 12,
      lineLegendPadding: mobile ? 12 : 18,
      barLegendPadding: mobile ? 14 : 22,
      pieLegendPadding: mobile ? 12 : 22,
      lineTopPadding: mobile ? 12 : 18,
      barTopPadding: mobile ? 28 : 36,
      piePadding: mobile ? 8 : 18,
      barLabelFont: mobile ? '700 9px sans-serif' : '700 11px sans-serif',
      barLabelOffset: mobile ? 2 : 4,
      pieBoxWidth: mobile ? 10 : 14,
      pieBoxHeight: mobile ? 10 : 14,
      pieUsePointStyle: mobile,
      piePointStyleWidth: mobile ? 10 : 12,
    };
  }

  let lineChart = null;
  let barChart  = null;
  let pieChart  = null;
  let lastChartsPayload = null;
  let resizeTimer = null;

  function renderPieLegend(chart) {
    const container = document.getElementById('js-pie-legend');
    if (!container) return;

    const labels = chart.data.labels || [];
    const colors = chart.data.datasets?.[0]?.backgroundColor || [];

    container.innerHTML = labels.map((label, i) => `
      <div class="zv-pie-legend-item">
        <span class="zv-pie-dot" style="background:${colors[i] || '#ccc'}"></span>
        <span class="zv-pie-text">${label}</span>
      </div>
    `).join('');
  }

  function destroyChart(ch) {
    if (ch && typeof ch.destroy === 'function') ch.destroy();
  }

  const barLabelPlugin = {
    id: 'barLabelPlugin',
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      const meta0 = chart.getDatasetMeta(0);
      if (!meta0 || !meta0.type || meta0.type !== 'bar') return;

      const size = getChartSizing();

      ctx.save();
      ctx.font = size.barLabelFont;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'bottom';
      ctx.fillStyle = '#111827';

      chart.data.datasets.forEach((ds, di) => {
        const meta = chart.getDatasetMeta(di);
        meta.data.forEach((barEl) => {
          const v = Number(ds.data?.[barEl.$context.dataIndex] ?? 0);
          if (!v) return;

          const x = barEl.x;
          const y = barEl.y - size.barLabelOffset;
          ctx.fillText(String(ds.label || ''), x, y);
        });
      });

      ctx.restore();
    }
  };

  function renderCharts(charts) {
    if (!charts || !window.Chart) return;

    lastChartsPayload = charts;

    const size = getChartSizing();
    const labels = charts.labels || [];
    const lineNet = (charts.line && charts.line.net_minor) ? charts.line.net_minor : [];
    const barBookings = (charts.bar && charts.bar.bookings_count) ? charts.bar.bookings_count : [];
    const barGross = (charts.bar && charts.bar.gross_minor) ? charts.bar.gross_minor : [];
    const pie = charts.pie || [];

    if (lineCanvas) {
      destroyChart(lineChart);

      lineChart = new Chart(lineCanvas, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Net Earnings',
            data: lineNet.map(v => Number(v || 0) / 100),
            tension: 0.3,
            borderColor: '#a7f3d0',
            backgroundColor: '#ecfdf5',
            pointBackgroundColor: '#22c55e',
            pointBorderColor: '#22c55e',
            pointRadius: size.mobile ? 2 : 3,
            pointHoverRadius: size.mobile ? 3 : 4,
            borderWidth: size.mobile ? 2 : 2,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              top: size.lineTopPadding,
              right: size.mobile ? 4 : 8,
              bottom: 0,
              left: size.mobile ? 4 : 8
            }
          },
          plugins: {
            tooltip: {
              bodyFont: { weight: '700', size: size.tooltipFontSize },
              titleFont: { weight: '700', size: size.tooltipFontSize },
              callbacks: {
                label: (ctx) => ' ' + ctx.dataset.label + ': ' + fmtMinor((ctx.raw || 0) * 100)
              }
            },
            legend: {
              display: true,
              position: 'top',
              align: 'center',
              labels: {
                padding: size.lineLegendPadding,
                boxWidth: size.mobile ? 12 : 16,
                boxHeight: size.mobile ? 12 : 16,
                font: {
                  weight: '700',
                  size: size.legendFontSize
                }
              }
            }
          },
          scales: {
            x: {
              offset: true,
              ticks: {
                font: { weight: '700', size: size.tickFontSize },
                maxRotation: 0,
                minRotation: 0
              },
              grid: {
                display: false
              }
            },
            y: {
              beginAtZero: false,
              ticks: {
                callback: (v) => '$' + Number(v).toFixed(0) + ' USD',
                font: { weight: '700', size: size.tickFontSize }
              },
              grid: {
                color: 'rgba(0,0,0,0.08)'
              }
            }
          }
        }
      });
    }

    if (barCanvas) {
      destroyChart(barChart);

      barChart = new Chart(barCanvas, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            {
              label: 'Bookings',
              data: barBookings.map(v => Number(v || 0)),
              yAxisID: 'yCount',
              backgroundColor: '#3b82f6',
              borderColor: '#3b82f6',
              borderWidth: 1,
              borderRadius: 0,
              categoryPercentage: size.mobile ? 0.7 : 0.62,
              barPercentage: size.mobile ? 0.88 : 0.8
            },
            {
              label: 'Gross',
              data: barGross.map(v => Number(v || 0) / 100),
              yAxisID: 'yMoney',
              backgroundColor: '#ef4444',
              borderColor: '#ef4444',
              borderWidth: 1,
              borderRadius: 0,
              categoryPercentage: size.mobile ? 0.7 : 0.62,
              barPercentage: size.mobile ? 0.88 : 0.8
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              top: size.barTopPadding,
              right: size.mobile ? 4 : 8,
              bottom: 0,
              left: size.mobile ? 4 : 8
            }
          },
          plugins: {
            tooltip: {
              bodyFont: { weight: '700', size: size.tooltipFontSize },
              titleFont: { weight: '700', size: size.tooltipFontSize },
              callbacks: {
                label: (ctx) => {
                  if (ctx.dataset.yAxisID === 'yCount') return ' BOOKINGS: ' + ctx.raw;
                  return ' GROSS: ' + fmtMinor((ctx.raw || 0) * 100);
                }
              }
            },
            legend: {
              display: true,
              position: 'top',
              align: 'center',
              labels: {
                padding: size.barLegendPadding,
                boxWidth: size.mobile ? 12 : 18,
                boxHeight: size.mobile ? 12 : 12,
                font: {
                  weight: '700',
                  size: size.legendFontSize
                }
              }
            }
          },
          scales: {
            x: {
              offset: true,
              ticks: {
                font: { weight: '700', size: size.tickFontSize },
                maxRotation: 0,
                minRotation: 0,
                autoSkip: false
              },
              grid: {
                display: false
              }
            },
            yCount: {
              type: 'linear',
              position: 'left',
              beginAtZero: true,
              grace: '8%',
              ticks: {
                precision: 0,
                font: { weight: '700', size: size.tickFontSize }
              },
              grid: {
                color: 'rgba(0,0,0,0.08)'
              }
            },
            yMoney: {
              type: 'linear',
              position: 'right',
              beginAtZero: true,
              grace: '8%',
              grid: {
                drawOnChartArea: false
              },
              ticks: {
                callback: (v) => '$' + Number(v).toFixed(0) + ' USD',
                font: { weight: '700', size: size.tickFontSize }
              }
            }
          }
        },
        plugins: [barLabelPlugin]
      });
    }

    if (pieCanvas) {
      destroyChart(pieChart);

      pieChart = new Chart(pieCanvas, {
        type: 'pie',
        data: {
          labels: pie.map(x => x.label),
          datasets: [{
            data: pie.map(x => (Number(x.value_minor || 0) / 100)),
            backgroundColor: ['#22c55e', '#3b82f6', '#ef4444', '#f59e0b'],
            borderColor: ['#22c55e', '#3b82f6', '#ef4444', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: size.mobile ? 4 : 6,
            radius: size.mobile ? '78%' : '84%'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              top: size.piePadding,
              right: size.mobile ? 6 : 12,
              bottom: size.mobile ? 8 : 12,
              left: size.mobile ? 6 : 12
            }
          },
          plugins: {
            tooltip: {
              bodyFont: { weight: '700', size: size.tooltipFontSize },
              titleFont: { weight: '700', size: size.tooltipFontSize },
              callbacks: {
                label: (ctx) => ' ' + ctx.label + ': ' + fmtMinor((ctx.raw || 0) * 100)
              }
            },
            legend: {
              display: false
            }
          }
        }
      });

      renderPieLegend(pieChart);
    }
  }

  function setLoading(isLoading) {
    form.classList.toggle('is-loading', isLoading);
    tilesWrap.style.opacity = isLoading ? '0.6' : '';
    tableWrap.style.opacity = isLoading ? '0.6' : '';
  }

  function setActivePill(value) {
    pills.forEach(btn => btn.classList.toggle('is-active', btn.value === value));
  }

  function closeCustomMenus() {
    document.querySelectorAll('.zv-custom-pick').forEach(el => {
      el.classList.remove('is-open');
      const trigger = el.querySelector('.zv-custom-trigger');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
  }

  function toggleCustomMenu(wrapper, trigger) {
    if (!wrapper || !trigger) return;
    const willOpen = !wrapper.classList.contains('is-open');
    closeCustomMenus();
    if (willOpen) {
      wrapper.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
    }
  }

  function showFields(range) {
    const showDay   = (range === 'daily');
    const showYear  = (range === 'yearly' || range === 'monthly');
    const showMonth = (range === 'monthly');
    const showCust  = (range === 'custom');

    if (dayField) dayField.style.display = showDay ? '' : 'none';
    if (yearFieldWrap) yearFieldWrap.style.display = showYear ? '' : 'none';
    if (monthFieldWrap) monthFieldWrap.style.display = showMonth ? '' : 'none';
    if (fromField) fromField.style.display = showCust ? '' : 'none';
    if (toField) toField.style.display = showCust ? '' : 'none';

    if (!showYear && yearFieldWrap) yearFieldWrap.classList.remove('is-open');
    if (!showMonth && monthFieldWrap) monthFieldWrap.classList.remove('is-open');
  }

  function toggleReset(params) {
    const p = params || new URLSearchParams(window.location.search);
    const r = (p.get('range') || 'lifetime').toLowerCase();

    const hasFilters =
      (r && r !== 'lifetime') ||
      p.get('from') || p.get('to') ||
      p.get('year') || p.get('month') || p.get('day');

    if (resetBtn) resetBtn.style.display = hasFilters ? '' : 'none';
  }

  function buildParams(extra = {}) {
    const fd = new FormData(form);
    const params = new URLSearchParams();

    for (const [k, v] of fd.entries()) {
      if (String(v).trim() !== '') params.set(k, v);
    }

    const r = (extra.range ?? params.get('range') ?? 'lifetime').toLowerCase();

    if (r !== 'daily') params.delete('day');
    if (r !== 'monthly' && r !== 'yearly') {
      params.delete('year');
      params.delete('month');
    }
    if (r !== 'monthly') params.delete('month');
    if (r !== 'custom') {
      params.delete('from');
      params.delete('to');
    }

    for (const [k, v] of Object.entries(extra)) {
      if (v === null || v === undefined || String(v).trim() === '') params.delete(k);
      else params.set(k, v);
    }

    return params;
  }

function initDatePickers() {
  if (typeof flatpickr === 'undefined') return;

  if (dayPicker) {
    dayPicker.destroy();
    dayPicker = null;
  }

  if (fromPicker) {
    fromPicker.destroy();
    fromPicker = null;
  }

  if (toPicker) {
    toPicker.destroy();
    toPicker = null;
  }

 if (dayInput) {
  dayPicker = flatpickr(dayInput, {
    dateFormat: 'Y-m-d',
    defaultDate: dayInput.value || null,
    allowInput: false,
    disableMobile: true,
    clickOpens: true,
    position: 'below',
    appendTo: document.body,
    onChange: function () {
      debounced(() => fetchDashboard(buildParams()));
    }
  });
}

if (fromInput) {
  fromPicker = flatpickr(fromInput, {
    dateFormat: 'Y-m-d',
    defaultDate: fromInput.value || null,
    allowInput: false,
    disableMobile: true,
    clickOpens: true,
    position: 'below',
    appendTo: document.body,
    onChange: function (selectedDates, dateStr) {
      if (toPicker) {
        toPicker.set('minDate', dateStr || null);
      }
      debounced(() => fetchDashboard(buildParams()));
    }
  });
}

if (toInput) {
  toPicker = flatpickr(toInput, {
    dateFormat: 'Y-m-d',
    defaultDate: toInput.value || null,
    allowInput: false,
    disableMobile: true,
    clickOpens: true,
    position: 'below',
    appendTo: document.body,
    onChange: function () {
      debounced(() => fetchDashboard(buildParams()));
    }
  });

  if (fromInput && fromInput.value) {
    toPicker.set('minDate', fromInput.value);
  }
}
}

  async function fetchDashboard(params, pushState = true) {
    const url = form.action + '?' + params.toString();

    setLoading(true);
    try {
      const res = await fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });
      if (!res.ok) throw new Error('Request failed');

      const data = await res.json();
      metaWrap.innerHTML  = data.meta_html;
      tilesWrap.innerHTML = data.tiles_html;
      tableWrap.innerHTML = data.table_html;

      if (periodText && data.periodLabel) periodText.textContent = data.periodLabel;
      if (data.charts) renderCharts(data.charts);
      initDatePickers();

      if (pushState) history.pushState({}, '', url);
      toggleReset(params);
      closeCustomMenus();
    } catch (e) {
      console.error(e);
      window.location.href = url;
    } finally {
      setLoading(false);
    }
  }

  let debounceTimer = null;
  function debounced(fn, wait = 350) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fn, wait);
  }

  pills.forEach(btn => {
    btn.addEventListener('click', () => {
      const range = btn.value.toLowerCase();
      rangeInput.value = range;

      setActivePill(range);
      showFields(range);
      closeCustomMenus();

      if (range !== 'daily' && dayInput) {
        dayInput.value = '';
      }

      if (range !== 'custom') {
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
      }

      fetchDashboard(buildParams({ range }));
    });
  });

  yearTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleCustomMenu(yearFieldWrap, yearTrigger);
  });

  monthTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleCustomMenu(monthFieldWrap, monthTrigger);
  });

  yearMenu?.addEventListener('click', (e) => {
    const btn = e.target.closest('.zv-custom-option[data-type="year"]');
    if (!btn || !yearInput) return;

    const value = btn.dataset.value;
    yearInput.value = value;
    if (yearTriggerText) yearTriggerText.textContent = value;

    yearMenu.querySelectorAll('.zv-custom-option').forEach(opt => opt.classList.remove('is-selected'));
    btn.classList.add('is-selected');

    closeCustomMenus();
    fetchDashboard(buildParams());
  });

  monthMenu?.addEventListener('click', (e) => {
    const btn = e.target.closest('.zv-custom-option[data-type="month"]');
    if (!btn || !monthInput) return;

    const value = btn.dataset.value;
    const label = btn.dataset.label || btn.textContent.trim();

    monthInput.value = value;
    if (monthTriggerText) monthTriggerText.textContent = label;

    monthMenu.querySelectorAll('.zv-custom-option').forEach(opt => opt.classList.remove('is-selected'));
    btn.classList.add('is-selected');

    closeCustomMenus();
    fetchDashboard(buildParams());
  });

 
  resetBtn?.addEventListener('click', (e) => {
  e.preventDefault();

  rangeInput.value = 'lifetime';
  setActivePill('lifetime');
  showFields('lifetime');
  closeCustomMenus();

  if (dayInput) dayInput.value = '';
  if (fromInput) fromInput.value = '';
  if (toInput) toInput.value = '';

  if (dayPicker) dayPicker.clear();
  if (fromPicker) fromPicker.clear();
  if (toPicker) toPicker.clear();

  fetchDashboard(new URLSearchParams({ range: 'lifetime' }));
});

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.zv-custom-pick')) {
      closeCustomMenus();
    }

    const a = e.target.closest('#js-dashboard-table .pagination a');
    if (!a) return;

    e.preventDefault();
    const url = new URL(a.href);
    fetchDashboard(url.searchParams, true);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeCustomMenus();
  });

  window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    fetchDashboard(url.searchParams, false);
  });

  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      if (lastChartsPayload) renderCharts(lastChartsPayload);
    }, 180);
  });

  const initRange = (rangeInput.value || 'lifetime').toLowerCase();
  showFields(initRange);
  toggleReset(new URLSearchParams(window.location.search));

  @php
    $initialCharts = [
      'labels' => $labels ?? [],
      'line' => ['net_minor' => $lineNet ?? []],
      'bar'  => ['bookings_count' => $barBookings ?? [], 'gross_minor' => $barGross ?? []],
      'pie'  => $pie ?? [],
    ];
  @endphp

  const initialCharts = @json($initialCharts);
  initDatePickers();
  renderCharts(initialCharts);
})();
</script>
@endpush