@extends('layouts.role-dashboard')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/client-booking-details.css') }}">
@endpush

@section('role-content')
@php
  $user = auth()->user();
  $tz   = $user->timezone ?? config('app.timezone','UTC');

  $service = $reservation->service;
  $package = $reservation->package;
  $coach   = $service?->coach;
  $walletPayment   = $reservation->walletPayment;
$externalPayment = $reservation->externalPayment;
$payment = $externalPayment ?? $walletPayment; // ✅ prefer Stripe/PayPal, fallback Wallet

$walletPayment   = $reservation->walletPayment;
$externalPayment = $reservation->externalPayment;
$payment = $externalPayment ?? $walletPayment;

$latestRefund = $reservation->latestRefund
    ?? ($reservation->relationLoaded('refunds') ? $reservation->refunds->sortByDesc('id')->first() : null);

$currencySymbol = '$';
$currencyCode   = 'USD';
$money = fn($minor) => $currencySymbol . number_format(((int)($minor ?? 0)) / 100, 2) . ' ' . $currencyCode;
$m = fn($minor) => number_format(((int)($minor ?? 0)) / 100, 2);

// -------------------------
// payment source / funding
// -------------------------
$walletPaidMinor   = (int) $reservation->walletPaidMinor();
$externalPaidMinor = (int) $reservation->externalPaidMinor();

$providerSource = strtolower((string) (
    $latestRefund?->provider
    ?? $externalPayment?->provider
    ?? $reservation->provider
    ?? ''
));

$providerName = match ($providerSource) {
    'stripe' => 'Stripe',
    'paypal' => 'PayPal',
    'wallet' => 'Wallet',
    default  => ucfirst($providerSource ?: 'External'),
};

// popover rows
$payBreakdown = [];

if ($walletPaidMinor > 0) {
    $payBreakdown[] =
        "<div class='d-flex align-items-center justify-content-between gap-3 mb-2'>
            <span>Wallet</span>
            <strong>{$money($walletPaidMinor)}</strong>
        </div>";
}

if ($externalPaidMinor > 0) {
    $payBreakdown[] =
        "<div class='d-flex align-items-center justify-content-between gap-3 mb-2'>
            <span>{$providerName}</span>
            <strong>{$money($externalPaidMinor)}</strong>
        </div>";
}

if (!$payBreakdown) {
    $payBreakdown[] = "<div class='text-muted'>No payment data</div>";
}

$paidViaPopoverHtml = "<div style='min-width:240px'>" . implode("", $payBreakdown) . "</div>";

// -------------------------
// booking money
// -------------------------
$subtotalMinor = (int) ($reservation->subtotal_minor ?? 0);
$feesMinor     = (int) ($reservation->fees_minor ?? 0);
$totalMinor    = (int) ($reservation->total_minor ?? 0);

$hasPenalties = ((int)($reservation->client_penalty_minor ?? 0) > 0)
             || ((int)($reservation->coach_penalty_minor ?? 0) > 0);

// -------------------------
// statuses
// -------------------------
$status       = strtolower((string) ($reservation->status ?? 'pending'));
$settlement   = strtolower((string) ($reservation->settlement_status ?? ''));
$refundStatus = strtolower((string) ($reservation->refund_status ?? 'none'));
$refundMethod = strtolower((string) ($reservation->refund_method ?? ''));

if (!in_array($refundMethod, ['wallet_credit', 'original_payment'], true)) {
    $refundMethod = 'not_selected';
}

$latestRefundStatus   = strtolower((string) ($latestRefund->status ?? ''));
$walletRefundStatus   = strtolower((string) ($latestRefund->wallet_status ?? ''));
$externalRefundStatus = strtolower((string) ($latestRefund->external_status ?? ''));
$refundFailureReason  = (string) ($latestRefund->failure_reason ?? '');

$refunds = $reservation->refunds ? $reservation->refunds->sortBy('id')->values() : collect();

/*
|--------------------------------------------------------------------------
| Aggregate final refund outcome across ALL attempts
|--------------------------------------------------------------------------
| wallet_amount_minor = wallet-funded component
| external_amount_minor = external-funded component
|
| Destination depends on method:
| - wallet_credit:
|     wallet component   -> wallet
|     external component -> wallet
| - original_payment:
|     wallet component   -> wallet
|     external component -> original payment
*/

