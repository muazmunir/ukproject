
{{-- resources/views/services/show.blade.php --}}
@extends('client.layout')
@section('title', $service->title)

@php
  // ✅ Safe even when $availabilityUrl was never passed to the view
  $coach  = $service->coach;               // must resolve to Users row
  $rawTz  = $coach?->timezone;
  try {
      $coachTz = $rawTz ? (new \DateTimeZone(trim($rawTz)))->getName()
                        : config('app.timezone','UTC');
  } catch (\Throwable $e) {
      $coachTz = config('app.timezone','UTC');
}
  $defaultTz = $coachTz;
  $availabilityUrl = $availabilityUrl ?? route('services.availability', $service->id);


  $gallery = (array) ($service->images ?? []);
  
@endphp


@section('content')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/service_show.css') }}">
@endpush

<div id="serviceShowRoot"
     data-availability-url="{{ $availabilityUrl }}"
     data-coach-tz="{{ $coachTz }}"
     data-default-tz="{{ $defaultTz }}"
     data-service-id="{{ $service->id }}"
     data-availability-day-url="{{ route('services.availability.day', $service->id) }}">
</div>

{{-- <div style="font-size:12px;color:#888">coachTz debug: {{ $coachTz }}</div> --}}

<div class="container my-4">
  <div class="row g-4">
    {{-- Left: Gallery + Details --}}
    <div class="col-12 col-lg-8">
      {{-- Gallery --}}
      <div class="mb-3">
        <div class="ratio ratio-16x9">
          <img src="{{ $service->thumbnail_url }}" class="hero-img w-100" alt="{{ $service->title }}">
        </div>
        @if(count($gallery))
          <div class="row g-2 mt-2 thumb-grid">
            @foreach(array_slice($gallery, 0, 6) as $img)
              <div class="col-4">
                <img src="{{ asset('storage/'.ltrim($img,'/')) }}" alt="photo">
              </div>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Title + host --}}
      <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div class="mb-3">
          <h3 class="mb-1">{{ $service->title }}</h3>
          <div class="subtle">
            <i class="bi bi-person-badge me-1"></i>
            {{ optional($coach)->full_name ?? __('Coach') }}
            <span class="mx-2">•</span>
            <i class="bi bi-geo-alt me-1"></i>{{ $service->city_name }}
            @if($service->badge)
              <span class="ms-2 muted-chip">{{ $service->badge }}</span>
            @endif
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-3">
          <img width="64" height="64" src="{{ $coach->avatar_url }}" alt="coach" class="rounded-circle" />
        </div>
      </div>

      {{-- Intro --}}
      <p class="text-secondary">{{ $service->description }}</p>
      <div class="divider"></div>

      {{-- Amenities-like: environments + accessibility --}}
      <div class="row">
        <div class="col-12 col-md-6">
          <h5 class="mb-3">{{ __('Where I Can Coach') }}</h5>
          @foreach((array)($service->environments ?? []) as $env)
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-geo me-1"></i><span>{{ $env }}</span></div>
          @endforeach
          @if($service->environment_other)
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-three-dots"></i><em>{{ $service->environment_other }}</em></div>
          @endif
        </div>
        <div class="col-12 col-md-6">
          <h5 class="mb-3">{{ __('Accessibility') }}</h5>
          @foreach((array)($service->accessibility ?? []) as $acc)
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-universal-access"></i><span>{{ $acc }}</span></div>
          @endforeach
          @if($service->accessibility_other)
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-three-dots"></i><em>{{ $service->accessibility_other }}</em></div>
          @endif
        </div>
      </div>

      <div class="divider"></div>
      <h5 class="mb-3 pkg-section-title">{{ __('Packages') }}</h5>

      <div class="row g-3">
        @forelse($service->packages->sortBy('sort_order') as $pkg)
          @php
            $hoursPerDay = (float)($pkg->hours_per_day ?? 0);
            $totalHours  = (float)($pkg->total_hours   ?? 0);
            $totalDays   = (int)($pkg->total_days      ?? 0);
          @endphp
      
          <div class="col-12">
            <div class="pkg-card d-flex flex-wrap align-items-start justify-content-between gap-3">
              
              {{-- LEFT: name + meta + description --}}
              <div class="pkg-card-main">
                <div class="pkg-name">{{ $pkg->name }}</div>
      
                <div class="pkg-meta-line">
                  @if($totalHours)
                    <span class="pkg-pill">
                      {{ number_format($totalHours,0) }} {{ __('total hours') }}
                    </span>
                    @if($hoursPerDay)
                      <span class="pkg-pill">
                        {{ number_format($hoursPerDay,0) }} {{ __('hrs/day') }}
                      </span>
                    @endif
                  @elseif($hoursPerDay && $totalDays)
                    <span class="pkg-pill">
                      {{ $totalDays }} {{ Str::plural('day',$totalDays) }}
                    </span>
                    <span class="pkg-pill">
                      {{ number_format($hoursPerDay,0) }} {{ __('hrs/day') }}
                    </span>
                  @endif
      
                  @if($pkg->equipments)
                    <span class="pkg-pill pkg-pill-soft">
                      {{ __('Equipment') }}: {{ $pkg->equipments }}
                    </span>
                  @endif
                </div>
      
                @if($pkg->description)
                  <div class="pkg-desc">
                    {{ $pkg->description }}
                  </div>
                @endif
              </div>
      
              {{-- MIDDLE: price --}}
              <div class="pkg-price text-end ms-auto">
                @if($pkg->total_price)
                  <div class="pkg-price-value">
                    ${{ number_format($pkg->total_price, 2) }}
                  </div>
                  <div class="pkg-price-label">{{ __('total') }}</div>
                @elseif($pkg->hourly_rate)
                  <div class="pkg-price-value">
                    ${{ number_format($pkg->hourly_rate, 2) }}
                  </div>
                  <div class="pkg-price-label">{{ __('Per Hour') }}</div>
                @endif
              </div>
      
              {{-- RIGHT: CTA --}}
              <div class="pkg-cta">
                @php
                  $pkgData = json_encode([
                    'id'           => $pkg->id,
                    'name'         => $pkg->name,
                    'hours_per_day'=> $hoursPerDay,
                    'total_hours'  => $totalHours,
                    'total_days'   => $totalDays,
                    'hourly_rate'  => (float)($pkg->hourly_rate ?? 0),
                    'total_price'  => (float)($pkg->total_price ?? 0),
                  ]);
                @endphp
      
                <button
                  type="button"
                  class="btn btn-outline-dark select-package"
                  data-pkg='{{ $pkgData }}'>
                  {{ __('Choose') }}
                </button>
              </div>
      
            </div>
          </div>
        @empty
          <div class="col-12">
            <div class="subtle">{{ __('No packages yet.') }}</div>
          </div>
        @endforelse
      </div>
      
 
    </div>

     <div class="col-12 col-lg-4">
      <div class="card sticky-card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-baseline">
            <div class="h5 mb-0" id="priceHeader">
              @if(!is_null($service->price_value))
                ${{ number_format($service->price_value,2) }}
                <span class="subtle small">/ {{ $service->price_unit }}</span>
              @else
                <span class="subtle">{{ __('Select a Package') }}</span>
              @endif
            </div>
            <div class="subtle small zv-secure">
  <span class="zv-secure-icon" aria-hidden="true"></span>
  {{ __('Secure Booking') }}
