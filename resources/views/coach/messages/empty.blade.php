{{-- resources/views/coach/messages/empty.blade.php --}}
@extends('layouts.role-dashboard')
@section('title', __('Messages'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/client.css') }}">
@endpush

@section('role-content')
<div class="zv-dashboard">

  {{-- Hero (same as coach home) --}}
  <section class="zv-profile-hero">
    <div class="container-narrow">
      <div class="zv-hero-card">
        <div class="zv-hero-row">
          <div class="zv-hero-left">
            <div class="zv-avatar-wrap">
              <img class="zv-avatar-img"
                   src="{{ auth()->user()?->avatar_path ? asset('storage/'.auth()->user()->avatar_path) : asset('assets/user.png') }}"
                   alt="">
            </div>

            <div class="zv-hero-id">
              <div class="zv-eyebrow">{{ __('Coach') }}</div>
              <h1 class="zv-name">{{ auth()->user()->full_name ?: auth()->user()->email }}</h1>

              <div class="zv-meta">
                <span class="zv-chip">{{ __('Coach') }}</span>
                <span class="zv-dot"></span>
                <span class="zv-online">
                  <span class="zv-online-dot {{ auth()->user()->is_online ? '' : 'offline' }}"></span>
                  {{ auth()->user()->is_online ? __('Online') : __('Offline') }}
                </span>
                @if(auth()->user()->city || auth()->user()->country)
                  <span class="zv-dot"></span>
                  <span class="zv-loc"><i class="bi bi-geo-alt me-1"></i>
                    {{ trim((auth()->user()->city ? auth()->user()->city.', ' : '').(auth()->user()->country ?? '')) }}
                  </span>
                @endif
                <span class="zv-dot d-none d-md-inline"></span>
                <span class="zv-joined d-none d-md-inline">
                  <i class="bi bi-calendar-week me-1"></i>{{ __('Joined') }} {{ auth()->user()->created_at?->format('M Y') }}
                </span>
              </div>

              <div class="zv-about-extra">
                <div class="zv-about-item">
                  <div class="zv-about-label">{{ __('Description') }}</div>
                  <div class="zv-about-text">
                    {{ auth()->user()->description ?: __('No description provided yet.') }}
                  </div>
                </div>
                <div class="zv-about-item">
                  <div class="zv-about-label">{{ __('Language') }}</div>
                  <div class="zv-about-text">
                    @php $langs = (array) (auth()->user()->languages ?? []); @endphp
                    {{ count($langs) ? implode(', ', $langs) : __('Not set') }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="zv-hero-actions">
            <a href="{{ route('coach.profile.edit') }}" class="btn-3d btn-plain">
              <i class="bi bi-pencil-square me-1"></i>{{ __('Edit') }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Wallet strip (optional, same as dashboard) --}}
  <div class="zv-wallet-strip">
    <div class="container-narrow zv-wallet-row">
      <div class="zv-wallet-left">
        <i class="bi bi-wallet2 me-2"></i>
        <span class="zv-wallet-label">{{ __('Wallet Balance') }}:</span>
        <span class="zv-wallet-amount">
          {{ __('$') }}{{ number_format((float) (auth()->user()->wallet_balance ?? 0), 2) }}
        </span>
      </div>
      <div class="zv-wallet-right">
        <a href="{{ route('coach.wallet.withdraw') }}"
           class="btn-3d btn-plain"
           @if(auth()->user()?->is_approved !== true) aria-disabled="true" onclick="return false;" @endif>
          <i class="bi bi-cash-coin me-1"></i>{{ __('Withdraw funds') }}
        </a>
      </div>
    </div>
  </div>

  {{-- Main grid --}}
  <div class="container-narrow zv-main-grid">
    <aside class="zv-sidenav">
      <nav class="zv-sidenav-inner">
        <a href="{{ route('coach.home') }}"           class="zv-sidenav-link {{ request()->routeIs('coach.home') ? 'active':'' }}"><i class="bi bi-house"></i><span>{{ __('Dashboard') }}</span></a>
        <a href="{{ route('coach.profile.edit') }}"   class="zv-sidenav-link {{ request()->routeIs('coach.profile.*') ? 'active':'' }}"><i class="bi bi-person-gear"></i><span>{{ __('Edit Profile') }}</span></a>
        <a href="{{ route('coach.calendar.index') }}" class="zv-sidenav-link {{ request()->routeIs('coach.calendar*') ? 'active':'' }}"><i class="bi bi-calendar-event"></i><span>{{ __('Calendar') }}</span></a>
        <a href="{{ route('coach.services.index') }}" class="zv-sidenav-link {{ request()->routeIs('coach.services.*') ? 'active':'' }}"><i class="bi bi-bag"></i><span>{{ __('Services') }}</span></a>
        <a href="{{ route('coach.bookings') }}"       class="zv-sidenav-link {{ request()->routeIs('coach.bookings') ? 'active':'' }}"><i class="bi bi-book"></i><span>{{ __('Bookings') }}</span></a>
        <a href="{{ route('coach.messages.index') }}" class="zv-sidenav-link active">
          <i class="bi bi-chat-dots"></i><span>{{ __('Messages') }}</span>
        </a>
        <a href="{{ route('coach.disputes.index') }}"       class="zv-sidenav-link {{ request()->routeIs('coach.disputes') ? 'active':'' }}"><i class="bi bi-flag"></i><span>{{ __('Disputes') }}</span></a>
        <a href="{{ route('coach.qualifications') }}" class="zv-sidenav-link {{ request()->routeIs('coach.qualifications') ? 'active':'' }}"><i class="bi bi-mortarboard"></i><span>{{ __('Qualifications') }}</span></a>
      </nav>
    </aside>

    <main class="zv-main">
      <div class="zv-messages-empty text-center py-5">
        <h3 class="mb-2">{{ __('No messages yet') }}</h3>
        <p class="text-muted mb-0">{{ __('Clients will appear here once they contact you from a service page.') }}</p>
      </div>
    </main>
  </div>

  {{-- Approval overlay --}}
  @if(auth()->user() && auth()->user()->role === 'coach' && !auth()->user()->is_approved)
    @include('coach.partials.approval-overlay')
  @endif

</div>
@endsection
