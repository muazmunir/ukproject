<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title','Admin — ZAIVIAS')</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <!-- Bootstrap 4 CSS -->
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  
  <link rel="stylesheet" href="{{ asset('assets/css/admin-top.css') }}">
  @stack('styles')
  
<style>
  /* Force Bootstrap-like pagination layout, override any global ul rules */
  /* --- ZAIVIAS Themed Pagination --- */

  /* ===== Agent Status Colors (same palette) ===== */

/* Dropdown items */


/* ===== Status Pills (oval badges) ===== */
#agentStatusMenu button[data-status] { display:flex; align-items:center; gap:10px; }

/* Make the button look clean */
#agentStatusMenu .linklike{
  background: transparent;
  border: 0;
  width: 100%;
}

/* Wrap text in a pill */
#agentStatusMenu button[data-status] .status-pill{
  display:inline-flex;
  align-items:center;
  /* justify-content:center; */
  width: 100px;
  padding: 6px 10px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 13px;
  line-height: 1;

  background: #fff;              /* ✅ white bg */
  border: 1px solid #000;        /* ✅ black border */
  color: var(--pill-text, #111); /* ✅ status color as text */
}


/* ✅ red dot on left of pill */
#agentStatusMenu button[data-status] .status-pill::before,
#agentStatusCurrentPill.status-pill.current::before{
  content:"";
  width:8px;
  height:8px;
  border-radius:50%;
  background: var(--dot-color, #ef4444); /* ✅ dot uses status color */
  display:inline-block;
  margin-right:8px;
}
/* Hover: slight fill but still matches color */


/* ===== Status Colors ===== */
/* ✅ pill text colors */
#agentStatusMenu button[data-status="available"]   .status-pill { --pill-text:black; --dot-color:#22c55e; }
#agentStatusMenu button[data-status="break"]       .status-pill { --pill-text:black; --dot-color:#facc15; }
#agentStatusMenu button[data-status="meeting"]     .status-pill { --pill-text:black; --dot-color:#a855f7; }
#agentStatusMenu button[data-status="admin"]       .status-pill { --pill-text:black; --dot-color:#3b82f6; }
#agentStatusMenu button[data-status="tech_issues"] .status-pill { --pill-text:black; --dot-color:#92400e; }
#agentStatusMenu button[data-status="holiday"]     .status-pill { --pill-text:black; --dot-color:#ec4899; }
#agentStatusMenu button[data-status="authorized_absence"]   .status-pill { --pill-text:black; --dot-color:#f59e0b; }
#agentStatusMenu button[data-status="unauthorized_absence"] .status-pill { --pill-text:black; --dot-color:#ef4444; }
#agentStatusMenu button[data-status="offline"]     .status-pill { --pill-text:black; --dot-color:#374151; }



#agentStatusCurrentPill[data-current-status="available"]   { --dot-color:#22c55e; }
#agentStatusCurrentPill[data-current-status="break"]       { --dot-color:#facc15; }
#agentStatusCurrentPill[data-current-status="meeting"]     { --dot-color:#a855f7; }
#agentStatusCurrentPill[data-current-status="admin"]       { --dot-color:#3b82f6; }
#agentStatusCurrentPill[data-current-status="tech_issues"] { --dot-color:#92400e; }
#agentStatusCurrentPill[data-current-status="holiday"]     { --dot-color:#ec4899; }
#agentStatusCurrentPill[data-current-status="authorized_absence"]   { --dot-color:#f59e0b; }
#agentStatusCurrentPill[data-current-status="unauthorized_absence"] { --dot-color:#ef4444; }
#agentStatusCurrentPill[data-current-status="offline"]     { --dot-color:#374151; }

/* ===== Current Status Pill (top of dropdown) ===== */
#agentStatusCurrentPill.status-pill.current{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:max-content;
  padding:6px 12px;
  border-radius:999px;
  font-weight:800;
  font-size:12px;
  line-height:1;
  text-transform: capitalize;

  background:#fff;               /* ✅ white */
  border:1px solid #000;         /* ✅ black border */
  color: var(--pill-text, #111); /* ✅ status color text */
}

