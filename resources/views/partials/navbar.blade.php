{{-- =========================
   ZAIVIAS PRO NAVBAR (LIGHT)
   - White navbar + black text
   - Mobile: ONE toggle only (offcanvas)
   - Offcanvas: Language accordion (collapsed by default) + scrollable list
   ========================= --}}
@php
  $user = auth()->user()?->loadMissing('coachProfile');
  $activeRole = strtolower((string) session('active_role', 'client'));

  $isCoachAccount = (bool) ($user?->is_coach ?? false);
  $coachAppStatus = (string) ($user?->coachProfile?->application_status ?? 'draft'); // draft|submitted|approved|rejected
  $hasEverSubmitted = in_array($coachAppStatus, ['submitted', 'approved', 'rejected'], true);
@endphp





<nav class="navbar navbar-expand-lg sticky-top py-2 zv-main-navbar-light">
  <div class="container-fluid px-3 px-md-4 px-lg-5">

      {{-- Middle navigation --}}
    

    {{-- Brand --}}
    <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
      <img class="zv-logo-light" src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS">
    </a>

    <div class="d-none d-lg-flex mx-auto gap-4">
      <a class="nav-link zv-nav-mid-light fw-semibold" href="{{ route('services.index') }}">{{ __('All Services') }}</a>
      {{-- <a class="nav-link zv-nav-mid-light" href="{{ route('services.catalog') }}">{{ __('Browse Coaches') }}</a> --}}
    </div>


    {{-- Right cluster --}}
    <div class="d-flex align-items-center ms-auto gap-2">

      {{-- Desktop: Language dropdown --}}
      <div class="dropdown me-1 me-md-2 d-none d-lg-block">
        @php
          $current      = $gtCurrent ?? 'en';
          $currentLabel = $gtCurrentLabel ?? 'English';
        @endphp

        <button class="btn rounded-pill px-3 d-flex align-items-center gap-2 zv-lang-btn-light fw-semibold"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" id="langDropdownBtn">
          <i class="bi bi-globe2"></i>
          <span  id="gtCurrentLabel">{{ $currentLabel }}</span>
          <i class="bi bi-chevron-down small fw-semibold"></i>
        </button>

        <div class="dropdown-menu dropdown-menu-end p-2 language-menu-light" style="min-width: 260px;">
          <div class="px-2 pb-2">
            <input type="search" class="form-control form-control-sm"
                   placeholder="{{ __('Search Language') }}" id="langSearch">
          </div>

          <div class="lang-list" id="gtLangList" style="max-height: 260px; overflow:auto;">
            @foreach($gtLanguages as $code => [$label, $rtl])
              <button class="dropdown-item d-flex justify-content-between align-items-center lang-pick"
                      data-locale="{{ $code }}" data-label="{{ $label }}">
                <span>{{ $label }}</span>
                @if($current === $code)
                  <i class="bi bi-check2"></i>
                @endif
              </button>
            @endforeach
          </div>
        </div>
      </div>

      {{-- Desktop: Account dropdown --}}
      <div class="dropdown d-none d-lg-block rounded-pill">
        <button class="user-bubble-light btn d-flex align-items-center px-3 gap-2 rounded-pill"
                data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bi bi-person-circle"></i>

          @auth
            <span class="zv-role-pill-light {{ $activeRole === 'coach' ? 'is-coach' : 'is-client' }}">
              {{ $activeRole === 'coach' ? __('Coach') : __('Client') }}
            </span>
          @endauth

          <i class="bi bi-chevron-down small"></i>
        </button>

        <ul class="dropdown-menu dropdown-menu-end zv-user-dropdown-light">
          @guest
            <li><a class="dropdown-item" href="{{ route('login') }}">{{ __('Log in') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('register') }}">{{ __('Sign up') }}</a></li>
          @else
            <li>
             <a class="dropdown-item" href="{{ route('profile.edit') }}">
  <i class="bi bi-person me-2"></i>{{ __('Profile') }}
