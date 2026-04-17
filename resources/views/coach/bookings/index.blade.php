  @extends('layouts.role-dashboard')
  @section('title', __('Bookings'))
  

  @push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/coach-bookings.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/client-bookings.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/coach-cancel-modal.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/coach-status-card.css') }}">

  @endpush

  @section('role-content')
  <div class="cb-page">

    <div class="cb-card">
      {{-- Tabs row --}}
    @php $tab = request('tab','my'); @endphp

  <div class="cb-tabs">
    <a class="cb-tab {{ $tab==='my' ? 'active' : '' }}" href="{{ route('coach.bookings',['tab'=>'my']) }}">My Bookings</a>
    <a class="cb-tab {{ $tab==='in_progress' ? 'active' : '' }}" href="{{ route('coach.bookings',['tab'=>'in_progress']) }}">In Progress</a>
    <a class="cb-tab {{ $tab==='completed' ? 'active' : '' }}" href="{{ route('coach.bookings',['tab'=>'completed']) }}">Completed</a>
    <a class="cb-tab {{ $tab==='cancelled' ? 'active' : '' }}" href="{{ route('coach.bookings',['tab'=>'cancelled']) }}">Cancelled</a>
    <a class="cb-tab {{ $tab==='refunded' ? 'active' : '' }}" href="{{ route('coach.bookings',['tab'=>'refunded']) }}">Refunded</a>
    <a class="cb-tab {{ $tab==='dispute' ? 'active' : '' }}" href="{{ route('coach.bookings',['tab'=>'dispute']) }}">In Dispute</a>
  </div>



      @if($bookings->count())
        <div class="cb-body">
          @foreach($bookings as $reservation)
            @php
              $service = $reservation->service;
              $package = $reservation->package;
              $client  = $reservation->client;

              // Service price (what client pays for the service itself)
            $currencySymbol = '$';
$currencyCode   = $reservation->currency ?? 'USD';

$subtotalMinor = (int) ($reservation->subtotal_minor ?? 0);

// ✅ Prefer saved net (best)
$coachNetMinor = (int) ($reservation->coach_net_minor ?? 0);

// ✅ If net not stored, compute from saved coach fee
if ($coachNetMinor <= 0) {
    $coachFeeMinor = (int) ($reservation->coach_fee_minor ?? 0);
    if ($coachFeeMinor > 0) {
        $coachNetMinor = max(0, $subtotalMinor - $coachFeeMinor);
    }
}

// ✅ Final fallback: old reservations (no snapshot) → use current fee percent
if ($coachNetMinor <= 0) {
    $pct = (float) ($fallbackCoachFeePercent ?? 0);
    $coachNetMinor = (int) round($subtotalMinor * (1 - ($pct / 100)));
}

$coachEarning = $coachNetMinor / 100;

          

              

              // COVER IMAGE
              $cover = null;
              if ($service && !empty($service->thumbnail_path)) {
                  $cover = asset('storage/' . $service->thumbnail_path);
              } else {
                  $images = (array) ($service->images ?? []);
                  $first  = reset($images);
                  if (!empty($first)) {
                      $cover = asset('storage/' . $first);
                  }
              }
              if (!$cover) {
                  $cover = asset('assets/img/service-placeholder.jpg');
              }

              // ===== SLOTS (multi-session support) =====
            // ===== SLOTS (multi-session support) =====
  $slots      = $reservation->slots->sortBy('start_utc');

  $coachNoShowSlot = $slots->first(fn($s) => strtolower(trim($s->session_status ?? '')) === 'no_show_coach');
  $bothNoShowSlot  = $slots->first(fn($s) => strtolower(trim($s->session_status ?? '')) === 'no_show_both');

  $settlement = strtolower(trim($reservation->settlement_status ?? ''));
  $rawStatus  = strtolower($reservation->status ?? 'pending');

  $isCoachNoShow = $slots->contains(fn($s) =>
      strtolower(trim($s->session_status ?? '')) === 'no_show_coach'
  );

  // ✅ UI pill should reflect MONEY truth first
 // ✅ UI pill should reflect MONEY truth first
