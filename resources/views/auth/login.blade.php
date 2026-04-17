@extends('layouts.app')
@section('title', __('Sign In'))

@section('content')
<div class="container my-4 my-md-5">
  <div class="mx-auto" style="max-width: 560px;">
    {{-- Logo + Title --}}
    <div class="text-center mb-3">
      <img src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS"  class="mb-2 w-25 h-25">
      <h5 class="fw-bold  mb-0">{{ __('Sign In') }}</h5>
    </div>

    {{-- Card --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3 p-md-4">

        @if(session('status'))
          <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
          @csrf
          <div class="mb-3">
            <label class="form-label small fw-semibold">{{ __('Email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control rounded-pill"
                   placeholder="{{ __('Enter Your Email') }}" autofocus>
            @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>

          <div class="mb-1 position-relative">
            <label class="form-label small fw-semibold">{{ __('Password') }}</label>
            <div class="input-group">
              <input type="password" id="password" name="password" class="form-control rounded-pill pe-5"
                     placeholder="{{ __('Enter Your Password') }}">
              <button class="btn btn-link position-absolute end-0 top-50 translate-middle-y me-2 p-0"
                      type="button" id="togglePass" aria-label="Show/Hide">
                <i class="bi bi-eye text-dark"></i>
              </button>
            </div>
            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>

          <div class="d-flex justify-content-end mb-3">
            <a href="#" class="small text-decoration-none text-dark">{{ __('Forgot Password') }}</a>
          </div>

          <button class="btn btn-dark w-100 rounded-pill py-2">{{ __('Log In') }}</button>


          <div class="d-flex align-items-center my-3">
            <hr class="flex-grow-1">
            <span class="px-2 small text-muted">{{ __('or') }}</span>
            <hr class="flex-grow-1">
          </div>

          {{-- Google login button --}}
          <a href="{{ route('login.google') }}" class="btn btn-outline-dark w-100 rounded-pill py-2 d-flex align-items-center justify-content-center gap-2">
            <img src="https://developers.google.com/identity/images/g-logo.png"
                 alt="Google" style="width:20px;height:20px;">
            <span>{{ __('Continue with Google') }}</span>
          </a>
          <div class="text-center mt-3 small">
            {{ __("Don't Have An Account Yet?") }}
            <a class=" text-dark" href="{{ route('register') }}">{{ __('Sign Up') }}</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .form-control { border:1px solid #e6e6e6; }
  .form-control:focus { border-color:#111; box-shadow:0 0 0 2px rgba(17,17,17,.08); }
</style>
@endpush

@push('scripts')
<script>
  document.getElementById('togglePass')?.addEventListener('click', function(){
    const i = this.querySelector('i');
    const f = document.getElementById('password');
    if (!f) return;
    const show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    i.classList.toggle('bi-eye');
    i.classList.toggle('bi-eye-slash');
  });
</script>
@endpush
