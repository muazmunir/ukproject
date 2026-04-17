@extends('layouts.role-dashboard')



@push('styles')
  
  <link rel="stylesheet" href="{{ asset('assets/css/coach-bookings.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/client-bookings.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/coach-cancel-modal.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/coach-status-card.css') }}">


@endpush
@section('role-content')

@php $tab = request('tab','my'); @endphp

<div class="cb-page">
  <div class="cb-card">

    <div class="cb-tabs">
      <a class="cb-tab {{ $tab==='my' ? 'active' : '' }}" href="{{ route('client.home',['tab'=>'my']) }}">My Bookings</a>
      <a class="cb-tab {{ $tab==='in_progress' ? 'active' : '' }}" href="{{ route('client.home',['tab'=>'in_progress']) }}">In Progress</a>
      <a class="cb-tab {{ $tab==='completed' ? 'active' : '' }}" href="{{ route('client.home',['tab'=>'completed']) }}">Completed</a>
      <a class="cb-tab {{ $tab==='cancelled' ? 'active' : '' }}" href="{{ route('client.home',['tab'=>'cancelled']) }}">Cancelled</a>
      <a class="cb-tab {{ $tab==='refunded' ? 'active' : '' }}" href="{{ route('client.home',['tab'=>'refunded']) }}">Refunded</a>
      <a class="cb-tab {{ $tab==='dispute' ? 'active' : '' }}" href="{{ route('client.home',['tab'=>'dispute']) }}">In Dispute</a>
    </div>



 @if(in_array($tab, ['my','in_progress','completed','cancelled','refunded','dispute'], true))



    @if($bookings->count())
      <div class="cb-body">

        @foreach($bookings as $reservation)
          @php
            $service = $reservation->service;
            $package = $reservation->package;
            $coach   = $service?->coach;

            // Prices
            $servicePrice = $reservation->subtotal_minor / 100;
            $clientFee    = $reservation->fees_minor / 100;
            $total        = $reservation->total_minor / 100;
            $currencySymbol = '$';
$currencyCode   = 'USD';

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

            // SLOT: we use the FIRST slot (or you can adapt for multiple)
          // ---- SLOTS (multi-session support) ----
$slots = $reservation->slots->sortBy('start_utc');
$tz    = auth()->user()->timezone ?? config('app.timezone');
$now   = now();

$finishedStatuses = [
  'completed',
  'no_show_coach',
  'no_show_client',
  'no_show_both',
  'cancelled',
  'canceled',
];

$completedStatuses = ['completed','no_show_client'];
$refundTrackStatuses = ['no_show_coach','no_show_both']; // optional helper


$totalSlots = $slots->count();

$completedSlots = $slots->filter(fn($s) =>
  in_array(trim(strtolower($s->session_status ?? '')), $completedStatuses, true)
)->count();

$finishedSlots = $slots->filter(fn($s) =>
  in_array(trim(strtolower($s->session_status ?? '')), $finishedStatuses, true)
)->count();

$allSessionsCompleted = $totalSlots > 0 && $completedSlots === $totalSlots;
$allSessionsFinished  = $totalSlots > 0 && $finishedSlots  === $totalSlots;


// ✅ event slots (DON'T depend on $slot)
$coachNoShowSlot = $slots->first(fn($s) => trim(strtolower($s->session_status ?? '')) === 'no_show_coach');
$bothNoShowSlot  = $slots->first(fn($s) => trim(strtolower($s->session_status ?? '')) === 'no_show_both');

$isCoachNoShow = (bool) $coachNoShowSlot;
$isBothNoShow  = (bool) $bothNoShowSlot;

// ✅ UI status should follow settlement_status first (your settlement service sets it)
$settlement = strtolower((string)($reservation->settlement_status ?? ''));
$status     = strtolower((string)($reservation->status ?? ''));
$refundStatus = strtolower((string)($reservation->refund_status ?? 'none'));
$refundMinor  = (int)($reservation->refund_total_minor ?? 0);

$latestRefund = $reservation->relationLoaded('latestRefund')
    ? $reservation->latestRefund
    : ($reservation->refunds->sortByDesc('id')->first());

$walletPaidMinor   = $reservation->walletPaidMinor();
$externalPaidMinor = $reservation->externalPaidMinor();

$feesMinor         = (int) ($reservation->fees_minor ?? 0);
$storedRefundMinor = (int) ($reservation->refund_total_minor ?? 0);
$refundStatus      = strtolower((string) ($reservation->refund_status ?? 'none'));
$cancelledBy       = strtolower((string) ($reservation->cancelled_by ?? ''));

$isWalletOnly   = ($walletPaidMinor > 0 && $externalPaidMinor <= 0);
$isExternalOnly = ($walletPaidMinor <= 0 && $externalPaidMinor > 0);
$isMixed        = ($walletPaidMinor > 0 && $externalPaidMinor > 0);

$feesRefundable =
    in_array($cancelledBy, ['coach', 'admin', 'system'], true)
    || (
        (int) ($reservation->platform_earned_minor ?? 0) === 0
        && (int) ($reservation->refund_total_minor ?? 0) === (int) ($reservation->total_minor ?? 0)
    );

// -------------------------
// Remaining retry amounts from latest refund
// -------------------------
$remainingWalletRetryMinor = 0;
$remainingExternalRetryMinor = 0;
$remainingRetryTotalMinor = 0;

if ($latestRefund && in_array($refundStatus, ['partial', 'failed'], true)) {
    $remainingWalletRetryMinor = in_array($latestRefund->wallet_status, ['succeeded', 'not_applicable'], true)
        ? 0
        : (int) ($latestRefund->wallet_amount_minor ?? 0);

    $remainingExternalRetryMinor = in_array($latestRefund->external_status, ['succeeded', 'not_applicable'], true)
        ? 0
        : (int) ($latestRefund->external_amount_minor ?? 0);

    $remainingRetryTotalMinor = $remainingWalletRetryMinor + $remainingExternalRetryMinor;
}

// -------------------------
// Preview split only for pending_choice
// -------------------------
$previewWalletMinor = 0;
$previewExternalMinor = 0;

if ($refundStatus === 'pending_choice' && $storedRefundMinor > 0) {
    $walletFeeNonRefundable = 0;
    $externalFeeNonRefundable = 0;

    if (!$feesRefundable && $feesMinor > 0) {
        $walletFeeNonRefundable = min($feesMinor, $walletPaidMinor);
        $spill = max(0, $feesMinor - $walletFeeNonRefundable);
        $externalFeeNonRefundable = min($spill, $externalPaidMinor);
    }

    $walletRefundableMax   = max(0, $walletPaidMinor - $walletFeeNonRefundable);
    $externalRefundableMax = max(0, $externalPaidMinor - $externalFeeNonRefundable);

    $previewExternalMinor = min($storedRefundMinor, $externalRefundableMax);
    $remaining = max(0, $storedRefundMinor - $previewExternalMinor);
    $previewWalletMinor = min($remaining, $walletRefundableMax);
}

// -------------------------
// UI display values
// -------------------------
$displayRefundTotalMinor = 0;
$displayRefundWalletMinor = 0;
$displayRefundExternalMinor = 0;

if (in_array($refundStatus, ['partial', 'failed'], true) && $latestRefund) {
    $displayRefundTotalMinor    = $remainingRetryTotalMinor;
    $displayRefundWalletMinor   = $remainingWalletRetryMinor;
    $displayRefundExternalMinor = $remainingExternalRetryMinor;
} elseif ($latestRefund && in_array($refundStatus, ['processing', 'succeeded', 'refunded'], true)) {
    $displayRefundTotalMinor    = (int) ($latestRefund->actual_amount_minor ?? 0);
    $displayRefundWalletMinor   = (int) ($latestRefund->wallet_amount_minor ?? 0);
    $displayRefundExternalMinor = (int) ($latestRefund->external_amount_minor ?? 0);
} else {
    $displayRefundTotalMinor    = $storedRefundMinor;
    $displayRefundWalletMinor   = $previewWalletMinor;
    $displayRefundExternalMinor = $previewExternalMinor;
}

$showRefundChoice = (
    $displayRefundTotalMinor > 0
    && in_array($refundStatus, ['pending_choice', 'failed', 'partial'], true)
);

// only for UI explanation
$feeFromWallet = (!$feesRefundable && $isMixed) ? min($feesMinor, $walletPaidMinor) : 0;
$walletRefundableMax = max(0, $walletPaidMinor - $feeFromWallet);