// ✅ UI pill should reflect MONEY truth first
if ($settlement === 'paid') {
    $uiStatus = 'paid';                 // coach has been paid out
} elseif ($settlement === 'pending') {
    $uiStatus = 'pending_payout';       // escrow pending release
} elseif ($settlement === 'in_dispute') {
    $uiStatus = 'in_dispute';
} elseif ($settlement === 'refunded') {
    $uiStatus = 'refunded';
} elseif ($settlement === 'refunded_partial') {
    $uiStatus = 'partially_refunded';
} elseif ($settlement === 'cancelled' || in_array($rawStatus, ['cancelled','canceled'], true)) {
    $uiStatus = 'cancelled';
} elseif (in_array($rawStatus, ['booked','confirmed','paid','in_escrow'], true)) {
    $uiStatus = 'in_progress';
} elseif ($rawStatus === 'completed') {
    $uiStatus = 'completed';
} else {
    $uiStatus = $rawStatus ?: 'pending';
}





  $totalSlots = $slots->count();

  $tz  = auth()->user()->timezone ?? config('app.timezone');
  $now = now();

  // ✅ finalized statuses (match your system/service)
  // ✅ STRICT completion for reservation progress
  $completedStatuses = ['completed'];

  // ✅ refund-track statuses (should NOT appear in In Progress tab)
  $refundTrackStatuses = ['no_show_coach', 'no_show_both'];

  // ✅ non-completed = anything not "completed"



  $completedSlots = $slots->filter(fn($s) =>
    in_array(strtolower(trim($s->session_status ?? '')), ['completed','no_show_client'], true)
  )->count();


  // “remaining” (not completed)
  $remainingSlots = $totalSlots - $completedSlots;
  $finishedStatuses = ['completed','no_show_client','no_show_coach','no_show_both','cancelled','canceled'];


  // ✅ current slot = first NOT finalized slot (prefer active/next)
  // ✅ current slot = first NOT finalized slot (prefer active/next)
  // ✅ current/next slot = first slot that is NOT completed
  $slot = $slots->first(function ($s) use ($finishedStatuses) {
      return !in_array(strtolower(trim($s->session_status ?? '')), $finishedStatuses, true);
  });

  // fallback: if all finished, show last slot
  if (!$slot) $slot = $slots->last();


  // fallback: if all completed, show last slot (history)



  // ✅ DEFINE ONCE, ALWAYS
  $isFinalized = $slot && !empty($slot->finalized_at);



  // fallback: if all are finalized, show last slot (optional)
  if (!$slot) {
      $slot = $slots->last();
      $isFinalized = $slot && !empty($slot->finalized_at);

  }


  $start = $slot?->start_utc;
  $end   = $slot?->end_utc;

  $startUtc = $start ? \Carbon\Carbon::parse($start)->utc() : null;
  $nowUtc   = now('UTC');

  $deadlineUtc = $slot?->extended_until_utc
    ? \Carbon\Carbon::parse($slot->extended_until_utc)->utc()        // start+10
    : ($slot?->wait_deadline_utc
        ? \Carbon\Carbon::parse($slot->wait_deadline_utc)->utc()     // usually start+5
        : ($startUtc ? $startUtc->copy()->addMinutes(5) : null));    // fallback start+5

  $extendOfferAtUtc = $startUtc ? $startUtc->copy()->addMinutes(4) : null;




  $allSessionsCompleted = $totalSlots > 0 && $completedSlots === $totalSlots;

  // ---- Session states for current slot ----
  $clientCheckedIn = $slot && !empty($slot->client_checked_in_at);
  $coachCheckedIn  = $slot && !empty($slot->coach_checked_in_at);
  // ✅ always define (prevents undefined variable)
  $waitingForClient = false;



  // --- NUDGES (coach checked-in, client not yet) ---
  $showNudge1Coach = $slot && $coachCheckedIn && ! $clientCheckedIn && !empty($slot->nudge1_sent_at);
  $showNudge2Coach = $slot && $coachCheckedIn && ! $clientCheckedIn && !empty($slot->nudge2_sent_at);




  $finalEndStates = ['completed','no_show_coach','no_show_client','no_show_both','cancelled','canceled'];



  $finalizedSlots = $slots->filter(fn($s) =>
      in_array(strtolower(trim($s->session_status ?? '')), $finalEndStates, true)
  )->count();

  $allSessionsFinished = $totalSlots > 0 && $finalizedSlots === $totalSlots;

  // strict completed (for “Completed tab” meaning)
  $allSessionsCompleted = $totalSlots > 0 && $completedSlots === $totalSlots;


  // active from -5 min to end
  $slotActiveWindow = $slot && $startUtc && $deadlineUtc
      && $nowUtc->greaterThanOrEqualTo($startUtc->copy()->subMinutes(5))
      && $nowUtc->lessThan($deadlineUtc); // ✅ only until deadline (5 or 10)

  $slotInProgress = $slot && $startUtc && $end
      && $nowUtc->greaterThanOrEqualTo($startUtc)
      && $nowUtc->lessThan(\Carbon\Carbon::parse($end)->utc());

      $waitingForClient   = $slotActiveWindow && $coachCheckedIn && ! $clientCheckedIn;
  $bothCheckedInEarly = $slotActiveWindow && ! $slotInProgress && $coachCheckedIn && $clientCheckedIn;


  


  $canExtend = ! $isFinalized
    && $slot
    && $coachCheckedIn
    && ! $clientCheckedIn
    && empty($slot->extended_until_utc)
    && $extendOfferAtUtc
    && $deadlineUtc
    && $nowUtc->between($extendOfferAtUtc, $deadlineUtc);


  $sessionStarted = $slot && $end
      && $coachCheckedIn
      && $clientCheckedIn
      && $now->lt($end);

  $bothCheckedInEarly  = $slotActiveWindow && ! $slotInProgress && $coachCheckedIn && $clientCheckedIn;


  // ✅ DEBUG (only when the "Both checked in" card WOULD show)
if ($bothCheckedInEarly) {
  \Log::info('COACH_CARD_DEBUG_READY', [
    'reservation_id' => $reservation->id ?? null,
    'slot_id' => $slot?->id,
    'slot_status' => (string) ($slot?->session_status ?? ''),
    'coach_checked_in' => (bool) ($slot?->coach_checked_in_at ?? false),
    'client_checked_in' => (bool) ($slot?->client_checked_in_at ?? false),

    'tz' => $tz,
    'start_local' => $start ? $start->timezone($tz)->format('Y-m-d H:i') : null,
    'end_local'   => $end   ? $end->timezone($tz)->format('Y-m-d H:i')   : null,

    'start_utc_raw' => (string) ($slot?->start_utc ?? ''),
    'end_utc_raw'   => (string) ($slot?->end_utc ?? ''),
    'startUtc' => $startUtc?->toIso8601String(),
    'deadlineUtc' => isset($deadlineUtc) ? $deadlineUtc?->toIso8601String() : null,
    'nowUtc' => now('UTC')->toIso8601String(),

    'slotActiveWindow' => (bool) $slotActiveWindow,
    'slotInProgress' => (bool) $slotInProgress,
    'bothCheckedInEarly' => (bool) $bothCheckedInEarly,
    'waitingForClient' => (bool) $waitingForClient,
    'sessionStarted' => (bool) $sessionStarted,
  ]);
}


              // Can coach start session now? (5 min before → 5 min after start, and coach not yet checked in)
          $coachJoinWindowEnd = $slot?->extended_until_utc
    ? \Carbon\Carbon::parse($slot->extended_until_utc)->utc() // ✅ start+10 if extended
    : ($startUtc ? $startUtc->copy()->addMinutes(5) : null);  // ✅ else start+5

  $canStartSession = ! $isFinalized
      && $slot && $startUtc && $coachJoinWindowEnd
      && ! $coachCheckedIn
      && $reservation->payment_status === 'paid'
      && in_array($reservation->status, ['booked','confirmed','paid','in_escrow'], true)
      && $nowUtc->between($startUtc->copy()->subMinutes(5), $coachJoinWindowEnd);









              // Show code only from 5 min before start and onward, and coach not yet checked in
            

              // ===== POST-SESSION ACTIONS (on card) =====

  // last end time of ALL slots (safe Carbon)
 // ===== POST-SESSION ACTIONS (coach, on card) =====

