{{-- resources/views/bookings/index.blade.php --}}
@extends('layouts.app')
@section('title', __('My bookings'))


@push('styles')
<style>
.zv-bookings { max-width: 1100px; margin: 0 auto; }
.zv-card { border: 1px solid rgba(0,0,0,.08); border-radius: 16px; overflow: hidden; box-shadow: 0 6px 18px rgba(0,0,0,.06); }
.zv-card:hover { box-shadow: 0 10px 24px rgba(0,0,0,.10); transform: translateY(-1px); transition: .2s ease; }
.zv-thumb { width: 160px; height: 120px; object-fit: cover; background:#f5f5f5; border-right: 1px solid rgba(0,0,0,.06); }
.zv-title { font-weight: 600; }
.zv-meta { color: #555; font-size: .95rem; }
.zv-badge { font-size: .75rem; }
.zv-actions a, .zv-actions button { border-radius: 999px; }
@media (max-width: 768px){ .zv-thumb{ width:100%; height: 180px; border-right:0; border-bottom:1px solid rgba(0,0,0,.06);} }
</style>
@endpush

@section('content')
<div class="container zv-bookings py-4">
<h2 class="mb-3">{{ __('Your bookingss') }}</h2>


@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif


@forelse($reservations as $res)
@php
$service = $res->service;
$coach = $service?->coach;
[$start,$end] = $res->localSpan($tz);
$img = $service?->cover_url ?? ($service?->images[0] ?? null);
@endphp


<div class="zv-card mb-3">
<div class="row g-0">
<div class="col-12 col-md-auto">
<img class="zv-thumb w-100" src="{{ $img ? asset($img) : asset('assets/placeholder.jpg') }}" alt="{{ $service?->title }}">
</div>
<div class="col">
<div class="p-3 p-md-3 d-flex flex-column h-100">
<div class="d-flex align-items-start justify-content-between gap-2">
<div>
<div class="zv-title">{{ $service?->title }}</div>
<div class="zv-meta">
{{ __('by') }} {{ $coach?->name ?? '—' }} • {{ strtoupper($res->currency ?? 'USD') }} {{ number_format($res->total(),2) }}
</div>
</div>
<span class="badge bg-{{ $res->statusColor() }} zv-badge text-uppercase">{{ $res->payment_status }}</span>
</div>

<div class="mt-2 zv-meta">
  @if($start && $end)
  <i class="bi bi-calendar3"></i>
  {{ $start->toFormattedDateString() }}
  •
  <i class="bi bi-clock"></i>
  {{ $start->format('H:i') }}–{{ $end->format('H:i') }}
  <span class="text-muted">({{ $tz }})</span>
  @else
  <em>{{ __('No time slots') }}</em>
  @endif
  </div>
  
  
  @if($res->environment)
  <div class="mt-1 text-muted small">
  <i class="bi bi-geo"></i> {{ $res->environment }}
  </div>
  @endif
  
  
  <div class="mt-3 d-flex align-items-center gap-2 flex-wrap zv-actions">
    <a class="btn btn-outline-dark btn-sm"
    href="{{ route('client.bookings.show', ['reservation' => $res->id]) }}">
   <i class="bi bi-receipt"></i> {{ __('View details') }}
 </a>
  <a class="btn btn-light btn-sm" href="{{ route('services.show', $service->id) }}">
  <i class="bi bi-box-arrow-up-right"></i> {{ __('Service') }}
  </a>
  @php $receipt = $res->payments->sortByDesc('id')->first()?->receipt_url; @endphp
  @if($receipt)
  <a class="btn btn-success btn-sm" href="{{ $receipt }}" target="_blank" rel="noopener">
  <i class="bi bi-download"></i> {{ __('Receipt') }}
  </a>
  @endif
  @if(in_array($res->payment_status, ['requires_payment','failed']) && $res->status !== 'cancelled')
  {{-- <form method="POST" action="{{ route('client.bookings.cancel', $res->id) }}" onsubmit="return confirm('{{ __('Cancel this reservation?') }}');"> --}}
  @csrf
  <button class="btn btn-outline-danger btn-sm">
  <i class="bi bi-x-circle"></i> {{ __('Cancel') }}
  </button>
  </form>
  @endif
  </div>
  </div>
  </div>
  </div>
  </div>
  @empty
  <div class="text-center text-muted py-5">
  <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
  {{ __('No Bookings Yet.') }}
  </div>
  @endforelse
  
  
  <div class="mt-3">{{ $reservations->withQueryString()->links() }}</div>
  </div>
  @endsection