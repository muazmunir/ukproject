{{-- resources/views/bookings/show.blade.php --}}
@extends('layouts.app')
@section('title', __('Booking details'))

@php
  // Expecting $reservation and $tz passed from controller
  [$start,$end] = $reservation->localSpan($tz);
  $service = $reservation->service;
  $coach   = $service?->coach;
  $groups  = $reservation->localizedSlots($tz);
@endphp

@push('styles')
<style>
  .zv-wrap { max-width: 1000px; margin: 0 auto; }
  .zv-section { border:1px solid rgba(0,0,0,.08); border-radius:16px; padding:16px; background:#fff; }
  .zv-title { font-weight: 700; }
  .zv-chip { border-radius: 999px; border:1px solid rgba(0,0,0,.12); padding:4px 10px; display:inline-flex; align-items:center; gap:6px; }
  .zv-li { border-bottom:1px dashed rgba(0,0,0,.1); padding:8px 0; }
  .zv-li:last-child{ border-bottom:0; }
</style>
@endpush

@section('content')
<div class="container py-4 zv-wrap">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="zv-title">{{ $service?->title }}</h3>
    <span class="badge bg-{{ $reservation->statusColor() }} text-uppercase">{{ $reservation->payment_status }}</span>
  </div>

  <div class="row g-3">
    <div class="col-md-7">
      <div class="zv-section mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="zv-chip"><i class="bi bi-person"></i> {{ $coach?->name }}</span>
          <span class="zv-chip"><i class="bi bi-globe2"></i> {{ $tz }}</span>
          <span class="zv-chip"><i class="bi bi-currency-dollar"></i> {{ strtoupper($reservation->currency ?? 'USD') }} {{ number_format($reservation->total(),2) }}</span>
        </div>
        <div class="mt-2 text-muted">
          @if($start && $end)
            <i class="bi bi-calendar3"></i> {{ $start->toFormattedDateString() }} ·
            <i class="bi bi-clock"></i> {{ $start->format('H:i') }}–{{ $end->format('H:i') }}
          @endif
        </div>
      </div>

      <div class="zv-section mb-3">
        <h6 class="mb-2">{{ __('Selected Time slotss') }}</h6>
        @forelse($groups as $date => $slots)
          <div class="mb-2">
            <div class="fw-semibold">{{ \Carbon\Carbon::parse($date)->tz($tz)->toFormattedDateString() }}</div>
            @foreach($slots as $s)
              <div class="zv-li d-flex align-items-center justify-content-between">
                <span>{{ $s['start'] }}–{{ $s['end'] }}</span>
                <span class="text-muted small">{{ $tz }}</span>
              </div>
            @endforeach
          </div>
        @empty
          <em class="text-muted">{{ __('No time slots') }}</em>
        @endforelse
      </div>

      @if($reservation->note)
        <div class="zv-section mb-3">
          <h6 class="mb-2">{{ __('Notes') }}</h6>
          <p class="mb-0">{{ $reservation->note }}</p>
        </div>
      @endif

      @if(in_array($reservation->payment_status,['requires_payment','failed']) && $reservation->status !== 'cancelled')
        <form method="POST"
              action="{{ route('client.bookings.cancel', ['reservation' => $reservation->id]) }}"
              onsubmit="return confirm('{{ __('Cancel this reservation?') }}');"
              class="mt-2">
          @csrf
          <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> {{ __('Cancel reservation') }}</button>
        </form>
      @endif
    </div>

    <div class="col-md-5">
      <div class="zv-section">
        <h6 class="mb-3">{{ __('Payment summary') }}</h6>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('Subtotal') }}</span>
          <span>{{ strtoupper($reservation->currency ?? 'USD') }} {{ number_format($reservation->subtotal(),2) }}</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('Service Fee') }}</span>
          <span>{{ strtoupper($reservation->currency ?? 'USD') }} {{ number_format($reservation->fees(),2) }}</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between fw-semibold">
          <span>{{ __('Total') }}</span>
          <span>{{ strtoupper($reservation->currency ?? 'USD') }} {{ number_format($reservation->total(),2) }}</span>
        </div>
        @php $receipt = $reservation->payments->sortByDesc('id')->first()?->receipt_url; @endphp
        @if($receipt)
          <a class="btn btn-success w-100 mt-3" href="{{ $receipt }}" target="_blank" rel="noopener">
            <i class="bi bi-download"></i> {{ __('Download receipt') }}
          </a>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