// ✅ ensure Carbon (avoid string copy() issues)
$lastSlotEndRaw = $slots->max('end_utc'); // string or null
$lastSlotEnd    = $lastSlotEndRaw ? \Carbon\Carbon::parse($lastSlotEndRaw)->utc() : null;

// fallback: if end_utc missing, use last updated_at
if (!$lastSlotEnd) {
  $lastUpdated = optional($slots->sortBy('updated_at')->last())->updated_at;
  $lastSlotEnd = $lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->utc() : null;
}

// 48h window (use UTC consistently)
$disputeWindowEnds   = $lastSlotEnd ? $lastSlotEnd->copy()->addHours(48) : null;
$withinDisputeWindow = $disputeWindowEnds ? now('UTC')->lessThanOrEqualTo($disputeWindowEnds) : true;

$noDispute = empty($reservation->disputed_by_client_at) && empty($reservation->disputed_by_coach_at);

// ✅ coach button should only care if COACH has completed
$coachNotCompleted = empty($reservation->completed_by_coach_at);


$notCancelled = !in_array($reservation->status, ['cancelled','canceled'], true);

// ✅ IMPORTANT: do NOT block post actions with !$isFinalized
// STRICT: only real "completed" counts
$strictCompletedSlots = $slots->filter(fn($s) =>
    strtolower(trim($s->session_status ?? '')) === 'completed'
)->count();

$allSessionsCompletedStrict = $totalSlots > 0 && $strictCompletedSlots === $totalSlots;

// Block complete in refund-track/no-show cases explicitly
$hasNoShowOrCancelled = $slots->contains(fn($s) =>
    in_array(strtolower(trim($s->session_status ?? '')), ['no_show_coach','no_show_client','no_show_both','cancelled','canceled'], true)
);

$canComplete =
    $notCancelled
    && $reservation->payment_status === 'paid'
    && $withinDisputeWindow
    && $coachNotCompleted
    && $allSessionsCompletedStrict
    && ! $hasNoShowOrCancelled;


