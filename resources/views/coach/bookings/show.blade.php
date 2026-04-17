  @extends('layouts.role-dashboard')

  @push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/coach-booking-details.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/client-booking-details.css') }}">
  @endpush

  @section('role-content')
  @php
    $user = auth()->user();
    $tz   = $user->timezone ?? config('app.timezone','UTC');

    $service = $reservation->service;
    $package = $reservation->package;

    $currencySymbol = '$';
$currencyCode   = $reservation->currency ?? 'USD';

// Money helper (minor -> "$12.34 USD")
$money = fn($minor) =>
    $currencySymbol . number_format(((int)($minor ?? 0)) / 100, 2) . ' ' . $currencyCode;

// If you still want "12.34" only (without symbol/code)
$m = fn($minor) => number_format(((int)($minor ?? 0)) / 100, 2);

     $status              = strtolower((string)($reservation->status ?? 'pending'));
    // Coach-only money fields (DO NOT show client totals)
$subtotalMinor     = (int)($reservation->subtotal_minor ?? 0);

$coachFeeType      = (string)($reservation->coach_fee_type ?? '');
$coachFeeAmount    = $reservation->coach_fee_amount !== null ? (float)$reservation->coach_fee_amount : null;
$coachFeeMinor     = (int)($reservation->coach_fee_minor ?? 0);
$coachNetMinor     = (int)($reservation->coach_net_minor ?? 0);

$coachEarnedMinor  = (int)($reservation->coach_earned_minor ?? 0);
$coachPenaltyMinor = (int)($reservation->coach_penalty_minor ?? 0);
$coachCompMinor    = (int)($reservation->coach_comp_minor ?? 0);

$settlement = strtolower((string)($reservation->settlement_status ?? ''));

$slots = $reservation->slots?->sortBy('start_utc') ?? collect();
$slotStatuses = $slots->map(fn($s) => strtolower(trim((string)($s->session_status ?? ''))));

$isCoachNoShow = $slotStatuses->contains('no_show_coach');
$isBothNoShow  = $slotStatuses->contains('no_show_both');
$coachFeeLabel = null;
if ($coachFeeMinor > 0) {
    if ($coachFeeType === 'percent' && $coachFeeAmount !== null) {
        $coachFeeLabel = rtrim(rtrim(number_format($coachFeeAmount, 2), '0'), '.') . '%';
   } elseif ($coachFeeType === 'fixed' && $coachFeeAmount !== null) {
    $coachFeeLabel = $currencySymbol . number_format($coachFeeAmount, 2) . ' ' . $currencyCode;
}
}

$isRefundTrack =
    in_array($settlement, ['refund_pending','refunded','refunded_partial'], true)
    || $isCoachNoShow
    || $isBothNoShow;


$cancelled = in_array($status, ['cancelled','canceled'], true);

$computedNetMinor = max(0, $subtotalMinor - $coachFeeMinor);

// ✅ Decide base payout according to scenario
if ($isRefundTrack) {
    $baseCoachForBooking = 0; // refunds/no-show tracks = no base payout
} elseif ($cancelled) {
    // ✅ if cancelled, base payout should be 0 (coach only gets compensation, minus penalties)
    $baseCoachForBooking = 0;
} else {
    // ✅ completed/paid/settled => coach gets subtotal - coach platform fee
    $baseCoachForBooking = $computedNetMinor;
}






$coachNetForBooking = max(0, $baseCoachForBooking + $coachCompMinor - $coachPenaltyMinor);




