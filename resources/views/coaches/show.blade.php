{{-- resources/views/coaches/show.blade.php --}}
@extends('layouts.app')

@section('title', $coach->full_name ?? ($coach->first_name ?? 'Coach'))

@push('styles')
  {{-- reuse services catalog styles for cards --}}
  <link rel="stylesheet" href="{{ asset('assets/css/services_catalog.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/coach_show.css') }}">
  
@endpush

@section('content')
@php
  $languages = (array) ($coach->languages ?? []);
  $serviceAreas = (array) ($coach->coach_service_areas ?? []);
  $quals = (array) ($coach->coach_qualifications ?? []);
  $gallery = (array) ($coach->coach_gallery ?? []);

  $isFavCoach = auth()->check()
      ? auth()->user()->favoriteCoaches->contains('id', $coach->id)
      : false;
@endphp

<div class="coach-page">
  <div class="container-lg py-4">

    {{-- HERO --}}
    <section class="coach-hero">
      <div class="coach-avatar-wrap">
  <button type="button"
          class="coach-avatar-btn"
          data-bs-toggle="modal"
          data-bs-target="#coachAvatarModal"
          aria-label="{{ __('View coach photo') }}">
    <img src="{{ $coach->avatar_path ? asset('storage/'.$coach->avatar_path) : asset('assets/user.png') }}"
         alt="{{ $coach->full_name ?? $coach->first_name ?? 'Coach' }}">
  </button>
</div>


      <div class="coach-main">
       <div class="coach-name-row">
  <div class="coach-name">
    {{ $coach->full_name ?? trim(($coach->first_name ?? '').' '.($coach->last_name ?? '')) }}
  </div>

  
</div>


        <div class="coach-meta-row">
         @php
  $coachRating = (float) ($coach->coach_rating_avg ?? 0);
  $coachReviewsCount = (int) ($coach->coach_reviews_count ?? 0);
@endphp

@if($coachReviewsCount > 0)
  <span>
    <i class="bi bi-star-fill me-1"></i>{{ number_format($coachRating, 1) }}
    <span class="text-muted">({{ $coachReviewsCount }})</span>
    <span class="coach-meta-dot"></span>
  </span>
@endif

          @if($coach->city || $coach->country)
            <span>
              <i class="bi bi-geo-alt me-1"></i>
              {{ trim(($coach->city ? $coach->city.', ' : '').($coach->country ?? '')) }}
              {{-- <span class="coach-meta-dot"></span> --}}
            </span>
          @endif

          {{-- <span>
            {{ $coach->is_online ? __('Available') : __('Offline') }}
          </span> --}}

          @if($coach->created_at)
            <span class="coach-meta-dot"></span>
            <span>{{ __('Joined') }} {{ $coach->created_at->format('M Y') }}</span>
          @endif
        </div>

       @if($coach->short_bio)
  <div class="coach-bio-box">
    <div class="coach-bio-title">{{ __('Short Bio') }}</div>
    <div class="coach-bio-text">{{ $coach->short_bio }}</div>
  </div>
@endif

      </div>

      <div class="coach-actions">
         <span class="coach-role-pill">{{ __('Coach') }}</span>

        @auth
      <form method="POST"
            action="{{ route('coaches.favorite.toggle', $coach->id) }}"
            class="coach-fav-form">
        @csrf
        <button type="submit"
                class="coach-fav-btn"
                aria-label="{{ $isFavCoach ? __('Remove from favorites') : __('Add to favorites') }}">
          @if($isFavCoach)
            <i class="bi bi-heart-fill"></i>
          @else
            <i class="bi bi-heart"></i>
          @endif
        </button>
      </form>
    @else
      <a href="{{ route('login') }}"
         class="coach-fav-btn"
         aria-label="{{ __('Log in to save coach') }}">
        <i class="bi bi-heart"></i>
      </a>
    @endauth
        {{-- you can later wire these to message / availability routes --}}
        {{-- <a href="{{ route('messages.index') }}" class="coach-btn">
          <i class="bi bi-chat-dots"></i> {{ __('Send Message') }}
        </a> --}}
        {{-- <a href="#coach-services" class="coach-btn coach-btn--outline">
          <i class="bi bi-calendar-event"></i> {{ __('View Services') }}
        </a> --}}
      </div>
    </section>

    {{-- MAIN LAYOUT --}}
    <div class="coach-layout">

      {{-- LEFT: info --}}
      <aside>
        <div class="coach-info-card">
          <div class="coach-info-title">{{ __('Service Area') }}</div>
          <div class="coach-info-row">
            @if(count($serviceAreas))
              @foreach($serviceAreas as $area)
                <span class="badge bg-light text-dark me-1 mb-1">{{ $area }}</span>
              @endforeach
            @else
              <span class="text-dark">{{ __('Not Specified') }}</span>
            @endif
          </div>

          <div class="coach-info-row">
            <div class="coach-info-title">{{ __('Description') }}</div>
            <div>{{ $coach->description ?: __('No Description Added Yet.') }}</div>
          </div>

          <div class="coach-info-row">
            <div class="coach-info-title">{{ __('Language(s)') }}</div>
            <div>
              @if(count($languages))
                {{ implode(', ', $languages) }}
              @else
                <span class="text-dark">{{ __('Not Specified') }}</span>
              @endif
            </div>
          </div>

          <div class="coach-info-row">
            <div class="coach-info-title">{{ __('Qualifications') }}</div>
            <div>
              @forelse($quals as $q)
                <div>{{ $q['title'] ?? '' }} @if(!empty($q['achieved_at'])) – {{ $q['achieved_at'] }} @endif</div>
              @empty
                <span class="text-dark">{{ __('Not Specified') }}</span>
              @endforelse
            </div>
          </div>

         