// Dispute is separate (we already decided: dispute can show after completed only)
$canDispute =
    $notCancelled
    && $reservation->payment_status === 'paid'
    && $withinDisputeWindow
    && $allSessionsCompletedStrict
    && $noDispute; // optional (only allow if no dispute exists yet)





  // ================== CANCELLATION (before first slot starts, no check-in) ==================
  $firstSlot  = $slots->first();
  $firstStart = $firstSlot?->start_utc ? \Carbon\Carbon::parse($firstSlot->start_utc)->utc() : null;

  $anyCheckin = $slots->contains(fn($s) => $s->client_checked_in_at || $s->coach_checked_in_at);

  $nowUtc = now('UTC');

  $canCancel = $firstStart
    && $nowUtc->lt($firstStart)        // only before first slot starts
    && ! $anyCheckin                  // no cancellation after any check-in
    && $reservation->payment_status === 'paid'
    && in_array($reservation->status, ['booked','confirmed','paid','in_escrow'], true)
    && empty($reservation->cancelled_at)
    && !in_array($reservation->status, ['cancelled','canceled'], true);

  // For UI message only (what penalty band would apply IF coach cancels now)
  $hoursUntil = $firstStart ? $nowUtc->diffInRealHours($firstStart, false) : null;
  $cancelBand = is_null($hoursUntil) ? null : ($hoursUntil >= 48 ? '48_plus' : ($hoursUntil >= 24 ? '24_48' : '0_24'));

  $coachPenaltyPercent = $cancelBand === '0_24' ? 20 : ($cancelBand === '24_48' ? 10 : 0);


            @endphp
          


            <article class="cb-booking-card"
          data-slot-id="{{ $slot?->id }}"
          data-nudge1="{{ $showNudge1Coach ? 1 : 0 }}"
          data-nudge2="{{ $showNudge2Coach ? 1 : 0 }}">


              <div class="cb-booking-thumb">
                <img src="{{ $cover }}" alt="{{ $service->title ?? 'Service' }}">
              </div>

              <div class="cb-booking-body">
                <div class="cb-booking-top">
                  <div class="cb-booking-title-wrap">
                    <div class="cb-booking-title">
                      {{ $package->title ?? $service->title ?? __('Package') }}
                    </div>
                    <div class="cb-booking-subtitle">
                      <i class="bi bi-person me-1"></i>
                      {{ $client->full_name ?? ($client->name ?? $reservation->client_email ?? __('Client')) }}
                    </div>
                  </div>

                  <div class="cb-booking-right">
                    <div class="cb-booking-price">
                      {{-- Coach net earning --}}
                     {{ $currencySymbol }}{{ number_format($coachEarning, 2) }} {{ $currencyCode }}
                    </div>
                  <span class="cb-status-pill cb-status-pill-{{ $uiStatus }}">
    {{ ucfirst(str_replace('_',' ', $uiStatus)) }}
  </span>

                  </div>
                </div>

                <div class="cb-booking-meta">
                  @if($slot && $start && $end)
                    {{-- CURRENT / NEXT SESSION TIME --}}
                    <div class="cb-booking-meta-item">
                      <i class="bi bi-calendar-event me-1"></i>
                      {{ $start->timezone($tz)->format('d M Y') }}
                    </div>
                    <div class="cb-booking-meta-item">
                      <i class="bi bi-clock me-1"></i>
                      {{ $start->timezone($tz)->format('H:i') }}
                      –
                      {{ $end->timezone($tz)->format('H:i') }}
                    </div>
                  @elseif($allSessionsCompleted)
                    <div class="cb-booking-meta-item text-muted small">
                      {{ __('All sessions for this booking are completed.') }}
                    </div>
                  @elseif($start || $end)
                    <div class="cb-booking-meta-item">
                      <i class="bi bi-clock me-1"></i>
                      @if($start && $end)
                        {{ $start->timezone($tz)->format('H:i') }}
                        –
                        {{ $end->timezone($tz)->format('H:i') }}
                      @elseif($start)
                        {{ $start->timezone($tz)->format('H:i') }}
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </div>
                  @endif

                @if($totalSlots > 1)
    <div class="cb-booking-meta-item small text-muted">
    {{ __('Sessions Completed: :done / :total', [
    'done' => $completedSlots,
    'total' => $totalSlots
  ]) }}

    </div>
  @endif

              
                </div>

                <div class="cb-booking-footer">
                  {{-- <div class="cb-booking-tag"> --}}
                    {{-- Tiny hint about commission --}}
                  {{-- {{ __('You earn :price after :percent% commission', [ --}}
      {{-- 'price'   => $currency . number_format($coachEarning, 2), --}}
      {{-- 'percent' => rtrim(rtrim(number_format($coachFeePercent, 2), '0'), '.') --}}
  {{-- ]) }} --}}

                  {{-- </div> --}}

                  <div class="d-flex flex-wrap gap-2 ms-auto align-items-center">
  @if($canCancel)
  <button type="button"
          class="cb-booking-btn-secondary js-open-cancel-modal"
          data-action="{{ route('coach.reservations.cancel', $reservation) }}"
          data-quote-url="{{ route('coach.reservations.cancel_quote', $reservation) }}"
          data-booking-title="{{ e($package->title ?? $service->title ?? 'Booking') }}">
    {{ __('Cancel Booking') }}
  </button>
@endif


                <a href="{{ route('coach.bookings.show', $reservation) }}"
    class="cb-booking-btn">
    {{ __('View Details') }}
  </a>

  {{-- Cancel booking (coach): within 24h AND before first slot starts. Penalty: 10% from wallet --}}



  {{-- Video call (ONLY when online + both checked-in) --}}
  @if(($reservation->environment ?? null) === 'online'
      && $slot
      && $slot->coach_checked_in_at
      && $end
      && $now->lt($end))

    <a href="{{ route('slots.call', $slot) }}" class="cb-booking-btn-secondary">
      {{ __('Video call') }}
    </a>
  @endif



  {{-- ===== POST SESSION ACTIONS (coach, on card) ===== --}}
 {{-- ===== POST SESSION ACTIONS (coach, on card) ===== --}}
@if((bool) $reservation->ui_can_complete)
  <button type="button"
          class="cb-booking-btn-secondary js-open-coach-complete-modal"
          data-action="{{ route('coach.bookings.complete', $reservation) }}">
    {{ __('Mark As Complete') }}
  </button>
@endif

{{-- ===== DISPUTE (coach) ===== --}}
@if((bool) ($reservation->has_coach_dispute ?? false))
  <a class="cb-booking-btn-secondary"
     href="{{ route('coach.disputes.show', $reservation->coach_dispute_id) }}">
    {{ __('In Dispute') }}
  </a>

@elseif((bool) ($reservation->has_client_dispute ?? false))
  {{-- optional: coach can still open their own dispute (independent), but UI should show that client already disputed --}}
  <a class="cb-booking-btn-secondary"
   href="{{ route('coach.disputes.show', $reservation->client_dispute_id) }}">
  {{ __('Client Raised Dispute') }}
</a>

@elseif((bool) $reservation->ui_can_dispute)
  <a class="cb-booking-btn-secondary"
     href="{{ route('coach.disputes.create', ['reservation' => $reservation->id]) }}">
    {{ __('Raise Dispute') }}
  </a>
@endif




 @php
  $slotStatus = strtolower(trim($slot->session_status ?? ''));

  $statusMsg = null;
  $statusLabel = null;

  if ($coachNoShowSlot) {
    $statusLabel = __('No-show');
    $statusMsg   = __('Coach did not join in time. Session auto-finalized and client refunded.');
  } elseif ($bothNoShowSlot) {
    $statusLabel = __('No-show');
    $statusMsg   = __('Both no-show. ');
  } elseif ($isFinalized && $slotStatus === 'no_show_client') {
    $statusLabel = __('No-show');
    $statusMsg   = __('Client did not join. Session finalized (client no-show).');
  } elseif (strtolower(trim($reservation->settlement_status ?? '')) === 'pending') {
    $statusLabel = __('Pending');
    $statusMsg   = __('Funds are in escrow and will be released after 48 hours if no dispute is raised.');
  } elseif (strtolower(trim($reservation->settlement_status ?? '')) === 'in_dispute') {
    $statusLabel = __('Dispute');
    $statusMsg   = __('This booking is in dispute. Settlement is on hold until it is resolved.');
  }