/* Set colors based on data-current-status */
#agentStatusCurrentPill[data-current-status="available"]   { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="break"]       { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="meeting"]     { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="admin"]       { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="tech_issues"] { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="holiday"]     { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="authorized_absence"]   { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="unauthorized_absence"] { --pill-text:black; }
#agentStatusCurrentPill[data-current-status="offline"]     { --pill-text:black; }
/* Current status text */
/* #agentStatusCurrent[data-current-status="available"]   { color:#22c55e; }
#agentStatusCurrent[data-current-status="break"]       { color:#facc15; }
#agentStatusCurrent[data-current-status="meeting"]     { color:#a855f7; }
#agentStatusCurrent[data-current-status="admin"]       { color:#3b82f6; }
#agentStatusCurrent[data-current-status="tech_issues"] { color:#92400e; }
#agentStatusCurrent[data-current-status="holiday"]     { color:#ec4899; }
#agentStatusCurrent[data-current-status="authorized_absence"]   { color:#f59e0b; }
#agentStatusCurrent[data-current-status="unauthorized_absence"] { color:#ef4444; }
#agentStatusCurrent[data-current-status="offline"]     { color:#374151; } */


/* ✅ Filled backgrounds for pills */
#agentStatusMenu button[data-status="available"]   .status-pill { --pill-bg:#22c55e; --pill-border:#22c55e; }
#agentStatusMenu button[data-status="break"]       .status-pill { --pill-bg:#facc15; --pill-border:#facc15; }
#agentStatusMenu button[data-status="meeting"]     .status-pill { --pill-bg:#a855f7; --pill-border:#a855f7; }
#agentStatusMenu button[data-status="admin"]       .status-pill { --pill-bg:#3b82f6; --pill-border:#3b82f6; }
#agentStatusMenu button[data-status="tech_issues"] .status-pill { --pill-bg:#92400e; --pill-border:#92400e; }
#agentStatusMenu button[data-status="holiday"]     .status-pill { --pill-bg:#ec4899; --pill-border:#ec4899; }
#agentStatusMenu button[data-status="authorized_absence"]   .status-pill { --pill-bg:#f59e0b; --pill-border:#f59e0b; }
#agentStatusMenu button[data-status="unauthorized_absence"] .status-pill { --pill-bg:#ef4444; --pill-border:#ef4444; }
#agentStatusMenu button[data-status="offline"]     .status-pill { --pill-bg:#374151; --pill-border:#374151; }
.pagination {
  display: flex !important;
  padding-left: 0 !important;
  list-style: none !important;
  margin: 1.2rem 0;
  gap: 6px;
  justify-content:end;
}

/* Each page button */
.pagination .page-link {
  display: block;
  padding: 6px 14px;
  font-size: 14px;
  border-radius: 10px;
  text-decoration: none;

  background: black;     
  color: #fff;                   /* white text */
  border: 1px solid var(--border);
  transition: 0.15s ease;
}


/* Hover */
.pagination .page-link:hover {
  background: var(--brand);    /* your blue */
  color: #fff;
  border-color: var(--brand);
}

/* Active (current page) */
.pagination .page-item.active .page-link {
  background: var(--brand);    /* stronger blue highlight */
  border-color:var(--brand);
  color: #fff;
  font-weight: 600;
}

/* Disabled (Prev/Next when inactive) */
.pagination .page-item.disabled .page-link {
  opacity: 0.45;
  cursor: not-allowed;
  background: var(--brand);
  border-color: var(--border);
}

</style>


</head>
<body>
  <!-- Top bar -->
  <header class="zv-topbar pro">
    <div class="zv-topbar__row">
      <a class="brand" href="{{ url('admin') }}">
       <img width="150px" src="/assets/logo.png">
      </a>
  
      <!-- Primary icon bar -->
      <nav class="primary iconbar" aria-label="Primary">
        <a class="tab {{ request()->is('admin') ? 'active' : '' }}" href="{{ url('admin') }}" title="Dashboard">
          <span class="ico">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" fill="currentColor"/></svg>
          </span>
          <span class="lbl">Dashboard</span>
        </a>
      
        <a class="tab {{ request()->is('admin/bookings*') ? 'active' : '' }}" href="{{ url('admin/bookings') }}" title="Bookings">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M7 2v3M17 2v3M4 7h16M5 11h14M5 15h9M6 22h12a2 2 0 0 0 2-2V7H4v13a2 2 0 0 0 2 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <span class="lbl">Bookings</span>
        </a>

       <a class="tab {{ request()->routeIs('admin.support.conversations.*') ? 'active' : '' }}"
   href="{{ route('admin.support.conversations.index') }}"
   title="Support">

    <span class="ico">
      <svg viewBox="0 0 24 24">
        <path d="M4 5h16v10H8l-4 4z"
              fill="none" stroke="currentColor" stroke-width="2"
              stroke-linejoin="round" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="lbl">Support</span>
  </a>



  @php $role = strtolower(trim(auth()->user()->role ?? '')); @endphp