$totalRefundedToWalletMinor = 0;
$totalRefundedToOriginalMinor = 0;
$totalRefundedMinor = 0;

$remainingWalletRetryMinor = 0;
$remainingExternalRetryMinor = 0;
$remainingRetryTotalMinor = 0;

foreach ($refunds as $rf) {
    $rfMethod = strtolower((string) ($rf->method ?? ''));
    $rfWalletStatus = strtolower((string) ($rf->wallet_status ?? ''));
    $rfExternalStatus = strtolower((string) ($rf->external_status ?? ''));

    $rfWalletMinor = (int) ($rf->wallet_amount_minor ?? 0);
    $rfExternalMinor = (int) ($rf->external_amount_minor ?? 0);

    // wallet-funded portion always returns to wallet when succeeded
    if ($rfWalletStatus === 'succeeded' && $rfWalletMinor > 0) {
        $totalRefundedToWalletMinor += $rfWalletMinor;
    }

    // external-funded portion destination depends on refund method
    if ($rfExternalStatus === 'succeeded' && $rfExternalMinor > 0) {
        if ($rfMethod === 'wallet_credit') {
            $totalRefundedToWalletMinor += $rfExternalMinor;
        } elseif ($rfMethod === 'original_payment') {
            $totalRefundedToOriginalMinor += $rfExternalMinor;
        }
    }
}

$totalRefundedMinor = $totalRefundedToWalletMinor + $totalRefundedToOriginalMinor;

/*
|--------------------------------------------------------------------------
| Latest unresolved retry amount
|--------------------------------------------------------------------------
*/
if ($latestRefund && in_array($refundStatus, ['partial', 'failed'], true)) {
    $latestWalletMinor   = (int) ($latestRefund->wallet_amount_minor ?? 0);
    $latestExternalMinor = (int) ($latestRefund->external_amount_minor ?? 0);

    $remainingWalletRetryMinor = in_array($walletRefundStatus, ['succeeded', 'not_applicable'], true)
        ? 0
        : $latestWalletMinor;

    $remainingExternalRetryMinor = in_array($externalRefundStatus, ['succeeded', 'not_applicable'], true)
        ? 0
        : $latestExternalMinor;

    $remainingRetryTotalMinor = $remainingWalletRetryMinor + $remainingExternalRetryMinor;
}

/*
|--------------------------------------------------------------------------
| Final display values for detail page
|--------------------------------------------------------------------------
*/
$displayRefundMinor = $totalRefundedMinor;
$refundToWalletMinor = $totalRefundedToWalletMinor;
$refundToExternalMinor = $totalRefundedToOriginalMinor;

$hasRefund = $displayRefundMinor > 0;
$showRefundSection = $hasRefund || $hasPenalties || $remainingRetryTotalMinor > 0;

/*
|--------------------------------------------------------------------------
| Amount kept
|--------------------------------------------------------------------------
*/
$amountKeptMinor = max(0, $totalMinor - $displayRefundMinor);

// -------------------------
// fees refundable note
// -------------------------
$cancelledBy = strtolower((string) ($reservation->cancelled_by ?? ''));
$feesRefundable =
    in_array($cancelledBy, ['coach', 'admin', 'system'], true)
    || ((int)($reservation->platform_earned_minor ?? 0) === 0);

// -------------------------
// slots
// -------------------------
$slots = $reservation->slots?->sortBy('start_utc') ?? collect();

