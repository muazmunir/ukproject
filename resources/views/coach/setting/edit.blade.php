@extends('coach.layout')

@section('coach-content')
<div class="container-narrow">
  <h4 class="mb-3"><i class="bi bi-gear-wide me-2"></i>{{ __('Coach Settings') }}</h4>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body">
      <h5 class="mb-3">{{ __('Timezone') }}</h5>
      <form method="POST" action="{{ route('coach.settings.tz.set') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-6">
          <label class="form-label">{{ __('Coach timezone (IANA)') }}</label>
          <input type="text" name="tz" id="tzInput" class="form-control" value="{{ $coachTz }}">
          <div class="form-text">{{ __('Examples: Asia/Karachi, Asia/Yerevan, Europe/Berlin') }}</div>
          @error('tz') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-auto">
          <button class="btn btn-dark" type="submit"><i class="bi bi-save me-1"></i>{{ __('Save') }}</button>
        </div>
        <div class="col-12 mt-2">
          <span class="badge bg-light text-dark border">
            <i class="bi bi-geo-alt me-1"></i>{{ $coachTz }}
          </span>
          <small class="text-muted ms-2" id="deviceTzHint"></small>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
  // Optional: show the device tz as a hint with a quick “use device” fill-in
  (function () {
    const devTz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    const hintEl = document.getElementById('deviceTzHint');
    const input  = document.getElementById('tzInput');
    if (hintEl && input) {
      hintEl.innerHTML = `{{ __('Your device timezone is') }} <strong>${devTz}</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="useDevTz">{{ __('Use device timezone') }}</button>`;
      document.getElementById('useDevTz').addEventListener('click', () => { input.value = devTz; });
    }
  })();
</script>
@endpush
@endsection