@endphp

@if($statusMsg)
  <div class="d-flex align-items-center gap-2">
    <span class="cb-badge cb-badge--warn">{{ $statusLabel }}</span>

    <button type="button"
            class="btn p-0 border-0 bg-transparent js-status-info"
            data-title="{{ e($package->title ?? $service->title ?? 'Booking') }}"
            data-message="{{ e($statusMsg) }}"
            aria-label="{{ __('View details') }}"
            title="{{ __('View details') }}">
      <i class="bi bi-info-circle"></i>
    </button>
  </div>

@elseif($sessionStarted)
  <span class="booking-session-label text-success fw-semibold">
    {{ __('Session has started') }}
  </span>

@elseif($bothCheckedInEarly)
  <div class="cb-status cb-status--ok">
    <div class="cb-status__left">
      <span class="cb-status__dot" aria-hidden="true"></span>
      <div class="cb-status__text">
        <div class="cb-status__title">{{ __('Both are checked in') }}</div>
        <div class="cb-status__sub">
          {{ __('Session will start at :time', ['time' => $start->timezone($tz)->format('H:i')]) }}
        </div>
      </div>
    </div>

    

    <div class="cb-status__right">
      <span class="cb-badge cb-badge--ok">{{ __('Ready') }}</span>
    </div>
  </div>

@elseif($waitingForClient)
  <div class="cb-status cb-status--warn">
    <div class="cb-status__left">
      <span class="cb-status__dot" aria-hidden="true"></span>

      <div class="cb-status__text">
        <div class="cb-status__title">{{ __('You are checked in') }}</div>
        <div class="cb-status__sub">{{ __('Waiting for client to join…') }}</div>

        <div class="cb-status__countdown">
          <span class="cb-status__label js-waiting-label">{{ __('Client must join in') }}</span>

          <span class="cb-timer js-slot-countdown"
                data-now="{{ now('UTC')->timestamp }}"
                data-start="{{ $startUtc?->timestamp }}"
                data-deadline="{{ $deadlineUtc?->timestamp }}"
                data-extend-at="{{ $extendOfferAtUtc?->timestamp }}"
                data-extended="{{ $slot?->extended_until_utc ? 1 : 0 }}">
            --
          </span>
        </div>
      </div>
    </div>

    <div class="cb-status__right">
      @if($canExtend && $slot)
        <form method="POST" action="{{ route('coach.slots.extend_wait', $slot) }}" class="cb-status__action">
          @csrf
          <button type="submit" class="cb-btn cb-btn--soft">
            {{ __('Extend 5 min') }}
          </button>
        </form>
        <div class="cb-status__hint">
          {{ __("Auto-finalizes if client doesn't arrive by the new deadline.") }}
        </div>
      @else
        <span class="cb-badge cb-badge--warn">{{ __('Waiting') }}</span>
      @endif
    </div>
  </div>

@else
  {{-- fallback actions --}}
  @if($canStartSession && $slot)
    <button type="button"
        class="cb-booking-btn-secondary js-coach-open-start-modal"
        data-confirm-url="{{ route('coach.slots.coach.checkin', $slot) }}">
  {{ __('Start session') }}
</button>
  @endif
@endif


                  </div>
                </div>
              </div>
            </article>
          @endforeach
        </div>

        @if(method_exists($bookings, 'links'))
          <div class="cb-pagination">
            {{ $bookings->links() }}
          </div>
        @endif
      @else
        <div class="cb-empty">
          <p class="mb-1 fw-semibold text-capitalize">{{ __('No bookings yet') }}</p>
          <p class="text-muted mb-0 text-capitalize">
            {{ __("Once clients book your services, they will appear here.") }}
          </p>
        </div>
      @endif
    </div>

  </div>

  {{-- ========= MODALS FOR COACH ========= --}}

  {{-- 1) Show coach session code --}}