@if($role === 'manager')
  <a class="tab {{ request()->routeIs('admin.support.reviews.*') ? 'active' : '' }}"
     href="{{ route('admin.support.reviews.index') }}"
     title="Support Analytics">
    <span class="ico">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M5 19V10M12 19V5M19 19v-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="lbl">Reviews</span>
  </a>
@endif

@if($role === 'manager')
  <a class="tab {{ request()->routeIs('admin.staff_analytics.*') ? 'active' : '' }}"
     href="{{ route('admin.staff_analytics.index') }}"
     title="Team Analytics">

    <span class="ico">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 19V10M12 19V5M20 19v-8"
              fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </span>

    <span class="lbl">Analytics</span>
  </a>
@endif


@if(in_array($role, ['manager','superadmin'], true))
  <a class="tab {{ request()->routeIs('admin.support.status.analytics.manager') ? 'active' : '' }}"
     href="{{ route('admin.support.status.analytics.manager') }}"
     title="Status Analytics">

    <span class="ico">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM4 20a8 8 0 0 1 16 0"
              fill="none" stroke="currentColor" stroke-width="2"/>
      </svg>
    </span>

    <span class="lbl">Status Analytics</span>
  </a>
@endif

@php
  use App\Models\AgentAbsenceRequest;

  $role = strtolower(trim(auth()->user()->role ?? ''));

  $pendingMy = AgentAbsenceRequest::where('agent_id', auth()->id())
      ->where('state', 'pending')
      ->count();

  $pendingReview = 0;

  if ($role === 'manager') {
      $pendingReview = AgentAbsenceRequest::where('state', 'pending')
          ->whereHas('agent', fn($q) => $q->where('role', 'admin'))
          ->count();
  }
@endphp

{{-- ✅ REQUESTS (My Apply) --}}
@if(in_array($role, ['admin','manager'], true))
  <a class="tab {{ request()->routeIs('admin.support_leave.my') ? 'active' : '' }} {{ $pendingMy>0 ? 'is-alert' : '' }}"
     href="{{ route('admin.support_leave.my') }}"
     title="Requests">
    <span class="ico" style="position:relative;">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M7 2v3M17 2v3M4 7h16M5 11h14M5 15h10M6 22h12a2 2 0 0 0 2-2V7H4v13a2 2 0 0 0 2 2z"
              fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>

      @if($pendingMy > 0)
        <span class="zv-tab-badge">{{ $pendingMy > 99 ? '99+' : $pendingMy }}</span>
      @endif
    </span>
    <span class="lbl">Requests</span>
  </a>
@endif

{{-- ✅ REVIEW (Manager only) --}}
@if($role === 'manager')
  <a class="tab {{ request()->routeIs('admin.support_leave.review') ? 'active' : '' }} {{ $pendingReview>0 ? 'is-alert' : '' }}"
     href="{{ route('admin.support_leave.review') }}"
     title="Leave Review">
    <span class="ico" style="position:relative;">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M9 11l3 3L22 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"
              fill="none" stroke="currentColor" stroke-width="2"/>
      </svg>

      @if($pendingReview > 0)
        <span class="zv-tab-badge">{{ $pendingReview > 99 ? '99+' : $pendingReview }}</span>
      @endif
    </span>
    <span class="lbl">Leave</span>
  </a>
@endif



  

  
  <a class="tab {{ request()->routeIs('admin.support.questions.*') ? 'active' : '' }}"
   href="{{ route('admin.support.questions.index') }}"
   title="Support Q&A">

  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 4h16v12H7l-3 3z"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round"/>
      <path d="M8 9h8M8 13h6"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round"/>
    </svg>
  </span>
  <span class="lbl">Q&amp;A</span>
</a>


