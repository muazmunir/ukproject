@extends('layouts.admin')

@push('styles')
<style>
  .sa-page{max-width:1400px;margin:0 auto;}
  .sa-title h4{font-weight:900;letter-spacing:-.03em;margin-bottom:4px;}
  .sa-title p{margin:0;color:#6b7280;font-size:13px;}

  .sa-shell{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:20px;box-shadow:0 14px 34px rgba(15,23,42,.06);}
  .sa-shell .card-body{padding:18px;}

  .sa-toolbar-grid{display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:16px;align-items:start;}
  @media (max-width: 1100px){.sa-toolbar-grid{grid-template-columns:1fr;}}

  .sa-panel{border:1px solid rgba(15,23,42,.08);border-radius:16px;background:linear-gradient(180deg,#fff,#fafafa);padding:14px;}
  .sa-panel label{display:block;font-size:12px;font-weight:900;margin-bottom:6px;color:#111827;}
  .sa-panel .form-control,.sa-panel .form-select{border-radius:12px;}

  .sa-toolbar-stack{display:grid;gap:12px;}
  .sa-toolbar-inline{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
  .sa-toolbar-inline .btn{border-radius:12px;font-weight:800;}
  .sa-toolbar-inline .form-control{max-width:190px;}

  .sa-select{height:250px;font-weight:700;overflow:auto;}
  .sa-select option{padding:10px 12px;}
  .sa-tip{font-size:12px;color:#6b7280;font-weight:800;margin-top:8px;}

  .sa-mode-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
  @media (max-width: 768px){.sa-mode-grid{grid-template-columns:1fr;}}

  .sa-results-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;}
  .sa-results-meta{font-size:13px;color:#6b7280;font-weight:800;}

  .sa-member-stack{display:grid;gap:18px;}
  .sa-member-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.05);overflow:hidden;}
  .sa-member-head{padding:18px;border-bottom:1px solid rgba(15,23,42,.07);display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;background:linear-gradient(180deg,#ffffff,#fbfbfb);}
  .sa-member-name{font-weight:900;font-size:20px;line-height:1.15;margin-bottom:4px;}
  .sa-member-sub{display:flex;flex-wrap:wrap;gap:8px;font-size:12px;color:#6b7280;font-weight:800;}
  .sa-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:900;background:#111827;color:#fff;}
  .sa-badge-light{background:#eef2ff;color:#3730a3;}
  .sa-member-body{padding:18px;display:grid;gap:16px;}

  .sa-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;}
  .sa-kpi{border:1px solid rgba(15,23,42,.08);border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 6px 16px rgba(15,23,42,.04);}
  .sa-kpi-top{padding:10px 12px;color:#fff;font-size:11px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;display:flex;align-items:center;gap:8px;}
  .sa-kpi-dot{width:9px;height:9px;border-radius:999px;background:#fff;box-shadow:0 0 0 3px rgba(255,255,255,.2);}
  .sa-kpi-bottom{padding:12px;background:#fff;display:flex;align-items:flex-end;justify-content:space-between;gap:8px;}
  .sa-kpi-time{font-weight:900;font-size:20px;line-height:1;letter-spacing:-.02em;}
  .sa-kpi-suffix{font-size:11px;color:#6b7280;font-weight:800;text-transform:uppercase;}

  .sa-timeline-card{border:1px solid rgba(15,23,42,.08);border-radius:16px;overflow:hidden;background:#fff;}
  .sa-timeline-head{padding:12px 14px;border-bottom:1px solid rgba(15,23,42,.07);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
  .sa-timeline-head strong{font-size:13px;}
  .sa-timeline-meta{font-size:12px;color:#6b7280;font-weight:800;}
  .sa-timeline-scroll{height:560px;overflow:auto;background:#f3f4f6;}
  .sa-empty{padding:28px 16px;text-align:center;color:#6b7280;font-weight:800;}

  .sa-day{display:grid;grid-template-columns:70px 1fr;min-height:720px;}
  .sa-hours-col{background:linear-gradient(180deg,#fff,#fbfbfb);border-right:1px solid rgba(15,23,42,.06);}
  .sa-hour{height:60px;font-size:12px;color:rgba(15,23,42,.55);padding:6px 10px;border-bottom:1px dashed rgba(15,23,42,.08);}
  .sa-lane{position:relative;background:#f3f4f6;}
  .sa-gridline{position:absolute;left:0;right:0;height:1px;border-top:1px dashed rgba(15,23,42,.08);pointer-events:none;}

  .sa-block{position:absolute;left:0;right:0;padding:10px 12px;color:#fff;box-shadow:0 10px 18px rgba(15,23,42,.1);overflow:hidden;cursor:help;}
  .sa-block .t1{font-weight:900;line-height:1.1;}
  .sa-block .t2{font-size:12px;opacity:.93;font-weight:800;margin-top:4px;}

  .sa-week{display:grid;grid-template-columns:repeat(7,1fr);}
  .sa-week-col{border-right:1px solid rgba(15,23,42,.06);}
  .sa-week-col:last-child{border-right:0;}
  .sa-week-head{padding:10px 12px;font-size:13px;font-weight:900;background:linear-gradient(180deg,#fff,#fbfbfb);border-bottom:1px solid rgba(15,23,42,.06);}
  .sa-week-daybody{display:grid;grid-template-columns:48px 1fr;min-height:520px;}
  .sa-week .sa-hour{height:44px;}
  .sa-week .sa-block{left:8px;right:8px;border-radius:10px;padding:8px 10px;}
  .sa-week .sa-block .t1{font-size:12px;}
  .sa-week .sa-block .t2{font-size:11px;}

  .sa-month{display:grid;grid-template-columns:repeat(7,1fr);background:#fff;border-top:1px solid rgba(15,23,42,.06);}
  .sa-month-day{border-right:1px solid rgba(15,23,42,.06);border-bottom:1px solid rgba(15,23,42,.06);min-height:96px;padding:8px;}
  .sa-month-day:nth-child(7n){border-right:0;}
  .sa-month-num{font-size:12px;font-weight:900;color:#6b7280;}
  .sa-mini{margin-top:8px;height:10px;border-radius:999px;background:rgba(15,23,42,.06);display:flex;overflow:hidden;}
  .sa-mini span{display:block;height:100%;}

  .sa-hidden{display:none !important;}
</style>
@endpush

@section('content')
<div class="container py-3 sa-page">
  <div class="sa-title mb-3">
    <h4>Agents Status Analytics</h4>
    <p>Manager view supports both single-person analytics and team-by-team stacked analytics using the same KPI and timeline blocks.</p>
  </div>

  <div class="card sa-shell mb-3">
    <div class="card-body">
      <div class="sa-toolbar-grid">
        <div class="sa-panel">
          <div class="sa-toolbar-stack">
            <div class="sa-mode-grid">
              <div>
                <label>Access Scope</label>
                <select id="scope" class="form-select">
                  <option value="team" @selected($scope === 'team')>My Team</option>
                  <option value="all" @selected($scope === 'all')>All</option>
                </select>
              </div>
              <div>
                <label>View Mode</label>
                <select id="mode" class="form-select">
                  <option value="individual" @selected($mode === 'individual')>Individual</option>
                  <option value="team" @selected($mode === 'team')>Team</option>
                </select>
              </div>
            </div>

            <div id="personWrap">
              <label>Select Person</label>
              <input type="text" id="userSearch" class="form-control mb-2" placeholder="Search by name, email or timezone..." autocomplete="off">
              <select id="userSelect" class="form-select sa-select" size="10">
                @foreach($people as $person)
                  @php
                    $displayName = trim(($person->first_name.' '.$person->last_name)) ?: $person->email;
                    $timezone = $person->timezone ?: 'UTC';
                    $search = strtolower($displayName.' '.$person->email.' '.$timezone.' '.$person->role);
                  @endphp
                  <option value="{{ $person->id }}" data-search="{{ $search }}" @selected((int) $selectedUserId === (int) $person->id)>
                    {{ $displayName }} — {{ $timezone }} — {{ ucfirst($person->role) }}
                  </option>
                @endforeach
              </select>
              <div class="sa-tip">Type to filter, then pick an individual.</div>
            </div>

            <div id="teamWrap">
              <label>Select Team</label>
              <select id="teamSelect" class="form-select">
                @foreach($teams as $team)
                  <option value="{{ $team->id }}" @selected((int) $selectedTeamId === (int) $team->id)>
                    {{ $team->name }} ({{ (int) $team->members_count }} members)
                  </option>
                @endforeach
              </select>
              <div class="sa-tip">Selecting a team renders the same analytics block for each member, one by one.</div>
            </div>
          </div>
        </div>

        <div class="sa-toolbar-stack">
          <div class="sa-mode-grid">
            <div class="sa-panel">
              <label>Date Navigation</label>
              <div class="sa-toolbar-inline">
                <button type="button" class="btn btn-outline-dark" id="prevBtn"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-dark" id="nextBtn"><i class="bi bi-chevron-right"></i></button>
                <button type="button" class="btn btn-outline-secondary" id="todayBtn">Today</button>
              </div>
            </div>

            <div class="sa-panel">
              <label>Anchor Date</label>
              <input type="date" id="anchorDate" class="form-control">
            </div>
          </div>

          <div class="sa-mode-grid">
            <div class="sa-panel">
              <label>Range</label>
              <select id="range" class="form-select">
                <option value="daily">Day</option>
                <option value="weekly" selected>Week</option>
                <option value="monthly">Month</option>
                <option value="yearly">Year</option>
                <option value="lifetime">Lifetime</option>
                <option value="custom">Custom</option>
              </select>
            </div>

            <div class="sa-panel">
              <label>Action</label>
              <div class="sa-toolbar-inline">
                <button type="button" class="btn btn-dark" id="refreshBtn">Refresh</button>
              </div>
            </div>
          </div>

          <div class="sa-panel sa-hidden" id="customRangeWrap">
            <label>Custom Range</label>
            <div class="sa-toolbar-inline">
              <input type="date" id="customFrom" class="form-control">
              <input type="date" id="customTo" class="form-control">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="sa-results-head">
    <div>
      <div class="fw-bold">Analytics Results</div>
      <div class="sa-results-meta" id="resultsSummary">Loading...</div>
    </div>
  </div>

  <div id="resultsWrap" class="sa-member-stack"></div>
</div>
@endsection

@push('scripts')
<script>
  const scopeEl = document.getElementById('scope');
  const modeEl = document.getElementById('mode');
  const userSearchEl = document.getElementById('userSearch');
  const userSelectEl = document.getElementById('userSelect');
  const teamSelectEl = document.getElementById('teamSelect');
  const personWrapEl = document.getElementById('personWrap');
  const teamWrapEl = document.getElementById('teamWrap');
  const rangeEl = document.getElementById('range');
  const anchorEl = document.getElementById('anchorDate');
  const customWrapEl = document.getElementById('customRangeWrap');
  const customFromEl = document.getElementById('customFrom');
  const customToEl = document.getElementById('customTo');
  const resultsWrapEl = document.getElementById('resultsWrap');
  const resultsSummaryEl = document.getElementById('resultsSummary');

  let currentUserId = Number(@json((int) $selectedUserId));
  let currentTeamId = Number(@json((int) $selectedTeamId));

  const STATUS_THEME = {
    available:            { label: 'Available',            color: '#22c55e' },
    offline:              { label: 'Offline',              color: '#64748b' },
    break:                { label: 'Break',                color: '#facc15' },
    meeting:              { label: 'Meeting',              color: '#a855f7' },
    admin:                { label: 'Admin',                color: '#3b82f6' },
    tech_issues:          { label: 'Tech',                 color: '#92400e' },
    holiday:              { label: 'Holiday',              color: '#ec4899' },
    authorized_absence:   { label: 'Authorised Absence',   color: '#f59e0b' },
    unauthorized_absence: { label: 'Unauthorised Absence', color: '#ef4444' },
    non_working_hours:    { label: 'Non Working',          color: '#cbd5e1' },
  };

  const KPI_ORDER = [
    'available',
    'offline',
    'break',
    'meeting',
    'admin',
    'tech_issues',
    'holiday',
    'authorized_absence',
    'unauthorized_absence',
  ];

  function syncModeVisibility() {
    const individual = modeEl.value === 'individual';
    personWrapEl.classList.toggle('sa-hidden', !individual);
    teamWrapEl.classList.toggle('sa-hidden', individual);
  }

  function filterUsers() {
    const query = (userSearchEl.value || '').trim().toLowerCase();
    const options = Array.from(userSelectEl.options);
    let firstVisible = null;

    options.forEach((option) => {
      const hay = (option.dataset.search || option.textContent || '').toLowerCase();
      const visible = !query || hay.includes(query);
      option.hidden = !visible;
      if (visible && !firstVisible) firstVisible = option;
    });

    const selected = userSelectEl.options[userSelectEl.selectedIndex];
    if (selected && selected.hidden && firstVisible) {
      userSelectEl.value = firstVisible.value;
      currentUserId = Number(firstVisible.value);
    }
  }

  function yyyyMmDd(date) {
    const d = new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function parseYmdHis(value) {
    return new Date(String(value).replace(' ', 'T'));
  }

  function clampDate(date, min, max) {
    return new Date(Math.min(max.getTime(), Math.max(min.getTime(), date.getTime())));
  }

  function startOfDay(date) {
    const d = new Date(date);
    d.setHours(0,0,0,0);
    return d;
  }

  function addDays(dateStr, n) {
    const d = new Date(`${dateStr}T00:00:00`);
    d.setDate(d.getDate() + n);
    return yyyyMmDd(d);
  }

  function addMonths(dateStr, n) {
    const d = new Date(`${dateStr}T00:00:00`);
    d.setMonth(d.getMonth() + n);
    return yyyyMmDd(d);
  }

  function addYears(dateStr, n) {
    const d = new Date(`${dateStr}T00:00:00`);
    d.setFullYear(d.getFullYear() + n);
    return yyyyMmDd(d);
  }

  function stepForRange(range) {
    switch (range) {
      case 'daily': return {type: 'day', n: 1};
      case 'weekly': return {type: 'day', n: 7};
      case 'monthly': return {type: 'month', n: 1};
      case 'yearly': return {type: 'year', n: 1};
      default: return {type: 'day', n: 0};
    }
  }

  function shiftAnchor(direction) {
    const range = rangeEl.value;
    if (range === 'lifetime' || range === 'custom') return;

    const step = stepForRange(range);
    if (!step.n) return;

    const current = anchorEl.value || yyyyMmDd(new Date());
    let next = current;

    if (step.type === 'day') next = addDays(current, direction * step.n);
    if (step.type === 'month') next = addMonths(current, direction * step.n);
    if (step.type === 'year') next = addYears(current, direction * step.n);

    anchorEl.value = next;
    loadData();
  }

  function buildUrl(base, params) {
    const url = new URL(base, window.location.origin);
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
        url.searchParams.set(key, params[key]);
      }
    });
    return url.toString();
  }

  function durationTextFromSeconds(totalSec) {
    const seconds = Math.max(0, Math.floor(Number(totalSec || 0)));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const parts = [];
    if (h > 0) parts.push(`${h}h`);
    if (m > 0 || h > 0) parts.push(`${m}m`);
    parts.push(`${s}s`);
    return parts.join(' ');
  }

  function hmsFromSeconds(totalSec) {
    const seconds = Math.max(0, Math.floor(Number(totalSec || 0)));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
  }

  function kpiValueText(hoursFloat) {
    return hmsFromSeconds(Math.round(Number(hoursFloat || 0) * 3600));
  }

  function toColor(status) {
    return (STATUS_THEME[status] || {}).color || '#94a3b8';
  }

  function toLabel(status) {
    return (STATUS_THEME[status] || {}).label || String(status || '').replaceAll('_', ' ');
  }

  function initTooltips(root = document) {
    try {
      if (window.bootstrap && bootstrap.Tooltip) {
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
          const existing = bootstrap.Tooltip.getInstance(el);
          if (existing) existing.dispose();
          new bootstrap.Tooltip(el);
        });
      }
    } catch (e) {}
  }

  function renderKpis(totals = {}) {
    const grid = document.createElement('div');
    grid.className = 'sa-kpi-grid';

    KPI_ORDER.forEach((key) => {
      const theme = STATUS_THEME[key] || {label: key, color: '#94a3b8'};
      const value = totals[key] !== undefined ? totals[key] : 0;

      const card = document.createElement('div');
      card.className = 'sa-kpi';
      card.innerHTML = `
        <div class="sa-kpi-top" style="background:${theme.color};">
          <span class="sa-kpi-dot"></span>
          <span>${theme.label}</span>
        </div>
        <div class="sa-kpi-bottom">
          <div class="sa-kpi-time">${kpiValueText(value)}</div>
          <div class="sa-kpi-suffix">time</div>
        </div>
      `;
      grid.appendChild(card);
    });

    return grid;
  }

  function buildDayView(sessions, winFrom, winTo, pxPerHour = 60) {
    const day = document.createElement('div');
    day.className = 'sa-day';

    const hours = document.createElement('div');
    hours.className = 'sa-hours-col';

    const lane = document.createElement('div');
    lane.className = 'sa-lane';
    lane.style.height = `${24 * pxPerHour}px`;

    for (let h = 0; h < 24; h++) {
      const row = document.createElement('div');
      row.className = 'sa-hour';
      row.textContent = `${String(h).padStart(2, '0')}:00`;
      hours.appendChild(row);

      const line = document.createElement('div');
      line.className = 'sa-gridline';
      line.style.top = `${h * pxPerHour}px`;
      lane.appendChild(line);
    }

    sessions.forEach((session) => {
      const start = parseYmdHis(session.start);
      const end = parseYmdHis(session.end);
      const clippedStart = clampDate(start, winFrom, winTo);
      const clippedEnd = clampDate(end, winFrom, winTo);
      if (clippedEnd <= clippedStart) return;

      const secondsFromMidnight = (clippedStart.getHours() * 3600) + (clippedStart.getMinutes() * 60) + clippedStart.getSeconds();
      const lengthSeconds = Math.max(0, Math.round((clippedEnd - clippedStart) / 1000));
      const top = (secondsFromMidnight / 3600) * pxPerHour;
      const height = Math.max(8, (lengthSeconds / 3600) * pxPerHour);
      const duration = durationTextFromSeconds(session.seconds ?? lengthSeconds);

      const block = document.createElement('div');
      block.className = 'sa-block';
      block.style.top = `${top}px`;
      block.style.height = `${height}px`;
      block.style.background = toColor(session.status);
      block.setAttribute('data-bs-toggle', 'tooltip');
      block.setAttribute('title', `${toLabel(session.status)} • ${session.start} – ${session.end} • ${duration}`);
      block.innerHTML = `<div class="t1">${toLabel(session.status)}</div><div class="t2">${session.start} – ${session.end} • ${duration}</div>`;
      lane.appendChild(block);
    });

    day.appendChild(hours);
    day.appendChild(lane);
    return day;
  }

  function buildWeekView(sessions, winFrom, winTo) {
    const root = document.createElement('div');
    root.className = 'sa-week';

    const map = {};
    sessions.forEach((session) => {
      const d = yyyyMmDd(parseYmdHis(session.start));
      (map[d] ||= []).push(session);
    });

    const start = startOfDay(winFrom);

    for (let i = 0; i < 7; i++) {
      const dayDate = new Date(start);
      dayDate.setDate(dayDate.getDate() + i);
      const dayKey = yyyyMmDd(dayDate);
      const title = dayDate.toLocaleDateString(undefined, {weekday: 'short', month: 'short', day: 'numeric'});

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
      lane.style.height = `${24 * pxPerHour}px`;

      for (let h = 0; h < 24; h++) {
        const row = document.createElement('div');
        row.className = 'sa-hour';
        row.textContent = h % 6 === 0 ? `${String(h).padStart(2, '0')}:00` : '';
        hours.appendChild(row);

        const line = document.createElement('div');
        line.className = 'sa-gridline';
        line.style.top = `${h * pxPerHour}px`;
        lane.appendChild(line);
      }

      (map[dayKey] || []).forEach((session) => {
        const startTime = parseYmdHis(session.start);
        const endTime = parseYmdHis(session.end);
        const clippedStart = clampDate(startTime, winFrom, winTo);
        const clippedEnd = clampDate(endTime, winFrom, winTo);
        if (clippedEnd <= clippedStart) return;

        const secondsFromMidnight = (clippedStart.getHours() * 3600) + (clippedStart.getMinutes() * 60) + clippedStart.getSeconds();
        const lengthSeconds = Math.max(0, Math.round((clippedEnd - clippedStart) / 1000));
        const top = (secondsFromMidnight / 3600) * pxPerHour;
        const height = Math.max(6, (lengthSeconds / 3600) * pxPerHour);
        const duration = durationTextFromSeconds(session.seconds ?? lengthSeconds);

        const hh1 = String(clippedStart.getHours()).padStart(2, '0');
        const mm1 = String(clippedStart.getMinutes()).padStart(2, '0');
        const ss1 = String(clippedStart.getSeconds()).padStart(2, '0');
        const hh2 = String(clippedEnd.getHours()).padStart(2, '0');
        const mm2 = String(clippedEnd.getMinutes()).padStart(2, '0');
        const ss2 = String(clippedEnd.getSeconds()).padStart(2, '0');

        const block = document.createElement('div');
        block.className = 'sa-block';
        block.style.top = `${top}px`;
        block.style.height = `${height}px`;
        block.style.background = toColor(session.status);
        block.setAttribute('data-bs-toggle', 'tooltip');
        block.setAttribute('title', `${toLabel(session.status)} • ${hh1}:${mm1}:${ss1} – ${hh2}:${mm2}:${ss2} • ${duration}`);
        block.innerHTML = `<div class="t1">${toLabel(session.status)}</div><div class="t2">${hh1}:${mm1}:${ss1} – ${hh2}:${mm2}:${ss2} • ${duration}</div>`;
        lane.appendChild(block);
      });

      body.appendChild(hours);
      body.appendChild(lane);
      col.appendChild(body);
      root.appendChild(col);
    }

    return root;
  }

  function buildMonthView(sessions, winFrom) {
    const root = document.createElement('div');
    root.className = 'sa-month';

    const byDay = {};
    sessions.forEach((session) => {
      const key = yyyyMmDd(parseYmdHis(session.start));
      byDay[key] ||= {};
      const sec = Number(session.seconds ?? 0);
      byDay[key][session.status] = (byDay[key][session.status] || 0) + sec;
    });

    const first = new Date(winFrom.getFullYear(), winFrom.getMonth(), 1);
    const last = new Date(winFrom.getFullYear(), winFrom.getMonth() + 1, 0);
    const firstDow = (first.getDay() + 6) % 7;
    const cells = firstDow + last.getDate();
    const totalCells = Math.ceil(cells / 7) * 7;

    for (let c = 0; c < totalCells; c++) {
      const cell = document.createElement('div');
      cell.className = 'sa-month-day';

      const dayNum = c - firstDow + 1;
      if (dayNum < 1 || dayNum > last.getDate()) {
        cell.innerHTML = '<div class="sa-month-num">&nbsp;</div>';
        root.appendChild(cell);
        continue;
      }

      const date = new Date(winFrom.getFullYear(), winFrom.getMonth(), dayNum);
      const key = yyyyMmDd(date);
      const totals = byDay[key] || {};
      const totalSec = Object.values(totals).reduce((sum, value) => sum + value, 0);

      let mini = '<div class="sa-mini">';
      if (totalSec > 0) {
        Object.keys(totals).forEach((status) => {
          const width = Math.round((totals[status] / totalSec) * 100);
          if (width > 0) {
            mini += `<span style="width:${width}%;background:${toColor(status)}"></span>`;
          }
        });
      } else {
        mini += '<span style="width:100%;background:rgba(15,23,42,.06)"></span>';
      }
      mini += '</div>';

      cell.innerHTML = `<div class="sa-month-num">${dayNum}</div>${mini}`;
      root.appendChild(cell);
    }

    return root;
  }

  function renderTimeline(member, range) {
    const card = document.createElement('div');
    card.className = 'sa-timeline-card';
    card.innerHTML = `
      <div class="sa-timeline-head">
        <strong>Timeline</strong>
        <span class="sa-timeline-meta">Window (${member.display_tz}): ${member.from} → ${member.to}</span>
      </div>
    `;

    const body = document.createElement('div');
    body.className = 'sa-timeline-scroll';
    card.appendChild(body);

    const sessions = member.sessions || [];
    if (!sessions.length) {
      body.innerHTML = '<div class="sa-empty">No sessions in this window.</div>';
      return card;
    }

    const winFrom = parseYmdHis(String(member.from).slice(0, 19));
    const winTo = parseYmdHis(String(member.to).slice(0, 19));

    if (range === 'daily' || range === 'custom') {
      body.appendChild(buildDayView(sessions, winFrom, winTo, 60));
    } else if (range === 'weekly') {
      body.appendChild(buildWeekView(sessions, winFrom, winTo));
    } else if (range === 'monthly') {
      body.appendChild(buildMonthView(sessions, winFrom));
    } else {
      body.innerHTML = '<div class="sa-empty">For yearly and lifetime range, KPIs act as the summary. Switch to day, week or month for timeline bars.</div>';
    }

    return card;
  }

  function renderMemberCard(member, range) {
    const card = document.createElement('div');
    card.className = 'sa-member-card';

    const teamBadge = member.team ? `<span class="sa-badge sa-badge-light">Team: ${member.team.name}</span>` : '';

    card.innerHTML = `
      <div class="sa-member-head">
        <div>
          <div class="sa-member-name">${member.user.name}</div>
          <div class="sa-member-sub">
            <span>${member.user.email}</span>
            <span>•</span>
            <span>${member.user.timezone}</span>
            <span>•</span>
            <span>${member.user.role}</span>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          ${teamBadge}
          <span class="sa-badge">${member.shift.label}</span>
        </div>
      </div>
    `;

    const body = document.createElement('div');
    body.className = 'sa-member-body';
    body.appendChild(renderKpis(member.totals || {}));
    body.appendChild(renderTimeline(member, range));
    card.appendChild(body);
    return card;
  }

  function renderMembers(payload) {
    resultsWrapEl.innerHTML = '';
    const members = payload.members || [];

    if (!members.length) {
      resultsSummaryEl.textContent = 'No members available for the current selection.';
      resultsWrapEl.innerHTML = '<div class="sa-empty sa-member-card">No analytics data available.</div>';
      return;
    }

    if (payload.mode === 'team' && payload.selected_team) {
      resultsSummaryEl.textContent = `Showing ${members.length} member analytics blocks for ${payload.selected_team.name}.`;
    } else {
      resultsSummaryEl.textContent = `Showing ${members.length} selected individual analytics block.`;
    }

    members.forEach((member) => {
      const card = renderMemberCard(member, payload.range);
      resultsWrapEl.appendChild(card);
      initTooltips(card);
    });
  }

  async function loadData() {
    const range = rangeEl.value;
    const mode = modeEl.value;
    const scope = scopeEl.value;
    const anchor = anchorEl.value || yyyyMmDd(new Date());

    const params = {range, mode, scope};
    if (range !== 'lifetime') params.anchor = anchor;
    if (mode === 'individual') params.user_id = currentUserId;
    if (mode === 'team') params.team_id = currentTeamId;
    if (range === 'custom') {
      params.from = customFromEl.value || anchor;
      params.to = customToEl.value || anchor;
    }

    resultsSummaryEl.textContent = 'Loading...';
    resultsWrapEl.innerHTML = '<div class="sa-empty sa-member-card">Loading analytics...</div>';

    const url = buildUrl(`{{ route('admin.support.status.analytics.manager.data') }}`, params);
    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const json = await response.json();

    if (!json.ok) {
      resultsSummaryEl.textContent = 'Failed to load.';
      resultsWrapEl.innerHTML = `<div class="sa-empty sa-member-card">${json.message || 'Failed to load analytics.'}</div>`;
      return;
    }

    renderMembers(json);
  }

  function reloadFiltersPage() {
    const url = buildUrl(window.location.pathname, {
      scope: scopeEl.value,
      mode: modeEl.value,
      user_id: userSelectEl.value || currentUserId,
      team_id: teamSelectEl.value || currentTeamId,
    });
    window.location.href = url;
  }

  anchorEl.value = yyyyMmDd(new Date());
  syncModeVisibility();
  filterUsers();

  document.getElementById('prevBtn').addEventListener('click', () => shiftAnchor(-1));
  document.getElementById('nextBtn').addEventListener('click', () => shiftAnchor(1));
  document.getElementById('todayBtn').addEventListener('click', () => {
    anchorEl.value = yyyyMmDd(new Date());
    loadData();
  });

  document.getElementById('refreshBtn').addEventListener('click', loadData);

  userSearchEl.addEventListener('input', filterUsers);
  userSelectEl.addEventListener('change', () => {
    currentUserId = Number(userSelectEl.value);
    if (modeEl.value === 'individual') loadData();
  });
  teamSelectEl.addEventListener('change', () => {
    currentTeamId = Number(teamSelectEl.value);
    if (modeEl.value === 'team') loadData();
  });

  scopeEl.addEventListener('change', reloadFiltersPage);
  modeEl.addEventListener('change', () => {
    syncModeVisibility();
    loadData();
  });

  anchorEl.addEventListener('change', loadData);
  rangeEl.addEventListener('change', () => {
    const isCustom = rangeEl.value === 'custom';
    customWrapEl.classList.toggle('sa-hidden', !isCustom);
    if (isCustom) {
      const anchor = anchorEl.value || yyyyMmDd(new Date());
      if (!customFromEl.value) customFromEl.value = anchor;
      if (!customToEl.value) customToEl.value = anchor;
    }
    loadData();
  });
  customFromEl.addEventListener('change', loadData);
  customToEl.addEventListener('change', loadData);

  loadData();
</script>
@endpush
