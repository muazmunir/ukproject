<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Super Admin — ZAIVIAS')</title>

  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  {{-- Bootstrap Icons (keep ONE) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  {{-- Bootstrap --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  {{-- Reuse the same topbar CSS --}}
  <link rel="stylesheet" href="{{ asset('assets/css/admin-top.css') }}">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

  @stack('styles')

  <style>
    /* --- ZAIVIAS Themed Pagination --- */
    .pagination{
      display:flex !important;
      padding-left:0 !important;
      list-style:none !important;
      margin:1.2rem 0;
      gap:6px;
      justify-content:end;
    }
    .pagination .page-link{
      display:block;
      padding:6px 14px;
      font-size:14px;
      border-radius:10px;
      text-decoration:none;
      background:#000;
      color:#fff;
      border:1px solid var(--border);
      transition:0.15s ease;
    }
    .pagination .page-link:hover{
      background:var(--brand);
      color:#fff;
      border-color:var(--brand);
    }
    .pagination .page-item.active .page-link{
      background:var(--brand);
      border-color:var(--brand);
      color:#fff;
      font-weight:600;
    }
    .pagination .page-item.disabled .page-link{
      opacity:.45;
      cursor:not-allowed;
      background:#000;
      border-color:var(--border);
    }
  </style>
  <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

<body>
  @php
    $u = auth()->user(); // ✅ superadmin user from users table (web auth)
    $initial = strtoupper(substr($u->first_name ?? $u->email ?? 'U', 0, 1));
  @endphp

  <!-- Top bar -->
  <header class="zv-topbar pro">
    <div class="zv-topbar__row">
      <a class="brand" href="{{ url('superadmin') }}">
        <img width="150" src="/assets/logo.png" alt="ZAIVIAS">
      </a>

      <!-- Primary icon bar -->
      <nav class="primary iconbar" aria-label="Primary">
        <a class="tab {{ request()->is('superadmin') ? 'active' : '' }}"
           href="{{ url('superadmin') }}" title="Dashboard">
          <span class="ico">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" fill="currentColor"/>
            </svg>
          </span>
          <span class="lbl">Dashboard</span>
        </a>

        <a class="tab {{ request()->is('superadmin/bookings*') ? 'active' : '' }}"
           href="{{ url('superadmin/bookings') }}" title="Bookings">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M7 2v3M17 2v3M4 7h16M5 11h14M5 15h9M6 22h12a2 2 0 0 0 2-2V7H4v13a2 2 0 0 0 2 2z"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <span class="lbl">Bookings</span>
        </a>

        <a class="tab {{ request()->routeIs('superadmin.support.conversations.*') ? 'active' : '' }}"
   href="{{ route('superadmin.support.conversations.index') }}">

          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M4 5h16v10H8l-4 4z"
                    fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="lbl">Support</span>
        </a>


        

        <a class="tab {{ request()->routeIs('superadmin.support.questions.*') ? 'active' : '' }}"
   href="{{ route('superadmin.support.questions.index') }}">

   
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





@php
  use App\Models\AgentAbsenceRequest;

  // Pending leave requests superadmin can review (admins + managers)
  $pendingLeaveReview = AgentAbsenceRequest::where('state', 'pending')
      ->whereHas('agent', fn($q) => $q->whereIn('role', ['admin','manager']))
      ->count();
@endphp

<a class="tab {{ request()->routeIs('superadmin.support.leave.*') ? 'active' : '' }} {{ $pendingLeaveReview>0 ? 'is-alert' : '' }}"
   href="{{ route('superadmin.support.leave.review') }}"
   title="Leave Review">
  <span class="ico" style="position:relative;">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M9 11l3 3L22 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"
            fill="none" stroke="currentColor" stroke-width="2"/>
    </svg>

    @if($pendingLeaveReview > 0)
      <span class="zv-tab-badge">{{ $pendingLeaveReview > 99 ? '99+' : $pendingLeaveReview }}</span>
    @endif
  </span>
  <span class="lbl">Leave</span>
</a>




<a class="tab {{ request()->routeIs('superadmin.support.status_analytics.*') ? 'active' : '' }}"
   href="{{ route('superadmin.support.status_analytics.index') }}"
   title="Status Analytics">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 19V5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M7 16l4-5 3 3 5-7" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M19 7v4h-4" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </span>
  <span class="lbl">Status Analytics</span>
</a>





        <a class="tab {{ request()->is('superadmin/transactions*') ? 'active' : '' }}"
           href="{{ url('superadmin/transactions') }}" title="Payments">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M3 6h18v12H3zM3 10h18"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <span class="lbl">Payments</span>
        </a>
<a class="tab {{ request()->routeIs('superadmin.analytics.index') ? 'active' : '' }}"
   href="{{ route('superadmin.analytics.index') }}" title="Analytics">
  <span class="ico">
    <svg viewBox="0 0 24 24">
      <path d="M4 19h16M7 16V10M12 16V5M17 16v-7"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </span>
  <span class="lbl">Analytics</span>
</a>

<a class="tab {{ request()->routeIs('superadmin.staff_analytics.*') ? 'active' : '' }}"
   href="{{ route('superadmin.staff_analytics.index') }}"
   title="Staff Analytics">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 19h16M7 16V10M12 16V5M17 16v-7"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </span>
  <span class="lbl">Staff Analytics</span>
</a>

        <a class="tab {{ request()->is('superadmin/services*') ? 'active' : '' }}"
           href="{{ url('superadmin/services') }}" title="Services">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M12 2l3 7h7l-5.5 4L19 21l-7-4-7 4 2.5-8L2 9h7z" fill="currentColor"/>
            </svg>
          </span>
          <span class="lbl">Services</span>
        </a>

        <a class="tab {{ request()->is('superadmin/categories*') ? 'active' : '' }}"
           href="{{ url('superadmin/categories') }}" title="Categories">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="currentColor"/>
            </svg>
          </span>
          <span class="lbl">Categories</span>
        </a>

        <a class="tab {{ request()->is('superadmin/coaches*') ? 'active' : '' }}"
           href="{{ url('superadmin/coaches') }}" title="Coaches">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm-8 9a8 8 0 0 1 16 0"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <span class="lbl">Coaches</span>
        </a>

        <a class="tab {{ request()->is('superadmin/clients*') ? 'active' : '' }}"
           href="{{ url('superadmin/clients') }}" title="Clients">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM3 20a7 7 0 0 1 14 0"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <span class="lbl">Clients</span>
        </a>

        <a class="tab {{ request()->is('superadmin/disputes*') ? 'active' : '' }}"
           href="{{ url('superadmin/disputes') }}" title="Disputes">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M12 22a10 10 0 1 1 10-10 10 10 0 0 1-10 10zm0-14v4m0 4h.01"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="lbl">Disputes</span>
        </a>

        <a class="tab {{ request()->is('superadmin/withdrawals*') ? 'active' : '' }}"
           href="{{ url('superadmin/withdrawals') }}" title="Withdrawals">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M12 19V5m0 0l-5 5m5-5l5 5M5 19h14"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="lbl">Withdrawals</span>
        </a>

        <a class="tab {{ request()->is('superadmin/email-subscriptions*') ? 'active' : '' }}"
           href="{{ url('superadmin/email-subscriptions') }}" title="Email Subscriptions">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M4 4h16v16H4zM4 4l8 8 8-8"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <span class="lbl">Email Subscription</span>
        </a>

        <a class="tab {{ request()->is('superadmin/settings*') ? 'active' : '' }}"
           href="{{ route('superadmin.settings.index') }}"
           title="Website Settings">
          <span class="ico">
            <svg viewBox="0 0 24 24">
              <path d="M12 15a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm7.94-2a7.94 7.94 0 0 0 0-2l2.06-1.6-2-3.46-2.44 1a8 8 0 0 0-1.73-1L15 2h-6l-.83 2.94a8 8 0 0 0-1.73 1l-2.44-1-2 3.46L4.06 11a7.94 7.94 0 0 0 0 2l-2.06 1.6 2 3.46 2.44-1a8 8 0 0 0 1.73 1L9 22h6l.83-2.94a8 8 0 0 0 1.73-1l2.44 1 2-3.46z"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </span>
          <span class="lbl">Website Setting</span>
        </a>

        <a class="tab {{ request()->is('superadmin/staff*') ? 'active' : '' }}"
   href="{{ route('superadmin.staff.index') }}">
  <span class="ico"><i class="bi bi-people"></i></span>
  <span class="lbl">Staff</span>
</a>


<a class="tab {{ request()->routeIs('superadmin.teams.*') ? 'active' : '' }}"
   href="{{ route('superadmin.teams.index') }}"
   title="Teams">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM3 20a7 7 0 0 1 14 0"
            fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M17 14a4 4 0 1 1 4 4"
            fill="none" stroke="currentColor" stroke-width="2"/>
    </svg>
  </span>
  <span class="lbl">Teams</span>
</a>


<a class="tab {{ request()->routeIs('superadmin.staff_chat.*') ? 'active' : '' }}"
   href="{{ route('superadmin.staff_chat.index') }}"
   title="Group Chat">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 5h16v10H8l-4 4z"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round"/>
    </svg>
  </span>
  <span class="lbl">Group Chat</span>
</a>

{{-- <a class="tab {{ request()->routeIs('superadmin.dm.*') ? 'active' : '' }}"
   href="{{ route('superadmin.dm.index') }}"
   title="DM Audit">
  <span class="ico">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 5h16v11H8l-4 4z"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round"/>
      <path d="M8 10h8M8 13h6"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round"/>
    </svg>
  </span>
  <span class="lbl">DM Audit</span>
</a> --}}



@php
  // show dot if there are open security events
  $secOpen = \App\Models\AdminSecurityEvent::where('status','open')->count();
@endphp

<a class="tab {{ request()->is('superadmin/security*') ? 'active' : '' }}"
   href="{{ route('superadmin.security.locked_staff') }}"
   title="Security">
  <span class="ico" style="position:relative">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 2l8 4v6c0 5-3.4 9.4-8 10-4.6-.6-8-5-8-10V6l8-4z"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <path d="M9 12l2 2 4-5"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>

    @if($secOpen > 0)
      <span class="zv-tabdot" title="{{ $secOpen }} open security event(s)"></span>
    @endif
  </span>
  <span class="lbl">Security</span>
</a>

      </nav>

      <!-- Right-side actions -->
      <div class="actions">
        {{-- <div class="btn-wrap">
          <button class="icon" id="bellBtn" aria-label="Notifications" type="button">
            <span class="dot"></span>
            <svg width="18" height="18" viewBox="0 0 24 24">
              <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"
                    fill="none" stroke="currentColor" stroke-width="2"/>
              <path d="M13.7 21a2 2 0 01-3.4 0"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>
          <div class="dropdown right" id="bellMenu">
            <span class="dd-title">Notifications</span>
            <a href="#">💳 Payment received · $60</a>
            <a href="#">👤 New coach signup</a>
            <a href="#">📅 Booking cancelled</a>
          </div>
        </div> --}}

        {{-- <button class="icon" id="themeBtn" aria-label="Theme" type="button">
          <svg width="18" height="18" viewBox="0 0 24 24">
            <path d="M21 12.8A9 9 0 1111.2 3 7 7 0 0021 12.8z"
                  fill="none" stroke="currentColor" stroke-width="2"/>
          </svg>
        </button> --}}

        <div class="btn-wrap">
          <button class="avatar" id="profBtn" type="button">
            {{ $initial }}
          </button>
          <div class="dropdown right" id="profMenu">
            <a href="{{ url('superadmin/profile') }}">Profile</a>
            {{-- <a href="{{ url('superadmin/settings') }}">Settings</a> --}}
            <form method="post" action="{{ route('superadmin.logout') }}">
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
     <form id="zvDeleteImageForm" method="POST" style="display:none;">
  @csrf
</form>
  </main>

  <script>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  @stack('scripts')
  <script src="{{ asset('assets/js/zv-image-uploader.js') }}"></script>
</body>
</html>
