@extends('superadmin.layout') {{-- or your superadmin layout --}}

@section('title', 'Compose Newsletter')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/newsletter-subscribers.css') }}">
@endpush

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-1 fw-bold">Compose Newsletter</h3>
      <div class="text-muted small text-capitalize">Send to all active subscribers</div>
    </div>
    <a class="btn btn-outline-dark btn-sm" href="{{ route('superadmin.email-subscriptions.index') }}">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2">
      <i class="bi bi-check-circle"></i>
      <div>{{ session('success') }}</div>
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('superadmin.email-subscriptions.send') }}" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
          <label class="form-label fw-semibold">Subject</label>
          <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
          @error('subject') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Message</label>
          <textarea name="message" rows="8" class="form-control" required>{{ old('message') }}</textarea>
          @error('message') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Attachment (Optional)</label>
          <input type="file"
       name="attachments[]"
       class="form-control"
       multiple
       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">

          @error('attachment') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <button class="btn btn-dark">
          <i class="bi bi-send me-1"></i> Send Newsletter
        </button>
      </form>
    </div>
  </div>

</div>
@endsection
