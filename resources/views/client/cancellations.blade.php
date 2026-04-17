@extends('client.layout')

@section('client-content')
<div class="zv-panel p-3">
  <h6 class="fw-bold mb-3">{{ __('Cancellation Request') }}</h6>
  <form>
    <div class="mb-3">
      <label class="form-label small">{{ __('Booking ID') }}</label>
      <input type="text" class="form-control" placeholder="#0000">
    </div>
    <div class="mb-3">
      <label class="form-label small">{{ __('Reason') }}</label>
      <textarea class="form-control" rows="4" placeholder="{{ __('Tell us why you want to cancel') }}"></textarea>
    </div>
    <button class="btn btn-dark">{{ __('Send Request') }}</button>
  </form>
</div>
@endsection