<a class="tab {{ request()->routeIs('admin.staff_chat.*') ? 'active' : '' }}"
   href="{{ route('admin.staff_chat.index') }}"
   title="Staff Chat">

  <span class="ico" style="position:relative;">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 5h16v10H8l-4 4z"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round"/>
      <path d="M8 9h8M8 12h6"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round"/>
    </svg>

    {{-- ✅ Staff chat unread badge (total) --}}
    <span id="staffChatTopBadge"
          style="display:none; position:absolute; top:-6px; right:-10px;
                 min-width:18px; height:18px; padding:0 6px;
                 border-radius:999px; font-size:11px; font-weight:800;
                 background:#22c55e; color:#fff;
                 align-items:center; justify-content:center;">
      0
    </span>
  </span>

  <span class="lbl">Staff</span>
</a>



{{-- @php $role = strtolower(auth()->user()->role ?? ''); @endphp
@if(in_array($role, ['admin','super_admin'], true))
<a class="tab {{ request()->routeIs('admin.dm.agent.*') ? 'active' : '' }}"
   href="{{ route('admin.dm.agent.index') }}"
   title="Message Manager">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 5h16v10H8l-4 4z" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round"/>
      <path d="M8 9h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
  </span>
  <span class="lbl">Manager</span>
</a>
@endif


@if($role === 'manager')
<a class="tab {{ request()->routeIs('admin.dm.manager.*') ? 'active' : '' }}"
   href="{{ route('admin.dm.manager.index') }}"
   title="My Agents">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5z"
            fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M4 21a8 8 0 0 1 16 0"
            fill="none" stroke="currentColor" stroke-width="2"/>
    </svg>
  </span>
  <span class="lbl">Agents</span>
</a>
@endif --}}


      
        <a class="tab {{ request()->is('admin/transactions*') ? 'active' : '' }}" href="{{ url('admin/transactions') }}" title="Payments">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M3 6h18v12H3zM3 10h18" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <span class="lbl">Payments</span>
        </a>
      
        <a class="tab {{ request()->is('admin/services*') ? 'active' : '' }}" href="{{ url('admin/services') }}" title="Services">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4L19 21l-7-4-7 4 2.5-8L2 9h7z" fill="currentColor"/></svg>
          </span>
          <span class="lbl">Services</span>
        </a>
      
        {{-- <a class="tab {{ request()->is('admin/categories*') ? 'active' : '' }}" href="{{ url('admin/categories') }}" title="Categories">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="currentColor"/></svg>
          </span>
          <span class="lbl">Categories</span>
        </a> --}}
      
        <a class="tab {{ request()->is('admin/coaches*') ? 'active' : '' }}" href="{{ url('admin/coaches') }}" title="Coaches">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm-8 9a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <span class="lbl">Coaches</span>
        </a>
      
        <a class="tab {{ request()->is('admin/clients*') ? 'active' : '' }}" href="{{ url('admin/clients') }}" title="Clients">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM3 20a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <span class="lbl">Clients</span>
        </a>
      
        <a class="tab {{ request()->is('admin/disputes*') ? 'active' : '' }}" href="{{ url('admin/disputes') }}" title="Disputes">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M12 22a10 10 0 1 1 10-10 10 10 0 0 1-10 10zm0-14v4m0 4h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </span>
          <span class="lbl">Disputes</span>
        </a>
      
        <a class="tab {{ request()->is('admin/withdrawals*') ? 'active' : '' }}" href="{{ url('admin/withdrawals') }}" title="Withdrawals">
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M12 19V5m0 0l-5 5m5-5l5 5M5 19h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </span>
          <span class="lbl">Withdrawals</span>
        </a>

        @php $role = strtolower(trim(auth()->user()->role ?? '')); @endphp
@if($role === 'manager')
  <a class="tab {{ request()->routeIs('admin.refunds.analytics') ? 'active' : '' }}"
     href="{{ route('admin.refunds.analytics') }}"
     title="Refund Analytics">
    <span class="ico">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 6h18v12H3zM3 10h18M8 15h3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="lbl">Refunds</span>
  </a>
