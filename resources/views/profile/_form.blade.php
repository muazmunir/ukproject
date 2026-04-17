<form class="zv-form-card" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
  @csrf @method('PUT')

  <div class="zv-form-header">
    <div>
      <div class="zv-eyebrow">
        {{ $activeRole === 'coach' ? __('Coach Profile') : __('Profile') }}
      </div>
      <h6 class="mb-0">{{ __('Edit your profile') }}</h6>
      <small class="text-muted d-block mt-1 text-capitalize">
        @if($activeRole === 'coach')
          {{ __('Add service areas, language proficiencies, gallery and qualifications.') }}
        @else
          {{ __('Keep Your Information Up To Date So Coaches Can Better Understand You.') }}
        @endif
      </small>
    </div>
  </div>

  {{-- Avatar --}}
  <section class="zv-section">
    <div class="zv-section-title">
      <h6 class="mb-0">{{ __('Profile photo') }}</h6>
      <small class="text-muted text-capitalize">
        {{ $activeRole === 'coach' ? __('This is how clients will see you.') : __('This is how coaches will see you.') }}
      </small>
    </div>

    <div class="zv-avatar-uploader">
      <div class="zv-avatar-large">
        <img id="avatar-preview"
             src="{{ $user->avatar_path ? asset('storage/'.$user->avatar_path) : asset('assets/user.png') }}"
             alt="">
      </div>
      <div class="zv-avatar-actions">
        <label class="btn-3d btn-plain mb-2">
          <input type="file" id="avatar-input" name="avatar" accept="image/*" hidden>
          <input type="hidden" name="avatar_cropped" id="avatar-cropped">
          <i class="bi bi-camera me-1"></i>{{ __('Change photo') }}
        </label>

        <div class="small text-muted">
          <span id="avatar-filename">{{ __('No file chosen') }}</span><br>
          {{ __('Max 2MB, JPG or PNG.') }}
        </div>
      </div>
    </div>
    @error('avatar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
  </section>

  {{-- Basic info --}}
  <section class="zv-section">
    <div class="zv-section-title"><h6 class="mb-0">{{ __('Basic information') }}</h6></div>

    <div class="row g-3">
      <div class="col-md-6">
  <label class="zv-label">{{ __('First Name') }} *</label>
  <input type="text" name="first_name"
         class="zv-input zv-input-readonly"
         value="{{ old('first_name',$user->first_name) }}" readonly>
  <small class="text-muted small text-capitalize">
    {{ __("If you need to change your first name, please contact support.") }}
  </small>
</div>


      <div class="col-md-6">
  <label class="zv-label">{{ __('Last Name') }}</label>
  <input type="text" name="last_name"
         class="zv-input zv-input-readonly"
         value="{{ old('last_name',$user->last_name) }}" readonly>
  <small class="text-muted small text-capitalize">
    {{ __("If you need to change your last name, please contact support.") }}
  </small>
</div>


      <div class="col-12">
        <label class="zv-label">{{ __('Email') }} *</label>
        <input type="email" name="email" class="zv-input zv-input-readonly"
               value="{{ $user->email }}" readonly>
        <small class="text-muted small text-capitalize">
          {{ __("If you need to change your email, please contact support.") }}
        </small>
      </div>
    </div>
  </section>

  {{-- Contact --}}
  <section class="zv-section">
    <div class="zv-section-title"><h6 class="mb-0">{{ __('Contact') }}</h6></div>

    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="zv-label">{{ __('Code') }}</label>
        <select name="phone_code" class="zv-input js-phone-profile"
                data-selected="{{ old('phone_code',$user->phone_code) }}">
          <option value="">{{ __('Code') }}</option>
        </select>
      </div>

      <div class="col-md-8">
        <label class="zv-label">{{ __('Contact No') }}</label>
        <input type="text" name="phone" class="zv-input"
               value="{{ old('phone',$user->phone) }}" placeholder="03xxxxxxxxx">
        @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
      </div>
    </div>
  </section>

  {{-- About --}}
  <section class="zv-section">
    <div class="zv-section-title"><h6 class="mb-0">{{ __('About You') }}</h6></div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="zv-label mb-0">{{ __('Short Bio') }} <small class="text-muted">(max 160)</small></label>
          <small class="zv-counter" id="short-bio-counter">0 / 160</small>
        </div>
        <input id="short_bio" type="text" name="short_bio" maxlength="160"
               class="zv-input" value="{{ old('short_bio',$user->short_bio) }}">
      </div>

      <div class="col-md-6">
        <label class="zv-label">{{ __('Timezone') }}</label>
        <select name="timezone" class="zv-input zv-input-readonly" disabled>
          <option value="">{{ $user->timezone ?: __('Not set') }}</option>
        </select>
        <small class="text-muted small text-capitalize">
          {{ __("Timezone is synced automatically. Contact support if it looks wrong.") }}
        </small>
      </div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="zv-label mb-0">{{ __('Description') }}</label>
          <small class="zv-counter" id="description-counter">0 / 600</small>
        </div>
        <textarea id="description" name="description" rows="4" maxlength="600"
                  class="zv-input zv-textarea">{{ old('description',$user->description) }}</textarea>
      </div>
    </div>
  </section>

  {{-- Location --}}
  <section class="zv-section">
    <div class="zv-section-title"><h6 class="mb-0">{{ __('Location') }}</h6></div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="zv-label">{{ __('Country') }}</label>
        <select name="country" class="zv-input js-country-profile"
                data-selected="{{ old('country',$user->country) }}">
          <option value="">{{ __('Select Country') }}</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="zv-label">{{ __('City') }}</label>
        <select name="city" class="zv-input js-city-profile"
                data-selected="{{ old('city',$user->city) }}">
          <option value="">{{ __('Select City') }}</option>
        </select>
      </div>
    </div>
  </section>

  {{-- Languages --}}
  <section class="zv-section">
    <div class="zv-section-title d-flex justify-content-between align-items-center">
      <h6 class="mb-0">{{ __('Languages') }}</h6>
      <small class="text-muted text-capitalize">{{ __('Add as many as you want') }}</small>
    </div>

    <div class="zv-lang-wrap">
      <input type="text" id="lang-input" class="zv-input text-capitalize" placeholder="{{ __('Type language & press Add') }}">
      <button type="button" class="btn-3d btn-plain" id="lang-add">
        <i class="bi bi-plus-lg me-1"></i>{{ __('Add') }}
      </button>
    </div>

    <div id="lang-tags" class="zv-lang-tags">
      @foreach((array) old('languages', $user->languages ?? []) as $lang)
        <span class="zv-tag">
          <span class="zv-tag-text">{{ $lang }}</span>
          <button type="button" class="zv-tag-x">&times;</button>
        </span>
        <input type="hidden" name="languages[]" value="{{ $lang }}">
      @endforeach
    </div>
  </section>

  {{-- Coach-only --}}
  @if($activeRole === 'coach')
    @include('profile.sections._coach', ['user' => $user])
  @endif

  {{-- Socials --}}
  <section class="zv-section">
    <div class="zv-section-title">
      <h6 class="mb-0">{{ __('Social Profiles') }}</h6>
      <small class="text-muted text-capitalize">
        {{ $activeRole === 'coach' ? __('Let clients learn more about you.') : __('Let coaches learn more about you.') }}
      </small>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="zv-label">Facebook</label>
        <input type="url" name="facebook_url" class="zv-input"
               value="{{ old('facebook_url', $user->facebook_url) }}" placeholder="https://facebook.com/...">
      </div>
      <div class="col-md-6">
        <label class="zv-label">Instagram</label>
        <input type="url" name="instagram_url" class="zv-input"
               value="{{ old('instagram_url', $user->instagram_url) }}" placeholder="https://instagram.com/...">
      </div>
      <div class="col-md-6">
        <label class="zv-label">LinkedIn</label>
        <input type="url" name="linkedin_url" class="zv-input"
               value="{{ old('linkedin_url', $user->linkedin_url) }}" placeholder="https://linkedin.com/in/...">
      </div>
      <div class="col-md-6">
        <label class="zv-label">Twitter / X</label>
        <input type="url" name="twitter_url" class="zv-input"
               value="{{ old('twitter_url', $user->twitter_url) }}" placeholder="https://x.com/...">
      </div>
      <div class="col-md-12">
        <label class="zv-label">YouTube</label>
        <input type="url" name="youtube_url" class="zv-input"
               value="{{ old('youtube_url', $user->youtube_url) }}" placeholder="https://youtube.com/@...">
      </div>
    </div>
  </section>

  <div class="zv-form-footer">
    <button class="btn-3d btn-dark-elev" type="submit">{{ __('Save Changes') }}</button>
  </div>
</form>
