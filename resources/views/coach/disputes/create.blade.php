@extends('layouts.role-dashboard')
@section('title', __('Raise Dispute'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/coach-disputes.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
@endpush

@section('role-content')
<div class="dv-page">
  <div class="card">
    <div class="card__head">
      <div>
        <h2 class="h2">Raise a Dispute</h2>
        <p class="muted">Reservation #{{ $reservation->id }}</p>
      </div>
      <a class="btn btn--ghost" href="{{ route('coach.disputes.index') }}">Back</a>
    </div>

    <form method="POST" action="{{ route('coach.disputes.store', $reservation) }}" enctype="multipart/form-data">
      @csrf

      <label class="dv-label">Title</label>
      <select class="dv-select" name="title_key" required>
        <option value="">Select Title</option>
        @foreach($titles as $k => $label)
          <option value="{{ $k }}" @selected(old('title_key')===$k)>{{ $label }}</option>
        @endforeach
      </select>
      @error('title_key') <div class="dv-err">{{ $message }}</div> @enderror

      <label class="dv-label">Description</label>
      <textarea class="dv-textarea" name="description" rows="6" required>{{ old('description') }}</textarea>
      @error('description') <div class="dv-err">{{ $message }}</div> @enderror

      <label class="dv-label">Attachments (Images / Videos)</label>
      <input class="dv-file" type="file" name="files[]" multiple>
      @error('files.*') <div class="dv-err">{{ $message }}</div> @enderror

      <div class="dv-actions">
        <button class="btn btn--primary rounded-pill  text-white submit" type="submit">Submit Dispute</button>
      </div>
    </form>
  </div>
</div>
@endsection
