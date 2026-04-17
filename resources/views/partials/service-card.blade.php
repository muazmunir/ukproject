@php
  $coach  = $s->coach;
  $avatar = $coach?->avatar_url ?? asset('assets/img/avatar-silhouette.png');

  $isFav = auth()->check()
    ? ($s->is_favorited ?? $s->favorites->contains('user_id', auth()->id()))
    : false;
@endphp

<div class="card h-100 shadow-sm border-0 svc-row-card position-relative">

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
    <a href="{{ route('login') }}"
       class="favorite-btn z-1 position-absolute top-0 end-0 m-2">
      <i class="bi bi-heart"></i>
    </a>
  @endauth

  <div class="ratio ratio-16x9 svc-row-media">
    <a href="{{ route('services.show', $s->id) }}" class="d-block w-100 h-100">
      <img src="{{ $s->thumbnail_url }}"
           class="card-img-top object-fit-cover"
           alt="{{ $s->title }}">
    </a>
  </div>

  <div class="card-body">

    <div class="d-flex justify-content-between align-items-start mb-2">
      <h6 class="card-title mb-0 text-truncate" title="{{ $s->title }}">
        {{ $s->title }}
      </h6>

      @if($s->badge)
        <span class="badge bg-primary-subtle text-primary">{{ $s->badge }}</span>
      @endif
    </div>

    <div class="d-flex align-items-center small text-muted mb-2">
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

    <div class="d-flex align-items-center gap-2 mb-2">
  <span class="badge rounded-pill bg-light text-dark">
    <i class="bi bi-lightning-charge me-1"></i>{{ $s->level_label }}
  </span>

  @php
    $coachRating = (float) ($coach->coach_rating_avg ?? 0);
    $coachReviewsCount = (int) ($coach->coach_reviews_count ?? 0);
  @endphp

  <span class="small">
    <i class="bi bi-star-fill"></i>
    @if($coachReviewsCount > 0)
      {{ number_format($coachRating, 1) }}
      <span class="text-muted">({{ $coachReviewsCount }})</span>
    @else
      {{ __('New') }}
    @endif
  </span>
</div>

    @if(!is_null($s->price_value))
      <div class="fw-semibold">
        {{ __('From') }}
        <span class="h6 mb-0">${{ number_format($s->price_value, 2) }} /</span>
        <span class="text-muted small">{{ $s->price_unit }}</span>
      </div>
    @endif
  </div>

  <div class="card-footer bg-transparent border-0 pt-0 pb-3">
    <a href="{{ route('services.show', $s->id) }}" class="btn btn-outline-dark w-100 svc-row-btn">
      {{ __('View Details') }}
    </a>
  </div>
</div>