</a>

            </li>

            {{-- Always show both entries --}}
           @if($activeRole === 'client')
  <li>
    <a class="dropdown-item" href="{{ route('client.home') }}">
      <i class="bi bi-calendar-check me-2"></i>{{ __('My Bookings') }}
    </a>
  </li>

  <li>
    <a class="dropdown-item" href="{{ route('favorites.index') }}">
      <i class="bi bi-heart me-2"></i>{{ __('My Favorites') }}
    </a>
  </li>
@endif


            <li><hr class="dropdown-divider"></li>

            {{-- Role actions (desktop dropdown) --}}
            <li class="px-3 py-2">
            @if(!$isCoachAccount)
  <a class="zv-role-cta-light w-100" href="{{ route('coach.application.show') }}">
    <span class="zv-role-cta__title">{{ __('Become a Coach') }}</span>
  </a>

@elseif(!$hasEverSubmitted || $coachAppStatus === 'rejected')
  <a class="zv-role-cta-light w-100" href="{{ route('coach.application.show') }}">
    <span class="zv-role-cta__title">
      {{ $coachAppStatus === 'rejected' ? __('Application rejected') : __('Complete Application') }}
    </span>
  </a>

@elseif($coachAppStatus === 'submitted')
  <div class="zv-role-pending-light w-100">
    <div class="d-flex align-items-center gap-2">
      <span class="zv-dot-light"></span>
      <div>
        <div class="fw-bold">{{ __('Under Review') }}</div>
      </div>
    </div>
  </div>

@else
                {{-- Approved: show toggle --}}
                <div class="zv-role-toggle-light">
                  <div class="zv-role-toggle__label">{{ __('Account Mode') }}</div>
                 <div class="zv-seg-light">
  <form method="POST" action="{{ route('role.switch') }}" class="zv-seg__item">
    @csrf
    <input type="hidden" name="role" value="client">

    <button type="submit" class="zv-seg__btn {{ $activeRole === 'client' ? 'is-active' : '' }}">
      <i class="bi bi-person"></i><span>{{ __('Client') }}</span>
    </button>

    <span class="zv-seg__active-logo" aria-hidden="true"></span>
  </form>

  <form method="POST" action="{{ route('role.switch') }}" class="zv-seg__item">
    @csrf
    <input type="hidden" name="role" value="coach">

    <button type="submit" class="zv-seg__btn {{ $activeRole === 'coach' ? 'is-active' : '' }}">
      <i class="bi bi-mortarboard"></i><span>{{ __('Coach') }}</span>
    </button>

    <span class="zv-seg__active-logo" aria-hidden="true"></span>
  </form>
</div>

                </div>
              @endif
            </li>

            <li><hr class="dropdown-divider"></li>

            <li>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item">
                  <i class="bi bi-box-arrow-right me-2"></i>{{ __('Log out') }}
                </button>
              </form>
            </li>
          @endguest
        </ul>
      </div>

      {{-- Mobile: ONE button only (offcanvas) --}}
      <button class="zv-mobile-one-light btn d-lg-none d-flex align-items-center gap-2"
              type="button"
              data-bs-toggle="offcanvas"
              data-bs-target="#mobileNav"
              aria-controls="mobileNav"
              aria-label="{{ __('Open menu') }}">
        <i class="bi bi-list"></i>
        @auth
          <span class="zv-mobile-one__text">
            {{ $activeRole === 'coach' ? __('Coach') : __('Client') }}
          </span>
        @else
          <span class="zv-mobile-one__text">{{ __('Menu') }}</span>
        @endauth
      </button>

    </div>
  </div>
</nav>

{{-- =========================
   Offcanvas (mobile)
   ========================= --}}
