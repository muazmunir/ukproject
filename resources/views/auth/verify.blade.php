@extends('layouts.app')
@section('title', __('Verify your email'))

@section('content')
<div class="container my-5 verify-page">
  <div class="mx-auto verify-wrapper">
    <div class="card border-0 shadow-sm verify-card">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <img src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS" height="26">
        </div>

        <h5 class="fw-bold text-center mb-2 verify-title">
          {{ __('Enter Verification Code') }}
        </h5>
        <p class="text-muted text-center verify-subtitle">
          {{ __('We Sent a 6-digit Code To') }}
          <strong>{{ $user->email }}</strong>
        </p>

        @if(session('status'))
          <div style="width:max-content;" class="alert alert-success py-2 verify-alert text-center mx-auto">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('auth.verify.submit') }}" class="mt-3">
          @csrf
          <div class="d-flex justify-content-center mb-3">
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   class="form-control text-center otp-input"
                   name="code" placeholder="••••••">
          </div>
          @error('code')
            <div class="invalid-feedback d-block text-center mb-2 small">{{ $message }}</div>
          @enderror

          <button class="btn btn-dark w-100 verify-btn">
            {{ __('Verify & Continue') }}
          </button>
        </form>

        <form method="POST" action="{{ route('auth.verify.resend') }}" class="text-center mt-3">
          @csrf
          <div id="resendTimer" class="small text-muted mb-1"></div>

          <button
            type="submit"
            id="resendBtn"
            class="btn  resend-link d-none"
          >
            {{ __('Resend Code') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn   = document.getElementById('resendBtn');
    const timer = document.getElementById('resendTimer');

    if (!btn || !timer) return;

    let remaining = 30; // seconds
    const label = @json(__('You Can Resend The Code In'));

    timer.textContent = `${label} ${remaining}s`;

    const interval = setInterval(() => {
        remaining--;

        if (remaining <= 0) {
            clearInterval(interval);
            timer.textContent = '';
            btn.classList.remove('d-none');
            btn.removeAttribute('disabled');
        } else {
            timer.textContent = `${label} ${remaining}s`;
        }
    }, 1000);
});
</script>
@endpush

@endsection