// for UI lines
$coachNetBeforeAdjust = $baseCoachForBooking;
$coachNetFinal        = $coachNetForBooking;

    $platformEarnedMinor = (int)($reservation->platform_earned_minor ?? 0); // platform kept (coach-side when coach cancels)
    
   
    $paymentStatus       = strtolower((string)($reservation->payment_status ?? ''));

    // Slots
    

    $finishedStatuses = [
      'completed','no_show_coach','no_show_client','no_show_both','cancelled','canceled'
    ];

    // Airbnb-ish status (coach view)
   if ($settlement === 'paid') {
  $uiStatus = 'paid';
} elseif ($settlement === 'pending') {
  $uiStatus = 'pending_payout';
} elseif ($settlement === 'in_dispute') {
  $uiStatus = 'in_dispute';
} elseif ($settlement === 'refunded') {
  $uiStatus = 'refunded';
} elseif ($settlement === 'refunded_partial') {
  $uiStatus = 'partially_refunded';
} elseif ($settlement === 'cancelled' || in_array($status, ['cancelled','canceled'], true)) {
  $uiStatus = 'cancelled';
} elseif ($status === 'completed') {
  $uiStatus = 'completed';
} elseif (in_array($status, ['booked','confirmed','paid','in_escrow'], true)) {
  $uiStatus = 'in_progress';
} else {
  $uiStatus = $status ?: 'pending';
}
    $statusLabel = fn($s) => ucwords(str_replace('_',' ', (string)$s));

    $dt = fn($utc) => $utc ? \Carbon\Carbon::parse($utc)->utc()->timezone($tz) : null;

    $firstStart = $slots->first()?->start_utc ? \Carbon\Carbon::parse($slots->first()->start_utc)->utc() : null;
    $lastEnd    = $slots->last()?->end_utc ? \Carbon\Carbon::parse($slots->last()->end_utc)->utc() : null;

    // Cover (optional)
    $cover = null;
    if ($service && !empty($service->thumbnail_path)) {
      $cover = asset('storage/' . $service->thumbnail_path);
    } else {
      $images = (array) ($service->images ?? []);
      $first  = reset($images);
      if (!empty($first)) $cover = asset('storage/' . $first);
    }
    if (!$cover) $cover = asset('assets/img/service-placeholder.jpg');

    // Cancellation info
    $cancelledBy = strtolower((string)($reservation->cancelled_by ?? ''));
    $cancelledByNice = $cancelledBy ? ucwords($cancelledBy) : '—';

    // Slot labels (coach-facing)
    $slotLabel = function($st){
      $st = trim(strtolower((string)$st));
     return match($st){
      'live' => 'Live',
      'completed' => 'Completed',
      'no_show_client' => 'Client Not Join',
      'no_show_coach' => 'Coach Not Join',
      'no_show_both' => 'None Joined',
      'cancelled','canceled' => 'Cancelled',
        default => $st ? ucwords(str_replace('_',' ', $st)) : 'Scheduled'
      };
    };

    $pillTone = function($st){
      $st = trim(strtolower((string)$st));
      return match($st){
        'completed','settled' => 'ok',
        'in_progress','live'  => 'warn',
        'cancelled','canceled' => 'muted',
        'no_show_coach','no_show_client','no_show_both' => 'danger',
        'refunded','partially_refunded','in_dispute' => 'danger',
        default => 'neutral'
      };
    };

    // Coach summary logic (what coach should understand)
    // - If settled: show coachEarnedMinor
    // - If cancelled by coach: show coachPenaltyMinor and that it was deducted
    // - If cancelled by client: likely 0 earning (depends on your rules) but show coachEarnedMinor if any
    // - Never show client totals/refunds

    // Create a simple "coach events" timeline
    $events = collect();

    $events->push([
      'title' => 'Booking Created',
      'time'  => $reservation->created_at ? $reservation->created_at->timezone($tz) : null,
      'meta'  => 'Booking #' . $reservation->id,
      'type'  => 'neutral',
    ]);

    if (!empty($reservation->cancelled_at)) {
      $meta = $cancelledBy ? ("Cancelled By: " . $cancelledByNice) : null;
      $events->push([
        'title' => 'Booking Cancelled',
        'time'  => \Carbon\Carbon::parse($reservation->cancelled_at)->timezone($tz),
        'meta'  => $meta,
        'type'  => 'danger',
      ]);
    }

    if ($settlement === 'settled') {
      $events->push([
        'title' => 'Earnings Settled',
        'time'  => $reservation->updated_at ? $reservation->updated_at->timezone($tz) : null,
        'meta'  => ($coachEarnedMinor > 0) ? ("You Earned: " . $money($coachEarnedMinor) ) : null,
        'type'  => 'ok',
      ]);
    }

    if (in_array($settlement, ['refunded','refunded_partial'], true)) {
      $events->push([
        'title' => 'Booking Refunded',
        'time'  => $reservation->updated_at ? $reservation->updated_at->timezone($tz) : null,
        'meta'  => 'Your Earnings For This Booking May Be Adjusted Based On Policy.',
        'type'  => 'danger',
      ]);
    }

    if ($coachPenaltyMinor > 0) {
      $events->push([
        'title' => 'Penalty Applied',
        'time'  => $reservation->updated_at ? $reservation->updated_at->timezone($tz) : null,
        'meta'  => "Penalty: " . $money($coachPenaltyMinor) ,
        'type'  => 'danger',
      ]);
    }



  @endphp

  <div class="zv-cbd">
    <div class="zv-cbd__top">
      <a href="{{ route('coach.bookings') }}" class="zv-back">
        <i class="bi bi-arrow-left"></i>
        <span>{{ __('Back to bookings') }}</span>
      </a>
    </div>

    {{-- Hero --}}
    <div class="zv-hero">
      <div class="zv-hero__media">
        <img src="{{ $cover }}" alt="{{ $service->title ?? 'Service' }}">
      </div>

      <div class="zv-hero__body">
        <div class="zv-hero__row">
          <div>
            <div class="zv-title">
              {{ $package->title ?? $service->title ?? __('Booking') }}
            </div>
            <div class="zv-sub">
              <span class="zv-muted">{{ __('Booking #') }}{{ $reservation->id }}</span>
              @if($service?->title)
                <span class="zv-dot">•</span>
                <span class="zv-muted">{{ $service->title }}</span>
              @endif
            </div>
          </div>

          <div class="zv-pillWrap">
            <span class="zv-pill zv-pill--{{ $pillTone($uiStatus) }}">
              {{ $statusLabel($uiStatus) }}
            </span>
          </div>
        </div>

        <div class="zv-facts">
          <div class="zv-fact">
            <div class="zv-fact__k">{{ __('Service Price') }}</div>
            <div class="zv-fact__v">{{ $money($subtotalMinor) }} </div>
          </div>

          <div class="zv-fact">
            <div class="zv-fact__k">{{ __('Sessions') }}</div>
            <div class="zv-fact__v">{{ $slots->count() }}</div>
          </div>

          <div class="zv-fact">
            <div class="zv-fact__k">{{ __('Date Range') }}</div>
            <div class="zv-fact__v">
              @if($firstStart && $lastEnd)
                {{ $firstStart->timezone($tz)->format('d M Y') }} – {{ $lastEnd->timezone($tz)->format('d M Y') }}
              @else
                —
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Grid --}}
    <div class="zv-grid">
      {{-- Left --}}
      <div class="zv-col">
        {{-- What happened --}}
        <div class="zv-card">
          <div class="zv-card__head">
            <div class="zv-card__title">{{ __('History For This Booking') }}</div>
            <div class="zv-card__sub text-capitalize">{{ __('Coach-side history of this booking.') }}</div>
          </div>

          <div class="zv-timeline">
            @foreach($events as $e)
              <div class="zv-timeline__item">
                <div class="zv-timeline__dot}}"></div>
                <div class="zv-timeline__body">
                  <div class="zv-timeline__row">
                    <div class="zv-timeline__title">{{ $e['title'] }}</div>
                    <div class="zv-timeline__time">
                      {{ $e['time'] ? $e['time']->format('d M Y, H:i') : '—' }}
                    </div>
                  </div>
                  @if(!empty($e['meta']))
                    <div class="zv-timeline__meta">{{ $e['meta'] }}</div>
                  @endif
                </div>
              </div>
            @endforeach
          </div>

          @if(!empty($reservation->cancel_reason))
            <div class="zv-note">
              <div class="zv-note__k">{{ __('Cancellation reason') }}</div>
              <div class="zv-note__v">{{ $reservation->cancel_reason }}</div>
            </div>
          @endif
        </div>

        {{-- Sessions --}}
        <div class="zv-card">
          <div class="zv-card__head">
            <div class="zv-card__title">{{ __('Sessions') }}</div>
            <div class="zv-card__sub text-capitalize">{{ __('Status and check-ins for each session.') }}</div>
          </div>

          <div class="zv-table">
            <div class="zv-table__head">
              <div>{{ __('Date') }}</div>
              <div>{{ __('Time') }}</div>
              <div>{{ __('Status') }}</div>
              <div class="zv-hide-sm">{{ __('Check-ins') }}</div>
            </div>

            @foreach($slots as $s)
              @php
                $st = trim(strtolower((string)($s->session_status ?? 'scheduled')));
                $startLocal = $dt($s->start_utc);
                $endLocal   = $dt($s->end_utc);

                $clientCI = $s->client_checked_in_at
                  ? \Carbon\Carbon::parse($s->client_checked_in_at)->timezone($tz)->format('d M Y, H:i')
                  : '—';

                $coachCI = $s->coach_checked_in_at
                  ? \Carbon\Carbon::parse($s->coach_checked_in_at)->timezone($tz)->format('d M Y, H:i')
                  : '—';
              @endphp

              <div class="zv-table__row">
                <div class="zv-table__cell">
                  <div class="zv-date">{{ $startLocal ? $startLocal->format('d M Y') : '—' }}</div>
                  <div class="zv-mini__v">{{ __('Session #:n', ['n' => $loop->iteration]) }}</div>
                </div>

                <div class="zv-table__cell">
                  @if($startLocal && $endLocal)
                    <div class="zv-time">{{ $startLocal->format('H:i') }} – {{ $endLocal->format('H:i') }}</div>
                    <div class="zv-mini__v">{{ $tz }}</div>
                  @else
                    —
                  @endif
                </div>

               <div class="zv-table__cell">
  <span class="zv-pill zv-pill--{{ $pillTone($st) }}">
    {{ $slotLabel($st) }}
  </span>