$finishedStatuses = [
    'completed',
    'no_show_coach',
    'no_show_client',
    'no_show_both',
    'cancelled',
    'canceled',
];



  // "Airbnb-ish" UI status
 if (in_array($refundStatus, ['pending_choice', 'processing'], true) || $settlement === 'refund_pending') {
    $uiStatus = 'refund_pending';
} elseif ($refundStatus === 'partial' || $settlement === 'refunded_partial') {
    $uiStatus = 'partially_refunded';
} elseif (in_array($refundStatus, ['refunded', 'succeeded'], true) || $settlement === 'refunded') {
    $uiStatus = 'refunded';
} elseif ($refundStatus === 'failed') {
    $uiStatus = 'refund_pending';
} elseif ($settlement === 'paid') {
    $uiStatus = 'paid';
} elseif ($settlement === 'pending') {
    $uiStatus = 'pending_payout';
} elseif ($settlement === 'in_dispute') {
    $uiStatus = 'in_dispute';
} elseif ($settlement === 'cancelled' || in_array($status, ['cancelled', 'canceled'], true)) {
    $uiStatus = 'cancelled';
} elseif ($status === 'completed') {
    $uiStatus = 'completed';
} elseif (in_array($status, ['booked', 'confirmed', 'paid', 'in_escrow'], true)) {
    $uiStatus = 'in_progress';
} else {
    $uiStatus = $status ?: 'pending';
}
  $statusLabel = fn($s) => ucwords(str_replace('_',' ', (string)$s));

  
  // Next/Current slot (first not finished)
  $currentSlot = $slots->first(function ($s) use ($finishedStatuses) {
    return !in_array(trim(strtolower($s->session_status ?? '')), $finishedStatuses, true);
  });
  if (!$currentSlot) $currentSlot = $slots->last();

  // Date helpers
  $dt = fn($utc) => $utc ? \Carbon\Carbon::parse($utc)->utc()->timezone($tz) : null;

  $firstStart = $slots->first()?->start_utc ? \Carbon\Carbon::parse($slots->first()->start_utc)->utc() : null;
  $lastEnd    = $slots->last()?->end_utc ? \Carbon\Carbon::parse($slots->last()->end_utc)->utc() : null;

  // Cover
  $cover = null;
  if ($service && !empty($service->thumbnail_path)) {
    $cover = asset('storage/' . $service->thumbnail_path);
  } else {
    $images = (array) ($service->images ?? []);
    $first  = reset($images);
    if (!empty($first)) $cover = asset('storage/' . $first);
  }
  if (!$cover) $cover = asset('assets/img/service-placeholder.jpg');

  // Cancel info
  $cancelledBy = strtolower((string)($reservation->cancelled_by ?? ''));
  $cancelledByNice = $cancelledBy ? ucwords($cancelledBy) : '—';

  // Timeline events (simple & clear)
  $events = collect();

  // Created
  $events->push([
    'title' => 'Booking Created',
    'time'  => $reservation->created_at ? $reservation->created_at->timezone($tz) : null,
    'meta'  => $payment ? ('Paid Via ' . ($payment->provider ?? $reservation->provider ?? '—')) : ($reservation->provider ?? '—'),
    'type'  => 'neutral',
  ]);

  if (!empty($reservation->cancelled_at)) {
    $events->push([
      'title' => 'Booking Cancelled',
      'time'  => \Carbon\Carbon::parse($reservation->cancelled_at)->timezone($tz),
      'meta'  => $cancelledBy ? ("Cancelled By: " . $cancelledByNice) : null,
      'type'  => 'danger',
    ]);
  }

 if (in_array($refundStatus, ['succeeded', 'refunded'], true) || $settlement === 'refunded') {
    $events->push([
        'title' => 'Refund issued',
        'time'  => $latestRefund?->processed_at
            ? \Carbon\Carbon::parse($latestRefund->processed_at)->timezone($tz)
            : ($reservation->updated_at ? $reservation->updated_at->timezone($tz) : null),
        'meta'  => $displayRefundMinor > 0 ? ('Refund: ' . $money($displayRefundMinor)) : null,
        'type'  => 'ok',
    ]);
} elseif ($refundStatus === 'partial' || $settlement === 'refunded_partial') {
    $events->push([
        'title' => 'Refund partially completed',
        'time'  => $latestRefund?->processed_at
            ? \Carbon\Carbon::parse($latestRefund->processed_at)->timezone($tz)
            : ($reservation->updated_at ? $reservation->updated_at->timezone($tz) : null),
        'meta'  => $displayRefundMinor > 0 ? ('Refunded so far: ' . $money($displayRefundMinor)) : null,
        'type'  => 'warn',
    ]);
} elseif ($refundStatus === 'failed') {
    $events->push([
        'title' => 'Refund failed',
        'time'  => $latestRefund?->processed_at
            ? \Carbon\Carbon::parse($latestRefund->processed_at)->timezone($tz)
            : ($reservation->updated_at ? $reservation->updated_at->timezone($tz) : null),
        'meta'  => !empty($refundFailureReason) ? $refundFailureReason : null,
        'type'  => 'danger',
    ]);
} elseif ($settlement === 'paid') {
  $events->push([
    'title' => 'Payout released',

      'time'  => $reservation->updated_at ? $reservation->updated_at->timezone($tz) : null,
      'meta'  => null,
      'type'  => 'ok',
    ]);
  }

  // Slot status label mapping
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

  $slotTone = function($st){
    $st = trim(strtolower((string)$st));
    return match($st){
      'completed' => 'ok',
      'live' => 'warn',
      'no_show_client','no_show_coach','no_show_both' => 'danger',
      'cancelled','canceled' => 'muted',
      default => 'neutral'
    };
  };
