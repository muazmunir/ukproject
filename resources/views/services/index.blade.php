{{-- resources/views/services/index.blade.php --}}
@extends('layouts.app')

@section('title')
  @if($selectedCategory)
    {{ $selectedCategory->name }} – {{ __('Services') }}
  @else
    {{ __('Services') }}
  @endif
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/services_catalog.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/services_category.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/coach_show.css') }}">
@endpush

@section('content')


<div class="svc-page">

  {{-- ===== Category / generic hero ===== --}}
 {{-- ===== Category hero only when specific category selected ===== --}}
@if($selectedCategory)
  @php
    $heroBg = $selectedCategory->cover_image
      ? asset('storage/' . $selectedCategory->cover_image)
      : asset('assets/img/services-hero-fallback.jpg');
  @endphp

  <section class="svc-hero svc-hero--has-category"
           style="background-image:url('{{ $heroBg }}');">
    <div class="svc-hero__overlay"></div>
    <div class="container-lg">
      <div class="svc-hero__content">
        <p class="svc-hero__eyebrow">{{ __('Category') }}</p>
        <h1 class="svc-hero__title">{{ $selectedCategory->name }}</h1>

        @if($selectedCategory->description)
          <p class="svc-hero__desc">{{ $selectedCategory->description }}</p>
        @else
          <p class="svc-hero__desc text-capitalize">
            {{ __('Browse curated services and coaches in this category.') }}
          </p>
        @endif

        <div class="svc-hero__actions">
          <a href="{{ route('services.index') }}" class="btn btn-outline-light btn-sm">
            {{ __('View All Services') }}
          </a>
        </div>
      </div>
    </div>
  </section>
@endif


  {{-- main body --}}
  <div class="container-lg py-4">


    {{-- Page heading --}}
    <div class="svc-header">
      <div>
        <h1 class="svc-header-title">
  @if($selectedCategory)
    {{ __('Search Result – :name', ['name' => $selectedCategory->name]) }}
  @else
    {{ __('Search Result') }}
  @endif
</h1>

        <p class="svc-header-sub text-capitalize">
          {{ __('Browse all available services and refine your search.') }}
        </p>
      </div>

      @if($services->total() > 0)
        <div class="svc-header-badge">
          {{ $services->total() }} {{ Str::plural('service', $services->total()) }}
        </div>
      @endif
    </div>

    <div class="svc-layout">
      {{-- ================= Filters sidebar ================= --}}
      <aside class="svc-sidebar">
        <div class="svc-sidebar-card">
          <div class="svc-sidebar-header">
            <span>{{ __('Refine') }}</span>
          </div>

          <form method="GET" action="{{ route('services.index') }}">
            {{-- Category --}}
            <div class="svc-filter-group">
              <label class="svc-filter-label">{{ __('Select Category') }}</label>
             <select name="category" class="svc-select">
  <option value="">{{ __('All') }}</option>
  @foreach($categories as $cat)
    <option value="{{ $cat->slug }}"
      @selected(optional($selectedCategory)->id === $cat->id)>
      {{ $cat->name }}
    </option>
  @endforeach
</select>

            </div>

            {{-- Coach --}}
            <div class="svc-filter-group">
              <label class="svc-filter-label">{{ __('Select Coach') }}</label>
              <select name="coach_id" class="svc-select">
                <option value="">{{ __('All') }}</option>
                @foreach($coaches as $coach)
                  <option value="{{ $coach->id }}" @selected($coachId == $coach->id)>
                    {{ $coach->full_name ?? trim($coach->first_name.' '.$coach->last_name) }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Price range --}}
            <div class="svc-filter-group svc-filter-price">
              <label class="svc-filter-label">{{ __('Price Range') }}</label>
              <div class="svc-price-inputs">
                <input type="number" name="min_price"
                       class="svc-input"
                       min="0"
                       placeholder="0"
                       value="{{ old('min_price', $minPrice ?? '') }}">
                <span class="svc-price-sep">—</span>
                <input type="number" name="max_price"
                       class="svc-input"
                       min="0"
                       placeholder="9999"
                       value="{{ old('max_price', $maxPrice ?? '') }}">
              </div>
            </div>

            {{-- Country --}}
            {{-- Country (API-driven) --}}
<div class="svc-filter-group">
  <label class="svc-filter-label">{{ __('Country') }}</label>
  <select name="country"
          class="svc-select js-country"
          data-selected="{{ $country }}">
    <option value="">{{ __('All') }}</option>
  </select>
</div>


            {{-- City --}}
           {{-- City (API-driven) --}}
<div class="svc-filter-group">
  <label class="svc-filter-label">{{ __('City') }}</label>
  <select name="city"
          class="svc-select js-city"
          data-selected="{{ $city }}">
    <option value="">{{ __('All') }}</option>
  </select>