<div class="offcanvas offcanvas-start zv-offcanvas-light" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <img src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS" class="zv-logo-sm-light">
      {{-- <h5 class="offcanvas-title mb-0" id="mobileNavLabel">{{ __('Menu') }}</h5> --}}
    </div>
    <button type="button" class="btn-close btn-dark" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
  </div>

  <div class="offcanvas-body d-flex flex-column">

    {{-- <a class="nav-link mb-2" href="{{ route('home') }}">{{ __('Home') }}</a>
    <a class="nav-link mb-2" href="{{ route('services.index') }}">{{ __('Services') }}</a>

    <hr> --}}

    

    {{-- Language accordion (collapsed by default) --}}
    <div class="accordion zv-acc" id="mobileAcc">
      <div class="accordion-item zv-acc__item rounded-pill">
        <h2 class="accordion-header" id="headingLang">
          <button class="accordion-button collapsed zv-acc__btn rounded-pill"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapseLang"
                  aria-expanded="false"
                  aria-controls="collapseLang">
            <span class="d-flex align-items-center gap-2">
              <i class="bi bi-globe2"></i>
              <span>{{ __('Language') }}</span>
            </span>
          </button>
        </h2>

        <div id="collapseLang" class="accordion-collapse collapse"
             aria-labelledby="headingLang" data-bs-parent="#mobileAcc">
          <div class="accordion-body pt-2">
            <input type="search" class="form-control form-control-sm mb-2"
                   placeholder="{{ __('Search language') }}" id="langSearchMobile">

            <div id="gtLangListMobile" class="zv-lang-scroll">
              @foreach($gtLanguages as $code => [$label, $rtl])
                <button class="zv-mobile-item lang-pick"
                        type="button"
                        data-locale="{{ $code }}"
                        data-label="{{ $label }}">
                  <span>{{ $label }}</span>
                  @if(($gtCurrent ?? 'en') === $code)
                    <i class="bi bi-check2"></i>
                  @endif
                </button>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>

    @auth
      <hr>

      <div class="zv-mobile-panel-light mb-3">
       <a class="zv-mobile-item" href="{{ route('profile.edit') }}">
  <span><i class="bi bi-person me-2"></i>{{ __('Profile') }}</span>
  <i class="bi bi-chevron-right"></i>
</a>


        {{-- Always show both --}}
        @if($activeRole === 'client')
  <a class="zv-mobile-item" href="{{ route('client.home') }}">
    <span><i class="bi bi-calendar-check me-2"></i>{{ __('My Bookings') }}</span>
    <i class="bi bi-chevron-right"></i>
  </a>

  <a class="zv-mobile-item" href="{{ route('favorites.index') }}">
    <span><i class="bi bi-heart me-2"></i>{{ __('My Favorites') }}</span>
    <i class="bi bi-chevron-right"></i>
  </a>
@endif

      </div>

      {{-- Mobile role actions (KYC based) --}}
      <div class="mb-3">
      @if(!$isCoachAccount)
  <a class="zv-role-cta-light w-100" href="{{ route('coach.application.show') }}">
    <span class="zv-role-cta__title">{{ __('Become a Coach') }}</span>
  </a>

@elseif(!$hasEverSubmitted || $coachAppStatus === 'rejected')
  <a class="zv-role-cta-light w-100" href="{{ route('coach.application.show') }}">
    <span class="zv-role-cta__title">
      {{ $coachAppStatus === 'rejected' ? __('Application rejected') : __('Complete Application') }}
    </span>
    @if($coachAppStatus === 'rejected')
      <span class="zv-role-cta__sub">{{ __('Resubmit your documents to get approved') }}</span>
    @endif
  </a>

@elseif($coachAppStatus === 'submitted')
  <div class="alert alert-light border text-center small text-capitalize mb-2">
    {{ __('Your application is under review. You cannot enter coach mode until approved.') }}
  </div>

  <form method="POST" action="{{ route('role.switch') }}">
    @csrf
    <input type="hidden" name="role" value="client">
    <button class="btn btn-outline-dark w-100" type="submit">
      {{ __('Switch to Client Account') }}
    </button>
  </form>