@if(count($gallery))
  <div class="coach-info-row">
    <div class="d-flex align-items-center justify-content-between">
      <div class="coach-info-title mb-0">{{ __('Gallery') }}</div>

      <button type="button"
              class="btn btn-sm btn-outline-dark rounded-pill coach-gallery-open"
              data-bs-toggle="modal"
              data-bs-target="#coachGalleryModal">
        <i class="bi bi-images me-1"></i>{{ __('View') }}
      </button>
    </div>

    {{-- small preview row --}}
    <div class="coach-gallery-mini mt-2">
      @foreach(array_slice($gallery, 0, 4) as $img)
        <button type="button"
                class="coach-gallery-thumb"
                data-bs-toggle="modal"
                data-bs-target="#coachGalleryModal"
                data-index="{{ $loop->index }}">
          <img src="{{ asset('storage/'.$img) }}" alt="">
        </button>
      @endforeach

      @if(count($gallery) > 4)
        <span class="coach-gallery-more">+{{ count($gallery) - 4 }}</span>
      @endif
    </div>
  </div>
@endif


         @if($showSocialProfiles)
  <div class="coach-info-row">
    <div class="coach-info-title">{{ __('Social Media Profile Links') }}</div>
    <div>
      @if($coach->facebook_url)
        <a href="{{ $coach->facebook_url }}" target="_blank" class="me-2"><i class="bi bi-facebook"></i></a>
      @endif
      @if($coach->instagram_url)
        <a href="{{ $coach->instagram_url }}" target="_blank" class="me-2"><i class="bi bi-instagram"></i></a>
      @endif
      @if($coach->youtube_url)
        <a href="{{ $coach->youtube_url }}" target="_blank" class="me-2"><i class="bi bi-youtube"></i></a>
      @endif
      @if($coach->tiktok_url)
        <a href="{{ $coach->tiktok_url }}" target="_blank" class="me-2"><i class="bi bi-tiktok"></i></a>
      @endif

      @if(!$coach->facebook_url && !$coach->instagram_url && !$coach->youtube_url && !$coach->tiktok_url)
        <span class="text-dark">{{ __('No Links Added Yet.') }}</span>
      @endif
    </div>
  </div>