@endif
      
        {{-- <a class="tab {{ request()->routeIs('admin.newsletter.subscribers.*') ? 'active' : '' }}"
   href="{{ route('admin.newsletter.subscribers.index') }}"
   title="Email Subscriptions">

          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM4 4l8 8 8-8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <span class="lbl">Email Subscription</span>
        </a> --}}
      
        {{-- <a class="tab {{ request()->is('admin/settings*') ? 'active' : '' }}"
          href="{{ route('admin.settings.index') }}"
          title="Website Settings">


        
       
          <span class="ico">
            <svg viewBox="0 0 24 24"><path d="M12 15a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm7.94-2a7.94 7.94 0 0 0 0-2l2.06-1.6-2-3.46-2.44 1a8 8 0 0 0-1.73-1L15 2h-6l-.83 2.94a8 8 0 0 0-1.73 1l-2.44-1-2 3.46L4.06 11a7.94 7.94 0 0 0 0 2l-2.06 1.6 2 3.46 2.44-1a8 8 0 0 0 1.73 1L9 22h6l.83-2.94a8 8 0 0 0 1.73-1l2.44 1 2-3.46z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </span>
          <span class="lbl">Website Setting</span>
        </a> --}}
      </nav>
      
  
      <!-- Right-side actions -->
      <div class="actions">
        {{-- <div class="btn-wrap">
          <button class="icon" id="bellBtn" aria-label="Notifications">
            <span class="dot"></span>
            <svg width="18" height="18" viewBox="0 0 24 24"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M13.7 21a2 2 0 01-3.4 0" fill="none" stroke="currentColor" stroke-width="2"/></svg>
          </button>
          <div class="dropdown right" id="bellMenu">
            <span class="dd-title">Notifications</span>

            <a href="#">💳 Payment received · $60</a>
            <a href="#">👤 New coach signup</a>
            <a href="#">📅 Booking cancelled</a>
          </div>
        </div> --}}
  
        {{-- <button class="icon" id="themeBtn" aria-label="Theme">
          <svg width="18" height="18" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1111.2 3 7 7 0 0021 12.8z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        </button>
   --}}
        <div class="btn-wrap">
         @php
  $u = auth()->user();
  $label = $u?->first_name ?: $u?->email ?: 'A';
@endphp



{{-- Admin Agent Status dropdown --}}
<div class="btn-wrap">
  <button class="icon" id="agentStatusBtn" aria-label="Agent Status">
    <i class="bi bi-activity"></i>
  </button>

  <div class="dropdown right" id="agentStatusMenu" style="min-width:220px;">
    {{-- <span class="dd-title">Support Status</span> --}}
{{-- 
    <a href="{{ route('admin.support.status.analytics') }}"
   class="linklike w-100 text-start p-2 d-block"
   style="text-decoration:none;">
   Analytics
</a> --}}




{{-- <a href="{{ route('admin.support.absence.my_log') }}"
   class="linklike w-100 text-start p-2 d-block"
   style="text-decoration:none;">
  🧾 My Status Log
</a> --}}




@php
  $u = auth()->user();
  $tz = $u->timezone ?: 'UTC';

  $phase = strtolower((string)($u->absence_phase ?? ''));
  $absenceActive  = $phase === 'active';
  $returnRequired = $phase === 'post' && (bool)($u->absence_return_required ?? false);

  $absenceLabel = null;

  if ($absenceActive) {
      $k = strtolower((string)($u->absence_kind ?? 'absence'));
      $absenceLabel = $k === 'holiday'
          ? 'HOLIDAY'
          : strtoupper((string)($u->absence_status ?? '')) . ' ABSENCE';
  }

  if ($returnRequired) {
      $absenceLabel = 'UNAUTHORIZED ABSENCE (RETURN REQUIRED)';
  }

  $endFmt = $u->absence_end_at
      ? \Carbon\Carbon::parse($u->absence_end_at)->timezone($tz)->format('d M Y, H:i')
      : null;
@endphp


{{-- ADMIN (agent) --}}


{{-- MANAGER + SUPERADMIN --}}




<hr class="my-1">


@php
  $st = ((string)(auth()->user()->support_status ?? 'available'));
  $presence = strtolower((string)(auth()->user()->support_presence ?? 'online'));
@endphp

<div class="px-2 pb-2 fw-bold" style="font-size:12px;opacity:.95">
  Status:
  <span id="agentStatusCurrentPill"
        class="status-pill current"
        data-current-status="{{ $st }}">
    {{ $st }}
  </span>

  @if($presence === 'offline')
    <span class="badge bg-danger ms-2">Offline</span>
  @endif
</div>




