{{-- resources/views/favorites/index.blade.php --}}
@extends('layouts.app')

@section('title', __('My Favorites'))

@push('styles')
  {{-- Keep favorites.css if you want header/tabs styles --}}
  <link rel="stylesheet" href="{{ asset('assets/css/favorites.css') }}">

  {{-- ✅ Import the same catalog styling used by services-row --}}
  <link rel="stylesheet" href="{{ asset('assets/css/services_catalog.css') }}">
@endpush

@section('content')
@php
  $activeTab = request('tab', 'services');
@endphp

<div class="container-fluid my-4 zv-fav-container">

  {{-- Header --}}
  <header class="zv-fav-header">
    <h1 class="zv-fav-title">{{ __('My Favorites') }}</h1>
    <p class="zv-fav-subtitle">
      {{ __('Review And Manage The Services And Coaches You’ve Saved For Later.') }}
    </p>
  </header>

  {{-- Tabs --}}
  <div class="zv-fav-tab-wrapper mb-4">
    <ul class="nav zv-fav-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link @if($activeTab === 'services') active @endif"
           href="{{ route('favorites.index', ['tab' => 'services']) }}">
          <i class="bi bi-briefcase me-1"></i> {{ __('Services') }}
        </a>
      </li>

      <li class="nav-item" role="presentation">
        <a class="nav-link @if($activeTab === 'coaches') active @endif"
           href="{{ route('favorites.index', ['tab' => 'coaches']) }}">
          <i class="bi bi-person-heart me-1"></i> {{ __('Coaches') }}
        </a>
      </li>
    </ul>
  </div>

  <div class="tab-content">

    {{-- =====================================================
        SERVICES TAB (same layout as services-row.blade.php)
      ===================================================== --}}
    <div class="tab-pane fade @if($activeTab === 'services') show active @endif">

      @if(($favoriteServices ?? collect())->count())
        <div class="row g-3">
          @foreach($favoriteServices as $s)
            @php
              $coach  = $s->coach;
              $avatar = $coach?->avatar_url ?? asset('assets/img/avatar-silhouette.png');

              // On favorites page it's already favorited, but keep safe
              $isFav = true;
            @endphp

            <div class="col-12 col-sm-6 col-lg-3">
              <div class="card h-100 shadow-sm border-0 svc-row-card position-relative">

                {{-- ✅ Remove (trash) button using modal --}}
                <button type="button"
                        class="zv-fav-remove-btn position-absolute top-0 end-0 m-2 z-2"
                        data-bs-toggle="modal"
                        data-bs-target="#favRemoveModal"
                        data-remove-url="{{ route('services.favorite.toggle', $s) }}"
                        data-item-label="{{ $s->title }}"
                        aria-label="{{ __('Remove from favorites') }}">
                  <i class="bi bi-trash"></i>
                </button>

                {{-- Thumbnail (same as services-row) --}}
                <div class="ratio ratio-16x9 svc-row-media">
  <a href="{{ route('services.show', $s->id) }}" class="d-block w-100 h-100">
    <img src="{{ $s->thumbnail_url }}"
         class="w-100 h-100 object-fit-cover"
         alt="{{ $s->title }}">
  </a>
</div>


                <div class="card-body">

                  {{-- Title + badge --}}
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0 text-truncate" title="{{ $s->title }}">
                      {{ $s->title }}
                    </h6>

                    @if($s->badge)
                      <span class="badge bg-primary-subtle text-primary">{{ $s->badge }}</span>
                    @endif
                  </div>

                  {{-- Coach + city --}}
                  <div class="d-flex align-items-center small text-dark mb-2">
                    <img src="{{ $avatar }}"
                         class="rounded-circle me-2"
                         alt="Coach Avatar"
                         style="width:28px; height:28px; object-fit:cover;">

                    @if($coach)
                      <a href="{{ route('coaches.show', $coach->id) }}"
                         class="text-decoration-none text-dark fw-semibold">
                        {{ $coach->full_name }}
                      </a>
                    @else
                      <span>{{ __('Coach') }}</span>
                    @endif

                    <span class="mx-2">•</span>
                    <i class="bi bi-geo-alt me-1"></i>{{ $s->city_name }}
                  </div>

                  {{-- Level + rating --}}
               <div class="d-flex align-items-center gap-2 mb-2">
  <span class="badge rounded-pill bg-light text-dark">
    <i class="bi bi-lightning-charge me-1"></i>{{ $s->level_label }}
  </span>

  @php
    $coachRating = (float) ($coach->coach_rating_avg ?? 0);
    $coachReviewsCount = (int) ($coach->coach_rating_count ?? 0);
  @endphp

  <span class="small">
    <i class="bi bi-star-fill"></i>
    {{ $coachReviewsCount > 0 ? number_format($coachRating, 1) : '0.0' }}
    <span class="text-muted">({{ $coachReviewsCount }})</span>
  </span>