@endif

        </div>
      </aside>

      {{-- RIGHT: services --}}
    {{-- RIGHT: services --}}
{{-- RIGHT: services --}}
<main id="coach-services">
  <div class="coach-services-section-title mb-2">
    {{ __('Services By') }} {{ $coach->full_name ?? $coach->first_name }}
  </div>

  <div class="row g-3">
    @forelse($services as $s)
      @php
        $svcCoach = $coach;
        $avatar   = $svcCoach?->avatar_path
                    ? asset('storage/'.$svcCoach->avatar_path)
                    : asset('assets/user.png');

        // keep rating logic similar to services-row
        $r = $s->rating_value ?? $s->avg_rating ?? 0;

        // ✅ same favorite logic as services-row.blade.php
        $isFav = auth()->check()
            ? ($s->is_favorited ?? $s->favorites->contains('user_id', auth()->id()))
            : false;
      @endphp

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">

          @auth
            <form method="POST"
                  action="{{ route('services.favorite.toggle', $s) }}"
                  class="favorite-form z-1 position-absolute top-0 end-0 m-2">
              @csrf
              <button type="submit"
                      class="favorite-btn"
                      data-service-id="{{ $s->id }}"
                      aria-label="{{ $isFav ? __('Remove from favorites') : __('Add to favorites') }}">

                @if($isFav)
                  <i class="bi bi-heart-fill "></i>
                @else
                  <i class="bi bi-heart"></i>
                @endif
              </button>
            </form>
          @else
            {{-- Guest: clicking heart sends to login --}}
            <a href="{{ route('login') }}"
               class="favorite-btn z-1 position-absolute top-0 end-0 m-2">
              <i class="bi bi-heart"></i>
            </a>
          @endauth

          {{-- Thumbnail (same structure/classes as services-row) --}}
          <div class="ratio ratio-16x9">
            <img src="{{ $s->thumbnail_url ?? asset('assets/img/service-placeholder.jpg') }}"
                 class="card-img-top object-fit-cover"
                 alt="{{ $s->title }}">
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

              @if($svcCoach)
                <a href="{{ route('coaches.show', $svcCoach->id) }}"
                   class="text-decoration-none text-dark fw-semibold">
                  {{ $svcCoach->full_name ?? trim(($svcCoach->first_name ?? '').' '.($svcCoach->last_name ?? '')) }}
                </a>
              @else
                <span>{{ __('Coach') }}</span>
              @endif

              <span class="mx-2">•</span>

              @if($s->city_name)
                <span><i class="bi bi-geo-alt me-1"></i>{{ $s->city_name }}</span>
              @elseif($svcCoach && ($svcCoach->city || $svcCoach->country))
                <span>
                  <i class="bi bi-geo-alt me-1"></i>
                  {{ trim(($svcCoach->city ? $svcCoach->city.', ' : '').($svcCoach->country ?? '')) }}
                </span>
              @endif
            </div>

            {{-- Level + rating (same structure/classes) --}}
<div class="d-flex align-items-center gap-2 mb-2">
  @if($s->level_label)
    <span class="badge rounded-pill bg-light text-dark">
      <i class="bi bi-lightning-charge me-1"></i>{{ $s->level_label }}
    </span>
  @endif

  <span class="small">
    <i class="bi bi-star-fill"></i>
    {{ $coachReviewsCount > 0 ? number_format($coachRating, 1) : '0.0' }}
    <span class="text-muted">({{ $coachReviewsCount }})</span>
  </span>
</div>

            {{-- Price (same text layout) --}}
            @if(!is_null($s->price_value))
              <div class="fw-semibold">
                {{ __('From') }}
                <span class="h6 mb-0">${{ number_format($s->price_value, 2) }} /</span>
                <span class="text-dark small">{{ $s->price_unit }}</span>
              </div>
            @else
              <div class="fw-semibold">
                {{ __('Custom Package') }}
              </div>
            @endif

          </div>

          <div class="card-footer bg-transparent border-0 pt-0 pb-3">
            <a href="{{ route('services.show', $s->id) }}" class="btn btn-outline-dark w-100 rounded-pill">
              {{ __('View Details') }}
            </a>
          </div>

        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="svc-empty text-dark text-capitalize">
          {{ __('This coach has no active services yet.') }}
        </div>
      </div>
    @endforelse
  </div>
</main>



    </div>
  </div>
</div>



@if(count($gallery))
<div class="modal fade" id="coachGalleryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered coach-gallery-dialog">

    <div class="modal-content coach-gallery-modal">

      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title">{{ __('Gallery') }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body pt-2">

        {{-- SNAP SLIDER (mobile + desktop) --}}
        <div class="coach-gallery-snap-wrap">

          <button type="button" class="coach-gallery-snap-arrow left" id="cgPrev" aria-label="Prev">
            <i class="bi bi-chevron-left"></i>
          </button>

          <div class="coach-gallery-snap" id="cgSnap">
            @foreach($gallery as $img)
              <div class="coach-gallery-snap-slide" data-index="{{ $loop->index }}">
                <img src="{{ asset('storage/'.$img) }}" alt="">
              </div>
            @endforeach
          </div>

          <button type="button" class="coach-gallery-snap-arrow right" id="cgNext" aria-label="Next">
            <i class="bi bi-chevron-right"></i>
          </button>

        </div>

        {{-- THUMB STRIP --}}
        <div class="coach-gallery-strip-wrap mt-3">
         

          <div class="coach-gallery-strip" id="coachGalleryStrip">
            @foreach($gallery as $img)
              <button type="button"
                      class="coach-gallery-strip-item @if($loop->first) active @endif"
                      data-index="{{ $loop->index }}">
                <img src="{{ asset('storage/'.$img) }}" alt="">
              </button>
            @endforeach
          </div>

         
        </div>

      </div>
    </div>
  </div>
</div>
@endif