<div class="modal fade" id="coachCancelBookingModal" tabindex="-1" aria-labelledby="coachCancelBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content zv-cancel">

      <div class="modal-header">
        <h5 class="modal-title" id="coachCancelBookingModalLabel">{{ __('Cancel Booking') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <p class="mb-1 fw-semibold" id="coachCancelBookingTitle">—</p>
        <p class="small text-capitalize text-muted mb-3">
          {{ __("Before you cancel, review the impact below. A penalty may apply depending on how close the first session is.") }}
        </p>

        {{-- Quote box --}}
        {{-- Quote box (coach-only) --}}
<div class="zv-cancel__quote" id="coachCancelQuoteBox" style="display:none;">
  <div class="zv-cancel__row">
    <div class="zv-muted text-black text-capitalize ">{{ __('Penalty charged to you') }}</div>
    <div class="zv-amt zv-amt--neg" id="coachCancelPenalty">—</div>
  </div>

  <div class="zv-cancel__row zv-cancel__row--hint">
    <div class="zv-muted text-black">{{ __('Timing') }}</div>
    <div class="zv-amt" id="coachCancelHours">—</div>
  </div>

  <div class="zv-cancel__note text-muted small mt-2" id="coachCancelNote">—</div>
</div>


        <div class="alert alert-light border small text-muted mb-3" id="coachCancelLoading">
          {{ __('Loading cancellation details…') }}
        </div>

        <div class="mb-3">
          <label class="form-label" for="coachCancelReason">{{ __('Reason (Optional)') }}</label>
          <textarea class="form-control text-capitalize" id="coachCancelReason" rows="3" placeholder="{{ __('Tell the client why you are cancelling…') }}"></textarea>
        </div>

        <div class="text-danger small d-none" id="coachCancelError"></div>
      </div>

      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Keep booking') }}
        </button>

        <form method="POST" id="coachCancelBookingForm" action="#">
          @csrf
          <input type="hidden" name="reason" id="coachCancelReasonHidden" value="">
          <button type="submit" class="btn bg-black text-white rounded-pill px-4" id="coachCancelSubmitBtn">
            {{ __('Yes, Cancel Booking') }}
          </button>
        </form>
      </div>

    </div>
  </div>
</div>


  {{-- Coach Nudge 1 Modal --}}
  <div class="modal fade" id="coachNudge1Modal" tabindex="-1" aria-labelledby="coachNudge1ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="coachNudge1ModalLabel">{{ __('Client is running late') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            {{ __("It looks like your Client is running a little late. We are nudging them to join you right away. (Attempt 1 of 2)") }}
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-dismiss="modal">
            {{ __('Okay') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Coach Nudge 2 Modal --}}
  <div class="modal fade" id="coachNudge2Modal" tabindex="-1" aria-labelledby="coachNudge2ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="coachNudge2ModalLabel">{{ __('Final nudge sent') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            {{ __("We are giving your Client one final nudge. If they don't arrive by the 4-minute mark, you can extend your wait by an additional 5 minutes. Otherwise, the session will automatically finalize at 5 minutes.") }}
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-dismiss="modal">
            {{ __('Got it') }}
          </button>
        </div>
      </div>
    </div>
  </div>


  {{-- 2) Start session modal (coach enters their code) --}}
<div class="modal fade" id="coachStartSessionModal" tabindex="-1" aria-labelledby="coachStartSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coachStartSessionModalLabel">{{ __('Start Session') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted text-capitalize">
          {{ __('Confirm to start your session.') }}
        </p>

        <p class="small text-muted text-capitalize">
          {{ __('location is used to verify attendance') }}
        </p>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn btn-dark rounded-pill px-4" id="coachStartSessionConfirmBtn">
          {{ __('Start Now') }}
        </button>
      </div>
    </div>
  </div>
</div>

  <div class="modal fade" id="coachCompleteBookingModal" tabindex="-1" aria-labelledby="coachCompleteBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="coachCompleteBookingModalLabel">{{ __('Complete booking') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>

        <div class="modal-body">
          <p class="mb-2">
            {{ __("Mark This Booking As Complete?") }}
          </p>
          <p class="small text-muted mb-0">
            {{ __("If the client also completes and no dispute is raised within the 48-hour window, funds will be released automatically.") }}
          </p>
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
            {{ __('Cancel') }}
          </button>

          <form method="POST" id="coachCompleteBookingForm" action="#">
            @csrf
            <button type="submit" class="btn btn-dark rounded-pill px-4">
              {{ __('Yes, complete') }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>


  {{-- Booking status info modal --}}
<div class="modal fade" id="bookingStatusInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('Details') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <p class="mb-1 fw-semibold" id="bookingStatusInfoBooking">—</p>
        <div class="alert alert-light border mb-0" id="bookingStatusInfoMessage">—</div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Okay') }}
        </button>
      </div>

    </div>
  </div>
</div>




@if($pendingCoachRatingReservation)
@php
  $ratingReservation = $pendingCoachRatingReservation;
  $ratingClient = $ratingReservation->client;
  $ratingTitle  = $ratingReservation->package?->title ?? $ratingReservation->service?->title ?? __('Booking');
@endphp

<div class="modal fade"
     id="coachMandatoryRatingModal"
     tabindex="-1"
     aria-labelledby="coachMandatoryRatingModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form method="POST"
            action="{{ route('coach.reviews.store', $ratingReservation) }}"
            id="coachMandatoryRatingForm">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title" id="coachMandatoryRatingModalLabel">
            {{ __('Rate Your Client') }}
          </h5>
        </div>

        <div class="modal-body">
          <p class="mb-1 fw-semibold">{{ $ratingTitle }}</p>

          <p class="small text-muted mb-3 text-capitalize">
            {{ __('Please rate your completed session with') }}
            <strong>{{ $ratingClient->full_name ?? $ratingClient->name ?? __('Client') }}</strong>.
            {{ __('This rating is required before continuing.') }}
          </p>

          <div class="mb-3">
            <label class="form-label d-block">{{ __('Your Rating') }}</label>

            <div class="coach-rating-stars" id="coachRatingStars">
              @for($i = 1; $i <= 5; $i++)
                <button type="button"
                        class="coach-star-btn"
                        data-value="{{ $i }}"
                        aria-label="Rate {{ $i }} star{{ $i > 1 ? 's' : '' }}">
                  ★
                </button>
              @endfor
            </div>

            <input type="hidden" name="stars" id="coachRatingStarsInput" value="{{ old('stars') }}">
            <div class="text-danger small mt-1 d-none text-capitalize" id="coachRatingStarsError">
              {{ __('Please select a star rating.') }}
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label" for="coachRatingComment">{{ __('Description') }}</label>
            <textarea
              name="description"
              id="coachRatingComment"
              rows="4"
              class="form-control"
              placeholder="{{ __('Share Your Experience With This Client...') }}"
              required
            >{{ old('description') }}</textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-dark rounded-pill px-4">
            {{ __('Submit Rating') }}
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
@endif

  @endsection


  
  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('coachCompleteBookingModal');
    const formEl  = document.getElementById('coachCompleteBookingForm');
    let modal     = null;

    if (modalEl && typeof bootstrap !== 'undefined') {
      modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    document.querySelectorAll('.js-open-coach-complete-modal').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const action = this.dataset.action;
        if (formEl && action) formEl.action = action;
        if (modal) modal.show();
      });
    });
  });
  </script>



