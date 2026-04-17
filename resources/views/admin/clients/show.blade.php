@extends('layouts.admin')
@section('title','Client Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-clients-show.css') }}">
@endpush

@section('content')
<section class="card">
  <div class="card__head">
    <div>
      <div class="card__title">Client Details</div>
      <div class="muted">Client Information</div>
    </div>
    <a href="{{ route('admin.clients.index') }}" class="btn ghost small">← Back</a>
  </div>

  <div class="details-wrap">
    @php
  $name = trim(($customer->first_name.' '.$customer->last_name)) ?: ($customer->username ?: 'U');
  $letter = strtoupper(mb_substr($name, 0, 1));
  $avatar = $customer->avatar_path ? asset('storage/'.$customer->avatar_path) : null;
@endphp

<div class="avatar-wrap avatar-wrap-lg">
  @if($avatar)
    <img class="avatar-img"
         src="{{ $avatar }}"
         alt="{{ $name }}"
         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
  @endif

  <div class="avatar-fallback avatar-fallback-lg" style="{{ $avatar ? 'display:none;' : '' }}">
    {{ $letter }}
  </div>
</div>


    <div class="details-info">
      <p><span class="lbl">Name:</span>{{ $customer->first_name }} {{ $customer->last_name }}</p>
      <p><span class="lbl">Email:</span>{{ $customer->email }}</p>
      <p><span class="lbl">Phone:</span>{{ $customer->phone ?: '—' }}</p>
      <p><span class="lbl">Username:</span>{{ $customer->username ?: '—' }}</p>
      <p><span class="lbl">Language:</span>{{ $customer->language ?? '—' }}</p>
    </div>
  </div>
</section>
@endsection
