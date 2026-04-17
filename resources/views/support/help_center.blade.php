@extends('layouts.app')
@section('title', __('Help Center'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/help_center.css') }}">
@endpush

@php
  $user = auth()->user();
  $activeRole = session('active_role') ?: ($user->role ?? null);
  $activeRole = in_array($activeRole, ['client','coach']) ? $activeRole : null;

  $showSupport = $user && $activeRole; // only client/coach
@endphp

@section('content')
<div class="zv-hc">

  {{-- HERO --}}
  <section class="zv-hc-hero">
    <div class="zv-hc-hero__bg"></div>
    <div class="zv-hc-hero__overlay"></div>

    <div class="container zv-hc-hero__inner">
      <div class="zv-hc-hero__top">
        <div class="zv-hc-badge">
          <i class="bi bi-info-circle"></i>
          <span>{{ __('ZAIVIAS Help Center') }}</span>
        </div>

        {{-- <a class="zv-hc-quicklink" href="#hc-popular">
          <i class="bi bi-stars"></i>
          <span>{{ __('Popular') }}</span>
        </a> --}}
      </div>

      <h1 class="zv-hc-hero__title">{{ __('How can we help?') }}</h1>
      <p class="zv-hc-hero__sub">
        {{ __('Search articles, browse topics, or contact support — fast and secure.') }}
      </p>

      {{-- Search --}}
      <div class="zv-hc-search" role="search">
        <i class="bi bi-search zv-hc-search__icon"></i>

        <input id="hcSearch"
               type="search"
               class="zv-hc-search__input"
               placeholder="{{ __('Search the Help Center…') }}"
               autocomplete="off">

        <button type="button" class="zv-hc-search__btn" id="hcClear">
          {{ __('Clear') }}
        </button>
      </div>

      {{-- Trust mini --}}
      <div class="zv-hc-stats">
        <div class="zv-hc-stat">
          <div class="zv-hc-stat__ic"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="zv-hc-stat__t">{{ __('Typical response time') }}</div>
            <div class="zv-hc-stat__s">{{ __('Within 24–48 hours') }}</div>
          </div>
        </div>

        <div class="zv-hc-stat">
          <div class="zv-hc-stat__ic"><i class="bi bi-shield-check"></i></div>
          <div>
            <div class="zv-hc-stat__t">{{ __('Safe & secure') }}</div>
            <div class="zv-hc-stat__s">{{ __('Reporting and support tools') }}</div>
          </div>
        </div>

        <div class="zv-hc-stat">
          <div class="zv-hc-stat__ic"><i class="bi bi-bag-check"></i></div>
          <div>
            <div class="zv-hc-stat__t">{{ __('Bookings') }}</div>
            <div class="zv-hc-stat__s">{{ __('Reschedule, cancel, refunds') }}</div>
          </div>
        </div>
      </div>

    </div>
  </section>

  {{-- MAIN --}}
  <section class="container zv-hc-main">

    {{-- Topics --}}
    <div class="zv-hc-section">
      <div class="zv-hc-section__head">
        <div>
          <h2 class="zv-hc-h2">{{ __('Browse by topic') }}</h2>
          <p class="zv-hc-muted">{{ __('Choose a category to get started.') }}</p>
        </div>

        
      </div>

      <div class="zv-hc-grid" id="hcCards">
        <a class="zv-hc-card" href="#hc-bookings" data-keywords="booking reschedule cancel refund reservation">
          <div class="zv-hc-card__icon"><i class="bi bi-calendar2-check"></i></div>
          <div class="zv-hc-card__title">{{ __('Bookings') }}</div>
          <div class="zv-hc-card__sub">{{ __('Reserve, reschedule, cancellations, refunds') }}</div>
          <div class="zv-hc-card__meta">{{ __('Most viewed') }}</div>
        </a>

        <a class="zv-hc-card" href="#hc-payments" data-keywords="payment wallet payout billing invoices card">
          <div class="zv-hc-card__icon"><i class="bi bi-credit-card"></i></div>
          <div class="zv-hc-card__title">{{ __('Payments & Wallet') }}</div>
          <div class="zv-hc-card__sub">{{ __('Charges, wallet credits, invoices') }}</div>
          <div class="zv-hc-card__meta">{{ __('Billing') }}</div>
        </a>

        <a class="zv-hc-card" href="#hc-coaching" data-keywords="coach client session program chat messaging">
          <div class="zv-hc-card__icon"><i class="bi bi-person-heart"></i></div>
          <div class="zv-hc-card__title">{{ __('Coaching & Sessions') }}</div>
          <div class="zv-hc-card__sub">{{ __('How sessions work, programs, messaging') }}</div>
          <div class="zv-hc-card__meta">{{ __('Sessions') }}</div>
        </a>

        <a class="zv-hc-card" href="#hc-account" data-keywords="account profile verification kyc password email">
          <div class="zv-hc-card__icon"><i class="bi bi-person-circle"></i></div>
          <div class="zv-hc-card__title">{{ __('Account & Profile') }}</div>
          <div class="zv-hc-card__sub">{{ __('Login, profile, verification, settings') }}</div>
          <div class="zv-hc-card__meta">{{ __('Security') }}</div>
        </a>

        <a class="zv-hc-card" href="#hc-safety" data-keywords="safety report abuse harassment fraud trust">
          <div class="zv-hc-card__icon"><i class="bi bi-shield-lock"></i></div>
          <div class="zv-hc-card__title">{{ __('Safety & Reporting') }}</div>
          <div class="zv-hc-card__sub">{{ __('Report issues and stay protected') }}</div>
          <div class="zv-hc-card__meta">{{ __('Trust') }}</div>
        </a>

        <a class="zv-hc-card" href="#hc-policies" data-keywords="policies terms privacy guidelines cancellation">
          <div class="zv-hc-card__icon"><i class="bi bi-file-earmark-text"></i></div>
          <div class="zv-hc-card__title">{{ __('Policies') }}</div>
          <div class="zv-hc-card__sub">{{ __('Guidelines, terms, cancellations') }}</div>
          <div class="zv-hc-card__meta">{{ __('Rules') }}</div>
        </a>
      </div>

      <div class="zv-hc-noresults d-none" id="hcNoResults">
        <div class="zv-hc-noresults__title">{{ __('No results') }}</div>
        <div class="zv-hc-noresults__sub">{{ __('Try a different search term.') }}</div>
      </div>
    </div>

    {{-- Popular --}}
    <div class="zv-hc-section" id="hc-popular">
      <div class="zv-hc-section__head">
        <div>
          <h2 class="zv-hc-h2">{{ __('Popular articles') }}</h2>
          <p class="zv-hc-muted">{{ __('Quick answers to common questions.') }}</p>
        </div>
      </div>

      <div class="zv-hc-list" id="hcArticles">
        <a class="zv-hc-row" href="#hc-bookings" data-keywords="cancel booking refund reschedule">
          <div class="zv-hc-row__left">
            <div class="zv-hc-row__title">{{ __('How do I cancel or reschedule a booking?') }}</div>
            <div class="zv-hc-row__sub">{{ __('Learn what happens to charges and refunds.') }}</div>
          </div>
          <i class="bi bi-chevron-right"></i>
        </a>

        <a class="zv-hc-row" href="#hc-payments" data-keywords="wallet credits refund payment split">
          <div class="zv-hc-row__left">
            <div class="zv-hc-row__title">{{ __('How wallet credits and refunds work') }}</div>
            <div class="zv-hc-row__sub">{{ __('Wallet-only, mixed payments, and timelines.') }}</div>
          </div>
          <i class="bi bi-chevron-right"></i>
        </a>

        <a class="zv-hc-row" href="#hc-coaching" data-keywords="message coach client inbox chat">
          <div class="zv-hc-row__left">
            <div class="zv-hc-row__title">{{ __('Messaging a coach or a client') }}</div>
            <div class="zv-hc-row__sub">{{ __('Where to find your inbox and conversation rules.') }}</div>
          </div>
          <i class="bi bi-chevron-right"></i>
        </a>

        <a class="zv-hc-row" href="#hc-account" data-keywords="verification kyc coach profile">
          <div class="zv-hc-row__left">
            <div class="zv-hc-row__title">{{ __('Account verification and profile tips') }}</div>
            <div class="zv-hc-row__sub">{{ __('Keep your profile accurate and trusted.') }}</div>
          </div>
          <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>

    {{-- FAQ --}}
    <div class="zv-hc-section" id="hc-faq">
      <div class="zv-hc-section__head">
        <div>
          <h2 class="zv-hc-h2">{{ __('Frequently asked questions') }}</h2>
          <p class="zv-hc-muted">{{ __('Clear answers in one place.') }}</p>
        </div>
      </div>

      <div class="accordion zv-hc-acc" id="hcFaq">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
              {{ __('When do I get a reply from support?') }}
            </button>
          </h2>
          <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#hcFaq">
            <div class="accordion-body">
              {{ __('Most messages are answered within 24–48 hours. During peak periods it can take a bit longer, but we reply in order of urgency.') }}
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
              {{ __('Can I contact support as a coach and as a client separately?') }}
            </button>
          </h2>
          <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#hcFaq">
            <div class="accordion-body">
              {{ __('Yes. If you switch roles, your support conversation stays separated by role to avoid confusion.') }}
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
              {{ __('What should I include in my support message?') }}
            </button>
          </h2>
          <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#hcFaq">
            <div class="accordion-body">
              <ul class="mb-0">
                <li>{{ __('Booking / reservation ID (if applicable)') }}</li>
                <li>{{ __('What you expected vs what happened') }}</li>
                <li>{{ __('Screenshots if relevant') }}</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Contact block --}}
   

    {{-- Anchors sections (simple placeholders) --}}
    <div class="zv-hc-section zv-hc-anchors">
      <div class="zv-hc-anchor" id="hc-bookings">
        <h3>{{ __('Bookings') }}</h3>
        <p class="zv-hc-muted">{{ __('Manage bookings, reschedule, cancellations and refunds.') }}</p>
      </div>

      <div class="zv-hc-anchor" id="hc-payments">
        <h3>{{ __('Payments & Wallet') }}</h3>
        <p class="zv-hc-muted">{{ __('Learn about charges, wallet credits, payouts and invoices.') }}</p>
      </div>

      <div class="zv-hc-anchor" id="hc-coaching">
        <h3>{{ __('Coaching & Sessions') }}</h3>
        <p class="zv-hc-muted">{{ __('Sessions, programs, and how messaging works.') }}</p>
      </div>

      <div class="zv-hc-anchor" id="hc-account">
        <h3>{{ __('Account & Profile') }}</h3>
        <p class="zv-hc-muted">{{ __('Login, profile settings, verification, and security.') }}</p>
      </div>

      <div class="zv-hc-anchor" id="hc-safety">
        <h3>{{ __('Safety & Reporting') }}</h3>
        <p class="zv-hc-muted">{{ __('Report an issue and keep your experience safe.') }}</p>
      </div>

      <div class="zv-hc-anchor" id="hc-policies">
        <h3>{{ __('Policies') }}</h3>
        <p class="zv-hc-muted">{{ __('Terms, guidelines, and cancellation policy information.') }}</p>
      </div>
    </div>
{{-- Bottom-right Support button (NOT floating, only one) --}}
@if($showSupport)
  <div class="zv-hc-supportbar">
    <a href="{{ route('support.conversation.index') }}" class="zv-hc-supportbtn">
      <img src="{{ asset('assets/shield_secure.png') }}" class="zv-hc-supportbtn__ic" alt="">
      <span>{{ __('Support') }}</span>
    </a>
  </div>