</div>

          </div>

          {{-- Refund policy link --}}
  <div class="d-flex align-items-center gap-2 my-2">
  <span class="">{{ __('Coach Refund Policy') }}</span>

  <button type="button"
          class="btn btn-link p-0 text-decoration-none"
          data-bs-toggle="modal"
          data-bs-target="#coachRefundPolicyModal"
          aria-label="{{ __('View Coach Refund Policy') }}">
    <i class="bi bi-info-circle text-black"></i>
  </button>
</div>


          <div class="divider"></div>

      

          {{-- Booking form --}}
        {{-- Booking form --}}
<form id="bookingForm"
action="{{ route('reserve.show') }}"
method="GET"
onsubmit="return window.__validateBooking?.();">

{{-- Package --}}
<div class="mb-3">
<label class="form-label">{{ __('Package') }}</label>
<select class="form-select" id="pkgSelect" required>
<option value="">{{ __('Select a Package') }}</option>
@foreach($service->packages->sortBy('sort_order') as $pkg)
  @php
    $hoursPerDay = (float)($pkg->hours_per_day ?? 0);
    $totalHours  = (float)($pkg->total_hours   ?? 0);
    $totalDays   = (int)($pkg->total_days      ?? 0);
  @endphp
  <option value="{{ $pkg->id }}"
          data-name="{{ $pkg->name }}"
          data-hours-per-day="{{ $hoursPerDay }}"
          data-total-hours="{{ $totalHours }}"
          data-total-days="{{ $totalDays }}"
          data-hourly-rate="{{ (float)($pkg->hourly_rate ?? 0) }}"
          data-total-price="{{ (float)($pkg->total_price ?? 0) }}">
    {{ $pkg->name }}
    @if($pkg->total_price) — ${{ number_format($pkg->total_price,2) }} @endif
  </option>