@if($absenceActive)
  <div class="mx-2 my-2 p-2 rounded"
       style="background:#fff7ed;border:1px solid #fed7aa;font-size:12px;color:#9a3412;">
    <strong>Status locked:</strong> {{ $absenceLabel }}
    @if($endFmt)
      <div style="opacity:.9">Until: {{ $endFmt }}</div>
    @endif
  </div>
@endif

@if($returnRequired)
  <div class="mx-2 my-2 p-2 rounded"
       style="background:#fee2e2;border:1px solid #fecaca;font-size:12px;color:#991b1b;">
    <strong>Return required:</strong> You are on Unauthorized Absence.
    <div style="opacity:.9">Set <strong>Available</strong> to return.</div>
  </div>
@endif

  <button type="button" class="linklike w-100 text-start p-2"
        data-status="available" {{ $absenceActive ? 'disabled' : '' }}>
  <span class="status-pill">Available</span>
</button>


<button type="button" class="linklike w-100 text-start p-2"
        data-status="break" {{ ($absenceActive || $returnRequired) ? 'disabled' : '' }}>
  <span class="status-pill">Break</span>
</button>

<button type="button" class="linklike w-100 text-start p-2"
        data-status="admin" {{ ($absenceActive || $returnRequired) ? 'disabled' : '' }}>
  <span class="status-pill">Admin</span>
</button>

<button type="button" class="linklike w-100 text-start p-2"
        data-status="tech_issues" {{ ($absenceActive || $returnRequired) ? 'disabled' : '' }}>
  <span class="status-pill">Tech</span>
</button>

<button type="button" class="linklike w-100 text-start p-2"
        data-status="meeting" {{ ($absenceActive || $returnRequired) ? 'disabled' : '' }}>
  <span class="status-pill">Meeting</span>
</button>





   
  </div>
</div>

<button class="avatar" id="profBtn">
  {{ strtoupper(substr($label, 0, 1)) }}
</button>

          <div class="dropdown right" id="profMenu">
            <a href="{{ url('admin/profile') }}">Profile</a>
             <a href="{{ route('admin.support.status.analytics') }}"
   class="linklike w-100 text-start p-2 d-block"
   style="text-decoration:none;">
   Analytics
</a>
            {{-- <a href="{{ url('admin/settings') }}">Settings</a> --}}
            <form id="logoutForm" method="post" action="{{ route('admin.logout') }}">

              @csrf
              <button class="linklike" type="submit">Logout</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>
  

  <main class="zv-container">
    @yield('content')
  </main>
 

  @auth
<script>
(async function () {
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!tz) return;

  const KEY = 'lastSentTimezone';
  if (sessionStorage.getItem(KEY) === tz) return;

  const res = await fetch(@json(route('admin.me.timezone.update')), {
    method: 'POST',
    credentials: 'same-origin', // ✅ ensure cookies are sent
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ timezone: tz })
  });

  const text = await res.text();
  console.log('TZ status:', res.status);
  console.log('TZ raw:', text);

  if (!res.ok) return;

  const data = JSON.parse(text);
  if (data.updated) sessionStorage.setItem(KEY, tz);
})();
</script>