</div>

                  {{-- Price --}}
                  @if(!is_null($s->price_value))
                    <div class="fw-semibold">
                      {{ __('From') }}
                      <span class="h6 mb-0">${{ number_format($s->price_value, 2) }} /</span>
                      <span class="text-muted small">{{ $s->price_unit }}</span>
                    </div>
                  @endif

                </div>

                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                  <a href="{{ route('services.show', $s->id) }}"
                     class="btn btn-outline-dark w-100 svc-row-btn">
                    {{ __('View Details') }}
                  </a>
                </div>

              </div>
            </div>
          @endforeach
        </div>

        <div class="zv-fav-pagination">
          {{ $favoriteServices->appends(['tab' => 'services'])->links() }}
        </div>

      @else
        <div class="zv-fav-empty text-center py-5">
          <i class="bi bi-heart fs-1 mb-3 d-block"></i>
          <h5 class="mb-2 text-dark">{{ __('No Favorited Services Yet') }}</h5>
          <p class="mb-3 text-dark">
            {{ __('Browse Services And Tap The Heart Icon To Save The Ones You Like.') }}
          </p>
          <a href="{{ route('services.index') }}" class="btn btn-outline-dark text-dark">
            {{ __('Discover services') }}
          </a>
        </div>
      @endif

    </div>


    {{-- =========================================
        COACHES TAB (fixed + clean cards)
      ========================================= --}}
    <div class="tab-pane fade @if($activeTab === 'coaches') show active @endif">

      @if(($favoriteCoaches ?? collect())->count())
        <div class="row g-3">
          @foreach($favoriteCoaches as $c)
            @php
              $avatar = $c->avatar_url ?? asset('assets/img/avatar-silhouette.png');
              $city = trim(($c->city ?? '').' '.($c->country ?? ''));
            @endphp

            <div class="col-12 col-sm-6 col-lg-4">
              <div class="card h-100 shadow-sm border-0">
                <button type="button"
    class="zv-fav-remove-btn position-absolute top-0 end-0 m-2"
    data-bs-toggle="modal"
    data-bs-target="#favRemoveModal"
    data-remove-url="{{ route('coaches.favorite.toggle', $c) }}"
    data-item-label="{{ $c->full_name }}"
    aria-label="Remove coach from favorites">
    <i class="bi bi-trash"></i>
  </button>
                <div class="card-body d-flex gap-3">

                  <img src="{{ $avatar }}"
                       class="rounded-circle"
                       alt="Coach Avatar"
                       style="width:56px; height:56px; object-fit:cover;">

                 <div class="flex-grow-1">
  <div class="d-flex justify-content-between align-items-start">
    <a href="{{ route('coaches.show', $c->id) }}"
       class="text-decoration-none text-dark fw-semibold">
      {{ $c->full_name }}
    </a>
  </div>

  @if($city)
    <div class="small dark mt-1">
      <i class="bi bi-geo-alt me-1"></i>{{ $city }}
    </div>
  @endif

  @php
    $coachRating = (float) ($c->coach_rating_avg ?? 0);
    $coachReviewsCount = (int) ($c->coach_rating_count ?? 0);
  @endphp

  <div class="small mt-2">
    <i class="bi bi-star-fill"></i>
    {{ $coachReviewsCount > 0 ? number_format($coachRating, 1) : '0.0' }}
    <span class="text-muted">({{ $coachReviewsCount }})</span>
  </div>
</div>
                </div>

                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                  <a href="{{ route('coaches.show', $c->id) }}" class="btn btn-outline-dark w-100">
                    {{ __('View Coach') }}
                  </a>
                </div>
              </div>
            </div>
          @endforeach
        </div>

        <div class="zv-fav-pagination">
          {{ $favoriteCoaches->appends(['tab' => 'coaches'])->links() }}
        </div>

      @else
        <div class="zv-fav-empty text-center py-5">
          <i class="bi bi-person-heart fs-1 mb-3 d-block"></i>
          <h5 class="mb-2 text-dark">{{ __('No Favorited Coaches Yet') }}</h5>
          <p class="mb-3 text-muted">
            {{ __('Explore Coaches And Save The Ones You Like.') }}
          </p>
          <a href="{{ route('services.index') }}" class="btn btn-outline-dark">
            {{ __('Browse Services') }}
          </a>
        </div>
      @endif

    </div>

  </div>
</div>


{{-- =====================================================
    Remove confirmation modal (same as your current one)
  ===================================================== --}}
<div class="modal fade" id="favRemoveModal" tabindex="-1" aria-labelledby="favRemoveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content zv-fav-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="favRemoveModalLabel">{{ __('Remove From Favorites') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">
          {{ __('Are You Sure You Want To Remove ') }}
          <span class="zv-fav-modal-item-label"></span>
          ?
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline-light btn-sm rounded-pill  px-2 py-1" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn-remove btn-sm rounded-pill btn-outline border-0 px-2 py-1" id="favRemoveConfirmBtn">
          {{ __('Remove') }}
        </button>
      </div>
    </div>
  </div>
</div>

<form id="favRemoveForm" method="POST" class="d-none">
  @csrf
</form>
@endsection


@push('scripts')
<script>
  (function () {
    const favModal   = document.getElementById('favRemoveModal');
    const confirmBtn = document.getElementById('favRemoveConfirmBtn');

    let removeUrl = null;
    let removeCard = null;

    if (!favModal || !confirmBtn) return;

    favModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      removeUrl  = button.getAttribute('data-remove-url');
      removeCard = button.closest('.col-12') || button.closest('.card');

      const label = button.getAttribute('data-item-label') || '';
      const labelSpan = favModal.querySelector('.zv-fav-modal-item-label');
      if (labelSpan) labelSpan.textContent = label;
    });

    confirmBtn.addEventListener('click', async function () {
      if (!removeUrl) return;

      confirmBtn.disabled = true;

      try {
        const res = await fetch(removeUrl, {
          method: "POST",
          headers: {
            "X-CSRF-TOKEN": "{{ csrf_token() }}",
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          }
        });

        const data = await res.json().catch(() => ({}));

        // close modal
        const modalInstance = bootstrap.Modal.getInstance(favModal);
        if (modalInstance) modalInstance.hide();

        // remove card from UI
        if (data.ok && removeCard) removeCard.remove();

        // ✅ unified toast
        if (window.zToast) {
          window.zToast(data.message || "Done", data.ok ? "ok" : "error");
        }

      } catch (e) {
        if (window.zToast) {
          window.zToast("Something went wrong", "error");
        }
        console.error(e);

      } finally {
        confirmBtn.disabled = false;
      }
    });
  })();
</script>
@endpush