@endforeach
</select>
<div class="form-text" id="pkgMeta"></div>
</div>

{{-- Calendar + selected chips + per-day times are outside the form visually,
but still on the page; that’s fine. We only need hidden inputs inside the form. --}}

{{-- Time slots (per day) --}}
{{-- Time slots (per day) --}}
<div class="mb-3">
  <label class="form-label mb-1">{{ __('Daily Time') }}</label>

  <div class="small text-muted" id="slotsHelp">
    {{ __('Select a Time Slot From The Coach’s Available Schedule.') }}
  </div>

  <div id="daysContainer" class="mt-2 vstack gap-2">
    <div class="subtle small">
      {{ __('Choose a Package And Date(s) To View Available Time Slots.') }}
    </div>
  </div>
</div>


{{-- Price summary (optional preview) --}}
<div class="d-flex justify-content-between align-items-center mb-3">
<span class="subtle">{{ __('Total') }}</span>
<span id="priceTotal" class="fw-semibold">$0.00</span>
</div>

{{-- <button class="btn btn-primary w-100" type="submit">{{ __('Reserve') }}</button> --}}

@php
  $activeRole = strtolower((string) session('active_role', 'client'));
@endphp

<a href="{{ route('messages.start.fromService', ['service' => $service->id, 'as' => $activeRole]) }}"
   class="btn btn-outline-secondary w-100 mt-2">
  <i class="bi bi-chat-dots me-1"></i>
  {{ __('Chat with coach') }}
</a>


{{-- Hidden fields to submit --}}
<input type="hidden" name="service_id" value="{{ $service->id }}">
<input type="hidden" name="package_id" id="packageId">
<input type="hidden" name="client_tz" id="clientTzHidden" value="UTC">
{{-- dates/slots will be injected as days[0][date|start|end] by JS --}}
<input type="hidden" name="date_start" id="dateStart"> {{-- optional/legacy --}}
<input type="hidden" name="date_end" id="dateEnd">     {{-- optional/legacy --}}
</form>

        </div>
      </div>
   

  
   
    
      {{-- Packages (list) --}}
      
  </div>
      <div class="divider"></div>

      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-light text-dark border" id="clientTzBadge">
          <i class="bi bi-geo-alt me-1"></i><span id="clientTzLabel">...</span>
        </span>
      
        <select disabled id="clientTzSelect" class="form-select form-select-sm" style="max-width: 280px;">
          <option value="auto">Auto (device)</option>
          <option value="UTC">UTC</option>
          <option value="Asia/Karachi">Asia/Karachi (UTC+5)</option>
          <option value="Asia/Yerevan">Asia/Yerevan (UTC+4)</option>
          <option value="Europe/Berlin">Europe/Berlin</option>
          <option value="America/New_York">America/New_York</option>
        </select>
      </div>
      