// allowed methods
$allowedRefundMethods = ['wallet_credit'];
if (!$isWalletOnly && $externalPaidMinor > 0) {
    $allowedRefundMethods = ['wallet_credit', 'original_payment'];
}

$refundDone       = in_array($refundStatus, ['refunded', 'succeeded'], true);
$refundFailed     = ($refundStatus === 'failed');
$refundProcessing = in_array($refundStatus, ['processing'], true);


if (in_array($refundStatus, ['pending_choice', 'processing'], true)) {
    $uiStatus = 'refund_pending';
}
elseif ($refundStatus === 'partial') {
    $uiStatus = 'partially_refunded';
}
elseif (in_array($refundStatus, ['succeeded', 'refunded'], true) || in_array($settlement, ['refunded'], true)) {
    $uiStatus = 'refunded';
}
elseif (in_array($settlement, ['refunded_partial'], true)) {
    $uiStatus = 'partially_refunded';
}

elseif ($settlement === 'paid') {
    $uiStatus = 'paid';
}
elseif ($settlement === 'pending') {
    $uiStatus = 'pending_payout';
}
elseif ($settlement === 'in_dispute') {
    $uiStatus = 'in_dispute';
}
elseif ($settlement === 'refunded') {
    $uiStatus = 'refunded';
}
elseif ($settlement === 'refunded_partial') {
    $uiStatus = 'partially_refunded';
}
elseif ($settlement === 'cancelled' || in_array($status, ['cancelled','canceled'], true)) {
    $uiStatus = 'cancelled';
}
elseif ($status === 'completed') {
    $uiStatus = 'completed';
}
else {
    $uiStatus = $status ?: 'pending';
}






// Active/upcoming slot = first NOT-final slot
// current/next slot = first slot that is NOT finished
$slot = $slots->first(function ($s) use ($finishedStatuses) {
    return !in_array(trim(strtolower($s->session_status ?? '')), $finishedStatuses, true);
});

// fallback: if all finished, show last slot (history)
if (!$slot) $slot = $slots->last();





// If no upcoming slot, we treat as all completed
$start = $slot?->start_utc;
$end   = $slot?->end_utc;
$isFinalized = $slot && !empty($slot->finalized_at);


$sessionStarted = $slot
    && $end
    && $now->lt($end)
    && (
        strtolower($slot->session_status ?? '') === 'live'
        || ($slot->client_checked_in_at && $slot->coach_checked_in_at)
    );



$startUtc = $start ? \Carbon\Carbon::parse($start)->utc() : null;
$nowUtc   = now('UTC');
// --- NUDGES (client checked-in, coach not yet) ---
$showNudge1 = $slot && $slot->client_checked_in_at && ! $slot->coach_checked_in_at && !empty($slot->nudge1_sent_at);
$showNudge2 = $slot && $slot->client_checked_in_at && ! $slot->coach_checked_in_at && !empty($slot->nudge2_sent_at);


$baseDeadline = $startUtc ? $startUtc->copy()->addMinutes(5) : null;

// ✅ If extended, deadline becomes start+10
$coachJoinDeadline = $slot?->extended_until_utc
  ? \Carbon\Carbon::parse($slot->extended_until_utc)->utc()
  : ($slot?->wait_deadline_utc
      ? \Carbon\Carbon::parse($slot->wait_deadline_utc)->utc()
      : $baseDeadline);

$extendOfferAt = $startUtc ? $startUtc->copy()->addMinutes(4) : null;


// $extendOfferAt = ($slot && $start) ? $start->copy()->addMinutes(4) : null;

$canExtend = ! $isFinalized
    && $slot
    && $slot->client_checked_in_at
    && ! $slot->coach_checked_in_at
    && empty($slot->extended_until_utc)
    && $extendOfferAt
    && $coachJoinDeadline
    && $nowUtc->between($extendOfferAt, $coachJoinDeadline);




// Start button: client can check-in ANYTIME until slot end


$canStartSession = ! $isFinalized
    && $slot && $startUtc
    && empty($slot->client_checked_in_at)
    && $reservation->payment_status === 'paid'
    && in_array($reservation->status, ['booked','in_escrow','confirmed','paid'], true)
    && $nowUtc->between($startUtc->copy()->subMinutes(5), $startUtc->copy()->addMinutes(5));




// Show waiting/timer only AFTER client has checked in and coach hasn't
$showWaitingTimer = ! $isFinalized
    && $slot
    && $slot->client_checked_in_at
    && ! $slot->coach_checked_in_at
    && $coachJoinDeadline
    && $nowUtc->lt($coachJoinDeadline)
    && $startUtc;



// Refund: if client checked in, coach not, and now >= start+5 (deadline)
$canAskRefund = $slot
  && $slot->client_checked_in_at
  && ! $slot->coach_checked_in_at
  && $coachJoinDeadline
  && $nowUtc->greaterThanOrEqualTo($coachJoinDeadline);





// Helper: are all sessions completed?

// ================== CANCELLATION (24h + before first slot starts) ==================

// ================== CANCELLATION (before first slot starts + no check-in) ==================

$firstSlot = $slots->first(); // slots already sorted
$firstStartUtc = $firstSlot?->start_utc
  ? \Carbon\Carbon::parse($firstSlot->start_utc)->utc()
  : null;


$nowUtc = now('UTC');

$anyCheckin = $slots->contains(fn($s) => $s->client_checked_in_at || $s->coach_checked_in_at);

$canCancel = (bool) (
  $firstStartUtc
  && $nowUtc->lt($firstStartUtc)
  && ! $anyCheckin
  && $reservation->payment_status === 'paid'
  && in_array($reservation->status, ['booked','in_escrow','confirmed','paid'], true)
  && empty($reservation->cancelled_at)
  && !in_array($reservation->status, ['cancelled','canceled'], true)
);








    // ===== POST-SESSION ACTIONS (client, on card) =====

$canComplete = (bool) ($reservation->ui_can_complete ?? false);
$canDispute  = (bool) ($reservation->ui_can_dispute ?? false);



// ===== REFUND CHOICE UI (client) =====




          @endphp


          





          <article class="cb-booking-card"
        data-slot-id="{{ $slot?->id }}"
        data-nudge1="{{ $showNudge1 ? 1 : 0 }}"
        data-nudge2="{{ $showNudge2 ? 1 : 0 }}">

            <div class="cb-booking-thumb">
              <img src="{{ $cover }}" alt="{{ $service->title ?? 'Service' }}" >
            </div>

            <div class="cb-booking-body">
              <div class="cb-booking-top">
                <div class="cb-booking-title-wrap">
                  <div class="cb-booking-title">
                    {{ $package->title ?? $service->title ?? __('Service') }}
                  </div>
                  @if($coach)
                    <div class="cb-booking-subtitle">
                      {{ __('with') }} {{ $coach->full_name ?? $coach->name }}
                    </div>
                  @endif
                </div>

                <div class="cb-booking-right">
                  <div class="cb-booking-price">
                  {{ $currencySymbol }}{{ number_format($total, 2) }} {{ $currencyCode }}
                  </div>
                  <div class="booking-price-caption">
                    {{ __('incl. fees') }}
                  </div>
                 <span class="cb-status-pill cb-status-pill-{{ $uiStatus }}">
  {{ ucwords(str_replace('_',' ', $uiStatus)) }}
</span>




{{-- ✅ Info Icon (opens modal) --}}
<button type="button"
        class="btn btn-black text-black p-0 ms-2 align-middle text-decoration-none js-open-info-modal"
        title="{{ __('Details') }}"
        data-bs-toggle="tooltip"
        data-title="{{ e($package->title ?? $service->title ?? 'Booking') }}"
        data-ui-status="{{ e($uiStatus) }}"
        data-res-status="{{ e($reservation->status ?? '') }}"
        data-settlement="{{ e($reservation->settlement_status ?? '') }}"
        data-cancelled-by="{{ e($reservation->cancelled_by ?? '') }}"
        data-cancel-reason="{{ e($reservation->cancel_reason ?? '') }}"
        data-refund-status="{{ e($reservation->refund_status ?? '') }}"
       data-refund-total="{{ (int)$displayRefundTotalMinor }}"
       data-refund-wallet="{{ (int)$displayRefundWalletMinor }}"