@endphp

<div class="zv-bd">
  {{-- Header --}}
  <div class="zv-bd__top">
    <a href="{{ route('client.home') }}" class="zv-back">
      <i class="bi bi-arrow-left"></i>
      <span>{{ __('Back to bookings') }}</span>
    </a>
  </div>

  <div class="zv-bd__hero card">
    <div class="zv-bd__heroMedia">
      <img src="{{ $cover }}" alt="{{ $service->title ?? 'Service' }}">
    </div>



    <div class="zv-bd__heroBody">
      <div class="zv-bd__heroRow">
        <div>
          <div class="zv-bd__title">
            {{ $package->title ?? $service->title ?? __('Booking') }}
          </div>
         <div class="zv-bd__sub">
  <span class="zv-muted">{{ __('Booking #') }}{{ $reservation->id }}</span>
  @if($service?->title)
    <span class="zv-dot">•</span>
    <span class="zv-muted">{{ $service->title }}</span>
  @endif
</div>

        </div>

        <div class="zv-bd__pillWrap">
  <span class="zv-pill zv-pill--{{ $uiStatus }}">{{ $statusLabel($uiStatus) }}</span>
</div>

      </div>

      <div class="zv-bd__facts">
        <div class="zv-fact">
          <div class="zv-fact__k text-capitalize">{{ __('Total paid') }}</div>
          <div class="zv-fact__v">{{ $money($totalMinor) }}</div>
        </div>

        <div class="zv-fact">
          <div class="zv-fact__k">{{ __('Sessions') }}</div>
          <div class="zv-fact__v">{{ $slots->count() }}</div>
        </div>

        <div class="zv-fact">
          <div class="zv-fact__k text-capitalize">{{ __('Date range') }}</div>
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

  {{-- Main grid --}}
  <div class="zv-bd__grid">
    {{-- Left column --}}
    <div class="zv-col">
      {{-- What happened --}}
      <div class="card zv-sec">
        <div class="zv-sec__head">
          <div class="zv-sec__title">{{ __('Booking History') }}</div>
          <div class="zv-sec__sub text-capitalize">{{ __('A complete history of this booking.') }}</div>
        </div>

        <div class="zv-timeline">
          @foreach($events as $e)
            <div class="zv-timeline__item">
              <div class="zv-timeline__dot"></div>
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

      {{-- Sessions table --}}
      <div class="card zv-sec">
        <div class="zv-sec__head">
          <div class="zv-sec__title">{{ __('Sessions') }}</div>
          <div class="zv-sec__sub text-capitalize">{{ __('Status for each session in this booking.') }}</div>
        </div>

        <div class="zv-table">
         <div class="zv-table__head">
  <div>{{ __('Date') }}</div>
  <div>{{ __('Time') }}</div>
  <div>{{ __('Status') }}</div>
  <div class="zv-hide-sm">{{ __('Check-in') }}</div>
