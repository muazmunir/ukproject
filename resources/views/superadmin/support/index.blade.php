@extends('superadmin.layout')
@section('title','Support · Inbox')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support.css') }}">

<style>
  .inbox-toolbar{
    display:flex; gap:10px; align-items:flex-start; justify-content:space-between;
    flex-wrap:wrap; margin:14px 0 18px;
  }
  .inbox-filters{
    display:flex; gap:10px; flex-wrap:wrap; align-items:center; flex:1 1 auto;
  }
  .inbox-filters .f{
    display:flex; align-items:center; gap:8px; padding:10px 12px;
    border:1px solid rgba(0,0,0,.12); border-radius:14px; background:#fff;
  }
  .inbox-filters input, .inbox-filters select{
    border:0; outline:0; background:transparent; font-size:14px;
  }

  /* RIGHT actions always at the end */
  .inbox-actions{
    display:flex; gap:10px; align-items:center; flex:0 0 auto;
    margin-left:auto;
  }
  .pill{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px; border-radius:999px; font-size:12px;
    border:1px solid rgba(0,0,0,.12); background:#fff;
    cursor:pointer;
  }
  .pill--solid{background:#000;color:#fff;border-color:#000;}
  .pill:disabled{opacity:.55; pointer-events:none;}

  .unread-badge{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:20px; height:20px; padding:0 6px;
    border-radius:999px; background:#111; color:#fff;
    font-size:12px; font-weight:700; line-height:1;
  }

  /* SLA badges */
  .badge--sla-ok{ background:#dcfce7; color:#166534; border:1px solid #86efac; }
  .badge--sla-warn{ background:#ffedd5; color:#9a3412; border:1px solid #fdba74; }
  .badge--sla-bad{ background:#fee2e2; color:#7f1d1d; border:1px solid #fca5a5; }
  .badge--sla-dead{ background:#e2e8f0; color:#334155; border:1px solid #cbd5e1; }

  /* Escalation badges */
  .badge--manager-requested{ background:#ffedd5; color:#9a3412; border:1px solid #fdba74; }
  .badge--manager-assigned{ background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
  .badge--manager-active{ background:#fecaca; color:#7f1d1d; border:1px solid #fca5a5; }
  .badge--manager-ended{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }

  .thread-cell{display:flex; align-items:center; gap:10px;}
  .thread-icons{display:flex; gap:8px; color:rgba(0,0,0,.62);}

  .sa-stats{
    display:grid;grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;margin:14px 0 18px;
  }
  @media(max-width:1200px){ .sa-stats{grid-template-columns:repeat(3,minmax(0,1fr));} }
  @media(max-width:640px){ .sa-stats{grid-template-columns:repeat(2,minmax(0,1fr));} }
  .sa-stat{
    border:1px solid rgba(0,0,0,.10); border-radius:14px; padding:12px 12px;
    background:#fff;
  }
  .sa-stat .k{font-size:.78rem;color:rgba(0,0,0,.55);text-transform:capitalize}
  .sa-stat .v{font-size:1.25rem;font-weight:700;margin-top:4px}

  .sa-leader{
    margin-top:16px;border:1px solid rgba(0,0,0,.10);border-radius:16px;background:#fff;
    overflow:hidden;
  }
  .sa-leader-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.08)
  }
  .sa-leader-title{font-weight:700}
  .sa-leader table{width:100%;border-collapse:collapse}
  .sa-leader th,.sa-leader td{padding:12px 14px;text-align:center;border-bottom:1px solid rgba(0,0,0,.06);font-size:.92rem}
  .sa-leader tr:last-child td{border-bottom:none}

  /* Range bar */
  .rangebar{
    display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    margin: 4px 0 10px;
  }
  .rangebar .f{
    display:flex; align-items:center; gap:8px; padding:10px 12px;
    border:1px solid rgba(0,0,0,.12); border-radius:14px; background:#fff;
  }
  .rangebar input, .rangebar select{
    border:0; outline:0; background:transparent; font-size:14px;
  }
</style>
@endpush

@section('content')
@php
  $staff = auth()->user();

  $from = $convs->firstItem() ?? 0;
  $to   = $convs->lastItem() ?? 0;
  $tot  = $convs->total();

  $q        = $q ?? request('q','');
  $status   = $status ?? request('status','open');
  $role     = $role ?? request('role','all');
  $per      = (int)($per ?? request('per',10));
  $assigned = $assigned ?? request('assigned','all');
  $unread   = $unread ?? request('unread','all');

  // leaderboard window
  $range = $range ?? request('range','monthly');

  // picker values
  $day   = request('day');
  $month = request('month');
  $year  = request('year');
  $fromDate = request('from');
  $toDate   = request('to');

  $resetUrl = route('superadmin.support.conversations.index');
@endphp

<section class="support-inbox">
  <div class="support-card">

    <div class="support-card__head">
      <div>
        <h1 class="support-title">Support Inbox</h1>
        <p class="support-subtitle text-capitalize">Admin view — conversations, escalations, and agent performance.</p>
        <p class="support-meta text-capitalize">
          Showing <strong>{{ $from }}</strong>–<strong>{{ $to }}</strong> of <strong>{{ $tot }}</strong> conversations
        </p>
      </div>
    </div>

    {{-- ✅ ONE MAIN FORM (Apply/Reset only here) --}}
    <form method="get" id="filtersForm" class="inbox-toolbar">
      <div class="inbox-filters">
        <div class="f">
          <i class="bi bi-search"></i>
          <input type="text" name="q" value="{{ $q }}" placeholder="Search Username, Email, #ID, Message…">
        </div>

        <div class="f">
          <i class="bi bi-funnel"></i>
          <select name="assigned">
            <option value="all" {{ $assigned==='all'?'selected':'' }}>All Conversations</option>
            <option value="mine" {{ $assigned==='mine'?'selected':'' }}>Assigned To Me</option>
            <option value="unassigned" {{ $assigned==='unassigned'?'selected':'' }}>Unassigned</option>
          </select>
        </div>

        <div class="f">
          <i class="bi bi-envelope"></i>
          <select name="unread">
            <option value="all" {{ $unread==='all'?'selected':'' }}>All</option>
            <option value="unread" {{ $unread==='unread'?'selected':'' }}>Unread only</option>
          </select>
        </div>

        <div class="f">
          <i class="bi bi-person-badge"></i>
          <select name="role">
            <option value="all" {{ $role==='all'?'selected':'' }}>All Threads</option>
            <option value="client" {{ $role==='client'?'selected':'' }}>Client</option>
            <option value="coach" {{ $role==='coach'?'selected':'' }}>Coach</option>
          </select>
        </div>

        <div class="f">
          <i class="bi bi-toggle2-on"></i>
          <select name="status">
            <option value="open" {{ $status==='open'?'selected':'' }}>Open</option>
            <option value="waiting_manager" {{ $status==='waiting_manager'?'selected':'' }}>Waiting Manager</option>
            <option value="closed" {{ $status==='closed'?'selected':'' }}>Closed</option>
            <option value="resolved" {{ $status==='resolved'?'selected':'' }}>Resolved</option>
            <option value="auto_closed" {{ $status==='auto_closed'?'selected':'' }}>Auto Closed</option>
            <option value="all" {{ $status==='all'?'selected':'' }}>All</option>
          </select>
        </div>

        <div class="f">
          <i class="bi bi-list"></i>
          <select name="per">
            @foreach([10,20,50,100] as $n)
              <option value="{{ $n }}" {{ (int)$per===$n?'selected':'' }}>{{ $n }}/Page</option>
            @endforeach
          </select>
        </div>

        {{-- hidden: range + pickers belong to SAME form --}}
        <input type="hidden" name="range" id="rangeHidden" value="{{ $range }}">
        <input type="hidden" name="day"   id="dayHidden"   value="{{ $day }}">
        <input type="hidden" name="month" id="monthHidden" value="{{ $month }}">
        <input type="hidden" name="year"  id="yearHidden"  value="{{ $year }}">
        <input type="hidden" name="from"  id="fromHidden"  value="{{ $fromDate }}">
        <input type="hidden" name="to"    id="toHidden"    value="{{ $toDate }}">
      </div>

      <div class="inbox-actions">
        <button class="pill pill--solid" type="submit">
          <i class="bi bi-arrow-repeat"></i> Apply
        </button>
        <a class="pill" href="{{ $resetUrl }}">
          <i class="bi bi-x-circle"></i> Reset
        </a>
      </div>
    </form>

    {{-- ✅ Range bar (AUTO submit on change, no extra Apply/Reset) --}}
    <div class="rangebar" id="rangeBar">
      <div class="f">
        <i class="bi bi-clock-history"></i>
        <select id="rangeSelect">
          <option value="daily"    @selected($range==='daily')>Day</option>
          <option value="weekly"   @selected($range==='weekly')>This Week</option>
          <option value="monthly"  @selected($range==='monthly')>Month</option>
          <option value="yearly"   @selected($range==='yearly')>Year</option>
          <option value="lifetime" @selected($range==='lifetime')>Lifetime</option>
          <option value="custom"   @selected($range==='custom')>Custom</option>
        </select>
      </div>

      <div class="f" id="rangeDayWrap" style="display:none;">
        <i class="bi bi-calendar3"></i>
        <input type="date" id="rangeDay" value="{{ $day }}">
      </div>

      <div class="f" id="rangeMonthWrap" style="display:none;">
        <i class="bi bi-calendar3"></i>
        <input type="month" id="rangeMonth" value="{{ $month }}">
      </div>

      <div class="f" id="rangeYearWrap" style="display:none;">
        <i class="bi bi-calendar3"></i>
        <input type="number" id="rangeYear" min="2000" max="2100" step="1"
               value="{{ $year }}" placeholder="YYYY" style="width:110px;">
      </div>

      <div class="f" id="rangeCustomWrap" style="display:none;">
        <span class="text-muted">From</span>
        <i class="bi bi-calendar3"></i>
        <input type="date" id="rangeFrom" value="{{ $fromDate }}">
        <span class="text-muted ms-2">To</span>
        <i class="bi bi-calendar3"></i>
        <input type="date" id="rangeTo" value="{{ $toDate }}">
      </div>
    </div>

    {{-- ✅ TOP STATS --}}
    <div class="sa-stats">
      <div class="sa-stat"><div class="k">Open</div><div class="v">{{ $stats['open'] ?? 0 }}</div></div>
      <div class="sa-stat"><div class="k">Closed</div><div class="v">{{ $stats['closed'] ?? 0 }}</div></div>
      <div class="sa-stat"><div class="k">Unassigned (Open)</div><div class="v">{{ $stats['unassigned_open'] ?? 0 }}</div></div>
      <div class="sa-stat"><div class="k">Assigned (Open)</div><div class="v">{{ $stats['assigned_open'] ?? 0 }}</div></div>
      <div class="sa-stat"><div class="k">Manager Waiting</div><div class="v">{{ $stats['manager_waiting'] ?? 0 }}</div></div>
      <div class="sa-stat"><div class="k">Manager Active</div><div class="v">{{ $stats['manager_active'] ?? 0 }}</div></div>
    </div>

    {{-- ✅ TABLE --}}
    <div class="support-table-wrap">
      <table class="support-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Thread</th>
            <th>Status</th>
            <th>Assigned</th>
            <th>Manager</th>
            <th>SLA</th>
            <th>Last Message</th>
            <th>Started</th>
            <th class="support-th-action">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($convs as $c)
          @php
            $sr = $c->scope_role ?? $c->user_type ?? 'client';
            $sr = in_array($sr, ['client','coach'], true) ? $sr : 'client';

            $managerRequested = !empty($c->manager_requested_at) && empty($c->manager_joined_at);
            $managerActive    = !empty($c->manager_joined_at) && empty($c->manager_ended_at);
            $managerEnded     = !empty($c->manager_joined_at) && !empty($c->manager_ended_at);
            $managerAssigned  = $managerRequested && !$managerActive && !$managerEnded && !empty($c->manager_id);

            $unreadCount = (int)($c->unread_count ?? 0);

            $assignedStaffName = $c->assignedStaff
              ? ($c->assignedStaff->username ?? $c->assignedStaff->email)
              : null;
            $assignedStaffRole = $c->assigned_staff_role ?? null;

            $adminM = is_null($c->admin_age_minutes) ? null : (int)$c->admin_age_minutes;
            $mgrM   = is_null($c->manager_age_minutes) ? null : (int)$c->manager_age_minutes;

            $adminSlaClass = null;
            if (!is_null($adminM)) {
              if ($adminM <= 5) $adminSlaClass = 'badge--sla-ok';
              elseif ($adminM == 6) $adminSlaClass = 'badge--sla-warn';
              elseif ($adminM == 7) $adminSlaClass = 'badge--sla-bad';
              else $adminSlaClass = 'badge--sla-dead';
            }

            $mgrSlaClass = null;
            if (!is_null($mgrM)) {
              if ($mgrM <= 5) $mgrSlaClass = 'badge--sla-ok';
              elseif ($mgrM == 6) $mgrSlaClass = 'badge--sla-warn';
              elseif ($mgrM == 7) $mgrSlaClass = 'badge--sla-bad';
              else $mgrSlaClass = 'badge--sla-dead';
            }
          @endphp

          <tr>
            <td>
              <div class="support-user">
                <div class="support-avatar">
                  @php
                    $uname = $c->user?->username
                      ?: (trim(($c->user?->first_name ?? '').' '.($c->user?->last_name ?? '')) ?: ($c->user?->email ?: 'U'));
                  @endphp
                  <span>{{ strtoupper(mb_substr($uname,0,1)) }}</span>
                </div>
                <div class="support-user__meta">
                  <div class="support-user__name">
                    {{ $c->user?->username ?: (trim(($c->user?->first_name ?? '').' '.($c->user?->last_name ?? '')) ?: ($c->user?->email ?: 'Unknown user')) }}
                  </div>
                  <div class="support-user__email">{{ $c->user?->email }}</div>
                  <div class="support-user__id">#{{ $c->id }}</div>
                </div>
              </div>
            </td>

            <td>
              <div class="thread-cell">
                <span class="badge {{ $sr === 'coach' ? 'badge--coach' : 'badge--client' }}">
                  {{ ucfirst($sr) }}
                </span>

                <div class="thread-icons">
                  @if($unreadCount > 0)
                    <span class="unread-badge">{{ $unreadCount }}</span>
                  @endif

                  @if($c->assigned_staff_id)
                    <i class="bi bi-person-check" title="Assigned"></i>
                  @else
                    <i class="bi bi-person-plus" title="Unassigned"></i>
                  @endif

                  @if($managerRequested)
                    <i class="bi bi-exclamation-triangle" title="Manager requested"></i>
                  @elseif($managerActive)
                    <i class="bi bi-shield-check" title="Manager active"></i>
                  @elseif($managerEnded)
                    <i class="bi bi-shield-x" title="Manager ended"></i>
                  @endif
                </div>
              </div>
            </td>

            <td>
              @php
                $st = $c->status;
                $badgeClass =
                  $st === 'open' ? 'badge--success' :
                  ($st === 'waiting_manager' ? 'badge--warning' :
                  ($st === 'resolved' ? 'badge--success' :
                  ($st === 'auto_closed' ? 'badge--danger' : 'badge--muted')));
              @endphp
              <span class="badge {{ $badgeClass }}">
                {{ ucfirst(str_replace('_',' ', $st)) }}
              </span>
            </td>

            <td>
              @if($assignedStaffName)
                <div class="d-flex flex-column align-items-center">
                  <span>{{ $assignedStaffName }}</span>
                  @if($assignedStaffRole)
                    <span class="support-small text-muted text-capitalize">{{ $assignedStaffRole }}</span>
                  @endif
                </div>
              @else
                <span class="badge badge--outline">Unassigned</span>
              @endif
            </td>

            <td>
              @if($managerActive)
                <span class="badge badge--manager-active">Active</span>
                <div class="support-small mt-1">
                  {{ $c->manager?->username ?: ($c->manager?->email ?: '—') }}
                </div>
              @elseif($managerAssigned)
                <span class="badge badge--manager-assigned">Assigned</span>
                <div class="support-small mt-1">
                  {{ $c->manager?->username ?: ($c->manager?->email ?: '—') }}
                </div>
              @elseif($managerRequested)
                <span class="badge badge--manager-requested">Requested</span>
              @elseif($managerEnded)
                <span class="badge badge--manager-ended">Ended</span>
              @else
                <span class="badge badge--outline">—</span>
              @endif
            </td>

            <td class="support-small">
              <div class="d-flex flex-column gap-1">
                @if($adminSlaClass)
                  <span class="badge {{ $adminSlaClass }}" title="Admin timer">
                    A: {{ $adminM }}m
                  </span>
                @else
                  <span class="badge badge--outline">—</span>
                @endif

                @if($mgrSlaClass)
                  <span class="badge {{ $mgrSlaClass }}" title="Manager timer">
                    M: {{ $mgrM }}m
                  </span>
                @endif
              </div>
            </td>

            <td class="support-small">
              {{ $c->last_message_at?->diffForHumans() ?? '—' }}
            </td>

            <td class="support-small">
              {{ $c->created_at->format('d M Y') }}
            </td>

            <td class="support-actions">
              <a href="{{ route('superadmin.support.conversations.show', $c) }}"
                 class="btn-chip btn-chip--info"
                 title="Open Conversation">
                <i class="bi bi-chat-dots"></i>
                @if($unreadCount > 0)
                  <span class="badge-dot">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
              </a>

              @if(!$c->assigned_staff_id && in_array($c->status, ['open','waiting_manager'], true))
                <form method="post"
                      action="{{ route('superadmin.support.conversations.assignMe', $c) }}"
                      class="support-actions__form">
                  @csrf
                  <button type="submit"
                          class="btn-chip btn-chip--success"
                          title="Assign To Me">
                    <i class="bi bi-person-plus"></i>
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="support-empty-row text-capitalize">No Conversations Found.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="support-pager">
      {{ $convs->links() }}
    </div>

    {{-- ✅ LEADERBOARD --}}
    <div class="sa-leader">
      <div class="sa-leader-head">
        <div class="sa-leader-title">Agent Ratings (Top 20)</div>
        <div class="support-small text-capitalize">Based on user-submitted support ratings</div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Agent</th>
            <th>Role</th>
            <th>Avg</th>
            <th>Ratings</th>
            <th>5★ %</th>
            <th>Mgr Requests</th>
          </tr>
        </thead>
        <tbody>
        @forelse($agentLeaderboard as $a)
          @php
            $name = trim(($a->first_name ?? '').' '.($a->last_name ?? '')) ?: ($a->email ?? 'Staff');
            $roleLbl = strtolower((string)($a->role ?? ''));
            $avg = $a->avg_stars ? number_format((float)$a->avg_stars, 2) : '—';
            $pct = number_format((float)$a->five_star_pct, 0).'%';
          @endphp
          <tr>
            <td>
              <div style="font-weight:600">{{ $name }}</div>
              <div class="support-small">{{ $a->email }}</div>
            </td>
            <td class="text-capitalize">{{ str_replace('_',' ', $roleLbl ?: 'staff') }}</td>
            <td><strong>{{ $avg }}</strong> / 5</td>
            <td>{{ (int)$a->ratings_count }}</td>
            <td>{{ $pct }}</td>
            <td>{{ (int)$a->manager_requests }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="support-empty-row text-capitalize">No ratings yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

  </div>
</section>
@endsection

@push('scripts')
<script>
  (function(){
    const form = document.getElementById('filtersForm');

    // hidden fields (same form submits)
    const rangeHidden = document.getElementById('rangeHidden');
    const dayHidden   = document.getElementById('dayHidden');
    const monthHidden = document.getElementById('monthHidden');
    const yearHidden  = document.getElementById('yearHidden');
    const fromHidden  = document.getElementById('fromHidden');
    const toHidden    = document.getElementById('toHidden');

    // range controls
    const sel = document.getElementById('rangeSelect');
    const dayWrap = document.getElementById('rangeDayWrap');
    const monthWrap = document.getElementById('rangeMonthWrap');
    const yearWrap = document.getElementById('rangeYearWrap');
    const customWrap = document.getElementById('rangeCustomWrap');

    const dayInput   = document.getElementById('rangeDay');
    const monthInput = document.getElementById('rangeMonth');
    const yearInput  = document.getElementById('rangeYear');
    const fromInput  = document.getElementById('rangeFrom');
    const toInput    = document.getElementById('rangeTo');

    function show(el, yes){ if(el) el.style.display = yes ? '' : 'none'; }

    function syncHidden(){
      if(!sel) return;
      const v = sel.value || 'monthly';

      rangeHidden.value = v;

      dayHidden.value   = dayInput ? (dayInput.value || '') : '';
      monthHidden.value = monthInput ? (monthInput.value || '') : '';
      yearHidden.value  = yearInput ? (yearInput.value || '') : '';
      fromHidden.value  = fromInput ? (fromInput.value || '') : '';
      toHidden.value    = toInput ? (toInput.value || '') : '';
    }

    function toggle(){
      if(!sel) return;
      const v = sel.value || 'monthly';

      show(dayWrap, v === 'daily');
      show(monthWrap, v === 'monthly');
      show(yearWrap, v === 'yearly');
      show(customWrap, v === 'custom');

      syncHidden();
    }

    function autoSubmit(){
      if(!form) return;
      syncHidden();
      form.submit();
    }

    // init UI
    if(sel){
      sel.addEventListener('change', function(){
        toggle();
        // for weekly/lifetime we can auto-submit immediately
        const v = sel.value;
        if(v === 'weekly' || v === 'lifetime'){
          autoSubmit();
        }
      });
      toggle();
    }

    // auto-submit on picker changes (your requirement)
    if(dayInput)   dayInput.addEventListener('change', autoSubmit);
    if(monthInput) monthInput.addEventListener('change', autoSubmit);
    if(yearInput)  yearInput.addEventListener('change', function(){
      // optional: submit only if year is 4 digits
      const val = (yearInput.value || '').trim();
      if(val.length >= 4) autoSubmit();
    });
    if(fromInput)  fromInput.addEventListener('change', function(){
      // wait for to as well; if already selected then submit
      if(toInput && toInput.value) autoSubmit();
    });
    if(toInput)    toInput.addEventListener('change', function(){
      // wait for from as well; if already selected then submit
      if(fromInput && fromInput.value) autoSubmit();
    });
  })();
</script>
@endpush