data-refund-external="{{ (int)$displayRefundExternalMinor }}"
        data-client-penalty="{{ (int)($reservation->client_penalty_minor ?? 0) }}"
        data-coach-penalty="{{ (int)($reservation->coach_penalty_minor ?? 0) }}"
        data-platform-earned="{{ (int)($reservation->platform_earned_minor ?? 0) }}"
        data-coach-earned="{{ (int)($reservation->coach_earned_minor ?? 0) }}"
        data-slot-status="{{ e($slot?->session_status ?? '') }}"
        data-slot-finalized="{{ e($slot?->finalized_at ?? '') }}"
        data-has-coach-no-show="{{ $coachNoShowSlot ? 1 : 0 }}"
        data-has-both-no-show="{{ $bothNoShowSlot ? 1 : 0 }}">
  <i class="bi bi-info-circle"></i>
</button>
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
  @else
    <div class="cb-booking-meta-item text-muted small">
      {{ __('Time Not Available') }}
    </div>
  @endif

  @if($totalSlots > 1)
  <div class="cb-booking-meta-item small text-muted">
    {{ __('Sessions Completed: :done / :total', [
        'done'  => $completedSlots,
        'total' => $totalSlots
    ]) }}
  </div>
@endif

</div>


              <div class="booking-footer">
            

@php
 

 $providerSource = strtolower((string) (
    $latestRefund?->provider
    ?? $reservation->externalPayment?->provider
    ?? $reservation->provider
    ?? ''
));

$providerName = match ($providerSource) {
    'stripe' => 'Stripe',
    'paypal' => 'PayPal',
    'wallet' => 'Wallet',
    default  => ucfirst($providerSource ?: 'External'),
};

  // Build popover HTML content (no card values, only inside popover)
  $payBreakdown = [];

  if ($walletPaidMinor > 0) {
 $payBreakdown[] =
  "<div class='d-flex align-items-center justify-content-between gap-3 mb-2'>
     <span>Wallet</span>
     <strong>{$currencySymbol}".number_format($walletPaidMinor/100,2)." {$currencyCode}</strong>
   </div>";
}

if ($externalPaidMinor > 0) {
 $payBreakdown[] =
  "<div class='d-flex align-items-center justify-content-between gap-3 mb-2'>
     <span>{$providerName}</span>
     <strong>{$currencySymbol}".number_format($externalPaidMinor/100,2)." {$currencyCode}</strong>
   </div>";
}
  if (!$payBreakdown) {
    $payBreakdown[] = "<div class='text-muted'>No payment data</div>";
  }

  $popoverHtml = "<div style='min-width:220px'>".implode("", $payBreakdown)."</div>";
@endphp

<div class="booking-tag small text-muted d-flex align-items-center gap-2">
  <span class="text-capitalize">
    {{ __('Paid via') }}: <strong>{{ $reservation->fundingLabel() }}</strong>
  </span>

  <button type="button"
          class="btn p-0 border-0 bg-transparent text-muted js-paidvia-info"
          data-bs-toggle="popover"
          data-bs-trigger="hover focus"
          data-bs-placement="top"
          data-bs-html="true"
          data-bs-custom-class="rm-popover-center"
          data-bs-content="{!! e($popoverHtml) !!}"
          aria-label="{{ __('Payment breakdown') }}">
    <i class="bi bi-info-circle"></i>
  </button>
</div>

              <div class="d-flex flex-wrap gap-2 ms-auto align-items-center">

@if($canCancel)
  <button type="button"
          class="cb-booking-btn-secondary js-open-cancel-modal"
          data-action="{{ route('client.reservations.cancel', $reservation) }}"
          data-quote-url="{{ route('client.reservations.cancel_quote', $reservation) }}"
          data-booking-title="{{ e($package->title ?? $service->title ?? 'Booking') }}"
          data-start="{{ $firstStartUtc?->timestamp ?? '' }}">
    {{ __('Cancel Booking') }}
  </button>
@endif


@if($showRefundChoice)
  <button type="button"
          class="cb-booking-btn-secondary js-open-refund-modal"
          data-reservation-id="{{ $reservation->id }}"
          data-action="{{ route('client.reservations.refund.choose', $reservation) }}"
         data-currency-symbol="{{ $currencySymbol }}"
data-currency-code="{{ $currencyCode }}"
          data-is-wallet-only="{{ $isWalletOnly ? 1 : 0 }}"
          data-is-mixed="{{ $isMixed ? 1 : 0 }}"
          data-allow-original="{{ in_array('original_payment', $allowedRefundMethods, true) ? 1 : 0 }}"
          data-refund-total="{{ (int)$displayRefundTotalMinor }}"
          data-refund-status="{{ $refundStatus }}"
          data-fee-wallet="{{ (int)$feeFromWallet }}"
          data-wallet-max="{{ (int)$walletRefundableMax }}"
         data-external-part="{{ (int)$displayRefundExternalMinor }}"
data-wallet-part="{{ (int)$displayRefundWalletMinor }}"
          data-fees-refundable="{{ $feesRefundable ? 1 : 0 }}"
data-cancelled-by="{{ $cancelledBy }}">
          
    {{ __('Refund Options') }}
  </button>
@endif







  {{-- View details (always available) --}}
 
{{-- Cancel booking (before first session starts, penalties depend on timing) --}}








@php
  $dispute = $reservation->dispute; // any dispute
@endphp

@if($dispute)
  <a href="{{ route('client.disputes.show', $dispute->id) }}" class="cb-booking-btn-secondary">
    {{ __('In Dispute') }}
  </a>
@elseif($reservation->ui_can_dispute ?? false)
  <a href="{{ route('client.disputes.create', ['reservation' => $reservation->id]) }}"
     class="cb-booking-btn-secondary">
    {{ __('Raise dispute') }}
  </a>
@endif














@if($canComplete)
  <button type="button"
          class="cb-booking-btn-secondary js-open-complete-modal"
          data-action="{{ route('client.bookings.complete', $reservation) }}">
    {{ __('Mark As Complete') }}
  </button>
@endif
 <a href="{{ route('client.bookings.show', $reservation) }}" class="cb-booking-btn">
  {{ __('View Details') }}
</a>
</div>



{{-- Video call (ONLY when online + both checked-in) --}}
@if(($reservation->environment ?? null) === 'online'
    && $slot
    && $slot->coach_checked_in_at
    && $end
    && now()->lt($end))

  <a href="{{ route('slots.call', $slot) }}" class="cb-booking-btn-secondary">
    {{ __('Video call') }}
  </a>
@endif


@if($sessionStarted)
  {{-- SESSION STARTED LABEL --}}
  <span class="booking-session-label text-success fw-semibold">
    {{ __('Session has started') }}
  </span>

@elseif($showWaitingTimer)
@php
  $startTs    = $startUtc ? $startUtc->timestamp : 0;
  $deadTs     = $coachJoinDeadline ? $coachJoinDeadline->timestamp : 0;
  $extendAtTs = $extendOfferAt ? $extendOfferAt->timestamp : 0;
  $nowTs      = now()->timestamp;
@endphp
  <div class="zv-waiting zv-waiting--compact">
  <div class="zv-waiting__icon" aria-hidden="true">
    <span class="zv-waiting__dot"></span>
  </div>

  <div class="zv-waiting__body">
    <div class="zv-waiting__title">
      {{ __('Session Countdown') }}
    </div>

    <div class="zv-waiting__sub zv-waiting__row">
      <span class="zv-waiting__label js-waiting-label">{{ __('Starts In') }}</span>

      <span class="zv-waiting__timer js-slot-countdown"
        data-now="{{ $nowTs }}"
        data-start="{{ $startTs }}"
        data-deadline="{{ $deadTs }}"
        data-extend-at="{{ $extendAtTs }}">
        --
      </span>

      @if($canExtend && $slot)
        <button type="button"
                class="zv-waiting__extend js-open-extend-modal"
                data-action="{{ route('client.slots.extend_wait', $slot) }}">
          {{ __('Extend wait') }}
        </button>
      @endif
    </div>
  </div>
</div>

@else
  {{-- Start Session button --}}
  @if($canStartSession && $slot)
   <button type="button"
        class="cb-booking-btn-secondary js-open-start-modal"
        data-confirm-url="{{ route('client.slots.client.checkin', $slot) }}">
  {{ __('Start Session') }}
</button>
  @endif