</div>


            {{-- Buttons --}}
            <div class="svc-filter-actions">
              <a href="{{ route('services.index') }}" class="svc-btn-ghost w-50">{{ __('Clear') }}</a>
              <button type="submit" class="svc-btn-solid w-50">{{ __('Apply') }}</button>
            </div>
          </form>
        </div>
      </aside>

      {{-- ================= Results grid ================= --}}
      <main class="svc-results">
        <div class="svc-toolbar">
            <div class="svc-count">
              @if($services->total() > 0)
                {{ trans_choice(':count Service Found|:count Services Found', $services->total(), ['count' => $services->total()]) }}
              @else
                {{ __('No Services Found For Your Filters.') }}
              @endif
            </div>
          
            {{-- Service level multi-filter (replaces sort) --}}
            <form method="GET" action="{{ route('services.index') }}" class="svc-sort-form">
              {{-- keep other filters --}}
              <input type="hidden" name="category"
       value="{{ $selectedCategory ? $selectedCategory->slug : request('category') }}">

              <input type="hidden" name="coach_id"    value="{{ $coachId }}">
              <input type="hidden" name="country"     value="{{ $country }}">
              <input type="hidden" name="city"        value="{{ $city }}">
              <input type="hidden" name="min_price"   value="{{ $minPrice }}">
              <input type="hidden" name="max_price"   value="{{ $maxPrice }}">
          
              @php
                $levels = $levels ?? [];
              @endphp
          
              <span class="svc-sort-label me-2">{{ __('Service Level') }}</span>
          
              <label class="me-2" style="font-size:0.8rem;">
                <input type="checkbox"
                       name="levels[]"
                       value="beginner"
                       {{ in_array('beginner', $levels) ? 'checked' : '' }}
                       onchange="this.form.submit()">
                {{ __('Beginner') }}
              </label>
          
              <label class="me-2" style="font-size:0.8rem;">
                <input type="checkbox"
                       name="levels[]"
                       value="intermediate"
                       {{ in_array('intermediate', $levels) ? 'checked' : '' }}
                       onchange="this.form.submit()">
                {{ __('Intermediate') }}
              </label>
          
              <label style="font-size:0.8rem;">
                <input type="checkbox"
                       name="levels[]"
                       value="advanced"
                       {{ in_array('advanced', $levels) ? 'checked' : '' }}
                       onchange="this.form.submit()">
                {{ __('Advanced') }}
              </label>

              <label class="me-2" style="font-size:0.8rem;">
                <input type="checkbox"
                       name="levels[]"
                       value="athlete"
                       {{ in_array('athlete', $levels) ? 'checked' : '' }}
                       onchange="this.form.submit()">
                {{ __('Athlete') }}
              </label>
            </form>
          </div>
          

        {{-- Cards grid --}}
       {{-- Cards grid – same structure/classes as services-row.blade.php --}}
<div class=" row g-3">
  @forelse($services as $s)
   @php
  $coach  = $s->coach;
  $avatar = $coach?->avatar_url ?? asset('assets/img/avatar-silhouette.png');

  $coachRating = (float) ($coach->coach_rating_avg ?? 0);
  $coachReviewsCount = (int) ($coach->coach_rating_count ?? 0);

  $levelLabel = $s->level_label ?? null;

  $priceValue = $s->price_value;
  $priceUnit  = $s->price_unit;

  $isFav = auth()->check()
    ? ($s->is_favorited ?? $s->favorites->contains('user_id', auth()->id()))
    : false;
@endphp
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card h-100 shadow-sm border-0 svc-row-card">

        @auth
          <form method="POST"
                action="{{ route('services.favorite.toggle', $s) }}"
                class="favorite-form z-1 position-absolute top-0 end-0 m-2">
            @csrf
            <button type="submit"
                    class="favorite-btn svc-row-heart"
                    data-service-id="{{ $s->id }}"
                    aria-label="{{ $isFav ? __('Remove from favorites') : __('Add to favorites') }}">

              @if($isFav)
                <i class="bi bi-heart-fill text-danger"></i>
              @else
                <i class="bi bi-heart"></i>
              @endif
            </button>
          </form>
        @else
          {{-- Guest: clicking heart sends to login --}}
          <a href="{{ route('login') }}"
             class="favorite-btn svc-row-heart z-1 position-absolute top-0 end-0 m-2">
            <i class="bi bi-heart"></i>
          </a>
        @endauth

        {{-- Thumbnail (same structure/classes as services-row) --}}
       <div class="ratio ratio-16x9 svc-row-media">
  <a href="{{ route('services.show', $s->id) }}" class="d-block w-100 h-100">
    <img
      src="{{ $s->thumbnail_url ?? asset('assets/img/service-placeholder.jpg') }}"
      alt="{{ $s->title }}"
      class="w-100 h-100 svc-card-img object-fit-cover"
    >
  </a>
