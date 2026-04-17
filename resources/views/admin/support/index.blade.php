@extends('layouts.admin')
@section('title','Support Conversations')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support.css') }}">
<style>
  .inbox-toolbar{
    display:flex; gap:10px; align-items:center; justify-content:space-between;
    flex-wrap:wrap; margin:14px 0 18px;
  }
  .inbox-filters{display:flex; gap:10px; flex-wrap:wrap; align-items:center;}
  .inbox-filters .f{
    display:flex; align-items:center; gap:8px; padding:10px 12px;
    border:1px solid rgba(0,0,0,.12); border-radius:14px; background:#fff;
  }
  .inbox-filters input, .inbox-filters select{
    border:0; outline:0; background:transparent; font-size:14px;
  }

  .badge--sla-ok{ background:#dcfce7; color:#166534; border:1px solid #86efac; }
  .badge--sla-warn{ background:#ffedd5; color:#9a3412; border:1px solid #fdba74; }
  .badge--sla-bad{ background:#fee2e2; color:#7f1d1d; border:1px solid #fca5a5; }
  .badge--sla-dead{ background:#e2e8f0; color:#334155; border:1px solid #cbd5e1; }

  .inbox-actions{display:flex; gap:10px; align-items:center;}
  .pill{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px; border-radius:999px; font-size:12px;
    border:1px solid rgba(0,0,0,.12); background:#fff;
  }
  .pill--solid{background:#000;color:#fff;border-color:#000;}
  .unread-badge{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:20px; height:20px; padding:0 6px;
    border-radius:999px; background:#111; color:#fff;
    font-size:12px; font-weight:700; line-height:1;
  }
  .thread-cell{display:flex; align-items:center; gap:10px;}
  .thread-icons{display:flex; gap:8px; color:rgba(0,0,0,.62);}
  .icon-btn[disabled], .icon-btn[aria-disabled="true"] { opacity:.45; pointer-events:none; }
</style>

<style>
/* MANAGER VISUAL PRIORITY (FULL ROW) */
tr.needs-manager > td{ background:#fff7ed !important; }      /* requested but not yet assigned */
tr.manager-assigned > td{ background:#fef3c7 !important; }   /* assigned to a manager but not joined yet */
tr.manager-active > td{ background:#fee2e2 !important; }     /* joined manager active */

/* SLA VISUAL PRIORITY (FULL ROW) */
tr.sla-ok > td   { background:#f0fdf4 !important; }  /* <= 5m */
tr.sla-warn > td { background:#fffbeb !important; }  /* 6m */
tr.sla-bad > td  { background:#fff7ed !important; }  /* 7m */
tr.sla-dead > td { background:#fef2f2 !important; }  /* 8m+ */

.badge--manager-requested{ background:#ffedd5; color:#9a3412; border:1px solid #fdba74; }
.badge--manager-active{ background:#fecaca; color:#7f1d1d; border:1px solid #fca5a5; }
.badge--manager-ended{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.badge--manager-assigned{ background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }

.manager-dot{ width:8px; height:8px; border-radius:999px; background:#dc2626; display:inline-block; margin-right:6px; }
</style>
@endpush

@section('content')
@php
  $staff = auth()->user();

  // ✅ admin/manager only
  $isAgent   = (($staff->role ?? '') === 'admin');
  $isManager = (($staff->role ?? '') === 'manager');

  $q        = $q ?? request('q','');
  $status   = $status ?? request('status','open');
  $role     = $role ?? request('role','all');
  $per      = (int)($per ?? request('per',10));

  // ✅ NEW: default filters
  $defaultAssigned = $isAgent ? 'mine' : 'all';
  $assigned = $assigned ?? request('assigned', $defaultAssigned);

  $unread   = $unread ?? request('unread','all');
  $dateFrom = $dateFrom ?? request('date_from');
  $dateTo   = $dateTo ?? request('date_to');

  // ✅ Capacity widget should follow assigned_staff_id (not assigned_admin_id)
  $myStatus = $staff->support_status ?? 'available';
  $myOpenCount = \App\Models\SupportConversation::where('assigned_staff_id', $staff->id)
      ->where('status', 'open')
      ->count();
@endphp

<section class="support-inbox">
<div class="support-card">

  <div class="support-card__head">
    <div>
      <h1 class="support-title">Support Inbox</h1>
      <p class="support-subtitle text-capitalize">
        {{ $isManager ? 'Manager view — escalations and oversight' : 'Agent view — handle and escalate when required' }}
      </p>
      <p class="support-meta">
        Showing {{ $convs->firstItem() ?? 0 }}–{{ $convs->lastItem() ?? 0 }} of {{ $convs->total() }}
      </p>

      <div class="mt-2">
        <span class="pill">
          <i class="bi bi-circle-fill me-1" style="font-size:10px"></i>
          Status: <strong class="ms-1">{{ ucfirst(str_replace('_',' ', $myStatus)) }}</strong>
        </span>
        <span class="pill">
          <i class="bi bi-chat-left-dots me-1"></i>
          Active: <strong class="ms-1">{{ $myOpenCount }}</strong>
        </span>
      </div>

    </div>
  </div>

  {{-- ✅ FILTER TOOLBAR --}}
  <form method="get" class="inbox-toolbar">
    <div class="inbox-filters">
      <div class="f">
        <i class="bi bi-search"></i>
        <input type="text" name="q" value="{{ $q }}" placeholder="Search Username, Email, #ID">
      </div>

      <div class="f">
        <i class="bi bi-funnel"></i>
        <select name="assigned">
          @if($isAgent)
            <option value="mine" {{ $assigned==='mine'?'selected':'' }}>My Chats</option>
            <option value="unassigned" {{ $assigned==='unassigned'?'selected':'' }}>Unassigned</option>
            <option value="all" {{ $assigned==='all'?'selected':'' }}>All I Can Access</option>
          @else
            <option value="all"  {{ $assigned==='all'?'selected':'' }}>All Conversations</option>
            <option value="mine" {{ $assigned==='mine'?'selected':'' }}>Assigned To Me</option>
          @endif
        </select>
      </div>

      <div class="f">
        <i class="bi bi-envelope"></i>
        <select name="unread">
          <option value="all" {{ $unread==='all'?'selected':'' }}>All</option>
          <option value="unread" {{ $unread==='unread'?'selected':'' }}>Unread Only</option>
        </select>
      </div>

      <div class="f">
        <i class="bi bi-person-badge"></i>
        <select name="role">
          <option value="all" {{ $role==='all'?'selected':'' }}>All threads</option>
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
        <i class="bi bi-calendar3"></i>
        <input type="date" name="date_from" value="{{ $dateFrom }}">
      </div>

      <div class="f">
        <i class="bi bi-calendar3"></i>
        <input type="date" name="date_to" value="{{ $dateTo }}">
      </div>

      <div class="f">
        <i class="bi bi-list"></i>
        <select name="per">
          @foreach([10,20,50,100] as $n)
            <option value="{{ $n }}" {{ (int)$per===$n?'selected':'' }}>{{ $n }}/Page</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="inbox-actions">
      <button class="pill pill--solid" type="submit">
        <i class="bi bi-arrow-repeat"></i> Apply
      </button>
      <a class="pill" href="{{ route('admin.support.conversations.index') }}">
        <i class="bi bi-x-circle"></i> Reset
      </a>
    </div>
  </form>

  {{-- TABLE --}}
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
          <th>Last message</th>
          <th>Started</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
      @forelse($convs as $c)
        @php
          // minutes
          $adminM = is_null($c->admin_age_minutes) ? null : (int)$c->admin_age_minutes;
          $mgrM   = is_null($c->manager_age_minutes) ? null : (int)$c->manager_age_minutes;

          // SLA badge classes
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

          // thread role
          $sr = $c->scope_role ?? $c->user_type ?? 'client';

          // manager state flags
          $managerRequested = !empty($c->manager_requested_at) && empty($c->manager_joined_at);
          $managerActive    = !empty($c->manager_joined_at) && empty($c->manager_ended_at);
          $managerEnded     = !empty($c->manager_joined_at) && !empty($c->manager_ended_at);

          // assigned manager but not joined yet
          $managerAssigned  = $managerRequested && !$managerActive && !$managerEnded && !empty($c->manager_id);

          // manager row priority
          $rowClass = '';
          if ($managerActive) $rowClass = 'manager-active';
          elseif ($managerAssigned) $rowClass = 'manager-assigned';
          elseif ($managerRequested) $rowClass = 'needs-manager';

          // SLA row class
          $rowMins = ($managerActive || $managerAssigned || $managerRequested) ? $mgrM : $adminM;
          $slaRowClass = '';
          if (!is_null($rowMins)) {
            if ($rowMins <= 5) $slaRowClass = 'sla-ok';
            elseif ($rowMins == 6) $slaRowClass = 'sla-warn';
            elseif ($rowMins == 7) $slaRowClass = 'sla-bad';
            else $slaRowClass = 'sla-dead';
          }

          $unreadCount = (int)($c->unread_count ?? 0);

          // NEW: assigned staff display
        $assignedStaffName = $c->assignedStaff
    ? ($c->assignedStaff->username ?? $c->assignedStaff->email)
    : null;


          $assignedStaffRole = $c->assigned_staff_role ?? null;
        @endphp

        <tr class="{{ trim($rowClass.' '.$slaRowClass) }}">
          {{-- USER --}}
          <td>
            <div class="support-user">
              <div class="support-avatar">
                @php $uname = $c->user?->username ?: ($c->user?->email ?: 'U'); @endphp
<span>{{ strtoupper(mb_substr($uname, 0, 1)) }}</span>

              </div>
              <div class="support-user__meta">
                <div class="support-user__name">
                 {{ $c->user?->username ?: ($c->user?->email ?: 'Unknown user') }}

                </div>
                <div class="support-user__email">{{ $c->user?->email }}</div>
                <div class="support-user__id">#{{ $c->id }}</div>
              </div>
            </div>
          </td>

          {{-- THREAD --}}
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

          {{-- STATUS --}}
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

          {{-- ASSIGNED (STAFF OWNER) --}}
          <td>
            @if($assignedStaffName)
              <div class="d-flex flex-column">
                <span>{{ $assignedStaffName }}</span>
                @if($assignedStaffRole)
                  <span class="support-small text-muted text-capitalize">{{ $assignedStaffRole }}</span>
                @endif
              </div>
            @else
              <span class="badge badge--outline">Unassigned</span>
            @endif
          </td>

          {{-- MANAGER --}}
          <td>
            @if($managerActive)
              <span class="badge badge--manager-active">
                @if($isManager)<span class="manager-dot"></span>@endif
                Active
              </span>
              <div class="support-small mt-1">
           {{ $c->manager?->username ?: ($c->manager?->email ?: '—') }}

              </div>
            @elseif($managerAssigned)
              <span class="badge badge--manager-assigned">Assigned</span>
              <div class="support-small mt-1">
               {{ $c->manager?->username ?: ($c->manager?->email ?: '—') }}

              </div>
            @elseif($managerRequested)
              <span class="badge badge--manager-requested">
                @if($isManager)<span class="manager-dot"></span>@endif
                Requested
              </span>
            @elseif($managerEnded)
              <span class="badge badge--manager-ended">Ended</span>
            @else
              <span class="badge badge--outline">—</span>
            @endif
          </td>

          {{-- SLA --}}
          <td class="support-small">
            <div class="d-flex flex-column gap-1">
              @if($adminSlaClass)
                <span class="badge {{ $adminSlaClass }}" title="Admin timer (first user msg → now/close)">
                  A: {{ $adminM }}m
                </span>
              @else
                <span class="badge badge--outline">—</span>
              @endif

              @if($mgrSlaClass)
                <span class="badge {{ $mgrSlaClass }}" title="Manager timer (joined → now/ended)">
                  M: {{ $mgrM }}m
                </span>
              @endif
            </div>
          </td>

          {{-- LAST MESSAGE --}}
          <td class="support-small">
            {{ $c->last_message_at?->diffForHumans() ?? '—' }}
          </td>

          {{-- STARTED --}}
          <td class="support-small">
            {{ $c->created_at->format('d M Y') }}
          </td>

          {{-- ACTION --}}
          <td>
            <div class="act" style="display:flex; gap:8px; align-items:center;">
              <a href="{{ route('admin.support.conversations.show', $c) }}"
                 class="icon-btn icon-btn--primary"
                 title="Open conversation">
                <i class="bi bi-chat-dots"></i>
                @if($unreadCount > 0)
                  <span class="badge-dot">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
              </a>

              {{-- ✅ IMPORTANT: with new flow, NO manual Join button --}}
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="support-empty-row">No Conversations Found.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="support-pager">
    {{ $convs->links() }}
  </div>

</div>
</section>
@endsection