@if($pendingCoachRatingReservation)
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl = document.getElementById('coachMandatoryRatingModal');
  const formEl  = document.getElementById('coachMandatoryRatingForm');
  const inputEl = document.getElementById('coachRatingStarsInput');
  const errEl   = document.getElementById('coachRatingStarsError');

  if (!modalEl || !formEl || !inputEl) return;

  const modal = new bootstrap.Modal(modalEl, {
    backdrop: 'static',
    keyboard: false
  });

  const starButtons = Array.from(modalEl.querySelectorAll('.coach-star-btn'));

  function paintStars(value) {
    starButtons.forEach(function (btn) {
      const btnVal = Number(btn.dataset.value || 0);
      btn.classList.toggle('is-active', btnVal <= value);
    });
  }

  starButtons.forEach(function (btn) {
    btn.addEventListener('mouseenter', function () {
      const value = Number(this.dataset.value || 0);
      starButtons.forEach(function (b) {
        const bVal = Number(b.dataset.value || 0);
        b.classList.toggle('is-hover', bVal <= value);
      });
    });

    btn.addEventListener('mouseleave', function () {
      starButtons.forEach(function (b) {
        b.classList.remove('is-hover');
      });
    });

    btn.addEventListener('click', function () {
      const value = Number(this.dataset.value || 0);
      inputEl.value = value;
      paintStars(value);
      if (errEl) errEl.classList.add('d-none');
    });
  });

  formEl.addEventListener('submit', function (e) {
    const stars = Number(inputEl.value || 0);
    if (stars < 1 || stars > 5) {
      e.preventDefault();
      if (errEl) errEl.classList.remove('d-none');
    }
  });

  modal.show();
});
</script>
@endif
  <script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl   = document.getElementById('coachCancelBookingModal');
  const formEl    = document.getElementById('coachCancelBookingForm');
  const titleEl   = document.getElementById('coachCancelBookingTitle');
  const reasonEl  = document.getElementById('coachCancelReason');
  const reasonHid = document.getElementById('coachCancelReasonHidden');
  const submitBtn = document.getElementById('coachCancelSubmitBtn');

  const loadingEl = document.getElementById('coachCancelLoading');
  const quoteBox  = document.getElementById('coachCancelQuoteBox');
  const errEl     = document.getElementById('coachCancelError');

 
  const penaltyEl = document.getElementById('coachCancelPenalty');
  const hoursEl   = document.getElementById('coachCancelHours');
  const noteEl    = document.getElementById('coachCancelNote');

  if (!modalEl || !formEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  function money(minor, currency) {
    const n = (Number(minor || 0) / 100);
    return n.toFixed(2) + ' ' + (currency || 'USD');
  }

  function showError(msg) {
    if (!errEl) return;
    errEl.textContent = msg || 'Something went wrong.';
    errEl.classList.remove('d-none');
  }

  function clearError() {
    if (!errEl) return;
    errEl.textContent = '';
    errEl.classList.add('d-none');
  }

  async function loadQuote(url) {
    if (loadingEl) loadingEl.style.display = '';
    if (quoteBox) quoteBox.style.display = 'none';
    clearError();

    const res = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });

    const data = await res.json().catch(() => null);
    if (!res.ok || !data || !data.ok) {
      throw new Error((data && data.message) || 'Unable to load cancellation details.');
    }

    const q = data.quote || {};
    const currency = q.currency || 'USD';

    
    if (penaltyEl) penaltyEl.textContent = (Number(q.coach_penalty_minor || 0) > 0)
      ? ('- ' + money(q.coach_penalty_minor, currency))
      : ('0.00 ' + currency);

    if (hoursEl) hoursEl.textContent = (typeof q.hours_until === 'number')
      ? `${Math.floor(q.hours_until)} Hour(s) Until First Session`
      : '—';

    // Coach-facing note (policy)
    if (noteEl) {
      const h = Number(q.hours_until);
      if (Number.isFinite(h)) {
        if (h >= 48) noteEl.textContent = 'No Penalty Applies (48+ Hours).';
        else if (h >= 24) noteEl.textContent = 'A 10% Penalty Applies To You (24–48 Hours).';
        else noteEl.textContent = 'A 20% Penalty Applies To You (Under 24 Hours).';
      } else {
        noteEl.textContent = '—';
      }
    }

    if (loadingEl) loadingEl.style.display = 'none';
    if (quoteBox) quoteBox.style.display = '';
  }

  document.querySelectorAll('.js-open-cancel-modal').forEach(function(btn){
    btn.addEventListener('click', async function(){
      const action   = this.dataset.action || '#';
      const quoteUrl = this.dataset.quoteUrl || '';
      const title    = this.dataset.bookingTitle || 'Booking';

      formEl.action = action;
      if (titleEl) titleEl.textContent = title;
      if (reasonEl) reasonEl.value = '';
      if (reasonHid) reasonHid.value = '';
      if (submitBtn) submitBtn.disabled = false;

      modal.show();

      try {
        if (quoteUrl) await loadQuote(quoteUrl);
        else throw new Error('Missing quote URL.');
      } catch (e) {
        if (loadingEl) loadingEl.style.display = 'none';
        showError(e.message);
      }
    });
  });

  formEl.addEventListener('submit', function(){
    if (reasonHid && reasonEl) reasonHid.value = (reasonEl.value || '').trim();
    if (submitBtn) submitBtn.disabled = true;
  });
});
</script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    function pad(n){ return String(n).padStart(2,'0'); }

    function fmt(seconds) {
      seconds = Math.max(0, Math.floor(seconds));
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      return `${pad(m)}:${pad(s)}`;
    }

    document.querySelectorAll('.js-slot-countdown').forEach(function (el) {
      const serverNow   = parseInt(el.dataset.now || '0', 10);       // UTC seconds from server
      const startTs     = parseInt(el.dataset.start || '0', 10);     // UTC seconds
      const deadlineTs  = parseInt(el.dataset.deadline || '0', 10);  // UTC seconds
      const extendAtTs  = parseInt(el.dataset.extendAt || '0', 10);  // UTC seconds

      if (!deadlineTs || !startTs) {
        el.textContent = '--';
        return;
      }

      // sync client clock to server clock
      const clientNow = Math.floor(Date.now() / 1000);
      const offset = serverNow ? (serverNow - clientNow) : 0;

      const card = el.closest('.cb-booking-card');
      const extendForm = card ? card.querySelector('form[action*="extend_wait"]') : null;

      function tick() {
        const now = Math.floor(Date.now() / 1000) + offset;

        // show countdown until deadline
        const remaining = deadlineTs - now;

        // Optional: show/hide extend button in the offered window (start+4 -> deadline)
        if (extendForm && extendAtTs && deadlineTs) {
          const inOffer = now >= extendAtTs && now < deadlineTs;
          // if you want it always visible, remove this block
          extendForm.style.display = inOffer ? '' : 'none';
        }

        if (remaining <= 0) {
          el.textContent = '00:00';
          // refresh once so UI/status updates after auto-finalize
          setTimeout(() => window.location.reload(), 800);
          return;
        }

        el.textContent = fmt(remaining);
      }

      tick();
      setInterval(tick, 1000);
    });
  });
  </script>


 <script>
