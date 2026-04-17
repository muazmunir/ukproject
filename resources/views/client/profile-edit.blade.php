@extends('client.layout')


@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/client-profile.css') }}">
<link rel="stylesheet" href="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.css">
@endpush

@section('client-content')

<div class="zv-profile-wrapper">
  <div class="row g-4 flex-lg-nowrap">

    {{-- LEFT: PROFILE FORM --}}
    <div class="col-12 col-lg-8">
      <form class="zv-form-card" method="POST" action="{{ route('client.profile.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="zv-form-header">
          <div>
            <div class="zv-eyebrow">{{ __('Profile') }}</div>
            <h5 class="mb-0">{{ __('Edit Your Profile') }}</h5>
            <small class="text-muted d-block mt-1">
              {{ __('Keep Your Information Up To Date So Coaches Can Better Understand You.') }}
            </small>
          </div>
        </div>

        {{-- AVATAR --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Profile Photo') }}</h6>
            <small class="text-muted">{{ __('This Is How Coaches Will See You.') }}</small>
          </div>

          <div class="zv-avatar-uploader">
            <div class="zv-avatar-large">
              <img id="avatar-preview"
                   src="{{ $user->avatar_path ? asset('storage/'.$user->avatar_path) : asset('assets/user.png') }}"
                   alt="Preview">
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

        {{-- BASIC INFO --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Basic information') }}</h6>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="zv-label">{{ __('First Name') }} *</label>
              <input type="text" name="first_name"
                     class="zv-input @error('first_name') is-invalid @enderror"
                     value="{{ old('first_name', $user->first_name) }}">
              @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="zv-label">{{ __('Last Name') }}</label>
              <input type="text" name="last_name"
                     class="zv-input @error('last_name') is-invalid @enderror"
                     value="{{ old('last_name', $user->last_name) }}">
              @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
              <label class="zv-label">{{ __('Email') }} *</label>
              <input type="email" name="email"
                     class="zv-input zv-input-readonly"
                     value="{{ $user->email }}" readonly>
              <small class="text-muted small">
                {{ __("If You Need To Change Your Email, Please Contact Support.") }}
              </small>
            </div>
          </div>
        </section>

        {{-- CONTACT --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Contact') }}</h6>
          </div>

          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="zv-label">{{ __('Code') }}</label>
              <select name="phone_code"
                      class="zv-input js-phone-profile"
                      data-selected="{{ old('phone_code', $user->phone_code) }}">
                <option value="">{{ __('Code') }}</option>
              </select>
            </div>

            <div class="col-md-8">
              <label class="zv-label">{{ __('Contact No') }}</label>
              <input type="text" name="phone" class="zv-input"
                     value="{{ old('phone', $user->phone) }}" placeholder="03xxxxxxxxx">
              @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
          </div>
        </section>

        {{-- ABOUT --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('About you') }}</h6>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="zv-label mb-0">
                {{ __('Short Bio') }}
                <small class="text-muted">(max 160)</small>
              </label>
              <small class="zv-counter" id="short-bio-counter">0 / 160</small>
            </div>
            <input id="short_bio" type="text" name="short_bio" maxlength="160"
                   class="zv-input"
                   value="{{ old('short_bio', $user->short_bio) }}">
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="zv-label mb-0">{{ __('Description') }}</label>
              <small class="zv-counter" id="description-counter">0 / 600</small>
            </div>
            <textarea id="description" name="description" rows="4" maxlength="600"
                      class="zv-input zv-textarea">{{ old('description', $user->description) }}</textarea>
          </div>

          <div class="mb-0">
            <label class="zv-label">{{ __('Timezone') }}</label>
            <select name="timezone" class="zv-input zv-input-readonly" disabled>
              <option value="">{{ $user->timezone ?: __('Not set') }}</option>
            </select>
            <small class="text-muted small text-capitalize">
              {{ __("Timezone is synced automatically. Contact support if it looks wrong.") }}
            </small>
          </div>
        </section>

        {{-- LOCATION --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Location') }}</h6>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="zv-label">{{ __('Country') }}</label>
              <select name="country" class="zv-input js-country-profile"
                      data-selected="{{ old('country', $user->country) }}">
                <option value="">{{ __('Select Country') }}</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="zv-label">{{ __('City') }}</label>
              <select name="city" class="zv-input js-city-profile"
                      data-selected="{{ old('city', $user->city) }}">
                <option value="">{{ __('Select City') }}</option>
              </select>
            </div>
          </div>
        </section>

        {{-- LANGUAGES --}}
        <section class="zv-section">
          <div class="zv-section-title d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ __('Languages') }}</h6>
            <small class="text-muted text-capitalize">{{ __('Add as many as you want') }}</small>
          </div>

          <div class="zv-lang-wrap">
            <input type="text" id="lang-input" class="zv-input"
                   placeholder="{{ __('Type Language & Press Add') }}">
            <button type="button" class="btn-3d btn-plain" id="lang-add">
              <i class="bi bi-plus-lg me-1"></i>{{ __('Add To List') }}
            </button>
          </div>

          <div id="lang-tags" class="zv-lang-tags">
            @foreach((array) old('languages', $user->languages ?? []) as $lang)
              <span class="zv-tag">
                <span class="zv-tag-text">{{ $lang }}</span>
                <button type="button" class="zv-tag-x" aria-label="Remove">&times;</button>
              </span>
              <input type="hidden" name="languages[]" value="{{ $lang }}">
            @endforeach
          </div>
        </section>

        {{-- SOCIALS --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Social profiles') }}</h6>
            <small class="text-muted text-capitalize">{{ __('Let coaches learn more about you.') }}</small>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="zv-label">Facebook</label>
              <input type="url" name="facebook_url" class="zv-input"
                     value="{{ old('facebook_url', $user->facebook_url) }}"
                     placeholder="https://facebook.com/...">
            </div>
            <div class="col-md-6">
              <label class="zv-label">Instagram</label>
              <input type="url" name="instagram_url" class="zv-input"
                     value="{{ old('instagram_url', $user->instagram_url) }}"
                     placeholder="https://instagram.com/...">
            </div>
            <div class="col-md-6">
              <label class="zv-label">LinkedIn</label>
              <input type="url" name="linkedin_url" class="zv-input"
                     value="{{ old('linkedin_url', $user->linkedin_url) }}"
                     placeholder="https://linkedin.com/in/...">
            </div>
            <div class="col-md-6">
              <label class="zv-label">Twitter / X</label>
              <input type="url" name="twitter_url" class="zv-input"
                     value="{{ old('twitter_url', $user->twitter_url) }}"
                     placeholder="https://x.com/...">
            </div>
            <div class="col-md-12">
              <label class="zv-label">YouTube</label>
              <input type="url" name="youtube_url" class="zv-input"
                     value="{{ old('youtube_url', $user->youtube_url) }}"
                     placeholder="https://youtube.com/@...">
            </div>
          </div>
        </section>

        <div class="zv-form-footer">
          <button class="btn-3d btn-dark-elev" type="submit">
            {{ __('Save Changes') }}
          </button>
        </div>
      </form>
    </div>

    {{-- RIGHT: PASSWORD FORM --}}
    <div class="col-12 col-lg-4">
      <form class="zv-form-card zv-form-card--side" method="POST" action="{{ route('client.profile.password') }}">
        @csrf @method('PUT')

        <div class="zv-form-header">
          <div>
            <div class="zv-eyebrow">{{ __('Security') }}</div>
            <h6 class="mb-0 text-capitalize">{{ __('Update password') }}</h6>
            <small class="text-muted d-block mt-1 text-capitalize">
              {{ __('Keep your account secure.') }}
            </small>
          </div>
        </div>

        <div class="zv-section">
          <label class="zv-label">{{ __('Current Password') }} *</label>
          <div class="zv-input-group">
            <input type="password" name="current_password" id="current_password"
                   class="zv-input @error('current_password') is-invalid @enderror"
                   placeholder="{{ __('Old Password') }}">
            <button type="button" class="zv-input-append" data-toggle="password" data-target="#current_password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="zv-section">
          <label class="zv-label">{{ __('New Password') }} *</label>
          <div class="zv-input-group">
            <input type="password" name="password" id="password"
                   class="zv-input @error('password') is-invalid @enderror"
                   placeholder="{{ __('New Password') }}">
            <button type="button" class="zv-input-append" data-toggle="password" data-target="#password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <small class="text-muted small d-block mt-1 " id="password-hint">
            {{ __('Use At Least 8 Characters, Including a Number And a Symbol.') }}
          </small>
        </div>

        <div class="zv-section mb-0">
          <label class="zv-label">{{ __('Confirm New Password') }} *</label>
          <div class="zv-input-group">
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="zv-input"
                   placeholder="{{ __('Confirm New Password') }}">
            <button type="button" class="zv-input-append" data-toggle="password" data-target="#password_confirmation">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="zv-form-footer">
          <button class="btn-3d btn-dark-elev w-100" type="submit">
            {{ __('Update Password') }}
          </button>
        </div>
      </form>
    </div>

  </div>
</div>



{{-- Avatar crop modal --}}
<div class="modal fade" id="avatarCropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content zv-cropper-modal">
      <div class="modal-header">
        <h6 class="modal-title mb-0">{{ __('Adjust your photo') }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <div class="zv-cropper-wrap">
          <img id="avatar-cropper-image" src="" alt="Crop preview">
        </div>
        <small class="text-muted d-block mt-2">
          {{ __('Drag to reposition, scroll to zoom in/out.') }}
        </small>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn-3d btn-plain" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn-3d btn-dark-elev" id="avatar-crop-save">
          {{ __('Use this photo') }}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.js"></script>
<script>
/* ---------------- Languages tags ---------------- */
document.getElementById('lang-add')?.addEventListener('click', function () {
  const inp  = document.getElementById('lang-input');
  const val  = (inp.value || '').trim();
  if (!val) return;

  const wrap = document.getElementById('lang-tags');

  const tag  = document.createElement('span');
  tag.className = 'zv-tag';
  tag.innerHTML =
    `<span class="zv-tag-text">${val}</span>` +
    `<button type="button" class="zv-tag-x" aria-label="Remove">&times;</button>`;
  wrap.appendChild(tag);

  const hid = document.createElement('input');
  hid.type  = 'hidden';
  hid.name  = 'languages[]';
  hid.value = val;
  wrap.appendChild(hid);

  inp.value = '';
});

document.getElementById('lang-tags')?.addEventListener('click', function (e) {
  if (e.target.classList.contains('zv-tag-x')) {
    const tag = e.target.closest('.zv-tag');
    const val = tag.querySelector('.zv-tag-text').textContent.trim();
    tag.remove();
    [...this.parentElement.querySelectorAll('input[name="languages[]"]')].forEach(h => {
      if (h.value === val) h.remove();
    });
  }
});

/* ---------------- Country / City / Phone (same API as register) ---------------- */
(function () {
  const API_COUNTRIES = '{{ route("cc.countries") }}';
  const API_CITIES    = '{{ route("cc.cities") }}';
  const API_CODES     = '{{ route("cc.codes") }}';

  const countrySel = document.querySelector('.js-country-profile');
  const citySel    = document.querySelector('.js-city-profile');
  const phoneSel   = document.querySelector('.js-phone-profile');

  if (!countrySel || !citySel) return;

  function setDisabled(el, on) { el.disabled = !!on; }

  async function fetchJSON(url, params = {}) {
    const qs   = new URLSearchParams(params).toString();
    const full = qs ? `${url}?${qs}` : url;

    const res  = await fetch(full, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await res.text();
    let json   = null;
    try { json = JSON.parse(text); } catch (e) {}

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    if (!json || json.success !== true) throw new Error((json && json.message) || 'API error');

    return json.data || [];
  }

  function setOptions(el, items, placeholder = 'Select', preselect = '') {
    const frag = document.createDocumentFragment();
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder;
    frag.appendChild(opt0);

    (items || []).forEach(item => {
      const opt = document.createElement('option');

      if (typeof item === 'string') {
        opt.value = item;
        opt.textContent = item;
        opt.dataset.name = item;
      } else if (item && typeof item === 'object') {
        const name = item.name || item.code || '';
        const code = item.code || '';

        opt.value       = name;
        opt.textContent = name;
        opt.dataset.name = name;
        if (code) opt.dataset.iso2 = String(code).toUpperCase();
      }

      frag.appendChild(opt);
    });

    el.innerHTML = '';
    el.appendChild(frag);

    if (preselect) {
      el.value = preselect;
      if (el.value !== preselect) {
        const opts  = Array.from(el.options);
        const match = opts.find(o => o.text === preselect || o.dataset.name === preselect);
        if (match) match.selected = true;
      }
    }
  }

  async function loadCities(countryName, preselect = '') {
    if (!countryName) {
      setOptions(citySel, [], '{{ __("Select a country first") }}');
      setDisabled(citySel, true);
      return;
    }

    setDisabled(citySel, true);
    setOptions(citySel, [], '{{ __("Loading...") }}');

    const cities = await fetchJSON(API_CITIES, { country: countryName }).catch(() => []);
    setOptions(citySel, cities, '{{ __("Select City") }}', preselect);
    setDisabled(citySel, false);
  }

  let phoneMap = null;

  async function loadPhoneCodesOnce() {
    if (phoneMap) return phoneMap;

    const rows = await fetchJSON(API_CODES).catch(() => []);

    if (phoneSel) {
      const frag = document.createDocumentFragment();
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = '{{ __("Code") }}';
      frag.appendChild(opt0);

      rows.forEach(c => {
        const o    = document.createElement('option');
        const dial = String(c.code || '').trim();
        const name = c.name || '';
        const iso2 = String(c.iso2 || '').toUpperCase();

        o.value = dial;
        o.textContent = `${name} (${dial})`;
        o.dataset.iso2 = iso2;
        frag.appendChild(o);
      });

      phoneSel.innerHTML = '';
      phoneSel.appendChild(frag);

      const pre = phoneSel.dataset.selected || '';
      if (pre) phoneSel.value = pre;
    }

    phoneMap = new Map(
      rows.map(r => [String(r.iso2 || '').toUpperCase(), String(r.code || '')])
    );
    if (phoneMap.has('GB')) phoneMap.set('UK',  phoneMap.get('GB'));
    if (phoneMap.has('US')) phoneMap.set('USA', phoneMap.get('US'));
    if (phoneMap.has('AE')) phoneMap.set('UAE', phoneMap.get('AE'));

    return phoneMap;
  }

  function syncPhoneByIso(map, iso2) {
    if (!phoneSel || !iso2) return;
    const dial = map.get(String(iso2).toUpperCase());
    if (!dial) return;

    const prev = phoneSel.value;
    phoneSel.value = dial;
    if (phoneSel.value !== dial) {
      phoneSel.value = prev || '';
    }
  }

  async function guessCountryFromCity(countries, selectedCity) {
    if (!selectedCity) return null;

    for (const c of countries) {
      const name = c.name || '';
      if (!name) continue;

      const cityList = await fetchJSON(API_CITIES, { country: name }).catch(() => []);
      if (Array.isArray(cityList) && cityList.includes(selectedCity)) {
        const opts  = Array.from(countrySel.options);
        const match = opts.find(
          o => o.text === name || o.dataset.name === name || o.value === (c.code || '')
        );
        if (match) match.selected = true;
        return name;
      }
    }
    return null;
  }

  async function initCountryCity() {
    setDisabled(countrySel, true);
    setDisabled(citySel, true);

    const selectedCountry = countrySel.dataset.selected || '';
    const selectedCity    = citySel.dataset.selected || '';

    const countries = await fetchJSON(API_COUNTRIES).catch(() => []);
    setOptions(countrySel, countries, '{{ __("Select Country") }}', selectedCountry);
    setDisabled(countrySel, false);

    if (phoneSel) {
      phoneMap = await loadPhoneCodesOnce();
    }

    let countryNameToUse = '';

    if (countrySel.value) {
      const opt = countrySel.options[countrySel.selectedIndex];
      countryNameToUse = opt?.text || '';
      if (phoneMap && opt?.dataset.iso2) {
        syncPhoneByIso(phoneMap, opt.dataset.iso2);
      }
    }

    if (!countryNameToUse && selectedCity) {
      countryNameToUse = await guessCountryFromCity(countries, selectedCity) || '';
    }

    if (countryNameToUse) {
      await loadCities(countryNameToUse, selectedCity);
    } else {
      setOptions(citySel, [], '{{ __("Select a country first") }}');
      setDisabled(citySel, true);
    }

    countrySel.addEventListener('change', async () => {
      const opt  = countrySel.options[countrySel.selectedIndex];
      const name = opt?.text || countrySel.value;

      await loadCities(name, '');

      if (phoneMap && opt?.dataset.iso2) {
        syncPhoneByIso(phoneMap, opt.dataset.iso2);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCountryCity, { once: true });
  } else {
    initCountryCity();
  }
})();

/* ---------------- Avatar preview ---------------- */
/* -------- Avatar crop (WhatsApp-style) -------- */
const avatarInput      = document.getElementById('avatar-input');
const avatarPreview    = document.getElementById('avatar-preview');
const avatarFilename   = document.getElementById('avatar-filename');
const avatarCropped    = document.getElementById('avatar-cropped');
const cropperImg       = document.getElementById('avatar-cropper-image');
const cropSaveBtn      = document.getElementById('avatar-crop-save');
const cropModalEl      = document.getElementById('avatarCropModal');

let avatarCropper      = null;
let avatarModalInstance = null;

if (cropModalEl && typeof bootstrap !== 'undefined') {
  avatarModalInstance = new bootstrap.Modal(cropModalEl, {
    backdrop: 'static',
    keyboard: false
  });
}

avatarInput?.addEventListener('change', function () {
  const file = this.files?.[0];

  if (!file) {
    avatarFilename.textContent = '{{ __("No file chosen") }}';
    return;
  }

  avatarFilename.textContent = file.name;

  // Read file and show in modal cropper
  const reader = new FileReader();
  reader.onload = function (e) {
    cropperImg.src = e.target.result;

    // Wait for img to load before creating cropper
    cropperImg.onload = function () {
      // Destroy old instance if any
      if (avatarCropper) {
        avatarCropper.destroy();
        avatarCropper = null;
      }

      avatarCropper = new Cropper(cropperImg, {
        aspectRatio: 1,          // square
        viewMode: 1,
        autoCropArea: 1,
        dragMode: 'move',
        background: false,
        minCropBoxWidth: 150,
        minCropBoxHeight: 150
      });

      avatarModalInstance?.show();
    };
  };
  reader.readAsDataURL(file);
});

cropSaveBtn?.addEventListener('click', function () {
  if (!avatarCropper) return;

  // Get cropped canvas (square) and generate dataURL
  const canvas = avatarCropper.getCroppedCanvas({
    width: 400,
    height: 400,
    imageSmoothingQuality: 'high'
  });

  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

  // Update preview avatar
  avatarPreview.src = dataUrl;

  // Store cropped image in hidden input (to be processed server-side)
  avatarCropped.value = dataUrl;

  avatarModalInstance?.hide();
  avatarCropper.destroy();
  avatarCropper = null;
});

</script>

<script>
  // ---------- Password show / hide ----------
  (function () {
    const toggles = document.querySelectorAll('[data-toggle="password"]');

    if (!toggles.length) return;

    toggles.forEach(btn => {
      btn.addEventListener('click', () => {
        const targetSelector = btn.getAttribute('data-target');
        if (!targetSelector) return;

        const input = document.querySelector(targetSelector);
        if (!input) return;

        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';

        // Swap icon (bi-eye <-> bi-eye-slash)
        const icon = btn.querySelector('i');
        if (icon) {
          if (isHidden) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
          } else {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
          }
        }
      });
    });
  })();
</script>

@endpush