</div>


        <div class="card-body">

          {{-- Title + badge (same classes) --}}
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="card-title mb-0 text-truncate" title="{{ $s->title }}">
              {{ $s->title }}
            </h6>

            @if($s->badge)
              <span class="badge bg-primary-subtle text-primary">{{ $s->badge }}</span>
            @endif
          </div>

          {{-- Coach avatar + name + city (same layout) --}}
          <div class="d-flex align-items-center small text-dark mb-2">

            <img src="{{ $avatar }}"
                 class="rounded-circle me-2"
                 alt="Coach Avatar"
                 style="width:28px; height:28px; object-fit:cover;">

            @if($coach)
              <a href="{{ route('coaches.show', $coach->id) }}"
                 class="text-decoration-none text-dark fw-semibold">
                {{ $coach->full_name ?? trim(($coach->first_name ?? '').' '.($coach->last_name ?? '')) }}
              </a>
            @else
              <span>{{ __('Coach') }}</span>
            @endif

            <span class="mx-2">•</span>

            @if($s->city_name)
              <span>
                <i class="bi bi-geo-alt me-1"></i>{{ $s->city_name }}
              </span>
            @elseif($coach && ($coach->city || $coach->country))
              <span>
                <i class="bi bi-geo-alt me-1"></i>
                {{ trim(($coach->city ? $coach->city.', ' : '').($coach->country ?? '')) }}
              </span>
            @endif
          </div>

          {{-- Level + rating (same structure/classes) --}}
          <div class="d-flex align-items-center gap-2 mb-2">
  @if($levelLabel)
    <span class="badge rounded-pill bg-light text-dark">
      <i class="bi bi-lightning-charge me-1"></i>{{ $levelLabel }}
    </span>
  @endif

  <span class="small">
    <i class="bi bi-star-fill"></i>
    {{ $coachReviewsCount > 0 ? number_format($coachRating, 1) : '0.0' }}
    <span class="text-muted">({{ $coachReviewsCount }})</span>
  </span>
</div>

          {{-- Price (same text layout) --}}
          @if(!is_null($priceValue))
            <div class="fw-semibold">
              {{ __('From') }}
              <span class="h6 mb-0">${{ number_format($priceValue, 2) }} /</span>
              <span class="text-dark small">{{ $priceUnit }}</span>
            </div>
          @else
            <div class="fw-semibold">
              {{ __('Custom package') }}
            </div>
          @endif

        </div>

        <div class="card-footer bg-transparent border-0 pt-0 pb-3">
          <a href="{{ route('services.show', $s->id) }}" class="btn svc-row-btn btn-outline-dark w-100">
            {{ __('View details') }}
          </a>
        </div>

      </div>
    </div>
  @empty
    <div class="col-12">
      <div class="svc-empty text-dark">
        <p class="mb-1">{{ __('No Services Match Your Filters.') }}</p>
        <a href="{{ route('services.index') }}" class="svc-btn-ghost">{{ __('Clear All Filters') }}</a>
      </div>
    </div>
  @endforelse