</div>



          @foreach($slots as $s)
            @php
              $st = trim(strtolower((string)($s->session_status ?? 'scheduled')));
              $startLocal = $dt($s->start_utc);
              $endLocal   = $dt($s->end_utc);

              $ci = $s->client_checked_in_at ? \Carbon\Carbon::parse($s->client_checked_in_at)->timezone($tz)->format('H:i') : '—';
              $co = $s->coach_checked_in_at ? \Carbon\Carbon::parse($s->coach_checked_in_at)->timezone($tz)->format('H:i') : '—';
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
                <span class="zv-pill zv-pill--{{ $slotTone($st) }}">
                  {{ $slotLabel($st) }}
                </span>
              </div>

           <div class="zv-table__cell zv-hide-sm">
  <div class="zv-checkin">
    <span class="zv-mini__v">{{ __('You') }}:</span> {{ $ci }}
   
    <span class="zv-mini__v">{{ __('Coach') }}:</span> {{ $co }}
  </div>

  @if(!empty($s->finalized_at))
    <div class="zv-mini__v">
      {{ __('Finalized') }}: {{ \Carbon\Carbon::parse($s->finalized_at)->timezone($tz)->format('d M Y, H:i') }}
    </div>
  @endif
</div>


            </div>
          @endforeach
        </div>

        @if($currentSlot)
          @php
            $curStart = $dt($currentSlot->start_utc);
            $curEnd   = $dt($currentSlot->end_utc);
          @endphp
          <div class="zv-mini">
            <div class="zv-mini__k">{{ __('Current / Next session') }}</div>
            <div class="zv-mini__v">
              @if($curStart && $curEnd)
                {{ $curStart->format('d M Y') }} • {{ $curStart->format('H:i') }} – {{ $curEnd->format('H:i') }} ({{ $tz }})
              @else
                —
              @endif
            </div>
          </div>
        @endif
      </div>

       @if(in_array($status, ['cancelled','canceled'], true))
        <div class="card zv-sec">
          <div class="zv-sec__head">
            <div class="zv-sec__title">{{ __('Cancellation Details') }}</div>
            <div class="zv-sec__sub text-capitalize">{{ __('Who cancelled and what happened financially.') }}</div>
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

            @if(!empty($reservation->cancel_reason))
              <div class="zv-kv__row">
                <div class="zv-kv__k">{{ __('Reason') }}</div>
                <div class="zv-kv__v">{{ $reservation->cancel_reason }}</div>
              </div>
            @endif
          </div>
        </div>
      @endif
    </div>

    {{-- Right column --}}
    <div class="zv-col">
      {{-- Payment / receipt --}}
      <div class="card zv-sec">
        <div class="zv-sec__head">
          <div class="zv-sec__title text-capitalize">{{ __('Payment & receipt') }}</div>
          <div class="zv-sec__sub text-capitalize">{{ __('A transparent breakdown of charges and refunds.') }}</div>
        </div>

      <div class="zv-receipt">
  {{-- ✅ 1) CHARGES --}}
  <div class="zv-mini mb-2">
    <div class="zv-mini__k">{{ __('Charges') }}</div>
  </div>

  <div class="zv-row">
    <div class="text-capitalize zv-mini__k">{{ __('Service subtotal') }}</div>
    <div class="zv-amt">{{ $money($subtotalMinor) }}</div>
  </div>

  <div class="zv-row">
    <div class="zv-mini__k">{{ __('Service Fee') }}</div>
    <div class="zv-amt">{{ $money($feesMinor) }}</div>
  </div>

  <div class="zv-divider"></div>

  <div class="zv-row zv-row--total text-capitalize">
    <div>{{ __('Total paid') }}</div>
    <div class="zv-amt">{{ $money($totalMinor) }}</div>
  </div>

  {{-- ✅ 2) PAYMENT DETAILS --}}
  <div class="zv-divider"></div>

  <div class="zv-mini mb-2">
    <div class="zv-mini__k">{{ __('Payment Details') }}</div>
  </div>

  <div class="zv-mini">
    <div class="zv-mini__k">{{ __('Payment Status') }}</div>
    <div class="zv-mini__v">{{ $statusLabel($reservation->payment_status ?? '—') }}</div>
  </div>

  <div class="zv-mini d-flex align-items-center justify-content-between">
    <div>
      <div class="zv-mini__k">{{ __('Paid Via') }}</div>
      <div class="zv-mini__v text-capitalize">{{ $reservation->fundingLabel() }}</div>
    </div>

    <button type="button"
            class="btn p-0 border-0 bg-transparent text-muted js-paidvia-info"
            data-bs-toggle="popover"
            data-bs-trigger="hover focus"
            data-bs-placement="top"
            data-bs-html="true"
            data-bs-custom-class="rm-popover-center"
            data-bs-content="{!! $paidViaPopoverHtml !!}"
            aria-label="{{ __('Payment breakdown') }}">
      <i class="bi bi-info-circle"></i>
    </button>
  </div>

  @if(!empty($externalPayment?->provider_payment_id))
    <div class="zv-mini">
      <div class="zv-mini__k">{{ __('Transaction') }}</div>
      <div class="zv-mini__v">{{ $externalPayment->provider_payment_id }}</div>
    </div>
  @endif

  {{-- ✅ 3) REFUND & ADJUSTMENTS (NOW UNDER PAYMENT DETAILS) --}}
  @if($showRefundSection)
    <div class="zv-divider"></div>

    <div class="zv-mini mb-2">
      <div class="zv-mini__k">{{ __('Refund & Adjustments') }}</div>
    </div>

  @if($displayRefundMinor > 0 && $amountKeptMinor > 0)
  <div class="zv-row">
    <div class="text-capitalize zv-mini__k">{{ __('Amount kept') }}</div>
    <div class="zv-amt">- {{ $money($amountKeptMinor) }}</div>
  </div>