@endauth
  <script>
    // use a different alias so we don't collide with jQuery
    const qs  = s => document.querySelector(s);
    const qsa = s => document.querySelectorAll(s);

    const createBtn = qs('#createBtn'), createMenu = qs('#createMenu');
    const bellBtn   = qs('#bellBtn'),  bellMenu   = qs('#bellMenu');
    const profBtn   = qs('#profBtn'),  profMenu   = qs('#profMenu');
    const themeBtn  = qs('#themeBtn');
    const searchInput = document.querySelector('.search input');

    function toggle(el){ el?.classList.toggle('open'); }

    document.addEventListener('click',(e)=>{
      if (e.target === createBtn) { toggle(createMenu); return; }
      if (!e.target.closest('#createMenu')) createMenu?.classList.remove('open');

      if (e.target.closest('#bellBtn')) { toggle(bellMenu); return; }
      if (!e.target.closest('#bellMenu')) bellMenu?.classList.remove('open');

      if (e.target.closest('#profBtn')) { toggle(profMenu); return; }
      if (!e.target.closest('#profMenu')) profMenu?.classList.remove('open');
    });

    // theme
    const apply = (t) => {
      document.documentElement.setAttribute('data-theme', t);
      localStorage.setItem('zv-theme', t);
    };
    apply(localStorage.getItem('zv-theme') || 'light');
    themeBtn?.addEventListener('click', () => {
      apply(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });

    // hotkey
    window.addEventListener('keydown', (e) => {
      if (e.key === '/' && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        searchInput?.focus();
      }
    });
  </script>

  {{-- load jQuery AFTER this, now $ is free for jQuery --}}
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="{{ asset('assets/js/zv-image-uploader.js') }}"></script>
  <script>
  (function () {
    const IDLE_MS = 60 * 10000; // 1 minute
    let t = null;

    const reset = () => {
      if (t) clearTimeout(t);
      t = setTimeout(lockNow, IDLE_MS);
    };

   async function lockNow() {
  // ✅ prevent go_offline beacon during softlock redirect
  window.__isSoftlocking = true;
  if (window.__suppressGoOffline) window.__suppressGoOffline(15000);

  try {
    await fetch("{{ route('admin.softlock.trigger') }}", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": "{{ csrf_token() }}"
      },
      body: JSON.stringify({})
    });
  } catch (e) {}

  window.location.href = "{{ route('admin.locked.soft') }}";
}


    // activity events
    ["mousemove","mousedown","keydown","scroll","touchstart"].forEach(evt => {
      window.addEventListener(evt, reset, { passive: true });
    });

    reset();
  })();
  </script>

  <script>
(function () {
  const badge = document.getElementById('staffChatTopBadge');
  if (!badge) return;

  const url = @json(route('admin.staff_chat.unreads'));

  async function poll() {
    try {
      const res = await fetch(url, { headers: { 'Accept':'application/json' }});
      const json = await res.json();

      if (!json || !json.ok) return;

      const data = json.data || {};
      let total = 0;

      // data example: { "1":3, "5":1 }
      Object.keys(data).forEach(k => {
        total += parseInt(data[k] || 0, 10);
      });

      if (total > 0) {
        badge.textContent = total > 99 ? '99+' : total;
        badge.style.display = 'inline-flex';
      } else {
        badge.style.display = 'none';
      }
    } catch (e) {}
  }

  poll();
  setInterval(poll, 5000); // every 5 sec
})();
</script>



<script>

  function showStatusToast(message, type = 'error') {
  const toast = document.getElementById('statusToast');
  if (!toast) return;

  toast.textContent = message;
  toast.className = `status-toast ${type} show`;

  clearTimeout(window.__statusToastTimer);
  window.__statusToastTimer = setTimeout(() => {
    toast.classList.remove('show');
  }, 3000);
}
(function () {
  const btn = document.querySelector('#agentStatusBtn');
  const menu = document.querySelector('#agentStatusMenu');
  const cur = document.querySelector('#agentStatusCurrentPill');

  function toggle(el){ el?.classList.toggle('open'); }

  document.addEventListener('click', (e) => {
    if (e.target.closest('#agentStatusBtn')) { toggle(menu); return; }
    if (!e.target.closest('#agentStatusMenu')) menu?.classList.remove('open');
  });

  menu?.addEventListener('click', async (e) => {
    const item = e.target.closest('[data-status]');
    if (!item) return;
     if (window.__suppressGoOffline) window.__suppressGoOffline(3000);

    const status = item.getAttribute('data-status');

    const res = await fetch("{{ route('admin.support.agent.status.update') }}", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": "{{ csrf_token() }}",
        "X-Requested-With": "XMLHttpRequest",
        "Accept": "application/json"
      },
      body: JSON.stringify({ status })
    });

   const data = await res.json();

if (data.ok) {
  if (cur) {
    cur.textContent = data.status;
    cur.setAttribute('data-current-status', data.status);
  }
  menu.classList.remove('open');
  showStatusToast('Status Updated Successfully', 'success');
  return;
}

if (data.locked) {
  showStatusToast(data.message || 'Your Status Is Locked Right Now.', 'warning');
  return;
}

showStatusToast(data.message || 'Failed To Update Status', 'error');

  });
})();
</script>



<script>
(function(){
  const hbUrl  = "{{ route('admin.support.heartbeat') }}";
  const token  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  async function ping(){
    try{
      await fetch(hbUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        }
      });
    }catch(e){}
  }

  ping();
  setInterval(ping, 60000);
})();
</script>







<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  @stack('scripts')


  

  <div id="statusToast" class="status-toast"></div>
</body>
</html>