</div>



        {{-- Pagination --}}
        @if($services->hasPages())
          <div class="svc-pagination">
            {{ $services->links() }}
          </div>
        @endif

      </main>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // only init once even if script is included on multiple pages
  if (window._ccInitCC) return;
  window._ccInitCC = true;

  const API_COUNTRIES = '{{ route("cc.countries") }}';
  const API_CITIES    = '{{ route("cc.cities") }}';

  function refreshNiceSelect(el){ /* no-op for native selects */ }
  function setDisabled(el, on){ el.disabled = !!on; refreshNiceSelect(el); }

  function setOptions(el, items, placeholder='Select', preselect='') {
    const frag = document.createDocumentFragment();

    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder;
    frag.appendChild(opt0);

    items.forEach(item => {
      const opt = document.createElement('option');
     if (typeof item === 'string') {
    opt.value = item;
    opt.textContent = item;
} else if (item && typeof item === 'object') {
    opt.value = item.name;   // ALWAYS use the full name
    opt.textContent = item.name;
}

      frag.appendChild(opt);
    });

    el.innerHTML = '';
    el.appendChild(frag);

    if (preselect) {
      el.value = preselect;
      if (el.value !== preselect) el.value = '';
    }
    refreshNiceSelect(el);
  }

  async function fetchJSON(url, params={}) {
    const qs   = new URLSearchParams(params).toString();
    const full = qs ? `${url}?${qs}` : url;

    const res  = await fetch(full, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const text = await res.text();
    let json = null;
    try { json = JSON.parse(text); } catch {}

    if (!res.ok) {
      console.error('[cc] HTTP error', res.status, text.slice(0,200));
      throw new Error('HTTP ' + res.status);
    }
    if (!json || json.success !== true) {
      console.error('[cc] API error body:', text.slice(0,200));
      throw new Error((json && json.message) || 'API error');
    }
    return json.data || [];
  }

  async function loadCities(countryName, citySel, preselect='') {
    if (!countryName) {
      setOptions(citySel, [], 'Select a country first');
      setDisabled(citySel, true);
      return;
    }
    setDisabled(citySel, true);
    setOptions(citySel, [], 'Loading...');
    const cities = await fetchJSON(API_CITIES, { country: countryName }).catch(e => {
      console.error('[cc] cities fetch failed:', e);
      return [];
    });
    setOptions(citySel, cities, 'Select', preselect);
    setDisabled(citySel, false);
  }

  async function initPair(countrySel, citySel) {
    // load countries
    setDisabled(countrySel, true);
    const countries = await fetchJSON(API_COUNTRIES).catch(e => {
      console.error('[cc] countries fetch failed:', e);
      return [];
    });
    setOptions(countrySel, countries, 'Select', countrySel.dataset.selected || '');
    setDisabled(countrySel, false);

    // preload cities if already selected
    const selectedCountryValue = countrySel.value || countrySel.dataset.selected || '';
    if (selectedCountryValue) {
      const name = countrySel.options[countrySel.selectedIndex]?.text || selectedCountryValue;
      await loadCities(name, citySel, citySel.dataset.selected || '');
    }

    countrySel.addEventListener('change', async () => {
      const name = countrySel.options[countrySel.selectedIndex]?.text || countrySel.value;
      await loadCities(name, citySel, '');
    });
  }

  // initialize for BOTH hero search-card and sidebar filter forms
  const forms = document.querySelectorAll('form.search-card, .svc-sidebar-card form');
  if (!forms.length) {
    console.warn('[cc] no forms with country/city selects found');
  }

  forms.forEach((form, idx) => {
    const countrySel = form.querySelector('.js-country');
    const citySel    = form.querySelector('.js-city');
    if (!countrySel || !citySel) {
      console.warn('[cc] missing selects in form', idx);
      return;
    }
    initPair(countrySel, citySel);
  });
});
</script>

<script>
document.addEventListener("click", async function (e) {
  const btn = e.target.closest(".favorite-btn");
  if (!btn) return;

  // Guest heart is <a href="/login"> ... </a> => allow default
  if (btn.tagName === "A") return;

  const form = btn.closest("form.favorite-form");
  if (!form) return;

  e.preventDefault();

  const icon = btn.querySelector("i");
  if (!icon) return;

  const wasFav = icon.classList.contains("bi-heart-fill");

  // optimistic toggle
  icon.classList.toggle("bi-heart");
  icon.classList.toggle("bi-heart-fill");
  icon.classList.toggle("text-danger");

  try {
    const res = await fetch(form.action, {
      method: "POST",
      headers: {
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
        "X-Requested-With": "XMLHttpRequest",
        "Accept": "application/json",
      },
      body: new FormData(form),
    });

    // session expired / not logged in
    if (res.status === 401 || res.status === 419) {
      window.location.href = "{{ route('login') }}";
      return;
    }

    const data = await res.json().catch(() => null);

    // backend must return: { ok:true, status:'added|removed', message:'...' }
    if (!res.ok || !data || data.ok !== true) {
      // revert if failed
      if (wasFav) {
        icon.classList.remove("bi-heart");
        icon.classList.add("bi-heart-fill", "text-danger");
      } else {
        icon.classList.remove("bi-heart-fill", "text-danger");
        icon.classList.add("bi-heart");
      }
      if (data?.message) window.zToast?.(data.message, "error");
      return;
    }

    // authoritative status from backend
    if (data.status === "added") {
      icon.classList.remove("bi-heart");
      icon.classList.add("bi-heart-fill", "text-danger");
    } else if (data.status === "removed") {
      icon.classList.remove("bi-heart-fill", "text-danger");
      icon.classList.add("bi-heart");
    }

    if (data.message) window.zToast?.(data.message, "ok");

  } catch (err) {
    // revert if request failed
    if (wasFav) {
      icon.classList.remove("bi-heart");
      icon.classList.add("bi-heart-fill", "text-danger");
    } else {
      icon.classList.remove("bi-heart-fill", "text-danger");
      icon.classList.add("bi-heart");
    }
    window.zToast?.("Network error. Please try again.", "error");
  }
});
</script>

@endpush


@endsection