@endif

  </section>

  {{-- FLOATING SUPPORT (ONE button only) --}}
  

</div>
@endsection

@push('scripts')
<script>
  (function () {
    const input = document.getElementById('hcSearch');
    const clear = document.getElementById('hcClear');
    const cards = Array.from(document.querySelectorAll('#hcCards .zv-hc-card'));
    const rows  = Array.from(document.querySelectorAll('#hcArticles .zv-hc-row'));
    const empty = document.getElementById('hcNoResults');

    function norm(s){ return (s || '').toLowerCase().trim(); }

    function applyFilter() {
      const q = norm(input.value);
      let shown = 0;

      const match = (el) => {
        const kw = norm(el.getAttribute('data-keywords'));
        const text = norm(el.innerText);
        return !q || kw.includes(q) || text.includes(q);
      };

      cards.forEach(el => {
        const ok = match(el);
        el.classList.toggle('d-none', !ok);
        if (ok) shown++;
      });

      rows.forEach(el => {
        const ok = match(el);
        el.classList.toggle('d-none', !ok);
      });

      empty && empty.classList.toggle('d-none', shown > 0);
    }

    input && input.addEventListener('input', applyFilter);
    clear && clear.addEventListener('click', function () {
      input.value = '';
      applyFilter();
      input.focus();
    });

    applyFilter();
  })();
</script>
@endpush
