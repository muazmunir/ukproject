// resources/js/admin-dashboard.js

document.addEventListener('DOMContentLoaded', () => {
  const data = window.dashboardChartData || {};

  // ---------- Base timeseries from controller ----------
 
  const salesTS    = data.sales    || { labels: [], values: [] };

  const comp       = data.composition || {};
  const compRoles  = comp.roles          || { labels: [], values: [] };
  const compBook   = comp.booking_status || { labels: [], values: [] };

  // Advanced analytics
  const bookingsRevenueTS   = data.bookings_revenue   || { labels: [], bookings: [], revenue: [] };
  
  const cancellationsTS     = data.cancellations      || { labels: [], values: [] };
  
  const bookingsByHourTS    = data.bookings_by_hour   || { labels: [], values: [] };
  const paymentMethodsTS    = data.payment_methods    || { labels: [], totals: [] };
  const coachRevenueTS      = data.coach_revenue      || { labels: [], with_platform: [], without_platform: [] };
  const platformFeesTS      = data.platform_fees      || { labels: [], values: [] };

  // ---------- Selects ----------
  
  const salesTypeSelect        = document.querySelector('[data-chart="sales-type"]');
  const compMetricSelect       = document.querySelector('[data-chart="comp-metric"]');
  const coachRevenueModeSelect = document.querySelector('[data-chart="coach-revenue-mode"]');

  // ---------- Canvas contexts ----------
 
  const salesCtx             = document.getElementById('salesChart')?.getContext('2d');
  const compCtx              = document.getElementById('compositionChart')?.getContext('2d');
  const bookingsRevenueCtx   = document.getElementById('bookingsRevenueChart')?.getContext('2d');
 
  const cancellationsCtx     = document.getElementById('cancellationsChart')?.getContext('2d');
  
  const bookingsHourCtx      = document.getElementById('bookingsByHourChart')?.getContext('2d');
  const paymentMethodsCtx    = document.getElementById('paymentMethodsChart')?.getContext('2d');
  const coachRevenueCtx      = document.getElementById('coachRevenueChart')?.getContext('2d');
  const platformFeesCtx      = document.getElementById('platformFeesChart')?.getContext('2d');

  // ---------- Chart instances ----------

  let salesChart            = null;
  let compChart             = null;
  let bookingsRevenueChart  = null;
 
  let cancellationsChart    = null;
  
  let bookingsByHourChart   = null;
  let paymentMethodsChart   = null;
  let coachRevenueChart     = null;
  let platformFeesChart     = null;

  // ---------- Colors ----------
  const brandColor   = '#1d9bf0';
  const brandSoft    = 'rgba(29,155,240,0.1)';
  const accentColors = ['#111827', '#f97316', '#22c55e', '#6366f1', '#e11d48', '#0f766e'];

  const cBlue    = '#1d9bf0';
  const cBlueSoft= 'rgba(29,155,240,0.12)';
  const cGreen   = '#22c55e';
  const cGreenSoft = 'rgba(34,197,94,0.12)';
  const cRed     = '#ef4444';
  const cRedSoft = 'rgba(239,68,68,0.12)';
  const cYellow  = '#eab308';
  const cYellowSoft = 'rgba(234,179,8,0.15)';

  // ======================================================
  // Helpers: main charts
  // ======================================================

  
  function buildSalesChart(type = 'bar') {
    if (!salesCtx) return;
    if (salesChart) salesChart.destroy();

    const chartType = type === 'line' ? 'line' : 'bar';

    salesChart = new Chart(salesCtx, {
      type: chartType,
      data: {
        labels: salesTS.labels,
        datasets: [{
          label: 'Sales',
          data: salesTS.values,
          borderColor: brandColor,
          backgroundColor: chartType === 'bar' ? brandSoft : brandColor,
          borderWidth: 2,
          tension: 0.3,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => `Sales: $${ctx.formattedValue}`
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            ticks: {
              callback: v => `$${v}`,
            }
          }
        }
      }
    });
  }

  function buildCompositionChart(metric = 'roles') {
    if (!compCtx) return;
    if (compChart) compChart.destroy();

    const src = metric === 'booking_status' ? compBook : compRoles;
    const label = metric === 'booking_status'
      ? 'Bookings by status'
      : 'Users by role';

    compChart = new Chart(compCtx, {
      type: 'pie',
      data: {
        labels: src.labels,
        datasets: [{
          label,
          data: src.values,
          backgroundColor: src.labels.map((_, i) => accentColors[i % accentColors.length]),
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 14 }
          },
          tooltip: {
            callbacks: {
              label: ctx => {
                const val   = ctx.parsed || 0;
                const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                const pct   = ((val / total) * 100).toFixed(1);
                return `${ctx.label}: ${val} (${pct}%)`;
              }
            }
          }
        }
      }
    });
  }

  // ======================================================
  // Helpers: advanced charts
  // ======================================================

  function buildBookingsRevenueChart() {
    if (!bookingsRevenueCtx) return;
    if (bookingsRevenueChart) bookingsRevenueChart.destroy();

    bookingsRevenueChart = new Chart(bookingsRevenueCtx, {
      type: 'line',
      data: {
        labels: bookingsRevenueTS.labels,
        datasets: [
          {
            label: 'Bookings',
            data: bookingsRevenueTS.bookings || [],
            borderColor: cBlue,
            backgroundColor: cBlueSoft,
            borderWidth: 2,
            tension: 0.4,
            yAxisID: 'y',
          },
          {
            label: 'Revenue ($)',
            data: bookingsRevenueTS.revenue || [],
            borderColor: cGreen,
            backgroundColor: cGreenSoft,
            borderWidth: 2,
            tension: 0.4,
            yAxisID: 'y1',
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: ctx => {
                const label = ctx.dataset.label || '';
                if (ctx.dataset.yAxisID === 'y1') {
                  return `${label}: $${ctx.formattedValue}`;
                }
                return `${label}: ${ctx.formattedValue}`;
              }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            position: 'left',
            title: { display: true, text: 'Bookings' }
          },
          y1: {
            beginAtZero: true,
            position: 'right',
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Revenue ($)' }
          }
        }
      }
    });
  }

  

  function buildCancellationsChart() {
    if (!cancellationsCtx) return;
    if (cancellationsChart) cancellationsChart.destroy();

    cancellationsChart = new Chart(cancellationsCtx, {
      type: 'bar',
      data: {
        labels: cancellationsTS.labels,
        datasets: [{
          label: 'Cancellations',
          data: cancellationsTS.values || [],
          backgroundColor: cRedSoft,
          borderColor: cRed,
          borderWidth: 1.5,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

 

  function buildBookingsByHourChart() {
    if (!bookingsHourCtx) return;
    if (bookingsByHourChart) bookingsByHourChart.destroy();

    bookingsByHourChart = new Chart(bookingsHourCtx, {
      type: 'bar',
      data: {
        labels: bookingsByHourTS.labels,
        datasets: [{
          label: 'Bookings',
          data: bookingsByHourTS.values || [],
          backgroundColor: cBlueSoft,
          borderColor: cBlue,
          borderWidth: 1.5,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: ctx => `Hour: ${ctx[0].label}`,
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

  function buildPaymentMethodsChart() {
    if (!paymentMethodsCtx) return;
    if (paymentMethodsChart) paymentMethodsChart.destroy();

    paymentMethodsChart = new Chart(paymentMethodsCtx, {
      type: 'doughnut',
      data: {
        labels: paymentMethodsTS.labels,
        datasets: [{
          data: paymentMethodsTS.totals || [],
          backgroundColor: [
            '#1d9bf0',
            '#22c55e',
            '#f97316',
            '#ef4444',
            '#6366f1',
            '#0f766e'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right' },
          tooltip: {
            callbacks: {
              label: ctx => {
                const val = ctx.parsed || 0;
                return `${ctx.label}: $${val}`;
              }
            }
          }
        }
      }
    });
  }

  function buildCoachRevenueChart(mode = 'without_platform') {
    if (!coachRevenueCtx) return;
    if (coachRevenueChart) coachRevenueChart.destroy();

    const labels = coachRevenueTS.labels || [];
    const gross  = coachRevenueTS.without_platform || [];
    const net    = coachRevenueTS.with_platform    || [];

    const isNet  = mode === 'with_platform';
    const dataSetValues = isNet ? net : gross;
    const label = isNet ? 'Coach revenue (after platform fee)' : 'Coach revenue (gross)';

    coachRevenueChart = new Chart(coachRevenueCtx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label,
          data: dataSetValues,
          backgroundColor: isNet ? cGreenSoft : cBlueSoft,
          borderColor: isNet ? cGreen : cBlue,
          borderWidth: 1.5,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => `${label}: $${ctx.formattedValue}`
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            ticks: { callback: v => `$${v}` }
          }
        }
      }
    });
  }

  function buildPlatformFeesChart() {
    if (!platformFeesCtx) return;
    if (platformFeesChart) platformFeesChart.destroy();

    platformFeesChart = new Chart(platformFeesCtx, {
      type: 'line',
      data: {
        labels: platformFeesTS.labels,
        datasets: [{
          label: 'Platform fees',
          data: platformFeesTS.values || [],
          borderColor: cRed,
          backgroundColor: cRedSoft,
          borderWidth: 2,
          tension: 0.3,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => `Fees: $${ctx.formattedValue}`
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            ticks: { callback: v => `$${v}` }
          }
        }
      }
    });
  }

  // ======================================================
  // Init charts with defaults
  // ======================================================

 

   if (salesCtx) {
    const defaultType = salesTypeSelect?.value || 'bar';
    buildSalesChart(defaultType);
  }

  if (compCtx) {
    const defaultMetric = compMetricSelect?.value || 'roles';
    buildCompositionChart(defaultMetric);
  }

  if (bookingsRevenueCtx)   buildBookingsRevenueChart();
  if (cancellationsCtx)     buildCancellationsChart();
  if (bookingsHourCtx)      buildBookingsByHourChart();
  if (paymentMethodsCtx)    buildPaymentMethodsChart();
  if (coachRevenueCtx) {
    const mode = coachRevenueModeSelect?.value || 'without_platform';
    buildCoachRevenueChart(mode);
  }
  if (platformFeesCtx)      buildPlatformFeesChart();


  // ======================================================
  // Dropdown listeners
  // ======================================================

 

 

  salesTypeSelect?.addEventListener('change', e => {
    buildSalesChart(e.target.value);
  });

  compMetricSelect?.addEventListener('change', e => {
    buildCompositionChart(e.target.value);
  });

  coachRevenueModeSelect?.addEventListener('change', e => {
    buildCoachRevenueChart(e.target.value || 'without_platform');
  });

  // ======================================================
  // Toggle for "New Users" clients/coaches list
  // ======================================================

  document.querySelectorAll('.toggle-group').forEach(group => {
    const buttons = group.querySelectorAll('.toggle-btn');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        buttons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const targetId = btn.dataset.target;
        if (!targetId) return;

        ['clientsList', 'coachesList'].forEach(id => {
          const el = document.getElementById(id);
          if (!el) return;
          if (id === targetId) {
            el.classList.remove('d-none');
          } else {
            el.classList.add('d-none');
          }
        });
      });
    });
  });

  // ======================================================
  // Range controls: show month/year vs custom
  // ======================================================

  document.querySelectorAll('.range-control').forEach(control => {
    const select = control.querySelector('select.range-select');
    if (!select) return;

    const monthYearBlock = control.querySelector('[data-role="month-year"]');
    const customBlock    = control.querySelector('[data-role="custom-range"]');

    const updateVisibility = () => {
      const val = select.value;
      if (monthYearBlock) {
        monthYearBlock.style.display = (val === 'month_year') ? 'flex' : 'none';
      }
      if (customBlock) {
        customBlock.style.display = (val === 'custom') ? 'flex' : 'none';
      }
    };

    select.addEventListener('change', updateVisibility);
    updateVisibility();
  });

  // ======================================================
  // Active Visitors live refresh
  // ======================================================

  const visitorsEl = document.getElementById('activeVisitorsKpi');
  if (visitorsEl) {
    const url = visitorsEl.dataset.url;

    const refreshVisitors = () => {
      if (!url) return;
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.json())
        .then(json => {
          if (typeof json.count !== 'undefined') {
            visitorsEl.textContent = json.count;
          }
        })
        .catch(() => {
          // ignore network errors silently
        });
    };

    refreshVisitors();
    setInterval(refreshVisitors, 5000);
  }

  // ======================================================
  // Print & "Export PDF" (open chart image in new window)
  // ======================================================

  function openChartWindow(canvasId, doPrint = false) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const dataUrl = canvas.toDataURL('image/png');
    const w = window.open('', '_blank');
    if (!w) return;

    w.document.write(`
      <html>
        <head>
          <title>${canvasId}</title>
          <style>
            body { margin:0; display:flex; align-items:center; justify-content:center; background:#fff; }
            img { max-width:100%; max-height:100vh; }
          </style>
        </head>
        <body>
          <img src="${dataUrl}" alt="Chart">
        </body>
      </html>
    `);
    w.document.close();

    if (doPrint) {
      w.focus();
      w.print();
    }
  }

  document.querySelectorAll('.chart-print').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.chartTarget;
      if (target) openChartWindow(target, true);
    });
  });

  document.querySelectorAll('.chart-export').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.chartTarget;
      if (target) openChartWindow(target, false); // user can Save-as-PDF from browser
    });
  });
});