</div>

                <div class="zv-table__cell zv-hide-sm">
<div class="zv-checkin">
    <span class="zv-mini__v">{{ __('Client') }}:</span>{{ $clientCI }}
     <span class="zv-dot zv-mini__v">•</span>
    <span class="zv-mini__v">{{ __('You') }}:</span>{{ $coachCI }}
    
  </div>

  

  @if(!empty($s->finalized_at))
    <div class="zv-mini__v">
      {{ __('Finalized') }}:
     
        {{ \Carbon\Carbon::parse($s->finalized_at)->timezone($tz)->format('d M Y, H:i') }}
      </span>
    </div>
  @endif
</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- Right --}}
      <div class="zv-col">
        {{-- Coach earnings --}}
        <div class="zv-card">
          <div class="zv-card__head">
            <div class="zv-card__title">{{ __('Your Earnings') }}</div>
            <div class="zv-card__sub text-capitalize">{{ __('Coach-side financial summary for this booking.') }}</div>
          </div>

          <div class="zv-receipt">
          <div class="zv-row">
  <div class="zv-mini__k">{{ __('Service Price') }}</div>
  <div class="zv-amt">{{ $money($subtotalMinor) }} </div>
</div>

@if($coachFeeMinor > 0)
  <div class="zv-divider"></div>
  <div class="zv-row">
    <div class="zv-mini__k">
      {{ __('Platform Fee') }}
      @if($coachFeeLabel) <span class="text-muted">({{ $coachFeeLabel }})</span> @endif
    </div>
    <div class="zv-amt">- {{ $money($coachFeeMinor) }} </div>
  </div>