@endif







              </div>
            </div>
          </article>
        @endforeach

      </div>

      <div class="mt-3">
       {{ $bookings->appends(['tab' => $tab])->links() }}

      </div>
    @else
      <div class="zv-empty text-center py-5">
        <p class="mb-1 text-dark fw-semibold text-capitalize">{{ __('No bookings yet') }}</p>
        <p class="text-muted mb-3 text-capitalize">
          {{ __("Once you book a service, it will appear here.") }}
        </p>
        <a href="{{ route('services.index') }}" class="btn bg-black text-white rounded-pill px-4">
          {{ __('Browse Services') }}
        </a>
      </div>
    @endif

    @else
  <div class="zv-empty text-center py-5">
    <p class="mb-1 fw-semibold">{{ __('Invalid tab') }}</p>
  </div>
@endif

    
 

{{-- ===================== CANCELLED TAB ===================== --}}


</div>



{{-- ============= MODALS ============= --}}




{{-- ✅ Info Modal --}}
<div class="modal fade" id="bookingInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="bookingInfoTitle">{{ __('Booking details') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <div class="small text-muted mb-2" id="bookingInfoSubtitle">—</div>

        <div class="border rounded-3 p-3">
          <div class="d-flex justify-content-between small">
            <span class="text-muted">{{ __('Status') }}</span>
            <strong id="bookingInfoStatus">—</strong>
          </div>

          <div class="d-flex justify-content-between small mt-2 text-capitalize">
            <span class="text-muted">{{ __('Settlement') }}</span>
            <strong id="bookingInfoSettlement">—</strong>
          </div>

          <hr class="my-3">

          <div class="small fw-semibold mb-1">{{ __('Explanation') }}</div>
          <div class="small text-capitalize text-muted" id="bookingInfoMessage">—</div>

          <div class="mt-3 d-none" id="bookingInfoReasonBox">
            <div class="small fw-semibold mb-1">{{ __('Reason') }}</div>
            <div class="small text-capitalize text-muted" id="bookingInfoReason">—</div>
          </div>

          <div class="mt-3 d-none" id="bookingInfoMoneyBox">
            <hr class="my-3">
            <div class="d-flex justify-content-between small">
              <span class="text-muted">{{ __('Refund') }}</span>
              <strong id="bookingInfoRefund">—</strong>
            </div>
            <div class="d-flex justify-content-between small mt-1">
              <span class="text-muted">{{ __('Platform keeps') }}</span>
              <strong id="bookingInfoPlatformKeeps">—</strong>
            </div>
          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Close') }}
        </button>
      </div>

    </div>
  </div>
</div>


<div class="modal fade" id="refundChoiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header ">
        <h5 class="modal-title m-auto fw-bold text-center fs-4">{{ __('Select Refund Method') }}</h5>
        <div>
          <button type="button" class="btn-close d-block" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
      </div>

      <div class="modal-body">

        <!-- ✅ STEP 1: CHOOSE -->
        <div id="rmStepChoose">

          {{-- Primary explanation --}}
          <div class="small text-muted text-capitalize mb-2 text-center" id="refundModalHint">—</div>

          {{-- Big summary --}}
          <div class="border rounded-3 p-3 mb-3">
            <div class="rm-summary rm-align-edge">
              <span class="text-muted fw-bold rm-label">{{ __('Total Refund') }}</span>
              <strong id="rmTotal" class="rm-amount">—</strong>
            </div>

            {{-- Coach/Admin/System: show “fees included” --}}
            <div class="small text-capitalize mt-2 d-none" id="rmFeesIncludedBox">
              <span class="text-capitalize text-muted" id="rmFeesIncludedText">—</span>
            </div>

            {{-- Mixed + NOT fees refundable => fee deducted from wallet --}}
            <div class="small mt-2 d-none" id="rmFeeDeductBox">
              <div class="d-flex justify-content-between">
                <span class="text-muted">{{ __('Platform fee taken from wallet') }}</span>
                <strong id="rmFeeWallet">—</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted">{{ __('Max wallet refundable after fee') }}</span>
                <strong id="rmWalletMax">—</strong>
              </div>
            </div>
          </div>

          {{-- Options --}}
          <div class="border rounded-3 p-3 mb-3">

            {{-- WALLET SECTION --}}
            <div class="fw-bold mb-3">{{ __('Refund To Wallet') }}</div>

            <div class="d-flex justify-content-between align-items-center gap-4 flex-nowrap">
              <div class="small flex-grow-1" style="min-width:0;">

                <div class="rm-row ">
                  <span class="text-muted text-capitalize rm-label">{{ __('Refund to wallet') }}</span>
                  <strong class="fw-bold rm-amount" id="rmWalletAll">—</strong>
                </div>

                <div class="d-flex align-items-center gap-2 mt-1">
                  <button type="button"
                          class="btn p-0 border-0 bg-transparent text-muted js-refund-info"
                          data-bs-toggle="popover"
                          data-bs-trigger="focus"
                          data-bs-placement="top"
                          data-bs-html="true"
                          data-bs-content=""
                          data-bs-custom-class="rm-popover-center"
                          aria-label="Wallet refund info">
                    <i class="bi bi-info-circle"></i>
                  </button>
                  <span class="small text-muted text-capitalize" id="rmWalletInfoLabel">{{ __('How wallet refunds work.') }}</span>
                </div>
              </div>

              <form method="POST" id="refundWalletForm" action="#" class="m-0 flex-shrink-0 align-self-end">
                @csrf
                <input type="hidden" name="refund_method" value="wallet_credit">

                <!-- ✅ changed to type="button" for step flow -->
                <button type="button"
                        class="btn btn-dark rounded-pill px-4 js-rm-proceed"
                        data-method="wallet">
                  {{ __('Proceed') }}
                </button>
              </form>
            </div>

            <hr class="my-4">

            {{-- ORIGINAL PAYMENT SECTION (UNCHANGED UI, only button behavior changes) --}}
            <div class="fw-bold mb-2">{{ __(' Refund To Original Payment Method') }}</div>

            <div class="small text-muted d-none text-capitalize" id="rmOriginalNotAvailable">
              {{ __('Original payment refund is not available for this booking.') }}
            </div>

            <div class="d-none" id="rmOriginalRow">
              <div class="d-flex justify-content-between align-items-center gap-4 flex-nowrap">

                <div class="small flex-grow-1" id="rmOriginalBreakdown" style="min-width:0;">

                  <div class="rm-row">
                    <span class="text-muted rm-label">{{ __('Refund To Original Payment Method') }}</span>
                    <strong id="rmExternal" class="fw-bold rm-amount">—</strong>
                  </div>

                  <div class="rm-row mt-1">
                    <span class="text-muted rm-label">{{ __('Refund To Wallet') }}</span>
                    <strong id="rmWalletForOriginal" class="fw-bold rm-amount">—</strong>
                  </div>

                  <div class="d-flex align-items-center gap-2 mt-2">
                    <button type="button"
                            class="btn p-0 border-0 bg-transparent text-muted js-refund-info"
                            data-bs-toggle="popover"
                            data-bs-trigger="focus"
                            data-bs-placement="top"
                            data-bs-html="true"
                            data-bs-custom-class="rm-popover-center"
                            data-bs-content=""
                            aria-label="Original payment refund info">
                      <i class="bi bi-info-circle"></i>
                    </button>
                    <span class="small text-muted text-capitalize">{{ __('How original payment refunds work.') }}</span>
                  </div>
                </div>

                <form method="POST" id="refundOriginalForm" action="#" class="m-0 flex-shrink-0 align-self-end">
                  @csrf
                  <input type="hidden" name="refund_method" value="original_payment">

                  <!-- ✅ changed to type="button" for step flow -->
                  <button type="button"
                          class="btn btn-outline-dark rounded-pill px-4 js-rm-proceed"
                          data-method="original">
                    {{ __('Proceed') }}
                  </button>
                </form>

              </div>
            </div>

          </div>
        </div>

        <!-- ✅ STEP 2: CONFIRM (same modal) -->
        <div id="rmStepConfirm" class="d-none">

          <div class="fw-bold  fs-5">{{ __('Confirm Refund') }}</div>

          <span class="mb-2 text-capitalize small">
            {{ __('You are about to request a refund using:') }}
            <strong id="rmConfirmMethodLabel">—</strong>
          </span>

         <div class="border rounded-3 p-3 mt-2">
  <div class="d-flex justify-content-between small">
    <span class="text-muted">{{ __('Total Refund') }}</span>
    <strong id="rmConfirmAmount">—</strong>
  </div>

  <!-- ✅ Only shown when method = original -->
  <div class="mt-3 d-none" id="rmConfirmBreakdownBox">
    <div class="d-flex justify-content-between small">
      <span class="text-muted text-capitalize">{{ __('Refund to original payment') }}</span>
      <strong id="rmConfirmExternal">—</strong>
    </div>

    <div class="d-flex justify-content-between small mt-2">
      <span class="text-muted text-capitalize">{{ __('Refund to wallet') }}</span>
      <strong id="rmConfirmWallet">—</strong>
    </div>
  </div>