document.addEventListener('DOMContentLoaded', function () {
  let coachCurrentConfirmUrl = null;

  const coachStartModalEl = document.getElementById('coachStartSessionModal');
  const coachStartBtn     = document.getElementById('coachStartSessionConfirmBtn');
  let coachStartModal     = null;

  if (coachStartModalEl && typeof bootstrap !== 'undefined') {
    coachStartModal = bootstrap.Modal.getOrCreateInstance(coachStartModalEl);
  }

  document.querySelectorAll('.js-coach-open-start-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      coachCurrentConfirmUrl = this.dataset.confirmUrl || null;

      if (coachStartModal) {
        coachStartModal.show();
      } else if (coachCurrentConfirmUrl) {
        coachStartSessionRequest(coachCurrentConfirmUrl);
      }
    });
  });

  if (coachStartBtn) {
    coachStartBtn.addEventListener('click', function () {
      if (!coachCurrentConfirmUrl) return;
      coachStartSessionRequest(coachCurrentConfirmUrl);
    });
  }

  function coachStartSessionRequest(confirmUrl) {
    if (!confirmUrl) return;

    if (!navigator.geolocation) {
      alert('Location is required but your browser does not support it.');
      return;
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
      const formData = new FormData();
      formData.append('_token', '{{ csrf_token() }}');
      formData.append('lat', pos.coords.latitude);
      formData.append('lng', pos.coords.longitude);

      fetch(confirmUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: formData
      })
      .then(async (res) => {
        let payload = null;
        try {
          payload = await res.json();
        } catch (e) {}

        if (!res.ok) {
          const msg =
            (payload && (payload.message || payload.error)) ||
            ('Unexpected server error (' + res.status + ').');
          throw new Error(msg);
        }

        if (coachStartModal) coachStartModal.hide();
        window.location.reload();
      })
      .catch((err) => {
        alert(err.message || 'Something went wrong while starting the session.');
      });

    }, function () {
      alert('We could not access your location. Please allow location access to start the session.');
    });
  }
});
</script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;

    const n1 = document.getElementById('coachNudge1Modal');
    const n2 = document.getElementById('coachNudge2Modal');
    if (!n1 || !n2) return;

    const m1 = bootstrap.Modal.getOrCreateInstance(n1);
    const m2 = bootstrap.Modal.getOrCreateInstance(n2);

    document.querySelectorAll('.cb-booking-card[data-slot-id]').forEach(function(card){
      const slotId = card.dataset.slotId;
      if (!slotId) return;

      const show1 = card.dataset.nudge1 === '1';
      const show2 = card.dataset.nudge2 === '1';

      // Use different keys than client side
      const key1 = `zv_coach_nudge1_shown_${slotId}`;
      const key2 = `zv_coach_nudge2_shown_${slotId}`;

      // Prefer nudge2
      if (show2 && !localStorage.getItem(key2)) {
        localStorage.setItem(key2, '1');
        m2.show();
        return;
      }

      if (show1 && !localStorage.getItem(key1)) {
        localStorage.setItem(key1, '1');
        m1.show();
        return;
      }
    });
  });
  </script>



<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl = document.getElementById('bookingStatusInfoModal');
  if (!modalEl) return;

  const bookingEl = document.getElementById('bookingStatusInfoBooking');
  const msgEl     = document.getElementById('bookingStatusInfoMessage');

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  document.querySelectorAll('.js-status-info').forEach(btn => {
    btn.addEventListener('click', function () {
      const title = this.dataset.title || 'Booking';
      const msg   = this.dataset.message || '—';

      if (bookingEl) bookingEl.textContent = title;
      if (msgEl) msgEl.textContent = msg;

      modal.show();
    });
  });
});
</script>
  @endpush
