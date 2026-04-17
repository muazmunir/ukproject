<div class="zv-form-card zv-form-card--side">

  <form method="POST" action="{{ route('profile.password') }}" class="m-0">
    @csrf @method('PUT')

    <div class="zv-form-header">
      <div>
        <div class="zv-eyebrow">{{ __('Security') }}</div>
        <h6 class="mb-0">{{ __('Update Password') }}</h6>
      </div>
    </div>

    <label class="zv-label">{{ __('Current Password') }} *</label>
    <input type="password" name="current_password"
           class="zv-input @error('current_password') is-invalid @enderror">
    @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror

    <label class="zv-label mt-2">{{ __('New Password') }} *</label>
    <input type="password" name="password"
           class="zv-input @error('password') is-invalid @enderror">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror

    <label class="zv-label mt-2">{{ __('Confirm New Password') }} *</label>
    <input type="password" name="password_confirmation" class="zv-input">

    <div class="mt-3">
      <button class="btn-3d btn-plain w-100" type="submit">{{ __('Update Password') }}</button>
    </div>
  </form>

  {{-- @if($activeRole === 'coach')
    <form method="POST"
          action="{{ route('profile.deactivate') }}"
          class="mt-3 m-0"
          onsubmit="return confirm('{{ __('Are You Sure you want to deactivate your account?') }}')">
      @csrf @method('DELETE')
      <button class="btn-3d btn-danger-soft w-100" type="submit">
        {{ __('Deactivate Account') }}
      </button>
    </form>
  @endif --}}

</div>