{{-- Helper bar above calendar --}}
<div class="zv-cal-help mb-2">
  <div class="zv-cal-help__title">
    <i class="bi bi-info-circle me-1 text-capitalize"></i>
    {{ __('Select up to 7 days to view available time slots') }}
  </div>

  <div class="zv-cal-help__sub" id="zvCalCount">
    {{ __('0 / 7 Selected') }}
  </div>
</div>


    <div id="bookingCalendar"></div>
<div class="row g-4">
  <div class="col-12 col-lg-8">
{{-- Left helper: list of up to 7 chosen days --}}


    <!-- LEFT: Explore Days (red) + Time Slots -->
    

        <!-- Explore Days List -->
        <div id="exploreDays" class="zv-explore-box mb-3"></div>

        <!-- Time Slots show here when clicking days -->
        <div id="dayTimes"></div>

    </div>

    <!-- RIGHT: Reserved Sessions (green) -->
    <div class="col-lg-4 col-12">

        <div id="reservedDays" class="zv-reserved-box"></div>

        <button type="button"
                class="btn btn-dark fw-bold w-100 mt-3"
                onclick="document.getElementById('bookingForm')?.requestSubmit();">
            Reserve
        </button>

    </div>

</div>

 @if($service->faqs->count())
        <h5 class="mb-3">{{ __('FAQ') }}</h5>
        <div class="accordion" id="faqs">
          @foreach($service->faqs->sortBy('sort_order') as $faq)
            <div class="accordion-item">
              <h2 class="accordion-header" id="h{{ $faq->id }}">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c{{ $faq->id }}">
                  {{ $faq->question }}
                </button>
              </h2>
              <div id="c{{ $faq->id }}" class="accordion-collapse collapse" data-bs-parent="#faqs">
                <div class="accordion-body">{{ $faq->answer }}</div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
  </div>
</div>




      {{-- FAQs (optional) --}}
     
    

    {{-- Right: Booking card --}}
   
  
{{-- Scripts --}}
@push('scripts')
  @vite(['resources/js/service_booking.js'])
 
@endpush


<div class="modal fade" id="coachRefundPolicyModal" tabindex="-1" aria-labelledby="coachRefundPolicyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="coachRefundPolicyModalLabel">{{ __('Coach Refund Policy') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <p class="mb-3 text-capitalize">
          {{ __('Each coach on the platform applies a cancellation policy to their sessions. By booking a session, you agree to the cancellation policy of the coach providing the service.') }}
        </p>

        <p class="mb-2 text-capitalize">
          {{ __('For this coach, the following cancellation terms apply where you cancel a session.') }}
        </p>

        <ul class="mb-3">
          <li class="mb-2 text-capitalize">
            {{ __('Cancellation within') }}
            <strong class="text-capitalize">{{ __('0 To 24 Hours') }}</strong>
            {{ __('of the scheduled session time:') }}
            <strong class="text-capitalize">{{ __('20 percent') }}</strong>
            {{ __('of the session price may be charged by the coach.') }}
          </li>

          <li class="mb-2 text-capitalize">
            {{ __('Cancellation between') }}
            <strong>{{ __('24 and 48 hours') }}</strong>
            {{ __('before the scheduled session time:') }}
            <strong>{{ __('10 percent') }}</strong>
            {{ __('of the session price may be charged by the coach.') }}
          </li>

          <li class="mb-2 text-capitalize">
            {{ __('Cancellation more than') }}
            <strong>{{ __('48 hours') }}</strong>
            {{ __('before the scheduled session time: no cancellation fee applies.') }}
          </li>
        </ul>

        <p class="mb-0 text-capitalize">
          {{ __('Cancellation fees compensate the coach for reserved time that cannot reasonably be rebooked.') }}
        </p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">{{ __('Got it') }}</button>
      </div>
    </div>
  </div>
</div>

@endsection


