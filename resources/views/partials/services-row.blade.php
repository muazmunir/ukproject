{{-- resources/views/partials/services-row.blade.php --}}

@if(($services ?? collect())->count())
<section class="py-4">
  <div class="container-narrow">
    {{-- <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">{{ __('Latest Services From Coaches') }}</h5>
      <a href="{{ route('services.index') }}" class="text-decoration-none text-dark">
        {{ __('Explore All') }} <i class="bi bi-arrow-right"></i>
      </a>
    </div> --}}

    <div class="row g-3">
      @foreach($services as $s)
        @php
          $coach = $s->coach;
          $avatar = $coach?->avatar_url ?? asset('assets/img/avatar-silhouette.png');
        @endphp

        <div class="col-12 col-sm-6 col-lg-3">
          <div class="card h-100 shadow-sm border-0 svc-row-card">
            
            @php
      $isFav = auth()->check()
        ? ($s->is_favorited ?? $s->favorites->contains('user_id', auth()->id()))
        : false;
    @endphp

    @auth
      <form method="POST"
            action="{{ route('services.favorite.toggle', $s) }}"
            class="favorite-form z-1 position-absolute top-0 end-0 m-2">
        @csrf
        <button type="submit" class="favorite-btn svc-row-heart" data-service-id="{{ $s->id }}">

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
         class="favorite-btn z-1 position-absolute top-0 end-0 m-2">
        <i class="bi bi-heart"></i>
      </a>
    @endauth
            {{-- Thumbnail --}}
            <div class="ratio ratio-16x9 svc-row-media">
              <img src="{{ $s->thumbnail_url }}"
                   class="card-img-top object-fit-cover"
                   alt="{{ $s->title }}">
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

              {{-- Coach avatar + clickable coach name + city --}}
              <div class="d-flex align-items-center small text-dark mb-2">

                {{-- Avatar --}}
                <img src="{{ $avatar }}"
                     class="rounded-circle me-2"
                     alt="Coach Avatar"
                     style="width:28px; height:28px; object-fit:cover;">

                {{-- Coach name (clickable) --}}
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
                  <span class="text-dark small">{{ $s->price_unit }}</span>
                </div>
              @endif

            </div>

            <div class="card-footer bg-transparent border-0 pt-0 pb-3">
              <a href="{{ route('services.show', $s->id) }}" class="btn btn-outline-dark w-100 svc-row-btn">
                {{ __('View Details') }}
              </a>
            </div>

          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>
@else
<section class="py-4">
  <div class="container-narrow text-center text-muted">
    {{ __('No Services Yet. Check Back Soon!') }}
  </div>
</section>
@endif
@push('scripts')
  

<script>
document.addEventListener("click", async function (e) {
  const btn = e.target.closest(".favorite-btn");
  if (!btn) return;

  // Guest <a> goes to login normally
  if (btn.tagName === "A") return;

  const form = btn.closest("form.favorite-form");
  if (!form) return;

  e.preventDefault();

  const icon = btn.querySelector("i");
  const wasFav = icon.classList.contains("bi-heart-fill"); // before toggle

  // optimistic UI toggle
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

    // expired / not logged in
    if (res.status === 401 || res.status === 419) {
      window.location.href = "{{ route('login') }}";
      return;
    }

    const data = await res.json().catch(() => null);

    // ✅ backend should now return: { ok: true, status: 'added|removed', message: '...' }
    if (!res.ok || !data || data.ok !== true) {
      // revert icon if backend says failed
      if (wasFav) {
        icon.classList.remove("bi-heart");
        icon.classList.add("bi-heart-fill", "text-danger");
      } else {
        icon.classList.remove("bi-heart-fill", "text-danger");
        icon.classList.add("bi-heart");
      }
      return;
    }

    // ✅ ensure icon matches backend status (authoritative)
    if (data.status === "added") {
      icon.classList.remove("bi-heart");
      icon.classList.add("bi-heart-fill", "text-danger");
    } else if (data.status === "removed") {
      icon.classList.remove("bi-heart-fill", "text-danger");
      icon.classList.add("bi-heart");
    }

    // OPTIONAL: show message somewhere (simple alert)
    // alert(data.message);

  } catch (err) {
    // revert UI if request failed
    if (wasFav) {
      icon.classList.remove("bi-heart");
      icon.classList.add("bi-heart-fill", "text-danger");
    } else {
      icon.classList.remove("bi-heart-fill", "text-danger");
      icon.classList.add("bi-heart");
    }
  }
});
</script>


@endpush