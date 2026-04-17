@extends('layouts.app')

@php
  $u = auth()->user()?->loadMissing('coachProfile');

  $activeRole = strtolower((string) session('active_role', 'client'));
  $isCoach = $activeRole === 'coach';

  $coachAppState = null;

  if ($u?->is_coach && $u?->coachProfile) {
      $st = $u->coachProfile->application_status; // draft|submitted|approved|rejected
      if (in_array($st, ['submitted','rejected'], true)) {
          $coachAppState = $st;
      }
  }
@endphp

@section('content')
@include('coach.partials.kyc-overlay')
{{-- <x-toastify /> --}}










  {{-- ===== About / Profile Hero ===== --}}
  <section class="zv-profile-hero">
    <div class="container-narrow">
      <div class="zv-hero-card">
        <div class="zv-hero-row">

          <div class="zv-hero-left">
            <div class="zv-avatar-wrap">
              <img class="zv-avatar-img"
                   src="{{ $u?->avatar_path ? asset('storage/'.$u->avatar_path) : asset('assets/user.png') }}"
                   alt="{{ $u?->full_name ?? 'User' }}">
              <span class="zv-avatar-fallback">
                {{ strtoupper(substr($u?->full_name ?: $u?->email ?: 'U', 0, 1)) }}
              </span>
            </div>

            <div class="zv-hero-id">
              <div class="zv-eyebrow">{{ __('About Me') }}</div>
              <h1 class="zv-name">{{ $u?->full_name ?: $u?->email }}</h1>

              <div class="zv-meta">
                <span class="zv-chip">{{ $isCoach ? __('Coach Mode') : __('Client Mode') }}</span>

                {{-- <span class="zv-dot"></span>
                <span class="zv-online">
                  <span class="zv-online-dot"></span>{{ __('Online') }}
                </span> --}}

                @if($u?->city || $u?->country)
                  <span class="zv-dot text-dark"></span>
                  <span class="zv-loc text-dark"><i class="bi bi-geo-alt me-1"></i>
                    {{ trim(($u?->city ? $u->city.', ' : '').($u?->country ?? '')) }}
                  </span>
                @endif

                <span class="zv-dot d-none d-md-inline"></span>
                <span class="zv-joined d-none d-md-inline text-dark">
                  <i class="bi bi-calendar-week me-1 text-dark"></i>{{ __('Joined') }} {{ $u?->created_at?->format('M Y') }}
                </span>
              </div>

              {{-- Embedded “About” extras --}}
              <div class="zv-about-extra">
                <div class="zv-about-item">
                  <div class="zv-about-label">{{ __('Description') }}</div>
                  <div class="zv-about-text">
                    {{ $u?->description ?: __('No description provided yet.') }}
                  </div>
                </div>
                <div class="zv-about-item">
                  <div class="zv-about-label">{{ __('Language') }}</div>
                  <div class="zv-about-text">
                    @php $langs = (array) ($u?->languages ?? []); @endphp
                    {{ count($langs) ? implode(', ', $langs) : __('Not set') }}
                  </div>
                </div>
              </div>

            </div>
          </div>

          {{-- Actions --}}
          <div class="zv-hero-actions">
            <a href="{{ route('profile.edit') }}" class="btn-3d btn-plain" aria-label="{{ __('Edit profile') }}">
              <i class="bi bi-pencil-square me-1"></i>{{ __('Edit') }}
            </a>

            {{-- <a href="{{ route('support.conversation.index') }}"
               class="btn-3d btn-primary"
               aria-label="{{ __('Contact support') }}">
              <i class="bi bi-life-preserver me-1"></i>{{ __('Support') }}
            </a> --}}
          </div>

        </div>
      </div>
    
  </section>

  {{-- ===== Wallet strip (role-based) ===== --}}
  @if(!$isCoach)
    {{-- Client wallet strip --}}
    <div class="zv-wallet-strip">
      <div class="container-narrow zv-wallet-row">
        <div class="zv-wallet-left">
          <i class="bi bi-wallet2 me-2"></i>
          <span class="zv-wallet-label">{{ __('Wallet Credit') }}:</span>
          <span class="zv-wallet-amount">
            {{ __('$') }}{{ number_format($u?->platform_credit ?? 0, 2) }} USD
          </span>
          {{-- <span class="small text-muted ms-2">{{ __('(non-withdrawable)') }}</span> --}}
        </div>

        <div class="zv-wallet-right">
          <a href="" class="btn-3d btn-plain" aria-disabled="true" onclick="return false;">
            <i class="bi bi-info-circle me-1"></i>{{ __('View wallet') }}
          </a>
        </div>
      </div>
    </div>
  @else
  {{-- Coach withdrawable strip --}}
  @php
    // ✅ correct: you have withdrawable_minor in users table
    $withdrawable = ((int)($u?->withdrawable_minor ?? 0)) / 100;
  @endphp

  <div class="zv-wallet-strip">
    <div class="container-narrow zv-wallet-row">
      <div class="zv-wallet-left">
        <i class="bi bi-cash-coin me-2"></i>
        <span class="zv-wallet-label">{{ __('Balance') }}:</span>
        <span class="zv-wallet-amount">
          {{ __('$') }}{{ number_format($withdrawable, 2) }} USD
        </span>