</div>

          <p class="small text-muted mb-0 text-capitalize" id="rmConfirmNote">
            {{ __('This action cannot be changed once submitted.') }}
          </p>

        </div>

      </div>

      <!-- ✅ Footer supports both steps -->
      <div class="modal-footer justify-content-between">

        <!-- Step 1 footer -->
        <button type="button"
                class="btn btn-outline-secondary rounded-pill px-4"
                data-bs-dismiss="modal"
                id="rmCloseBtn">
          {{ __('Close') }}
        </button>

        <!-- Step 2 footer -->
        <div class="d-none d-flex align-items-center justify-content-between w-100" id="rmConfirmFooter">
          <button type="button"
                  class="btn btn-outline-secondary rounded-pill px-4 me-2"
                  id="rmBackBtn">
            {{ __('Back') }}
          </button>

          <button type="button"
                  class="btn  bg-black text-white rounded-pill px-4"
                  id="rmConfirmSubmitBtn">
            {{ __('Confirm Refund') }}
          </button>
        </div>

      </div>

    </div>
  </div>
</div>


{{-- Cancel Booking Modal --}}
{{-- Cancel Booking Modal --}}
<div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="cancelBookingModalLabel">{{ __('Cancel booking') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

     <div class="modal-body">
  <p class="mb-2 fw-semibold" id="cancelBookingTitle">—</p>

  <p class="small text-muted mb-3 text-capitalize">
    {{ __("You're about to cancel this booking. Refunds or penalties may apply depending on how close the first session is.") }}
  </p>

  {{-- ✅ Quote preview --}}
  <div class="border rounded-3 p-3 mb-3" id="cancelQuoteBox">
    <div class="d-flex justify-content-between small">
      <span class="text-dark">{{ __('Refund') }}</span>
      <strong class="fw-bold" id="cqRefund">—</strong>
    </div>

    <div class="d-flex justify-content-between small mt-1">
      <span class="text-dark">{{ __('Penalty') }}</span>
      <strong class="fw-bold" id="cqPenalty">—</strong>
    </div>

    {{-- <div class="d-flex justify-content-between small mt-1">
      <span class="text-dark">{{ __('Platform Keeps') }}</span>
      <strong id="cqKeeps">—</strong>
    </div> --}}

    <div class="small text-dark mt-2" id="cqRuleLine">—</div>
    <div class="small text-dark mt-1" id="cancelTimeInfo"></div>
  </div>

  <div class="alert alert-warning d-none text-dark" id="cancelQuoteError"></div>

  {{-- ✅ Refund destination --}}
  

  <div class="mb-2 mt-3">
    <label class="form-label" for="cancelReason">{{ __('Reason (Optional)') }}</label>
    <textarea class="form-control" id="cancelReason" rows="3"
              placeholder="{{ __('Tell Us Why You’re Cancelling…') }}"></textarea>
  </div>
</div>


      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Keep booking') }}
        </button>

        <form method="POST" id="cancelBookingForm" action="#">
          @csrf
          <input type="hidden" name="reason" id="cancelReasonHidden" value="">
          

          <button type="submit" class="btn btn-outline-secondary rounded-pill px-4" id="cancelBookingSubmitBtn">
            {{ __('Yes, Cancel') }}
          </button>
        </form>
      </div>

    </div>
  </div>
</div>




{{-- Nudge 1 Modal --}}
<div class="modal fade" id="nudge1Modal" tabindex="-1" aria-labelledby="nudge1ModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="nudge1ModalLabel">{{ __('Coach is running late') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">
          {{ __("It looks like your Coach is running a little late. We are nudging them to join you right away. (Attempt 1 of 2)") }}
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn bg-black text-white rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Okay') }}
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Nudge 2 Modal --}}
<div class="modal fade" id="nudge2Modal" tabindex="-1" aria-labelledby="nudge2ModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="nudge2ModalLabel">{{ __('Final nudge sent') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">
          {{ __("We are giving your Coach one final nudge. If they don't arrive by the 4-minute mark, you can extend your wait by an additional 5 minutes. Otherwise, the session will automatically cancel at 5 minutes.") }}
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn bg-black text-white rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Got it') }}
        </button>
      </div>
    </div>
  </div>
</div>




<div class="modal fade" id="extendWaitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('Extend wait time') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2">
          {{ __("Extend your wait by 5 minutes?") }}
        </p>
        <p class="small text-muted mb-0">
          {{ __("If the coach still doesn't arrive by the new deadline, the session will auto-cancel and you'll be refunded.") }}
        </p>
      </div>

      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Not now') }}
        </button>

        <form method="POST" id="extendWaitForm" action="#">
          @csrf
          <button type="submit" class="btn bg-black text-white rounded-pill px-4">
            {{ __('Yes, extend') }}
          </button>
        </form>
      </div>

    </div>
  </div>