<div class="modal fade" id="coachAvatarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered coach-avatar-dialog">
    <div class="modal-content coach-avatar-modal">

      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title">{{ $coach->full_name ?? $coach->first_name }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body pt-2">
        <div class="coach-avatar-view">
          <img src="{{ $coach->avatar_path ? asset('storage/'.$coach->avatar_path) : asset('assets/user.png') }}"
               alt="{{ $coach->full_name ?? $coach->first_name ?? 'Coach' }}">
        </div>
      </div>

    </div>
  </div>
</div>


@push('scripts')
<script>
document.addEventListener("click", function(e) {
    const btn = e.target.closest(".favorite-btn");
    if (!btn) return;

    // if there is no form (guest link), let it go to login normally
    const form = btn.closest("form");
    if (!form) return;

    e.preventDefault(); // stop normal submit

    const id = btn.dataset.serviceId;
    const icon = btn.querySelector("i");
    if (!id || !icon) return;

    // instant UI toggle
    icon.classList.toggle("bi-heart");
    icon.classList.toggle("bi-heart-fill");
    icon.classList.toggle("text-danger");

    // send ajax request – same endpoint as in services-row
    fetch(`/services/${id}/favorite`, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
        }
    }).catch(() => {
        // optional: revert UI if request fails
        icon.classList.toggle("bi-heart");
        icon.classList.toggle("bi-heart-fill");
        icon.classList.toggle("text-danger");
    });
});
</script>

<script>
(function () {
  const modalEl = document.getElementById('coachGalleryModal');
  if (!modalEl) return;

  const snap = document.getElementById('cgSnap');
  const strip = document.getElementById('coachGalleryStrip');

  const prevBtn = document.getElementById('cgPrev');
  const nextBtn = document.getElementById('cgNext');

  let activeIndex = 0;
  let keyHandlerAttached = false;

  function slides() {
    return Array.from(snap?.querySelectorAll('.coach-gallery-snap-slide') || []);
  }
  function stripBtns() {
    return Array.from(strip?.querySelectorAll('.coach-gallery-strip-item') || []);
  }

  function setActive(index, smooth = true) {
    const s = slides();
    if (!s.length) return;

    activeIndex = Math.max(0, Math.min(index, s.length - 1));
    const el = s[activeIndex];

    // scroll slider
    const left = el.offsetLeft;
    snap.scrollTo({ left, behavior: smooth ? 'smooth' : 'auto' });

    // sync strip active
    const btns = stripBtns();
    btns.forEach(b => b.classList.remove('active'));
    if (btns[activeIndex]) {
      btns[activeIndex].classList.add('active');
      btns[activeIndex].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
  }

  // next/prev buttons
  prevBtn?.addEventListener('click', () => setActive(activeIndex - 1));
  nextBtn?.addEventListener('click', () => setActive(activeIndex + 1));

  // strip click -> go to slide
  strip?.addEventListener('click', (e) => {
    const btn = e.target.closest('.coach-gallery-strip-item');
    if (!btn) return;
    const i = parseInt(btn.getAttribute('data-index') || '0', 10);
    setActive(i);
  });

  // slider scroll -> update activeIndex (snap position)
  let scrollTimer = null;
  snap?.addEventListener('scroll', () => {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
      const s = slides();
      if (!s.length) return;

      const x = snap.scrollLeft + (snap.clientWidth / 2);
      let best = 0, bestDist = Infinity;

      s.forEach((el, i) => {
        const mid = el.offsetLeft + el.clientWidth / 2;
        const d = Math.abs(mid - x);
        if (d < bestDist) { bestDist = d; best = i; }
      });

      if (best !== activeIndex) setActive(best, false);
    }, 60);
  });

  // mini thumbs open modal at index (your existing mini thumbs)
  document.addEventListener('click', function (e) {
    const mini = e.target.closest('.coach-gallery-thumb');
    if (!mini) return;
    const index = parseInt(mini.getAttribute('data-index') || '0', 10);
    setTimeout(() => setActive(index), 180);
  });

  // keyboard arrows on PC when modal open
  function onKey(e){
    if (e.key === 'ArrowLeft') { e.preventDefault(); setActive(activeIndex - 1); }
    if (e.key === 'ArrowRight'){ e.preventDefault(); setActive(activeIndex + 1); }
  }

  modalEl.addEventListener('shown.bs.modal', () => {
    // ensure strip items have data-index (add once)
    stripBtns().forEach((b, i) => b.setAttribute('data-index', i));
    setActive(activeIndex, false);

    if (!keyHandlerAttached) {
      document.addEventListener('keydown', onKey);
      keyHandlerAttached = true;
    }
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    // stop keyboard when modal closed
    if (keyHandlerAttached) {
      document.removeEventListener('keydown', onKey);
      keyHandlerAttached = false;
    }
  });

})();
</script>



@endpush

@endsection
