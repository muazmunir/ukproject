@extends('layouts.admin')

@push('styles')
<style>
  .sa-wrap { max-width: 1180px; margin: 0 auto; }
  .sa-head h4 { font-weight: 800; letter-spacing: -0.2px; }
  .sa-sub { font-size: 13px; opacity: .8; }


  .sa-hours{
  font-weight: 900;
  font-size: 16px;      /* was 20px */
  letter-spacing: -0.2px;
  white-space: nowrap;
}

  .sa-card {
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 10px 26px rgba(0,0,0,.06);
  }

  .sa-toolbar .form-select, .sa-toolbar .form-control { border-radius: 12px; }
  .sa-toolbar .btn { border-radius: 12px; font-weight: 700; }

  /* ===== KPIs ===== */
  .sa-kpi .card {
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,.05);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .sa-kpi .card:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(0,0,0,.08); }
  .sa-kpi-top{ display:flex; align-items:center; gap:10px; padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,.18); }
  .sa-dot{ width: 10px; height: 10px; border-radius: 999px; box-shadow: 0 0 0 3px rgba(255,255,255,.25); flex: 0 0 auto; }
  .sa-kpi-title{ font-size: 12px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; color: rgba(255,255,255,.95); }
  .sa-kpi-val{ padding: 10px 12px; background: #fff; display:flex; align-items:baseline; justify-content:space-between; }
  .sa-hours{ font-weight: 900; font-size: 20px; letter-spacing: -0.2px; }
  .sa-suffix{ font-size: 12px; opacity: .7; font-weight: 700; }

  /* ===== Timeline (SCROLL) ===== */
  .sa-timeline{
    background:#fff;
    border:1px solid rgba(0,0,0,.08);
    border-radius:16px;
    box-shadow:0 10px 26px rgba(0,0,0,.04);
    overflow:hidden;
  }
  .sa-timeline-inner{
    height: 720px;
    overflow: auto;
    background: #f3f4f6;
  }

  /* Day view */
  .sa-day{ display:grid; grid-template-columns: 70px 1fr; min-height: 720px; }
  .sa-hours-col{ background: linear-gradient(180deg,#fff,#fbfbfb); border-right:1px solid rgba(0,0,0,.06); position:relative; }
  .sa-hour{ height: 60px; font-size: 12px; color: rgba(0,0,0,.55); padding: 6px 10px; border-bottom:1px dashed rgba(0,0,0,.06); }
  .sa-lane{ position:relative; background:#f3f4f6; }
  .sa-gridline{ position:absolute; left:0; right:0; height:1px; border-top:1px dashed rgba(0,0,0,.07); pointer-events:none; }

  .sa-block{
    position:absolute;
    left:0 !important; right:0 !important;
    border-radius:0 !important;
    padding:10px 12px;
    color:#fff;
    box-shadow: 0 10px 18px rgba(0,0,0,.08);
    overflow:hidden;
    cursor: help; /* ✅ cursor like manager */
  }
  .sa-block .t1{ font-weight:900; letter-spacing:-.2px; line-height:1.1; }
  .sa-block .t2{ font-size:12px; opacity:.92; font-weight:700; margin-top:4px; }

  /* Week view */
  .sa-week{ display:grid; grid-template-columns: repeat(7, 1fr); gap: 0; }
  .sa-week-col{ border-right:1px solid rgba(0,0,0,.06); }
  .sa-week-col:last-child{ border-right:0; }
  .sa-week-head{ padding:10px 12px; font-weight:900; font-size:13px; background: linear-gradient(180deg,#fff,#fbfbfb); border-bottom:1px solid rgba(0,0,0,.06); }
  .sa-week-daybody{ display:grid; grid-template-columns: 48px 1fr; min-height: 520px; }
  .sa-week .sa-hour{ height: 44px; }
  .sa-week .sa-lane{ background:#f3f4f6; }
  .sa-week .sa-block{ left: 8px !important; right: 8px !important; border-radius: 10px !important; padding: 8px 10px; }
  .sa-week .sa-block .t1{ font-size: 12px; }
  .sa-week .sa-block .t2{ font-size: 11px; }

  /* Month view */
  .sa-month{ display:grid; grid-template-columns: repeat(7, 1fr); border-top:1px solid rgba(0,0,0,.06); background:#fff; }
  .sa-month .day{ border-right:1px solid rgba(0,0,0,.06); border-bottom:1px solid rgba(0,0,0,.06); min-height: 92px; padding: 8px; }
  .sa-month .day:nth-child(7n){ border-right:0; }
  .sa-month .dnum{ font-weight:900; font-size:12px; opacity:.75; }
  .sa-mini{ margin-top:6px; height: 10px; border-radius: 999px; background: rgba(0,0,0,.06); overflow:hidden; display:flex; }
  .sa-mini > span{ height:100%; display:block; }
</style>
@endpush

@section('content')
<div class="container py-3 sa-wrap">

  <div class="d-flex align-items-center justify-content-between mb-3 sa-head flex-wrap gap-2">
    <div>
      <h4 class="mb-0">My Status Analytics</h4>
      <div class="sa-sub text-muted">Time spent per status</div>
    </div>

    <div class="d-flex gap-2 sa-toolbar flex-wrap justify-content-end">
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-dark" id="prevBtn" title="Previous"><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="btn btn-outline-dark" id="nextBtn" title="Next"><i class="bi bi-chevron-right"></i></button>
        <button type="button" class="btn btn-outline-secondary" id="todayBtn">Today</button>
        <input type="date" id="anchorDate" class="form-control" style="max-width:170px">
      </div>

      <select id="range" class="form-select" style="min-width:160px;">
        <option value="daily">Day</option>
        <option value="weekly" selected>Week</option>
        <option value="monthly">Month</option>
        {{-- <option value="yearly">Year</option> --}}
        {{-- <option value="lifetime">Lifetime</option> --}}
        {{-- <option value="custom">Custom</option> --}}
      </select>

     

      <input type="hidden" id="tz_mode" value="target">
      <input type="hidden" id="tz" value="{{ auth()->user()->timezone ?? 'UTC' }}">

      <button id="refresh" class="btn btn-primary bg-black">Refresh</button>
    </div>
  </div>

  {{-- Working hours --}}
  <div class="mb-3">
    <div class="card sa-card">
      <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div style="font-weight:900">Working Hours</div>
          <div class="text-muted small" id="shiftText">—</div>
        </div>
        <div class="text-muted small">
          <span class="badge bg-dark" id="shiftTzBadge">TZ: —</span>
        </div>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-3 sa-kpi" id="kpis"></div>

  {{-- Timeline --}}
  <div class="card sa-card">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="small text-muted" style="font-weight:800;">Timeline</div>
        <div class="small text-muted" id="timelineMeta">—</div>
      </div>

      <div class="sa-timeline">
        <div class="sa-timeline-inner" id="timelineScroll">
          <div id="timelineWrap"></div>
        </div>
      </div>

      <div class="small text-muted mt-2" style="font-weight:800;">
        Tip: Hover any colored bar to see the exact duration.
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
  const anchorEl = document.getElementById('anchorDate');
  const rangeEl  = document.getElementById('range');

//   const customWrap = document.getElementById('customWrap');
//   const customFrom = document.getElementById('customFrom');
//   const customTo   = document.getElementById('customTo');

  function yyyy_mm_dd(d){
    const x = new Date(d);
    const y = x.getFullYear();
    const m = String(x.getMonth()+1).padStart(2,'0');
    const da = String(x.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
  }
  function parseYmdHis(s){
  // expects "YYYY-MM-DD HH:MM:SS"
  return new Date(s.replace(' ', 'T'));
}

  function minutesBetween(a,b){ return Math.max(0, Math.round((b - a) / 60000)); }
  function clampDate(d, min, max){ return new Date(Math.min(max.getTime(), Math.max(min.getTime(), d.getTime()))); }
  function dayKey(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const da = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
  }
  function startOfDay(d){ const x = new Date(d); x.setHours(0,0,0,0); return x; }
  function addDaysDate(d, n){ const x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function addDays(dateStr, n){ const d = new Date(dateStr + "T00:00:00"); d.setDate(d.getDate() + n); return yyyy_mm_dd(d); }
  function addMonths(dateStr, n){ const d = new Date(dateStr + "T00:00:00"); d.setMonth(d.getMonth() + n); return yyyy_mm_dd(d); }
  function addYears(dateStr, n){ const d = new Date(dateStr + "T00:00:00"); d.setFullYear(d.getFullYear() + n); return yyyy_mm_dd(d); }

  function stepForRange(range){
    switch(range){
      case 'daily': return { type:'day', n:1 };
      case 'weekly': return { type:'day', n:7 };
      case 'monthly': return { type:'month', n:1 };
      case 'yearly': return { type:'year', n:1 };
      default: return { type:'day', n:0 };
    }
  }

  function shiftAnchor(dir){
    const r = rangeEl.value;
    if (r === 'lifetime' || r === 'custom') return;

    const step = stepForRange(r);
    if (!step.n) return;

    const cur = anchorEl.value || yyyy_mm_dd(new Date());
    let next = cur;

    if (step.type === 'day')   next = addDays(cur, dir*step.n);
    if (step.type === 'month') next = addMonths(cur, dir*step.n);
    if (step.type === 'year')  next = addYears(cur, dir*step.n);

    anchorEl.value = next;
    loadData();
  }

 function durationTextFromSeconds(totalSec){
  const s = Math.max(0, Number(totalSec || 0));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = Math.floor(s % 60);

  const parts = [];
  if (h > 0) parts.push(`${h}h`);
  if (m > 0 || h > 0) parts.push(`${m}m`);
  parts.push(`${r}s`);
  return parts.join(' ');
}


  const STATUS_THEME = {
    available:            { label: 'Available',            color: '#22c55e' },
    break:                { label: 'Break',                color: '#facc15' },
  
    meeting:              { label: 'Meeting',              color: '#a855f7' },
    admin:                { label: 'Admin',                color: '#3b82f6' },
    tech_issues:          { label: 'Tech',                 color: '#92400e' },
    // non_working_hours:    { label: 'Non Working Hours',    color: '#9ca3af' },
    holiday:              { label: 'Holiday',              color: '#ec4899' },
    authorized_absence:   { label: 'Authorised Absence',   color: '#f59e0b' },
    unauthorized_absence: { label: 'Unauthorised Absence', color: '#ef4444' },
    offline: { label: 'Offline', color: '#374151' },

  };

  const KPI_ORDER = [
    'available','break','meeting','admin','tech_issues',
    'holiday','authorized_absence','unauthorized_absence', 'offline',
  ];

  function hmsFromSeconds(totalSec){
  const s = Math.max(0, Math.floor(Number(totalSec || 0)));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = s % 60;
  return `${h}h ${String(m).padStart(2,'0')}m ${String(r).padStart(2,'0')}s`;
}

// ✅ KPI formatter (expects seconds; if backend sends hours, it will still work below)
function kpiValueText(v){
  const sec = Math.max(0, Math.floor(Number(v || 0)));
  return hmsFromSeconds(sec);
}
  function prettyKey(k){ return (STATUS_THEME[k]?.label) || k.replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase()); }
  function toColor(status){ return (STATUS_THEME[status]?.color) || '#9ca3af'; }
  function toLabel(status){ return (STATUS_THEME[status]?.label) || prettyKey(status); }

  function renderKpis(totals){
    const wrap = document.getElementById('kpis');
    wrap.innerHTML = '';
    KPI_ORDER.forEach(k => {
      const theme = STATUS_THEME[k] || { color:'#111827', label: prettyKey(k) };
      const val = (totals && totals[k] !== undefined) ? totals[k] : 0;

      const col = document.createElement('div');
      col.className = 'col-6 col-md-4 col-lg-3 col-xl-2';
      col.innerHTML = `
        <div class="card">
          <div class="sa-kpi-top" style="background:${theme.color};">
            <span class="sa-dot" style="background:#fff;"></span>
            <div class="sa-kpi-title">${theme.label.toUpperCase()}</div>
          </div>
          <div class="sa-kpi-val">
            <div class="sa-hours">${kpiValueText(val)}</div>
<div class="sa-suffix">time</div>
          </div>
        </div>
      `;
      wrap.appendChild(col);
    });
  }

  function initTooltips(){
    try {
      if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
          const inst = bootstrap.Tooltip.getInstance(el);
          if (inst) inst.dispose();
          new bootstrap.Tooltip(el);
        });
      }
    } catch(e){}
  }

  function renderTimeline(range, sessions, fromStr, toStr, tz){
    const wrap = document.getElementById('timelineWrap');
    const meta = document.getElementById('timelineMeta');
    wrap.innerHTML = '';
    meta.textContent = `Window (${tz}): ${fromStr} → ${toStr}`;

    const winFrom = parseYmdHis(fromStr.slice(0,19));
const winTo   = parseYmdHis(toStr.slice(0,19));


    if (!sessions || !sessions.length){
      wrap.innerHTML = `<div class="p-3 text-muted small">No sessions in this window.</div>`;
      return;
    }

    if (range === 'daily' || range === 'custom'){
      wrap.appendChild(buildDayView(sessions, winFrom, winTo, 60));
      initTooltips();
      return;
    }
    if (range === 'weekly'){
      wrap.appendChild(buildWeekView(sessions, winFrom, winTo));
      initTooltips();
      return;
    }
    if (range === 'monthly'){
      wrap.appendChild(buildMonthView(sessions, winFrom, winTo));
      return;
    }

    wrap.innerHTML = `
      <div class="p-3">
        <div class="text-muted small">For ${range.toUpperCase()} view, KPIs are the summary.</div>
        <div class="text-muted small mt-1">Tip: switch to Day/Week/Month to see the timeline blocks.</div>
      </div>
    `;
  }

  function buildDayView(sessions, winFrom, winTo, pxPerHour=60){
    const day = document.createElement('div');
    day.className = 'sa-day';

    const hours = document.createElement('div');
    hours.className = 'sa-hours-col';

    const lane = document.createElement('div');
    lane.className = 'sa-lane';

    for(let h=0; h<24; h++){
      const row = document.createElement('div');
      row.className = 'sa-hour';
      row.textContent = String(h).padStart(2,'0') + ':00';
      hours.appendChild(row);

      const gl = document.createElement('div');
      gl.className = 'sa-gridline';
      gl.style.top = (h * pxPerHour) + 'px';
      lane.appendChild(gl);
    }

    lane.style.height = (24 * pxPerHour) + 'px';

    sessions.forEach(s => {
    const st = parseYmdHis(s.start);
const en = parseYmdHis(s.end);

const cStart = clampDate(st, winFrom, winTo);
const cEnd   = clampDate(en, winFrom, winTo);
if (cEnd <= cStart) return;

// seconds-from-midnight + length in seconds
const secsFromMidnight =
  (cStart.getHours()*3600) + (cStart.getMinutes()*60) + cStart.getSeconds();

const lenSec = Math.max(0, Math.round((cEnd - cStart) / 1000));

const topPx = (secsFromMidnight / 3600) * pxPerHour;
const hPx   = Math.max(8, (lenSec / 3600) * pxPerHour); // ✅ thin blocks still visible

const dur = durationTextFromSeconds(s.seconds ?? lenSec);


      const b = document.createElement('div');
      b.className = 'sa-block';
      b.style.top = topPx + 'px';
      b.style.height = hPx + 'px';
      b.style.background = toColor(s.status);

      // ✅ Bootstrap tooltip content (same style as manager)
     const tip = `${toLabel(s.status)} • ${s.start} – ${s.end} • ${dur}`;


      b.setAttribute('title', tip);
      b.setAttribute('data-bs-toggle', 'tooltip');
      b.setAttribute('data-bs-placement', 'top');

      b.innerHTML = `
        <div class="t1">${toLabel(s.status)}</div>
        <div class="t2">${s.start} – ${s.end} • ${dur}</div>
      `;
      lane.appendChild(b);
    });

    day.appendChild(hours);
    day.appendChild(lane);
    return day;
  }

  function buildWeekView(sessions, winFrom, winTo){
    const root = document.createElement('div');
    root.className = 'sa-week';

    const map = {};
    sessions.forEach(s => {
      const d = dayKey(parseYmdHis(s.start));

      (map[d] ||= []).push(s);
    });

    const start = startOfDay(winFrom);

    for(let i=0;i<7;i++){
      const d = addDaysDate(start, i);
      const dk = dayKey(d);
      const title = d.toLocaleDateString(undefined, { weekday:'short', month:'short', day:'numeric' });

      const col = document.createElement('div');
      col.className = 'sa-week-col';
      col.innerHTML = `<div class="sa-week-head">${title}</div>`;

      const body = document.createElement('div');
      body.className = 'sa-week-daybody';

      const hours = document.createElement('div');
      hours.className = 'sa-hours-col';

      const lane = document.createElement('div');
      lane.className = 'sa-lane';

      const pxPerHour = 44;

      for(let h=0; h<24; h++){
        const row = document.createElement('div');
        row.className = 'sa-hour';
        row.textContent = (h % 6 === 0) ? (String(h).padStart(2,'0') + ':00') : '';
        hours.appendChild(row);

        const gl = document.createElement('div');
        gl.className = 'sa-gridline';
        gl.style.top = (h * pxPerHour) + 'px';
        lane.appendChild(gl);
      }
      lane.style.height = (24 * pxPerHour) + 'px';

      (map[dk] || []).forEach(s => {
      const st = parseYmdHis(s.start);
const en = parseYmdHis(s.end);

const cStart = clampDate(st, winFrom, winTo);
const cEnd   = clampDate(en, winFrom, winTo);
if (cEnd <= cStart) return;

const secsFromMidnight =
  (cStart.getHours()*3600) + (cStart.getMinutes()*60) + cStart.getSeconds();

const lenSec = Math.max(0, Math.round((cEnd - cStart) / 1000));

const topPx = (secsFromMidnight / 3600) * pxPerHour;
const hPx   = Math.max(6, (lenSec / 3600) * pxPerHour);

const hh1 = cStart.getHours().toString().padStart(2,'0');
const mm1 = cStart.getMinutes().toString().padStart(2,'0');
const ss1 = cStart.getSeconds().toString().padStart(2,'0');

const hh2 = cEnd.getHours().toString().padStart(2,'0');
const mm2 = cEnd.getMinutes().toString().padStart(2,'0');
const ss2 = cEnd.getSeconds().toString().padStart(2,'0');

const dur = durationTextFromSeconds(s.seconds ?? lenSec);


        const b = document.createElement('div');
        b.className = 'sa-block';
        b.style.top = topPx + 'px';
        b.style.height = hPx + 'px';
        b.style.background = toColor(s.status);

      const tip = `${toLabel(s.status)} • ${hh1}:${mm1}:${ss1} – ${hh2}:${mm2}:${ss2} • ${dur}`;
b.setAttribute('title', tip);
b.setAttribute('data-bs-toggle', 'tooltip');
b.setAttribute('data-bs-placement', 'top');


        b.innerHTML = `
          <div class="t1">${toLabel(s.status)}</div>
        <div class="t2">${hh1}:${mm1}:${ss1} – ${hh2}:${mm2}:${ss2} • ${dur}</div>
        `;
        lane.appendChild(b);
      });

      body.appendChild(hours);
      body.appendChild(lane);
      col.appendChild(body);
      root.appendChild(col);
    }

    return root;
  }

  function buildMonthView(sessions, winFrom, winTo){
    const root = document.createElement('div');
    root.className = 'sa-month';

    const byDay = {};
    sessions.forEach(s => {
      const d = dayKey(parseYmdHis(s.start));

      byDay[d] ||= {};
     const sec = Number(s.seconds ?? (s.minutes ? s.minutes*60 : 0));
byDay[d][s.status] = (byDay[d][s.status] || 0) + sec;

    });

    const first = new Date(winFrom.getFullYear(), winFrom.getMonth(), 1);
    const last  = new Date(winFrom.getFullYear(), winFrom.getMonth()+1, 0);

    const firstDow = (first.getDay() + 6) % 7; // Monday=0
    const cells = firstDow + last.getDate();
    const totalCells = Math.ceil(cells / 7) * 7;

    for(let c=0;c<totalCells;c++){
      const cell = document.createElement('div');
      cell.className = 'day';

      const dayNum = c - firstDow + 1;
      if (dayNum < 1 || dayNum > last.getDate()){
        cell.innerHTML = `<div class="dnum">&nbsp;</div>`;
        root.appendChild(cell);
        continue;
      }

      const date = new Date(winFrom.getFullYear(), winFrom.getMonth(), dayNum);
      const dk = dayKey(date);
      const totals = byDay[dk] || {};
      const totalSec = Object.values(totals).reduce((a,b)=>a+b,0);

      let mini = `<div class="sa-mini">`;
      if (totalSec > 0){
        Object.keys(totals).forEach(st => {
          const w = Math.round((totals[st] / totalSec) * 100);
          if (w <= 0) return;
          mini += `<span style="width:${w}%;background:${toColor(st)}"></span>`;
        });
      } else {
        mini += `<span style="width:100%;background:rgba(0,0,0,.06)"></span>`;
      }
      mini += `</div>`;

      cell.innerHTML = `<div class="dnum">${dayNum}</div>${mini}`;
      root.appendChild(cell);
    }

    return root;
  }

  function buildUrl(base, params){
    const u = new URL(base, window.location.origin);
    Object.keys(params).forEach(k => {
      if (params[k] !== undefined && params[k] !== null) u.searchParams.set(k, params[k]);
    });
    return u.toString();
  }

  // init defaults
  anchorEl.value = yyyy_mm_dd(new Date());

  document.getElementById('prevBtn').addEventListener('click', () => shiftAnchor(-1));
  document.getElementById('nextBtn').addEventListener('click', () => shiftAnchor(1));
  document.getElementById('todayBtn').addEventListener('click', () => { anchorEl.value = yyyy_mm_dd(new Date()); loadData(); });

  anchorEl.addEventListener('change', loadData);

  rangeEl.addEventListener('change', () => {
    // const isCustom = rangeEl.value === 'custom';
    // customWrap.style.display = isCustom ? 'flex' : 'none';

    // if (isCustom) {
    //   const a = anchorEl.value || yyyy_mm_dd(new Date());
    //   customFrom.value = customFrom.value || a;
    //   customTo.value   = customTo.value   || a;
    // }
    loadData();
  });

//   customFrom.addEventListener('change', loadData);
//   customTo.addEventListener('change', loadData);
  document.getElementById('refresh').addEventListener('click', loadData);

  async function loadData(){
    const range = rangeEl.value;
    const tzMode = document.getElementById('tz_mode').value;
    const tz = document.getElementById('tz').value;

    const anchor = anchorEl.value || yyyy_mm_dd(new Date());
    const params = { range, tz_mode: tzMode, tz };

    if (range !== 'lifetime') params.anchor = anchor;

    // if (range === 'custom') {
    //   params.from = customFrom.value || anchor;
    //   params.to   = customTo.value   || anchor;
    // }

    const url = buildUrl(`{{ route('admin.support.status.analytics.data') }}`, params);

    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const json = await res.json();

    if (!json.ok) {
      alert(json.message || 'Failed');
      return;
    }

    document.getElementById('shiftText').textContent =
      (json.shift && json.shift.label) ? json.shift.label : '—';

    document.getElementById('shiftTzBadge').textContent =
      `TZ: ${json.display_tz || '—'}`;

    renderKpis(json.totals_seconds || json.totals || {});
    renderTimeline(range, json.sessions || [], json.from, json.to, json.display_tz);

    // ✅ re-init tooltips after DOM updates
    initTooltips();
  }

  loadData();
</script>
@endpush