@endif

 @if($displayRefundMinor > 0 || $remainingRetryTotalMinor > 0)
  <div class="zv-mini mt-2">
    <div class="zv-mini__k">{{ __('Refund Breakdown') }}</div>
  </div>

  @if($displayRefundMinor > 0)
    @if($refundToWalletMinor > 0)
      <div class="d-flex justify-content-between small mt-1">
        <span class="zv-fact__k">{{ __('Returned to wallet') }}</span>
        <strong>{{ $money($refundToWalletMinor) }}</strong>
      </div>
    @endif

    @if($refundToExternalMinor > 0)
      <div class="d-flex justify-content-between small mt-1">
        <span class="zv-fact__k">{{ __('Returned to :provider', ['provider' => $providerName]) }}</span>
        <strong>{{ $money($refundToExternalMinor) }}</strong>
      </div>
    @endif
  @endif

  @if($remainingRetryTotalMinor > 0 && in_array($refundStatus, ['partial', 'failed'], true))
    <div class="zv-divider"></div>

    <div class="zv-mini mt-2">
      <div class="zv-mini__k">{{ __('Remaining Refund Available To Retry') }}</div>
    </div>

    <div class="zv-mini__v text-muted mb-2">
      @if($refundStatus === 'partial')
        {{ __('Part of the refund was completed successfully. The amount below is still unresolved.') }}
      @else
        {{ __('The previous refund attempt failed. The amount below can still be retried.') }}
      @endif
    </div>

    @if($remainingExternalRetryMinor > 0)
      <div class="d-flex justify-content-between small mt-1">
        <span class="zv-fact__k">{{ __('Original payment remaining') }}</span>
        <strong>{{ $money($remainingExternalRetryMinor) }}</strong>
      </div>
    @endif

    @if($remainingWalletRetryMinor > 0)
      <div class="d-flex justify-content-between small mt-1">
        <span class="zv-fact__k">{{ __('Wallet remaining') }}</span>
        <strong>{{ $money($remainingWalletRetryMinor) }}</strong>
      </div>
    @endif
  @endif

  @if(!empty($refundFailureReason) && in_array($refundStatus, ['partial', 'failed'], true))
    <div class="zv-mini__v text-danger mt-2">
      {{ $refundFailureReason }}
    </div>
  @endif
@endif
  @endif
</div>
      </div>

      {{-- Cancellation panel --}}
     

      {{-- Refunded panel --}}
   
    </div>
  </div>
</div>
@endsection


@push('scripts')
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