</div>
{{-- 2) Modal: Start session (enter code) --}}
<div class="modal fade" id="startSessionModal" tabindex="-1" aria-labelledby="startSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="startSessionModalLabel">{{ __('Start Session') }}</h5>
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
        <button type="button" class="btn bg-black text-white rounded-pill px-4" id="startSessionConfirmBtn">
          {{ __('Start Now') }}
        </button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="completeBookingModal" tabindex="-1" aria-labelledby="completeBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="completeBookingModalLabel">{{ __('Complete Booking') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2 text-capitalize">
          {{ __("Mark this booking as complete?") }}
        </p>
        <p class="small text-muted mb-0 text-capitalize">
         {{ __("This will  mark the booking as permanently completed.") }}
        </p>
      </div>

      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>

        <form method="POST" id="completeBookingForm" action="#">
          @csrf
          <button type="submit" class="btn bg-black text-white rounded-pill px-4">
            {{ __('Yes, Complete') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</div>





@if($pendingClientRatingReservation)
@php
    $ratingReservation = $pendingClientRatingReservation;
    $ratingService     = $ratingReservation->service;
    $ratingCoach       = $ratingService?->coach;
@endphp

<div class="modal fade"
     id="clientMandatoryRatingModal"
     tabindex="-1"
     aria-labelledby="clientMandatoryRatingModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form method="POST" action="{{ route('client.reviews.store', $ratingReservation) }}" id="clientMandatoryRatingForm">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title" id="clientMandatoryRatingModalLabel">
            {{ __('Rate Your Coach') }}
          </h5>
        </div>

        <div class="modal-body">
          <p class="mb-1 fw-semibold">
            {{ $ratingService?->title ?? $ratingReservation->package?->title ?? __('Booking') }}
          </p>

          <p class="small text-muted mb-3 text-capitalize">
            {{ __('Please rate your completed service with') }}
            <strong>{{ $ratingCoach->full_name ?? $ratingCoach->name ?? __('Coach') }}</strong>.
            {{ __('This rating is required before continuing.') }}
          </p>

          <div class="mb-3">
            <label class="form-label d-block">{{ __('Your Rating') }}</label>

            <div class="client-rating-stars" id="clientRatingStars">
              @for($i = 1; $i <= 5; $i++)
                <button type="button"
                        class="client-star-btn"
                        data-value="{{ $i }}"
                        aria-label="Rate {{ $i }} star{{ $i > 1 ? 's' : '' }}">
                  ★
                </button>
              @endfor
            </div>

            <input type="hidden" name="stars" id="clientRatingStarsInput" value="{{ old('stars') }}">
            <div class="text-danger text-capitalize small mt-1 d-none" id="clientRatingStarsError">
              {{ __('Please select a star rating.') }}
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label" for="clientRatingComment">{{ __('Description') }}</label>
            <textarea
              name="description"
              id="clientRatingComment"
              rows="4"
              class="form-control"
              placeholder="{{ __('Share Your Experience With This Coach...') }}"
              required
            >{{ old('description') }}</textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn bg-black text-white rounded-pill px-4">
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
  // ✅ global reload guard (prevents refresh loops)
  window.__zvReloadOnce = window.__zvReloadOnce || false;

  function zvReloadOnce(delay = 1200) {
    if (window.__zvReloadOnce) return;
    window.__zvReloadOnce = true;
    setTimeout(() => location.reload(), delay);
  }
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const completeModalEl = document.getElementById('completeBookingModal');
  const completeForm    = document.getElementById('completeBookingForm');
  
  let completeModal     = null;

  if (completeModalEl && typeof bootstrap !== 'undefined') {
    completeModal = bootstrap.Modal.getOrCreateInstance(completeModalEl);
  }

  document.querySelectorAll('.js-open-complete-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const action = this.dataset.action;
      if (completeForm && action) completeForm.action = action;
      if (completeModal) completeModal.show();
      else if (action) window.location.href = action;
    });
  });
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl   = document.getElementById('cancelBookingModal');
  const formEl    = document.getElementById('cancelBookingForm');
  const titleEl   = document.getElementById('cancelBookingTitle');

  const reasonEl  = document.getElementById('cancelReason');
  const reasonHid = document.getElementById('cancelReasonHidden');
  const submitBtn = document.getElementById('cancelBookingSubmitBtn');

  const refundEl  = document.getElementById('cqRefund');
  const penEl     = document.getElementById('cqPenalty');
  const keepEl    = document.getElementById('cqKeeps');
  const ruleEl    = document.getElementById('cqRuleLine');
  const infoEl    = document.getElementById('cancelTimeInfo');
  const errEl     = document.getElementById('cancelQuoteError');

  if (!modalEl || !formEl) return;
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  const money = (minor, symbol='$', code='USD') => {
  const v = (Number(minor || 0) / 100).toFixed(2);
  return `${symbol}${v} ${code}`;
};

  function resetUI() {
    if (refundEl) refundEl.textContent = '—';
    if (penEl) penEl.textContent = '—';
    if (keepEl) keepEl.textContent = '—';
    if (ruleEl) ruleEl.textContent = 'Calculating refund/penalty…';
    if (infoEl) infoEl.textContent = '';
    if (errEl) errEl.classList.add('d-none');
    if (submitBtn) submitBtn.disabled = true;
  }
   

 


  function showError(msg) {
    if (errEl) {
      errEl.textContent = msg || 'Unable to calculate cancellation amounts.';
      errEl.classList.remove('d-none');
    }
    if (ruleEl) ruleEl.textContent = '—';
    if (submitBtn) submitBtn.disabled = true;
  }

 function applyQuote(q, symbol='$', code='USD') {
  if (refundEl) refundEl.textContent = money(q.refund_minor, symbol, code);
  if (penEl)    penEl.textContent = money(q.client_penalty_minor, symbol, code);
  if (keepEl)   keepEl.textContent = money(q.platform_earned_minor, symbol, code);

  const h = Number(q.hours_until);
  let rule = '';
  if (Number.isFinite(h)) {
    if (h >= 48) rule = 'Rule: 48+ Hours Before Start → Full Refund.';
    else if (h >= 24) rule = 'Rule: 24–48 Hours Before Start → Service Fee + 10% Penalty.';
    else rule = 'Rule: <24 Hours Before Start → Service Fee + 20% Penalty.';
  }
  if (ruleEl) ruleEl.textContent = rule || '—';

  if (submitBtn) submitBtn.disabled = false;
}

  document.querySelectorAll('.js-open-cancel-modal').forEach(function(btn){
    btn.addEventListener('click', async function(){
      const action   = this.dataset.action || '#';
      const quoteUrl = this.dataset.quoteUrl || null;
      const bookingTitle = this.dataset.bookingTitle || 'Booking';

      formEl.action = action;
      if (titleEl) titleEl.textContent = bookingTitle;

      if (reasonEl) reasonEl.value = '';
      if (reasonHid) reasonHid.value = '';

      resetUI();
     
      modal.show();

      if (!quoteUrl) {
        showError('Missing cancel quote URL.');
        return;
      }

      try {
        const res = await fetch(quoteUrl, {
          method: 'GET',
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data || data.ok !== true) {
          showError((data && data.message) || 'Cancellation preview failed.');
          return;
        }

        applyQuote(data);
      } catch (e) {
        showError('Network error while calculating amounts.');
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
  let currentConfirmUrl = null;

  const startModalEl = document.getElementById('startSessionModal');
  const startBtn     = document.getElementById('startSessionConfirmBtn');
  let startModal     = null;

  if (startModalEl && typeof bootstrap !== 'undefined') {
    startModal = bootstrap.Modal.getOrCreateInstance(startModalEl);
  }

  document.querySelectorAll('.js-open-start-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentConfirmUrl = this.dataset.confirmUrl || null;

      if (startModal) startModal.show();
      else if (currentConfirmUrl) {
        startSessionRequest(currentConfirmUrl);
      }
    });
  });

  if (startBtn) {
    startBtn.addEventListener('click', function () {
      if (!currentConfirmUrl) return;
      startSessionRequest(currentConfirmUrl);
    });
  }

  function startSessionRequest(confirmUrl) {
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
        headers: { 'Accept': 'application/json' },
        body: formData
      })
      .then(async (res) => {
        const data = await res.json().catch(() => null);
        if (!res.ok) {
          const msg = (data && data.message) || ('HTTP ' + res.status);
          throw new Error(msg);
        }
        return data;
      })
      .then(() => {
        if (startModal) startModal.hide();
        zvReloadOnce(800);
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

{{-- ✅ Countdown script (NO nested <script> tags) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.js-slot-countdown').forEach(function (el) {
    const nowTs   = Number(el.dataset.now);
    const startTs = Number(el.dataset.start);
    const deadTs  = Number(el.dataset.deadline);

    if (!Number.isFinite(nowTs) || !Number.isFinite(startTs) || !Number.isFinite(deadTs)) return;
    if (startTs <= 0 || deadTs <= 0) return;

    const startMs  = startTs * 1000;
    const deadMs   = deadTs * 1000;
    const offsetMs = (nowTs * 1000) - Date.now();

    const labelEl = el.closest('.zv-waiting__sub')?.querySelector('.js-waiting-label');

    function fmt(ms) {
      const mins = Math.floor(ms / 60000);
      const secs = Math.floor((ms % 60000) / 1000);
      return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    (function tick() {
      const now = Date.now() + offsetMs;

      if (now < startMs) {
        if (labelEl) labelEl.textContent = 'Starts in';
        el.textContent = fmt(startMs - now);
        return setTimeout(tick, 1000);
      }

      if (now < deadMs) {
        if (labelEl) labelEl.textContent = 'Coach Must Join In';
        el.textContent = fmt(deadMs - now);
        return setTimeout(tick, 1000);
      }

      el.textContent = '00:00';
      // ✅ stop here (NO reload)
    })();
  });
});
</script>

{{-- ✅ Polling script (reload only once per finalized_at change, persisted) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-slot-id]').forEach(function (card) {
    const slotId = card.dataset.slotId;
    if (!slotId) return;

    const url = `/api/slots/${slotId}/status`;

    let stopped = false;

    const storeKey = `zv_finalized_at_${slotId}`;
    let lastFinalized = localStorage.getItem(storeKey) || '';

    async function poll() {
      if (stopped) return;

      try {
        const res = await fetch(url, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        if (!res.ok) return;

        const data = await res.json();

       const finalizedAt = Boolean(data.finalized_at);

if (finalizedAt && lastFinalized !== '1') {
  localStorage.setItem(storeKey, '1');
  stopped = true;
  zvReloadOnce(500);
}


      } catch (e) {
        console.warn('Slot poll failed', e);
      }
    }

    poll();
    const intId = setInterval(() => {
      if (stopped) return clearInterval(intId);
      poll();
    }, 10000);
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const nudge1El = document.getElementById('nudge1Modal');
  const nudge2El = document.getElementById('nudge2Modal');
  if (!nudge1El || !nudge2El) return;

  const nudge1Modal = bootstrap.Modal.getOrCreateInstance(nudge1El);
  const nudge2Modal = bootstrap.Modal.getOrCreateInstance(nudge2El);

  document.querySelectorAll('.cb-booking-card[data-slot-id]').forEach(function(card){

    const slotId = card.dataset.slotId;
    if (!slotId) return;

    const show1 = card.dataset.nudge1 === '1';
    const show2 = card.dataset.nudge2 === '1';

    const key1 = `zv_nudge1_shown_${slotId}`;
    const key2 = `zv_nudge2_shown_${slotId}`;

    if (show2 && !localStorage.getItem(key2)) {
      localStorage.setItem(key2, '1');
      nudge2Modal.show();
      return;
    }

    if (show1 && !localStorage.getItem(key1)) {
      localStorage.setItem(key1, '1');
      nudge1Modal.show();
    }
  });
});
</script>



<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl = document.getElementById('bookingInfoModal');
  if (!modalEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  const titleEl    = document.getElementById('bookingInfoTitle');
  const subtitleEl = document.getElementById('bookingInfoSubtitle');

  const stEl   = document.getElementById('bookingInfoStatus');
  const setEl  = document.getElementById('bookingInfoSettlement');
  const msgEl  = document.getElementById('bookingInfoMessage');

  const reasonBox = document.getElementById('bookingInfoReasonBox');
  const reasonEl  = document.getElementById('bookingInfoReason');

  const moneyBox  = document.getElementById('bookingInfoMoneyBox');
  const refundEl  = document.getElementById('bookingInfoRefund');
  const keepsEl   = document.getElementById('bookingInfoPlatformKeeps');

  const money = (minor, symbol='$', code='USD') => {
  const v = (Number(minor || 0) / 100).toFixed(2);
  return `${symbol}${v} ${code}`;
};

  function safeText(v) {
    v = (v || '').toString().trim();
    return v.length ? v : '—';
  }

  function buildMessage(d) {
    const ui = (d.uiStatus || '').toLowerCase();

    const hasCoachNoShow = d.hasCoachNoShow === '1';
    const hasBothNoShow  = d.hasBothNoShow === '1';

    if (hasCoachNoShow) {
      return "Coach did not join in time. This session was auto-finalized and refund rules were applied.";
    }
    if (hasBothNoShow) {
      return "Neither party joined. Service amount refunded; platform fee may be kept depending on policy.";
    }

    if (ui === 'cancelled') {
      const by = (d.cancelledBy || '').toLowerCase();
      if (by === 'coach') return "The coach cancelled this booking. Refund rules were applied.";
      if (by === 'admin' || by === 'system') return "This booking was cancelled by the platform. Refund rules were applied.";
      if (by === 'client') return "You cancelled this booking. Refund/penalty rules were applied based on timing.";
      return "This booking was cancelled. Refund rules were applied.";
    }

    if (ui === 'refunded' || ui === 'refund_pending' || ui === 'partially_refunded') {
      return "This booking has a refund action/status. Open 'Refund Options' if available for more choices.";
    }

    if (ui === 'in_dispute') {
      return "This booking is in dispute. Resolution will determine final settlement.";
    }

    if (ui === 'paid') return "This booking is settled and paid out.";
    if (ui === 'completed') return "All sessions are marked completed.";
    return "Booking details and current state.";
  }

  document.querySelectorAll('.js-open-info-modal').forEach(function(btn){
    btn.addEventListener('click', function(){
      const d = this.dataset;

      if (titleEl) titleEl.textContent = safeText(d.title);
      if (subtitleEl) subtitleEl.textContent = `Slot status: ${safeText(d.slotStatus)}${d.slotFinalized ? ' • Finalized' : ''}`;

      if (stEl)  stEl.textContent  = safeText(d.uiStatus);
      if (setEl) setEl.textContent = safeText(d.settlement);

      const message = buildMessage(d);
      if (msgEl) msgEl.textContent = message;

      // Reason block (cancel reason if present)
      const cancelReason = (d.cancelReason || '').trim();
      if (cancelReason.length) {
        reasonBox.classList.remove('d-none');
        reasonEl.textContent = cancelReason;
      } else {
        reasonBox.classList.add('d-none');
        reasonEl.textContent = '—';
      }

      // Money block (only show if refund_total_minor > 0)
     const refundMinor = Number(d.refundTotal || 0);
const refundWalletMinor = Number(d.refundWallet || 0);
const refundExternalMinor = Number(d.refundExternal || 0);
const platformEarned = Number(d.platformEarned || 0);

if (refundMinor > 0) {
  moneyBox.classList.remove('d-none');

  let refundText = money(refundMinor, '$');

  if (refundWalletMinor > 0 || refundExternalMinor > 0) {
    const parts = [];
    if (refundWalletMinor > 0) parts.push(`Wallet: ${money(refundWalletMinor, '$')}`);
    if (refundExternalMinor > 0) parts.push(`Original payment: ${money(refundExternalMinor, '$')}`);
    refundText += ` (${parts.join(' • ')})`;
  }

  refundEl.textContent = refundText;
  keepsEl.textContent  = money(platformEarned, '$');
} else {
  moneyBox.classList.add('d-none');
  refundEl.textContent = '—';
  keepsEl.textContent  = '—';
}

      modal.show();
    });
  });
});
</script>



<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl = document.getElementById('extendWaitModal');
  const formEl  = document.getElementById('extendWaitForm');
  if (!modalEl || !formEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  document.querySelectorAll('.js-open-extend-modal').forEach(function(btn){
    btn.addEventListener('click', function(){
      const action = this.dataset.action || '#';
      formEl.action = action;
      modal.show();
    });
  });
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl = document.getElementById('refundChoiceModal');
  if (!modalEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  // const refundStatus = (this.dataset.refundStatus || '').toLowerCase();

  // ===== Step containers =====
  const stepChooseEl   = document.getElementById('rmStepChoose');
  const stepConfirmEl  = document.getElementById('rmStepConfirm');
  const confirmFooter  = document.getElementById('rmConfirmFooter');
  const closeBtn       = document.getElementById('rmCloseBtn');
  const backBtn        = document.getElementById('rmBackBtn');
  const confirmBtn     = document.getElementById('rmConfirmSubmitBtn');

  // ===== Existing elements you already fill =====
  const hintEl = document.getElementById('refundModalHint');
  const walletForm   = document.getElementById('refundWalletForm');
  const originalForm = document.getElementById('refundOriginalForm');

  const rmTotal = document.getElementById('rmTotal');

  const rmFeesIncludedBox  = document.getElementById('rmFeesIncludedBox');
  const rmFeesIncludedText = document.getElementById('rmFeesIncludedText');

  const rmFeeDeductBox = document.getElementById('rmFeeDeductBox');
  const rmFeeWallet    = document.getElementById('rmFeeWallet');
  const rmWalletMax    = document.getElementById('rmWalletMax');

  const rmOriginalNotAvailable = document.getElementById('rmOriginalNotAvailable');
  const rmExternal             = document.getElementById('rmExternal');
  const rmWalletForOriginal    = document.getElementById('rmWalletForOriginal');

  const rmWalletAll = document.getElementById('rmWalletAll');
  const rmOriginalRow = document.getElementById('rmOriginalRow');

  // ===== Confirm step labels inside same modal =====
  const confirmMethodLabelEl = document.getElementById('rmConfirmMethodLabel');
  const confirmAmountEl      = document.getElementById('rmConfirmAmount');
  const confirmNoteEl        = document.getElementById('rmConfirmNote');
  const confirmBreakdownBox  = document.getElementById('rmConfirmBreakdownBox');
const confirmExternalEl    = document.getElementById('rmConfirmExternal');
const confirmWalletEl      = document.getElementById('rmConfirmWallet');

  // ===== State =====
  let pendingForm = null;
  let currentAction = '#';
  let currentSymbol = '$';
  let currentCode   = 'USD';
  let currentRefundTotalMinor = 0;
  let currentExternalPartMinor = 0;
let currentWalletPartMinor   = 0;

  const money = (minor, symbol='$', code='USD') => {
    const v = (Number(minor || 0) / 100).toFixed(2);
    return `${symbol}${v} ${code}`;
  };

  function showStep(step) {
    const isConfirm = step === 'confirm';

    if (stepChooseEl)  stepChooseEl.classList.toggle('d-none', isConfirm);
    if (stepConfirmEl) stepConfirmEl.classList.toggle('d-none', !isConfirm);

    if (confirmFooter) confirmFooter.classList.toggle('d-none', !isConfirm);
    if (closeBtn)      closeBtn.classList.toggle('d-none', isConfirm);
  }

  function resetSteps() {
    pendingForm = null;
    if (confirmBtn) confirmBtn.disabled = false;
    showStep('choose');
    if (confirmBreakdownBox) confirmBreakdownBox.classList.add('d-none');
if (confirmExternalEl) confirmExternalEl.textContent = '—';
if (confirmWalletEl) confirmWalletEl.textContent = '—';

    // close any popovers still open
    modalEl.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => {
      const inst = bootstrap.Popover.getInstance(el);
      if (inst) inst.hide();
    });
  }

  // Reset every time modal hides
  modalEl.addEventListener('hidden.bs.modal', resetSteps);

  // Back button
  if (backBtn) {
    backBtn.addEventListener('click', function () {
      showStep('choose');
    });
  }

  // Final confirm submit
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      if (!pendingForm) return;
      confirmBtn.disabled = true;
      pendingForm.submit();
    });
  }

  // Proceed buttons (Step 1 -> Step 2)
  modalEl.querySelectorAll('.js-rm-proceed').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const method = (this.dataset.method || '').toLowerCase();

    if (method === 'wallet') {
      pendingForm = walletForm;

      if (confirmMethodLabelEl) confirmMethodLabelEl.textContent = 'Wallet';
      if (confirmNoteEl) confirmNoteEl.textContent = 'Funds will be credited to your wallet balance.';

      // ✅ hide breakdown on wallet
      if (confirmBreakdownBox) confirmBreakdownBox.classList.add('d-none');

    } else if (method === 'original') {
      pendingForm = originalForm;

      if (confirmMethodLabelEl) confirmMethodLabelEl.textContent = 'Original Payment Method';
      if (confirmNoteEl) confirmNoteEl.textContent =
        'Any eligible amount will be returned to your original payment method. Any wallet portion will return to wallet.';

      // ✅ show breakdown on original
      if (confirmBreakdownBox) confirmBreakdownBox.classList.remove('d-none');
      if (confirmExternalEl) confirmExternalEl.textContent = money(currentExternalPartMinor, currentSymbol, currentCode);
      if (confirmWalletEl)   confirmWalletEl.textContent   = money(currentWalletPartMinor, currentSymbol, currentCode);

    } else {
      return;
    }

    // total always
    if (confirmAmountEl) {
      confirmAmountEl.textContent = money(currentRefundTotalMinor, currentSymbol, currentCode);
    }

    showStep('confirm');
  });
});

  // ===== Open modal + fill values (your existing logic, updated for step reset + state) =====
  document.querySelectorAll('.js-open-refund-modal').forEach(function(btn){
  btn.addEventListener('click', function(){
    resetSteps();

    const refundStatus = (this.dataset.refundStatus || '').toLowerCase();

    currentAction = this.dataset.action || '#';
      currentSymbol = this.dataset.currencySymbol || '$';
      currentCode   = this.dataset.currencyCode || 'USD';

      const isWalletOnly  = this.dataset.isWalletOnly === '1';
      const isMixed       = this.dataset.isMixed === '1';
      const allowOriginal = this.dataset.allowOriginal === '1';

      const feesRefundable = this.dataset.feesRefundable === '1';
      const cancelledBy    = (this.dataset.cancelledBy || '').toLowerCase();

      const refundTotal = Number(this.dataset.refundTotal || 0);
      currentRefundTotalMinor = refundTotal;

      const extPart = Number(this.dataset.externalPart || 0);
      const walPart = Number(this.dataset.walletPart || 0);
      currentExternalPartMinor = extPart;
currentWalletPartMinor   = walPart;

      const feeWallet = Number(this.dataset.feeWallet || 0);
      const walletMax = Number(this.dataset.walletMax || 0);

      // Build clean info text
      let walletInfo = '';
      let originalInfo = '';

      if (allowOriginal) {
        walletInfo = 'The entire refund will be credited to your wallet, including any remaining amount previously intended for card refund.';
      originalInfo = 'Any eligible remaining external amount will be sent back to the original payment method. Any wallet portion stays in wallet.';
      } else {
        walletInfo = 'This Booking Can Only Be Refunded To Wallet.';
        originalInfo = 'Original Payment Refund Is Not Available For This Booking.';
      }

      // Update popovers content
      modalEl.querySelectorAll('.js-refund-info').forEach((infoBtn) => {
        const labelText = infoBtn.nextElementSibling?.textContent?.toLowerCase() || '';
        if (labelText.includes('wallet')) infoBtn.setAttribute('data-bs-content', walletInfo);
        else infoBtn.setAttribute('data-bs-content', originalInfo);
      });

      // Re-init popovers each time modal opens
      modalEl.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => {
        bootstrap.Popover.getOrCreateInstance(el, {
          container: '#refundChoiceModal',
          boundary: 'viewport',
          trigger: 'focus',
          html: true
        });
      });

      // set form actions
      if (walletForm) walletForm.action = currentAction;
      if (originalForm) originalForm.action = currentAction;

      // total
      if (rmTotal) rmTotal.textContent = money(refundTotal, currentSymbol, currentCode);

      // hint text
     if (hintEl) {
  if (refundStatus === 'partial') {
    hintEl.textContent = 'Part of your refund was completed successfully. You can retry the remaining unresolved amount.';
  } else if (refundStatus === 'failed') {
    hintEl.textContent = 'Your previous refund attempt failed. You can retry the remaining refundable amount.';
  } else if (isWalletOnly) {
    hintEl.textContent = 'This booking was paid fully with wallet credit, so it can only be refunded to wallet.';
  } else if (cancelledBy === 'coach') {
    hintEl.textContent = 'The coach cancelled this booking. Choose how you would like to receive your refund.';
  } else if (cancelledBy === 'admin' || cancelledBy === 'system') {
    hintEl.textContent = 'This booking was cancelled by the platform. Choose where you would like to receive your refund.';
  } else {
    hintEl.textContent = 'Choose where you want to receive your refund.';
  }
}

      // fees included
      const showFeesIncluded = feesRefundable && (cancelledBy === 'coach' || cancelledBy === 'admin' || cancelledBy === 'system');
      if (rmFeesIncludedBox) rmFeesIncludedBox.classList.toggle('d-none', !showFeesIncluded);
      if (showFeesIncluded && rmFeesIncludedText) rmFeesIncludedText.textContent = 'This refund includes the platform fee.';

      // fee deducted box
      const showFeeDeduct = refundStatus !== 'partial' && refundStatus !== 'failed' && isMixed && !feesRefundable && feeWallet > 0;
      if (rmFeeDeductBox) rmFeeDeductBox.classList.toggle('d-none', !showFeeDeduct);
      if (showFeeDeduct) {
        if (rmFeeWallet) rmFeeWallet.textContent = money(feeWallet, currentSymbol, currentCode);
        if (rmWalletMax) rmWalletMax.textContent = money(walletMax, currentSymbol, currentCode);
      }

      // original show/hide
      if (rmOriginalNotAvailable) rmOriginalNotAvailable.classList.toggle('d-none', allowOriginal);
      if (rmOriginalRow) rmOriginalRow.classList.toggle('d-none', !allowOriginal);

      if (allowOriginal) {
        if (rmExternal) rmExternal.textContent = money(extPart, currentSymbol, currentCode);
        if (rmWalletForOriginal) rmWalletForOriginal.textContent = money(walPart, currentSymbol, currentCode);
      }

      // wallet always full amount
      if (rmWalletAll) rmWalletAll.textContent = money(refundTotal, currentSymbol, currentCode);

      modal.show();
    });
  });
});
</script>


@if($pendingClientRatingReservation)
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  const modalEl = document.getElementById('clientMandatoryRatingModal');
  const formEl  = document.getElementById('clientMandatoryRatingForm');
  const inputEl = document.getElementById('clientRatingStarsInput');
  const errEl   = document.getElementById('clientRatingStarsError');

  if (!modalEl || !formEl || !inputEl) return;

  const modal = new bootstrap.Modal(modalEl, {
    backdrop: 'static',
    keyboard: false
  });

  const starButtons = Array.from(modalEl.querySelectorAll('.client-star-btn'));

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
      return false;
    }
  });

  modal.show();
});
</script>
@endif


<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof bootstrap === 'undefined') return;

  document.querySelectorAll('.js-paidvia-info').forEach(function (el) {
    bootstrap.Popover.getOrCreateInstance(el, {
      container: 'body',
      boundary: 'viewport',
      trigger: 'hover focus',
      html: true,
      placement: 'top'
    });
  });
});
</script>
@endpush