@else
          <div class="zv-role-toggle-light">
            <div class="zv-seg-light">
              <form method="POST" action="{{ route('role.switch') }}" class="zv-seg__item">
                @csrf
                <input type="hidden" name="role" value="client">
                <button type="submit" class="zv-seg__btn {{ $activeRole === 'client' ? 'is-active' : '' }}">
                  <i class="bi bi-person"></i><span>{{ __('Client') }}</span>
                </button>
              </form>

              <form method="POST" action="{{ route('role.switch') }}" class="zv-seg__item">
                @csrf
                <input type="hidden" name="role" value="coach">
                <button type="submit" class="zv-seg__btn {{ $activeRole === 'coach' ? 'is-active' : '' }}">
                  <i class="bi bi-mortarboard"></i><span>{{ __('Coach') }}</span>
                </button>
              </form>
            </div>
          </div>
        @endif
      </div>

      <form method="POST" action="{{ route('logout') }}">@csrf
        <button class="btn btn-outline-dark w-100" type="submit">
          <i class="bi bi-box-arrow-right me-2"></i>{{ __('Log out') }}
        </button>
      </form>
    @endauth

    @guest
      <hr>
      <a class="btn btn-dark w-100 mb-2 rounded-pill" href="{{ route('login') }}">{{ __('Log in') }}</a>
      <a class="btn btn-outline-dark w-100 rounded-pill" href="{{ route('register') }}">{{ __('Sign up') }}</a>
    @endguest
  </div>
</div>

{{-- =========================
   Language + Google Translate JS
   (desktop dropdown + mobile accordion list)
   ========================= --}}
<script>
(function () {
  const LOCALES = @json(collect(config('locales'))->mapWithKeys(fn($v,$k)=>[$k=>['rtl'=>$v[1]]]));

  function setGoogTransCookie(target) {
    const path = '/auto/' + target;
    const cookieVal = `googtrans=${encodeURIComponent(path)}; path=/`;
    document.cookie = cookieVal;
    const domain = location.hostname.startsWith('.') ? location.hostname : '.' + location.hostname;
    document.cookie = cookieVal + `; domain=${domain}`;
  }

  function setHtmlLangDir(target) {
    const html = document.documentElement;
    html.setAttribute('lang', target);
    html.setAttribute('dir', LOCALES[target]?.rtl ? 'rtl' : 'ltr');
  }

  function triggerGoogleTranslate(target) {
    setGoogTransCookie(target);
    if (window.google && window.google.translate && window.google.translate.TranslateElement) {
      const el = document.getElementById('google_translate_element');
      if (el) el.innerHTML = '';
      new google.translate.TranslateElement(
        {pageLanguage: '{{ app()->getLocale() }}', autoDisplay: false},
        'google_translate_element'
      );
    } else {
      location.reload();
    }
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.lang-pick');
    if (!btn) return;
    const target = btn.dataset.locale;
    if (!target) return;
    setHtmlLangDir(target);
    triggerGoogleTranslate(target);
  });

  const langSearch = document.getElementById('langSearch');
  const langList = document.getElementById('gtLangList');
  if (langSearch && langList) {
    langSearch.addEventListener('input', function () {
      const q = this.value.toLowerCase();
      langList.querySelectorAll('.lang-pick').forEach(function (btn) {
        const label = (btn.dataset.label || btn.textContent || '').toLowerCase();
        btn.style.display = label.includes(q) ? '' : 'none';
      });
    });
  }

  const langSearchMobile = document.getElementById('langSearchMobile');
  const langListMobile = document.getElementById('gtLangListMobile');
  if (langSearchMobile && langListMobile) {
    langSearchMobile.addEventListener('input', function () {
      const q = this.value.toLowerCase();
      langListMobile.querySelectorAll('.lang-pick').forEach(function (btn) {
        const label = (btn.dataset.label || btn.textContent || '').toLowerCase();
        btn.style.display = label.includes(q) ? '' : 'none';
      });
    });
  }

  (function syncLangFromCookie() {
    const m = document.cookie.match(/(?:^|;\s*)googtrans=([^;]+)/);
    if (m) {
      const val = decodeURIComponent(m[1]);
      const code = val.split('/').pop();
      if (code) setHtmlLangDir(code);
    }
  })();
})();
</script>

