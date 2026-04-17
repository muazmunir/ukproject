@extends('layouts.role-dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/client-bookings.css') }}">
@endpush

@section('role-content')
<div class="zv-panel p-4">

  {{-- Header --}}
  <div class="mb-4">
    <h5 class="fw-bold mb-1">{{ __('Raise a Dispute') }}</h5>
    <p class="text-muted small mb-0">
      {{ __('Please provide clear details. Our team will review this dispute carefully.') }}
    </p>
  </div>

  {{-- Reservation context --}}
  <div class="border rounded p-3 mb-4 bg-light">
    <div class="fw-semibold mb-1">
      {{ $reservation->package->title ?? $reservation->service->title ?? __('Service') }}
    </div>
    <div class="small text-muted">
      {{ __('Booking ID') }}: #{{ $reservation->id }}<br>
      {{ __('Total Paid') }}:
      {{ number_format($reservation->total_minor / 100, 2) }}
      {{ $reservation->currency ?? 'USD' }}
    </div>
  </div>

  {{-- Form --}}
  <form
    method="POST"
    action="{{ route('client.disputes.store', $reservation) }}"
    enctype="multipart/form-data"
  >
    @csrf

    {{-- Category --}}
    <div class="mb-3">
      <label class="form-label small fw-semibold">
        {{ __('Issue Category') }}
      </label>
      <select name="category" class="form-select @error('category') is-invalid @enderror" required>
        <option value="">{{ __('Select an issue') }}</option>
        <option value="coach_no_show" @selected(old('category')==='coach_no_show')>
          {{ __('Coach did not attend') }}
        </option>
        <option value="client_issue" @selected(old('category')==='client_issue')>
          {{ __('Client-side issue') }}
        </option>
        <option value="quality_issue" @selected(old('category')==='quality_issue')>
          {{ __('Service quality issue') }}
        </option>
        <option value="technical_issue" @selected(old('category')==='technical_issue')>
          {{ __('Technical / connection issue') }}
        </option>
        <option value="other" @selected(old('category')==='other')>
          {{ __('Other') }}
        </option>
      </select>
      @error('category')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    {{-- Description --}}
    <div class="mb-3">
      <label class="form-label small fw-semibold">
        {{ __('Describe the issue') }}
      </label>
      <textarea
        name="description"
        rows="5"
        class="form-control @error('description') is-invalid @enderror"
        placeholder="{{ __('Explain what happened during or after the session') }}"
        required
      >{{ old('description') }}</textarea>
      @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    {{-- Attachments --}}
    <div class="mb-4">
      <label class="form-label small fw-semibold">
        {{ __('Attachments (optional)') }}
      </label>
      <input
        type="file"
        name="attachments[]"
        class="form-control @error('attachments.*') is-invalid @enderror"
        multiple
        accept="image/*,video/mp4,video/mov,video/webm"
      >
      <div class="small text-muted mt-1">
        {{ __('You may upload images or short videos (max 10MB each).') }}
      </div>
      @error('attachments.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
      @enderror
    </div>

    {{-- Actions --}}
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-dark bg-black text-white rounded-pill px-4">
        {{ __('Submit Dispute') }}
      </button>
      <a href="{{ url()->previous() }}" class="btn btn-outline-secondary rounded-pill px-4">
        {{ __('Cancel') }}
      </a>
    </div>

  </form>

</div>
@endsection