@endif

<div class="zv-divider"></div>
<div class="zv-row">
  <div class="zv-mini__k">{{ __('Earnings Before Penalty') }}</div>
  <div class="zv-amt">{{ $money($coachNetBeforeAdjust) }} </div>
</div>

@if($coachCompMinor > 0)
  <div class="zv-divider"></div>
  <div class="zv-row">
    <div class="zv-mini__k">{{ __('Cancellation compensation') }}</div>
    <div class="zv-amt">{{ $money($coachCompMinor) }} </div>
  </div>
@endif

@if($coachPenaltyMinor > 0)
  <div class="zv-divider"></div>
  <div class="zv-row">
    <div class="zv-mini__k text-capitalize">{{ __('Penalty charged to you') }}</div>
    <div class="zv-amt">- {{ $money($coachPenaltyMinor) }} </div>
  </div>
@endif

<div class="zv-divider"></div>
<div class="zv-row zv-row--total zv-mini__k">
  <div>{{ __('Net Payout') }}</div>
  <div class="zv-amt">{{ $money($coachNetFinal) }} </div>
</div>

            {{-- Only show platform kept if it was from COACH side (i.e. coach cancels -> platformEarned == coachPenalty in your rules) --}}
            {{-- @if($platformEarnedMinor > 0 && $coachPenaltyMinor > 0)
              <div class="zv-row">
                <div class="zv-muted">{{ __('ZAIVIAS kept') }}</div>
                <div class="zv-amt">{{ $m($platformEarnedMinor) }} {{ $currency }}</div>
              </div>
            @endif --}}

            {{-- <div class="zv-mini">
              <div class="zv-mini__k">{{ __('Payment status') }}</div>
              <div class="zv-mini__v">{{ $statusLabel($paymentStatus ?: '—') }}</div>
            </div>

            <div class="zv-mini">
              <div class="zv-mini__k">{{ __('Settlement') }}</div>
              <div class="zv-mini__v">{{ $statusLabel($settlement ?: '—') }}</div>
            </div> --}}
          </div>
        </div>

        {{-- Cancellation summary (coach-facing) --}}
        @if(in_array($status, ['cancelled','canceled'], true))
          <div class="zv-card">
            <div class="zv-card__head">
              <div class="zv-card__title">{{ __('Cancellation') }}</div>
              <div class="zv-card__sub text-capitalize">{{ __('Who cancelled and how it affected you.') }}</div>
            </div>

            <div class="zv-kv">
              <div class="zv-kv__row">
                <div class="zv-kv__k">{{ __('Cancelled By') }}</div>
                <div class="zv-kv__v">{{ $cancelledByNice }}</div>
              </div>

              <div class="zv-kv__row">
                <div class="zv-kv__k">{{ __('Cancelled At') }}</div>
                <div class="zv-kv__v">
                  {{ $reservation->cancelled_at ? \Carbon\Carbon::parse($reservation->cancelled_at)->timezone($tz)->format('d M Y, H:i') : '—' }}
                </div>
              </div>

              @if($coachPenaltyMinor > 0)
                <div class="zv-kv__row">
                  <div class="zv-kv__k text-capitalize">{{ __('Penalty charged to you') }}</div>
                  <div class="zv-kv__v">- {{ $money($coachPenaltyMinor) }} </div>
                </div>
              @endif
            </div>
          </div>
        @endif

      </div>
    </div>
  </div>
  @endsection
