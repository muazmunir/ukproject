@extends('superadmin.layout')

@push('styles')
<style>
  .sa-wrap{ max-width:1360px; margin:0 auto; }
  .sa-head h4{ font-weight:900; letter-spacing:-.2px; }
  .sa-sub{ font-size:13px; opacity:.8; }

.sa-head{

  
  display: flex;
  justify-content: center;
  flex-direction: column;
  align-items: center;
}
  .sa-card{
    border-radius:18px;
    border:1px solid rgba(0,0,0,.08);
    box-shadow:0 10px 26px rgba(0,0,0,.06);
  }

  .sa-toolbar .form-select,
  .sa-toolbar .form-control{
    border-radius:12px;
  }

  .sa-toolbar .btn{
    border-radius:12px;
    font-weight:800;
  }

  .sa-filter-grid{
    display:grid;
    grid-template-columns: 460px 1fr;
    gap:18px;
    align-items:start;
  }

  @media (max-width: 992px){
    .sa-filter-grid{ grid-template-columns:1fr; }
  }

  .sa-userbox{
    background:linear-gradient(180deg,#fff,#fbfbfb);
    border:1px solid rgba(0,0,0,.08);
    border-radius:14px;
    padding:12px;
  }

  .sa-userbox label,
  .sa-ctrl label{
    font-weight:900;
    font-size:12px;
    margin-bottom:6px;
    display:block;
  }

  .sa-userbox .stack{
    display:grid;
    gap:10px;
  }

  #pickerSearch{
    border-radius:12px;
  }

  #userSelect,
  #teamSelect{
    font-weight:800;
    border-radius:12px;
    height:240px;
    overflow:auto;
  }

  #userSelect option,
  #teamSelect option{
    padding:10px 12px;
  }

  .sa-tip{
    font-size:12px;
    opacity:.75;
    font-weight:800;
    margin-top:8px;
  }

  .sa-controls{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:14px;
  }

  @media (max-width: 992px){
    .sa-controls{ grid-template-columns:1fr; }
  }

  .sa-ctrl{
    background:#fff;
    border:1px solid rgba(0,0,0,.08);
    border-radius:14px;
    padding:12px;
  }

  .sa-inline{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }

  .sa-inline .form-control{ max-width:180px; }

  .btn-refresh{
    background:#000 !important;
    border-color:#000 !important;
    color:#fff !important;
    padding:10px 16px !important;
  }

  .sa-summary{
    display:grid;
    grid-template-columns: 1.4fr .9fr;
    gap:14px;
  }

  @media (max-width: 992px){
    .sa-summary{ grid-template-columns:1fr; }
  }

  .sa-summary-label{
    font-size:12px;
    font-weight:900;
    color:rgba(0,0,0,.55);
    text-transform:capitalize;
    letter-spacing:.3px;
    margin-bottom:4px;
  }

  .sa-summary-title{
    font-size:22px;
    font-weight:900;
    letter-spacing:-.3px;
  }

  .sa-summary-sub{
    font-size:13px;
    color:rgba(0,0,0,.62);
    margin-top:4px;
    font-weight:700;
  }

  .sa-badges{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:10px;
  }

  .sa-soft-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:8px 10px;
    border-radius:999px;
    background:#f3f4f6;
    color:#111827;
    font-size:12px;
    font-weight:800;
    border:1px solid rgba(0,0,0,.06);
  }

  .sa-role-badge{
    text-transform:capitalize;
  }

  .sa-stack{
    display:grid;
    gap:16px;
  }

  .sa-member-card{
    border:1px solid rgba(0,0,0,.08);
    border-radius:18px;
    box-shadow:0 8px 22px rgba(0,0,0,.05);
    overflow:hidden;
    background:#fff;
  }

  .sa-member-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    padding:16px 18px;
    border-bottom:1px solid rgba(0,0,0,.06);
    background:linear-gradient(180deg,#fff,#fcfcfc);
  }

  .sa-member-title{
    font-size:20px;
    font-weight:900;
    letter-spacing:-.2px;
    line-height:1.1;
  }

  .sa-member-meta{
    margin-top:6px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .sa-member-shift{
    color:rgba(0,0,0,.65);
    font-size:13px;
    font-weight:700;
    margin-top:6px;
  }

  .sa-member-body{
    padding:16px 18px 18px;
  }

  .sa-kpi-grid{
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap:12px;
    margin-bottom:16px;
  }

  @media (max-width: 1200px){
    .sa-kpi-grid{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }

  @media (max-width: 768px){
    .sa-kpi-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }

  @media (max-width: 480px){
    .sa-kpi-grid{ grid-template-columns: 1fr; }
  }

  .sa-kpi{
    border:1px solid rgba(0,0,0,.08);
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 6px 18px rgba(0,0,0,.05);
    background:#fff;
  }

  .sa-kpi-top{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    border-bottom:1px solid rgba(255,255,255,.18);
  }

  .sa-dot{
    width:10px;
    height:10px;
    border-radius:999px;
    box-shadow:0 0 0 3px rgba(255,255,255,.25);
    background:#fff;
  }

  .sa-kpi-title{
    font-size:12px;
    font-weight:900;
    letter-spacing:.4px;
    text-transform:capitalize;
    color:rgba(255,255,255,.95);
  }

  .sa-kpi-val{
    padding:10px 12px;
    background:#fff;
    display:flex;
    align-items:baseline;
    justify-content:space-between;
    gap:10px;
  }

  .sa-hours{
    font-weight:900;
    font-size:18px;
    letter-spacing:-.2px;
    white-space:nowrap;
  }

  .sa-suffix{
    font-size:12px;
    opacity:.7;
    font-weight:800;
  }

  .sa-section-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:8px;
  }

  .sa-section-title{
    font-size:13px;
    font-weight:900;
    color:rgba(0,0,0,.72);
    text-transform:capitalize;
    letter-spacing:.35px;
  }

  .sa-section-meta{
    font-size:12px;
    color:rgba(0,0,0,.55);
    font-weight:800;
  }

  .sa-timeline{
    background:#fff;
    border:1px solid rgba(0,0,0,.08);
    border-radius:16px;
    box-shadow:0 10px 26px rgba(0,0,0,.04);
    overflow:hidden;
  }

  .sa-timeline-inner{
    height:720px;
    overflow:auto;
    background:#f3f4f6;
  }

  .sa-day{
    display:grid;
    grid-template-columns:70px 1fr;
    min-height:720px;
  }

  .sa-hours-col{
    background:linear-gradient(180deg,#fff,#fbfbfb);
    border-right:1px solid rgba(0,0,0,.06);
  }

  .sa-hour{
    height:60px;
    font-size:12px;
    color:rgba(0,0,0,.55);
    padding:6px 10px;
    border-bottom:1px dashed rgba(0,0,0,.06);
  }

  .sa-lane{
    position:relative;
    background:#f3f4f6;
  }

  .sa-gridline{
    position:absolute;
    left:0;
    right:0;
    height:1px;
    border-top:1px dashed rgba(0,0,0,.07);
    pointer-events:none;
  }

  .sa-block{
    position:absolute;
    left:0 !important;
    right:0 !important;
    border-radius:0 !important;
    padding:10px 12px;
    color:#fff;
    box-shadow:0 10px 18px rgba(0,0,0,.08);
    overflow:hidden;
    cursor:help;
  }

  .sa-block .t1{
    font-weight:900;
    letter-spacing:-.2px;
    line-height:1.1;
  }

  .sa-block .t2{
    font-size:12px;
    opacity:.92;
    font-weight:800;
    margin-top:4px;
  }

  .sa-week{
    display:grid;
    grid-template-columns:repeat(7, 1fr);
    gap:0;
  }

  .sa-week-col{
    border-right:1px solid rgba(0,0,0,.06);
  }

  .sa-week-col:last-child{ border-right:0; }

  .sa-week-head{
    padding:10px 12px;
    font-weight:900;
    font-size:13px;
    background:linear-gradient(180deg,#fff,#fbfbfb);
    border-bottom:1px solid rgba(0,0,0,.06);
  }

  .sa-week-daybody{
    display:grid;
    grid-template-columns:48px 1fr;
    min-height:520px;
  }

  .sa-week .sa-hour{ height:44px; }

  .sa-week .sa-block{
    left:8px !important;
    right:8px !important;
    border-radius:10px !important;
    padding:8px 10px;
  }

  .sa-week .sa-block .t1{ font-size:12px; }
  .sa-week .sa-block .t2{ font-size:11px; }

  .sa-month{
    display:grid;
    grid-template-columns:repeat(7, 1fr);
    border-top:1px solid rgba(0,0,0,.06);
    background:#fff;
  }

  .sa-month .day{
    border-right:1px solid rgba(0,0,0,.06);
    border-bottom:1px solid rgba(0,0,0,.06);
    min-height:92px;
    padding:8px;
  }

  .sa-month .day:nth-child(7n){ border-right:0; }

  .sa-month .dnum{
    font-weight:900;
    font-size:12px;
    opacity:.75;
  }

  .sa-mini{
    margin-top:6px;
    height:10px;
    border-radius:999px;
    background:rgba(0,0,0,.06);
    overflow:hidden;
    display:flex;
  }
  .status-container{
    background: white;
    border: 1px solid rgba(0, 0, 0, .12);
    border-radius: 18px;
    box-shadow: 0 18px 45px rgba(0, 0, 0, .08);
    padding: 18px 20px 22px;
  }

  .sa-mini > span{
    height:100%;
    display:block;
  }

  .sa-empty{
    padding:18px;
    font-size:13px;
    color:rgba(0,0,0,.6);
    font-weight:700;
    background:#fff;
    border:1px dashed rgba(0,0,0,.12);
    border-radius:14px;
  }
  

  .d-none-force{ display:none !important; }
</style>
@endpush

@section('content')
<div class="status-container  py-3 sa-wrap">

  <div class="sa-head mb-3">
    <h4 class="mb-1 text-center">Status Analytics</h4>
    <div class="sa-sub text-muted text-capitalize text-center">
      Full visibility across managers, admins, and team-level analytics
    </div>
  </div>

  <div class="card sa-card mb-3">
    <div class="card-body sa-toolbar">
      <div class="sa-filter-grid">

        <div class="sa-userbox">
          <div class="stack">
            <div>
              <label>View Mode</label>
              <select id="mode" class="form-select">
                <option value="individual" @selected(($mode ?? 'individual') === 'individual')>Individual</option>
                <option value="team" @selected(($mode ?? 'individual') === 'team')>Team</option>
              </select>
            </div>

            <div>
              <label>Search</label>
              <input type="text"
                     id="pickerSearch"
                     class="form-control"
                     placeholder="Search By Name, Email, Role, Team Or Timezone..."
                     autocomplete="off">
            </div>

            <div id="userPickerWrap">
              <label>Select User</label>
              <select id="userSelect" class="form-select" size="10">
                @foreach($people as $p)
                  @php
                    $name = trim(($p->first_name.' '.$p->last_name)) ?: $p->email;
                    $tz   = $p->timezone ?: 'UTC';
                    $role = strtolower((string) $p->role);
                    $text = "{$name} — ".ucfirst($role)." — {$tz}";
                    $search = strtolower($name.' '.$p->email.' '.$role.' '.$tz);
                  @endphp
                  <option value="{{ $p->id }}"
                          data-search="{{ $search }}"
                          data-role="{{ $role }}"
                          @selected(isset($target) && $target && (int)$target->id === (int)$p->id)>
                    {{ $text }}
                  </option>
                @endforeach
              </select>
              <div class="sa-tip text-muted text-capitalize">Tip: type to filter, then pick a user.</div>
            </div>

            <div id="teamPickerWrap" class="d-none-force">
              <label>Select Team</label>
              <select id="teamSelect" class="form-select" size="10">
                @foreach(($teams ?? []) as $team)
                  @php
                    $managerName = trim((($team->manager_first_name ?? '').' '.($team->manager_last_name ?? '')));
                    if ($managerName === '') $managerName = (string)($team->manager_email ?? 'No Manager');
                    $teamText = ($team->name ?? 'Unnamed Team').' — '.$managerName;
                    $teamSearch = strtolower(($team->name ?? '').' '.$managerName.' '.($team->manager_email ?? ''));
                  @endphp
                  <option value="{{ $team->id }}"
                          data-search="{{ $teamSearch }}"
                          @selected(isset($selectedTeam) && $selectedTeam && (int)$selectedTeam->id === (int)$team->id)>
                    {{ $teamText }}
                  </option>
                @endforeach
              </select>
              <div class="sa-tip text-muted text-capitalize">Tip: choose a team to render every member one by one.</div>
            </div>
          </div>
        </div>

        <div class="sa-controls">
          <div class="sa-ctrl">
            <label>Set Date</label>
            <div class="sa-inline">
              <button type="button" class="btn btn-outline-dark" id="prevBtn" title="Previous">
                <i class="bi bi-chevron-left"></i>
              </button>
              <button type="button" class="btn btn-outline-dark" id="nextBtn" title="Next">
                <i class="bi bi-chevron-right"></i>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="todayBtn">Today</button>
            </div>
          </div>

          <div class="sa-ctrl">
            <label>Date</label>
            <div class="sa-inline">
              <input type="date" id="anchorDate" class="form-control">
            </div>
          </div>

          <div class="sa-ctrl">
            <label>Range</label>
            <div class="sa-inline">
              <select id="range" class="form-select" style="min-width:200px;">
                <option value="daily">Day</option>
                <option value="weekly" selected>Week</option>
                <option value="monthly">Month</option>
                <option value="yearly">Year</option>
                <option value="lifetime">Lifetime</option>
                <option value="custom">Custom</option>
              </select>
            </div>
          </div>

          <div class="sa-ctrl">
            <label>Actions</label>
            <div class="sa-inline">
              <button id="refresh" class="btn btn-refresh">Refresh</button>
            </div>
          </div>

          <div class="sa-ctrl" id="customWrap" style="display:none;">
            <label>Custom Range</label>
            <div class="sa-inline">
              <input type="date" id="customFrom" class="form-control">
              <input type="date" id="customTo" class="form-control">
            </div>
          </div>

          <input type="hidden" id="tz_mode" value="target">
        </div>

      </div>
    </div>
  </div>

  <div class="sa-summary mb-3">
    <div class="card sa-card">
      <div class="card-body">
        <div class="sa-summary-label">Selection</div>
        <div class="sa-summary-title text-capitalize" id="summaryTitle">—</div>
        <div class="sa-summary-sub text-capitalize" id="summarySubtitle">—</div>
        <div class="sa-badges" id="summaryBadges"></div>
      </div>
    </div>

    <div class="card sa-card">
      <div class="card-body">
        <div class="sa-summary-label">Window</div>
        <div class="sa-summary-title text-capitalize" id="windowTitle">—</div>
        <div class="sa-summary-sub" id="windowSubtitle">—</div>
      </div>
    </div>
  </div>

  <div id="analyticsRoot" class="sa-stack"></div>
</div>
@endsection

@push('scripts')
<script>
  const modeEl        = document.getElementById('mode');
  const pickerSearch  = document.getElementById('pickerSearch');
  const userWrap      = document.getElementById('userPickerWrap');
  const teamWrap      = document.getElementById('teamPickerWrap');
  const userSel       = document.getElementById('userSelect');
  const teamSel       = document.getElementById('teamSelect');

  const anchorEl      = document.getElementById('anchorDate');
  const rangeEl       = document.getElementById('range');
  const customWrap    = document.getElementById('customWrap');
  const customFrom    = document.getElementById('customFrom');
  const customTo      = document.getElementById('customTo');

  const summaryTitle    = document.getElementById('summaryTitle');
  const summarySubtitle = document.getElementById('summarySubtitle');
  const summaryBadges   = document.getElementById('summaryBadges');
  const windowTitle     = document.getElementById('windowTitle');
  const windowSubtitle  = document.getElementById('windowSubtitle');
  const analyticsRoot   = document.getElementById('analyticsRoot');

  let currentMode   = `{{ ($mode ?? 'individual') }}` || 'individual';
  let currentUserId = Number(`{{ isset($target) && $target ? (int)$target->id : 0 }}`) || 0;
  let currentTeamId = Number(`{{ isset($selectedTeam) && $selectedTeam ? (int)$selectedTeam->id : 0 }}`) || 0;

  const STATUS_THEME = {
    available:            { label: 'Available',            color: '#22c55e' },
    break:                { label: 'Break',                color: '#facc15' },
    meeting:              { label: 'Meeting',              color: '#a855f7' },
    admin:                { label: 'Admin',                color: '#3b82f6' },
    tech_issues:          { label: 'Tech',                 color: '#92400e' },
    holiday:              { label: 'Holiday',              color: '#ec4899' },
    authorized_absence:   { label: 'Authorised Absence',   color: '#f59e0b' },
    unauthorized_absence: { label: 'Unauthorised Absence', color: '#ef4444' },
   
  };

  const KPI_ORDER = [
    'available','break','meeting','admin','tech_issues',
    'holiday','authorized_absence','unauthorized_absence',
   
  ];

  function prettyKey(k){
    return (STATUS_THEME[k]?.label) || k.replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  function toColor(status){
    return (STATUS_THEME[status]?.color) || '#9ca3af';
  }

  function toLabel(status){
    return (STATUS_THEME[status]?.label) || prettyKey(status);
  }

function sanitizeTotals(totals){
  const source = totals && typeof totals === 'object' ? totals : {};
  return Object.fromEntries(
    Object.entries(source).filter(([key]) => {
      return key !== 'offline' && key !== 'non_working_hours';
    })
  );
}

  function sanitizeSessions(sessions){
  return (Array.isArray(sessions) ? sessions : []).filter(session => {
    const status = String(session?.status || '').toLowerCase();
    return status !== 'offline' && status !== 'non_working_hours';
  });
}

  function yyyy_mm_dd(d){
    const x = new Date(d);
    const y = x.getFullYear();
    const m = String(x.getMonth() + 1).padStart(2,'0');
    const da = String(x.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
  }

  function parseYmdHis(s){
    return new Date(String(s).replace(' ', 'T'));
  }

  function clampDate(d, min, max){
    return new Date(Math.min(max.getTime(), Math.max(min.getTime(), d.getTime())));
  }

  function dayKey(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const da = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
  }

  function startOfDay(d){
    const x = new Date(d);
    x.setHours(0,0,0,0);
    return x;
  }

  function addDaysDate(d, n){
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
  }

  function addDays(dateStr, n){
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + n);
    return yyyy_mm_dd(d);
  }

  function addMonths(dateStr, n){
    const d = new Date(dateStr + 'T00:00:00');
    d.setMonth(d.getMonth() + n);
    return yyyy_mm_dd(d);
  }

  function addYears(dateStr, n){
    const d = new Date(dateStr + 'T00:00:00');
    d.setFullYear(d.getFullYear() + n);
    return yyyy_mm_dd(d);
  }

  function stepForRange(range){
    switch(range){
      case 'daily':   return { type:'day', n:1 };
      case 'weekly':  return { type:'day', n:7 };
      case 'monthly': return { type:'month', n:1 };
      case 'yearly':  return { type:'year', n:1 };
      default:        return { type:'day', n:0 };
    }
  }

  function shiftAnchor(dir){
    const r = rangeEl.value;
    if (r === 'lifetime' || r === 'custom') return;

    const step = stepForRange(r);
    if (!step.n) return;

    const cur = anchorEl.value || yyyy_mm_dd(new Date());
    let next = cur;

    if (step.type === 'day')   next = addDays(cur, dir * step.n);
    if (step.type === 'month') next = addMonths(cur, dir * step.n);
    if (step.type === 'year')  next = addYears(cur, dir * step.n);

    anchorEl.value = next;
    loadData();
  }

  function durationTextFromSeconds(totalSec){
    const s = Math.max(0, Math.floor(Number(totalSec || 0)));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const r = s % 60;

    const parts = [];
    if (h > 0) parts.push(`${h}h`);
    if (m > 0 || h > 0) parts.push(`${m}m`);
    parts.push(`${r}s`);
    return parts.join(' ');
  }

  function hmsFromSeconds(totalSec){
    const s = Math.max(0, Math.floor(Number(totalSec || 0)));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const r = s % 60;
    return `${h}h ${String(m).padStart(2,'0')}m ${String(r).padStart(2,'0')}s`;
  }

  function kpiValueText(hoursValue, secondsValue){
    if (secondsValue !== undefined && secondsValue !== null) {
      return hmsFromSeconds(secondsValue);
    }
    const hours = Number(hoursValue || 0);
    return hmsFromSeconds(Math.round(hours * 3600));
  }

  function buildUrl(base, params){
    const u = new URL(base, window.location.origin);
    Object.keys(params).forEach(k => {
      if (params[k] !== undefined && params[k] !== null && params[k] !== '') {
        u.searchParams.set(k, params[k]);
      }
    });
    return u.toString();
  }

  function setModeUI(){
    currentMode = modeEl.value || 'individual';
    userWrap.classList.toggle('d-none-force', currentMode !== 'individual');
    teamWrap.classList.toggle('d-none-force', currentMode !== 'team');
    filterPickerOptions();
  }

  function filterPickerOptions(){
    const q = (pickerSearch.value || '').trim().toLowerCase();

    const select = currentMode === 'team' ? teamSel : userSel;
    if (!select) return;

    let firstVisible = null;

    Array.from(select.options).forEach(opt => {
      const hay = (opt.getAttribute('data-search') || opt.textContent || '').toLowerCase();
      const visible = !q || hay.includes(q);
      opt.hidden = !visible;
      if (visible && !firstVisible) firstVisible = opt;
    });

    const selected = select.options[select.selectedIndex];
    if (selected && selected.hidden && firstVisible) {
      select.value = firstVisible.value;
      if (currentMode === 'team') currentTeamId = Number(firstVisible.value);
      else currentUserId = Number(firstVisible.value);
    }
  }

  function makeBadge(text, extraClass = ''){
    const span = document.createElement('span');
    span.className = `sa-soft-badge ${extraClass}`.trim();
    span.textContent = text;
    return span;
  }

  function setWindowSummary(json){
    windowTitle.textContent = `${(json.range || '')} Window`;
    if (json.custom && (json.custom.from || json.custom.to)) {
      windowSubtitle.textContent = `From ${json.custom.from || '—'} to ${json.custom.to || '—'}`;
      return;
    }
    const from = json.from || '—';
    const to = json.to || '—';
    windowSubtitle.textContent = `${from} → ${to}`;
  }

  function setIndividualSummary(json){
    summaryTitle.textContent = json.target_name || '—';
    summarySubtitle.textContent = `Individual analytics in ${json.display_tz || 'UTC'}`;

    summaryBadges.innerHTML = '';
    summaryBadges.appendChild(makeBadge(`Role: ${(json.target_role || 'user')}`,'sa-role-badge'));
    summaryBadges.appendChild(makeBadge(`TZ: ${json.display_tz || 'UTC'}`));
    summaryBadges.appendChild(makeBadge((json.shift && json.shift.label) ? json.shift.label : 'Shift Disabled'));
  }

  function setTeamSummary(json){
    summaryTitle.textContent = json.team_name || 'Selected Team';
    summarySubtitle.textContent = `Rendering ${json.member_count || 0} member(s) one by one`;

    summaryBadges.innerHTML = '';
    if (json.team_manager) summaryBadges.appendChild(makeBadge(`Manager: ${json.team_manager}`));
    summaryBadges.appendChild(makeBadge(`Members: ${json.member_count || 0}`));
    if (Array.isArray(json.members) && json.members.length > 0) {
      const firstTz = json.members[0]?.display_tz || 'Mixed';
      summaryBadges.appendChild(makeBadge(`Primary TZ: ${firstTz}`));
    }
  }

  function renderKpiGrid(hoursTotals = {}, secondsTotals = {}){
    const wrap = document.createElement('div');
    wrap.className = 'sa-kpi-grid';

    KPI_ORDER.forEach(k => {
      const theme = STATUS_THEME[k] || { color:'#9ca3af', label: prettyKey(k) };
      const hoursVal = hoursTotals && hoursTotals[k] !== undefined ? hoursTotals[k] : 0;
      const secondsVal = secondsTotals && secondsTotals[k] !== undefined ? secondsTotals[k] : null;

      const card = document.createElement('div');
      card.className = 'sa-kpi';
      card.innerHTML = `
        <div class="sa-kpi-top" style="background:${theme.color};">
          <span class="sa-dot"></span>
          <div class="sa-kpi-title">${theme.label.toUpperCase()}</div>
        </div>
        <div class="sa-kpi-val">
          <div class="sa-hours">${kpiValueText(hoursVal, secondsVal)}</div>
          <div class="sa-suffix">time</div>
        </div>
      `;
      wrap.appendChild(card);
    });

    return wrap;
  }

  function initTooltips(scope = document){
    try {
      if (window.bootstrap && bootstrap.Tooltip) {
        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
          const inst = bootstrap.Tooltip.getInstance(el);
          if (inst) inst.dispose();
          new bootstrap.Tooltip(el);
        });
      }
    } catch (e) {}
  }

  function renderTimelineInto(container, range, sessions, fromStr, toStr, tz){
    container.innerHTML = '';

    const metaRow = document.createElement('div');
    metaRow.className = 'sa-section-row';
    metaRow.innerHTML = `
      <div class="sa-section-title">Timeline</div>
      <div class="sa-section-meta">Window (${tz || 'UTC'}): ${fromStr || '—'} → ${toStr || '—'}</div>
    `;
    container.appendChild(metaRow);

    const shell = document.createElement('div');
    shell.className = 'sa-timeline';

    const inner = document.createElement('div');
    inner.className = 'sa-timeline-inner';
    shell.appendChild(inner);
    container.appendChild(shell);

    const cleanSessions = sanitizeSessions(sessions);
    if (!cleanSessions.length) {
      inner.innerHTML = `<div class="sa-empty text-capitalize">No sessions in this window.</div>`;
      return;
    }

    const winFrom = parseYmdHis(String(fromStr).slice(0,19));
    const winTo   = parseYmdHis(String(toStr).slice(0,19));

    if (range === 'daily' || range === 'custom') {
     inner.appendChild(buildDayView(cleanSessions, winFrom, winTo, 60));
      initTooltips(inner);
      return;
    }

    if (range === 'weekly') {
     inner.appendChild(buildWeekView(cleanSessions, winFrom, winTo, 60));
      initTooltips(inner);
      return;
    }

    if (range === 'monthly') {
     inner.appendChild(buildMonthView(cleanSessions, winFrom, winTo));
      return;
    }

    inner.innerHTML = `
      <div class="sa-empty">
        For ${(range || '').toUpperCase()} view, KPIs are the summary. Switch to Day, Week or Month to see timeline bars.
      </div>
    `;
  }

  function buildDayView(sessions, winFrom, winTo, pxPerHour = 60){
    const day = document.createElement('div');
    day.className = 'sa-day';

    const hours = document.createElement('div');
    hours.className = 'sa-hours-col';

    const lane = document.createElement('div');
    lane.className = 'sa-lane';

    for(let h = 0; h < 24; h++){
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

      const secsFromMidnight =
        (cStart.getHours() * 3600) +
        (cStart.getMinutes() * 60) +
        cStart.getSeconds();

      const lenSec = Math.max(0, Math.round((cEnd - cStart) / 1000));
      const topPx  = (secsFromMidnight / 3600) * pxPerHour;
      const hPx    = Math.max(8, (lenSec / 3600) * pxPerHour);
      const dur    = durationTextFromSeconds(s.seconds ?? lenSec);

      const b = document.createElement('div');
      b.className = 'sa-block';
      b.style.top = topPx + 'px';
      b.style.height = hPx + 'px';
      b.style.background = toColor(s.status);

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

    for(let i = 0; i < 7; i++){
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

      for(let h = 0; h < 24; h++){
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
          (cStart.getHours() * 3600) +
          (cStart.getMinutes() * 60) +
          cStart.getSeconds();

        const lenSec = Math.max(0, Math.round((cEnd - cStart) / 1000));
        const topPx  = (secsFromMidnight / 3600) * pxPerHour;
        const hPx    = Math.max(6, (lenSec / 3600) * pxPerHour);

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
      const sec = Number(s.seconds ?? (s.minutes ? s.minutes * 60 : 0));
      byDay[d][s.status] = (byDay[d][s.status] || 0) + sec;
    });

    const first = new Date(winFrom.getFullYear(), winFrom.getMonth(), 1);
    const last  = new Date(winFrom.getFullYear(), winFrom.getMonth() + 1, 0);

    const firstDow = (first.getDay() + 6) % 7;
    const cells = firstDow + last.getDate();
    const totalCells = Math.ceil(cells / 7) * 7;

    for(let c = 0; c < totalCells; c++){
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
      const totalSec = Object.values(totals).reduce((a,b) => a + b, 0);

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

  function createMemberCard(member, range){
    const card = document.createElement('div');
    card.className = 'sa-member-card';

    const role = (member.target_role || 'user').toLowerCase();
    const shiftLabel = member.shift && member.shift.label ? member.shift.label : 'Shift Disabled';

    card.innerHTML = `
      <div class="sa-member-head">
        <div>
          <div class="sa-member-title">${member.target_name || 'Unnamed User'}</div>
          <div class="sa-member-meta">
            <span class="sa-soft-badge sa-role-badge">Role: ${role}</span>
            <span class="sa-soft-badge">TZ: ${member.display_tz || 'UTC'}</span>
          </div>
          <div class="sa-member-shift">${shiftLabel}</div>
        </div>
        <div class="sa-soft-badge">User ID: ${member.user_id ?? '—'}</div>
      </div>
      <div class="sa-member-body">
        <div class="sa-section-row">
          <div class="sa-section-title">KPIs</div>
          <div class="sa-section-meta">${(member.range || range || '').toUpperCase()}</div>
        </div>
      </div>
    `;

    const body = card.querySelector('.sa-member-body');
    body.appendChild(
  renderKpiGrid(
    sanitizeTotals(member.totals || {}),
    sanitizeTotals(member.totals_seconds || {})
  )
);

    const timelineHolder = document.createElement('div');
    renderTimelineInto(
      timelineHolder,
      member.range || range,
      member.sessions || [],
      member.from,
      member.to,
      member.display_tz
    );
    body.appendChild(timelineHolder);

    return card;
  }

  function renderIndividualPayload(json){
    analyticsRoot.innerHTML = '';
    setIndividualSummary(json);
    setWindowSummary(json);
    analyticsRoot.appendChild(createMemberCard(json, json.range));
  }

  function renderTeamPayload(json){
    analyticsRoot.innerHTML = '';
    setTeamSummary(json);

    const firstMember = Array.isArray(json.members) && json.members.length ? json.members[0] : null;
    setWindowSummary(firstMember || json);

    const teamSummaryCard = document.createElement('div');
    teamSummaryCard.className = 'card sa-card';
    teamSummaryCard.innerHTML = `
      <div class="card-body">
        <div class="sa-section-row">
          <div class="sa-section-title">Team Summary KPIs</div>
          <div class="sa-section-meta">${(json.range || '').toUpperCase()}</div>
        </div>
      </div>
    `;
   teamSummaryCard.querySelector('.card-body').appendChild(
  renderKpiGrid(
    sanitizeTotals(json.totals || {}),
    sanitizeTotals(json.totals_seconds || {})
  )
);
    analyticsRoot.appendChild(teamSummaryCard);

    if (!Array.isArray(json.members) || !json.members.length) {
      const empty = document.createElement('div');
      empty.className = 'sa-empty';
      empty.textContent = 'No active members found for this team in the selected window.';
      analyticsRoot.appendChild(empty);
      return;
    }

    json.members.forEach(member => {
      analyticsRoot.appendChild(createMemberCard(member, json.range));
    });
  }

  async function loadData(){
    const range = rangeEl.value;
    const anchor = anchorEl.value || yyyy_mm_dd(new Date());
    currentMode = modeEl.value || 'individual';

    const params = {
      mode: currentMode,
      range,
      tz_mode: 'target'
    };

    if (range !== 'lifetime') params.anchor = anchor;

    if (range === 'custom') {
      params.from = customFrom.value || anchor;
      params.to   = customTo.value || anchor;
    }

    if (currentMode === 'team') {
      params.team_id = currentTeamId || Number(teamSel?.value || 0);
    } else {
      params.user_id = currentUserId || Number(userSel?.value || 0);
    }

    const url = buildUrl(`{{ route('superadmin.support.status_analytics.data') }}`, params);

    const res = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const json = await res.json();

    if (!json.ok) {
      alert(json.message || 'Failed');
      return;
    }

    if (json.mode === 'team') renderTeamPayload(json);
    else renderIndividualPayload(json);

    initTooltips(document);
  }

  anchorEl.value = yyyy_mm_dd(new Date());
  modeEl.value = currentMode;

  document.getElementById('prevBtn').addEventListener('click', () => shiftAnchor(-1));
  document.getElementById('nextBtn').addEventListener('click', () => shiftAnchor(1));
  document.getElementById('todayBtn').addEventListener('click', () => {
    anchorEl.value = yyyy_mm_dd(new Date());
    loadData();
  });

  anchorEl.addEventListener('change', loadData);

  rangeEl.addEventListener('change', () => {
    const isCustom = rangeEl.value === 'custom';
    customWrap.style.display = isCustom ? 'block' : 'none';

    if (isCustom) {
      const a = anchorEl.value || yyyy_mm_dd(new Date());
      if (!customFrom.value) customFrom.value = a;
      if (!customTo.value) customTo.value = a;
    }

    loadData();
  });

  customFrom.addEventListener('change', loadData);
  customTo.addEventListener('change', loadData);

  document.getElementById('refresh').addEventListener('click', loadData);

  modeEl.addEventListener('change', () => {
    setModeUI();
    loadData();
  });

  pickerSearch.addEventListener('input', filterPickerOptions);

  if (userSel) {
    userSel.addEventListener('change', () => {
      currentUserId = Number(userSel.value || 0);
      if (currentMode === 'individual') loadData();
    });
  }

  if (teamSel) {
    teamSel.addEventListener('change', () => {
      currentTeamId = Number(teamSel.value || 0);
      if (currentMode === 'team') loadData();
    });
  }

  setModeUI();
  filterPickerOptions();
  loadData();
</script>
@endpush