@if(($coachAppState ?? null) === 'submitted')
  <span class="small text-muted ms-2">{{ __('(application under review)') }}</span>
@elseif(($u?->coachProfile?->application_status ?? null) !== 'approved')
  <span class="small text-muted ms-2">{{ __('(withdrawals locked until approved)') }}</span>
@endif
      <div class="zv-wallet-right">
       @php $coachApproved = (($u?->coachProfile?->application_status ?? null) === 'approved'); @endphp

@if($coachApproved)
  <a href="{{ route('coach.withdraw.index') }}" class="btn-3d btn-plain">
    <i class="bi bi-arrow-up-right-circle me-1"></i>{{ __('Withdraw') }}
  </a>
@else
  <a href="{{ route('coach.application.show') }}" class="btn-3d btn-plain">
    <i class="bi bi-shield-check me-1"></i>{{ __('Complete Application') }}
  </a>
@endif
      </div>
    </div>
  </div>
@endif



  {{-- ===== Main grid: Sidebar + Content ===== --}}
  <div class="container-narrow zv-main-grid">

    <aside class="zv-sidenav" aria-label="Profile navigation">
      <nav class="zv-sidenav-inner">

  @if(!$isCoach)
    {{-- ========== CLIENT NAV ========== --}}
    <a href="{{ route('client.home') }}"
       class="zv-sidenav-link {{ request()->routeIs('client.home') ? 'active' : '' }}">
      <i class="bi bi-house"></i><span>{{ __('Dashboard') }}</span>
    </a>

    <a href="{{ route('client.messages.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('client.messages') || request()->routeIs('client.messages.*') ? 'active' : '' }}">

      <i class="bi bi-chat-dots"></i><span>{{ __('Messages') }}</span>
    </a>

    {{-- <a href="{{ route('client.bookings.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('client.bookings.*') ? 'active' : '' }}">
      <i class="bi bi-journal-check"></i><span>{{ __('Bookings') }}</span>
    </a> --}}

    {{-- <a href="{{ route('client.cancellations') }}"
       class="zv-sidenav-link {{ request()->routeIs('client.cancellations') ? 'active' : '' }}">
      <i class="bi bi-x-circle"></i><span>{{ __('Cancellations') }}</span>
    </a> --}}

    <a href="{{ route('client.disputes.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('client.disputes.*') ? 'active' : '' }}">
      <i class="bi bi-flag"></i><span>{{ __('Disputes') }}</span>
    </a>

  @else
    {{-- ========== COACH NAV ========== --}}
    <a href="{{ route('coach.home') }}"
       class="zv-sidenav-link {{ request()->routeIs('coach.home') ? 'active' : '' }}">
      <i class="bi bi-house"></i><span>{{ __('Dashboard') }}</span>
    </a>

    <a href="{{ route('coach.messages.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('coach.messages.*') ? 'active' : '' }}">
      <i class="bi bi-chat-dots"></i><span>{{ __('Messages') }}</span>
    </a>

    {{-- IMPORTANT: your route name is coach.bookings (not coach.bookings.index) --}}
    <a href="{{ route('coach.bookings') }}"
       class="zv-sidenav-link {{ request()->routeIs('coach.bookings') || request()->routeIs('coach.bookings.*') ? 'active' : '' }}">
      <i class="bi bi-journal-check"></i><span>{{ __('Bookings') }}</span>
    </a>

    <a href="{{ route('coach.services.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('coach.services.*') ? 'active' : '' }}">
      <i class="bi bi-briefcase"></i><span>{{ __('Services') }}</span>
    </a>

    <a href="{{ route('coach.calendar.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('coach.calendar.*') ? 'active' : '' }}">
      <i class="bi bi-calendar3"></i><span>{{ __('Calendar') }}</span>
    </a>

    <a href="{{ route('coach.disputes.index') }}"
       class="zv-sidenav-link {{ request()->routeIs('coach.disputes.*') ? 'active' : '' }}">
      <i class="bi bi-flag"></i><span>{{ __('Disputes') }}</span>
    </a>
  @endif

</nav>

    </aside>

    <main class="zv-main">
      @yield('role-content')
    </main>

  </div>
</div>



@include('partials.footer')
@endsection
