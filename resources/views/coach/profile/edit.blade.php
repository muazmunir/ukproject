@extends('coach.layout')

@push('styles')
  {{-- Reuse the same CSS as client (zv-form-card, etc.) --}}
  <link rel="stylesheet" href="{{ asset('assets/css/client-profile.css') }}">
  <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.css">
@endpush

@section('coach-content')

@if(session('ok'))
  <div class="alert alert-success zv-alert">{{ session('ok') }}</div>
@endif

<div class="zv-profile-wrapper">
  <div class="row g-4 flex-lg-nowrap">

    {{-- LEFT: main profile form --}}
    <div class="col-12 col-xl-8">
      <form class="zv-form-card" method="POST" action="{{ route('coach.profile.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="zv-form-header">
          <div>
            <div class="zv-eyebrow">{{ __('Coach profile') }}</div>
            <h6 class="mb-0">{{ __('Edit your profile') }}</h6>
            <small class="text-muted d-block mt-1">
              {{ __('Add service areas, language proficiencies, gallery and qualifications.') }}
            </small>
          </div>
        </div>

        {{-- Avatar --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Profile photo') }}</h6>
            <small class="text-muted">{{ __('This is how clients will see you.') }}</small>
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
                <input type="hidden" name="avatar_cropped" id="avatar-cropped"> {{-- 🔁 NEW --}}
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
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Basic information') }}</h6>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="zv-label">{{ __('First Name') }} *</label>
              <input type="text" name="first_name"
                     class="zv-input @error('first_name') is-invalid @enderror"
                     value="{{ old('first_name',$user->first_name) }}">
              @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="zv-label">{{ __('Last Name') }}</label>
              <input type="text" name="last_name"
                     class="zv-input @error('last_name') is-invalid @enderror"
                     value="{{ old('last_name',$user->last_name) }}">
              @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-12">
              <label class="zv-label">{{ __('Email') }} *</label>
              <input type="email" name="email"
                     class="zv-input zv-input-readonly"
                     value="{{ $user->email }}" readonly>
              <small class="text-muted small">
                {{ __("If you need to change your email, please contact support.") }}
              </small>
            </div>
          </div>
        </section>

        {{-- Contact --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Contact') }}</h6>
          </div>

          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="zv-label">{{ __('Code') }}</label>
              <select name="phone_code"
                      class="zv-input js-phone-profile"
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
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('About you') }}</h6>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="zv-label mb-0">
                  {{ __('Short Bio') }} <small class="text-muted">(max 150)</small>
                </label>
                <small class="zv-counter" id="short-bio-counter">0 / 150</small>
              </div>
              <input id="short_bio" type="text" name="short_bio" maxlength="150"
                     class="zv-input"
                     value="{{ old('short_bio',$user->short_bio) }}">
            </div>

            <div class="col-md-6">
              <label class="zv-label">{{ __('Timezone') }}</label>
              <select name="timezone" class="zv-input zv-input-readonly" disabled>
                <option value="">{{ $user->timezone ?: __('Not set') }}</option>
              </select>
              <small class="text-muted small">
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
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Location') }}</h6>
          </div>

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
            <small class="text-muted">{{ __('Add as many as you want') }}</small>
          </div>

          <div class="zv-lang-wrap">
            <input type="text" id="lang-input" class="zv-input"
                   placeholder="{{ __('Type language & press Add') }}">
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

        {{-- Service Areas --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Service Areas') }}</h6>
          </div>

          <div class="zv-lang-wrap">
            <input type="text" id="area-input" class="zv-input"
                   placeholder="{{ __('Type area & press Add') }}">
            <button type="button" class="btn-3d btn-plain" id="area-add">
              <i class="bi bi-plus-lg me-1"></i>{{ __('Add') }}
            </button>
          </div>

          <div id="area-tags" class="zv-lang-tags">
            @foreach((array) old('service_areas', $user->coach_service_areas ?? []) as $area)
              <span class="zv-tag">
                <span class="zv-tag-text">{{ $area }}</span>
                <button type="button" class="zv-tag-x">&times;</button>
              </span>
              <input type="hidden" name="service_areas[]" value="{{ $area }}">
            @endforeach
          </div>
        </section>

        {{-- Gallery --}}
        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Gallery') }}</h6>
            <small class="text-muted">{{ __('Show relevant images from your work.') }}</small>
          </div>
        
          <label class="btn-3d btn-plain">
            <input type="file" id="gallery-input" name="gallery[]" accept="image/*" multiple hidden>
            <i class="bi bi-images me-1"></i>{{ __('Choose images') }}
          </label>
          @error('gallery.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        
          <div id="gallery-preview" class="zv-gallery-grid mt-2">
            {{-- existing images from DB --}}
            @foreach((array) ($user->coach_gallery ?? []) as $g)
              <div class="zv-gallery-item" data-existing="1" data-path="{{ $g }}">
                <button type="button" class="zv-gallery-remove" aria-label="{{ __('Remove') }}">&times;</button>
                <img src="{{ asset('storage/'.$g) }}" alt="">
                <div class="zv-gallery-name" title="{{ basename($g) }}">{{ basename($g) }}</div>
        
                {{-- keep track of existing items --}}
                <input type="hidden" name="gallery_existing[]" value="{{ $g }}">
              </div>
            @endforeach
          </div>
        </section>
        

        {{-- Qualifications --}}
        <section class="zv-section">
          <div class="zv-section-title d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ __('Qualifications') }}</h6>
            <button type="button" class="btn-3d btn-plain" id="qual-add">
              <i class="bi bi-plus-lg me-1"></i>{{ __('Add Qualification') }}
            </button>
          </div>

          <div id="qual-list" class="zv-quals">
            @php $quals = (array) ($user->coach_qualifications ?? []); @endphp
            @forelse(old('qual_title', array_column($quals,'title')) as $i => $title)
              <div class="zv-qual-row">
                <div><input type="text" class="zv-input" name="qual_title[]" placeholder="{{ __('Title') }}" value="{{ $title }}"></div>
                <div><input type="date" class="zv-input" name="qual_date[]" value="{{ old('qual_date.'.$i, $quals[$i]['achieved_at'] ?? '') }}"></div>
                <div><input type="text" class="zv-input" name="qual_desc[]" placeholder="{{ __('Description') }}" value="{{ old('qual_desc.'.$i, $quals[$i]['description'] ?? '') }}"></div>
                <button type="button" class="zv-qual-remove" aria-label="{{ __('Remove') }}">&times;</button>
              </div>
            @empty
              <div class="zv-qual-row">
                <div><input type="text" class="zv-input" name="qual_title[]" placeholder="{{ __('Title') }}"></div>
                <div><input type="date" class="zv-input" name="qual_date[]"></div>
                <div><input type="text" class="zv-input" name="qual_desc[]" placeholder="{{ __('Description') }}"></div>
                <button type="button" class="zv-qual-remove" aria-label="{{ __('Remove') }}">&times;</button>
              </div>
            @endforelse
          </div>
        </section>

        <section class="zv-section">
          <div class="zv-section-title">
            <h6 class="mb-0">{{ __('Social profiles') }}</h6>
            <small class="text-muted">{{ __('Let clients learn more about you.') }}</small>
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
          <button class="btn-3d btn-dark-elev" type="submit">{{ __('Save Changes') }}</button>
        </div>
      </form>
    </div>

    {{-- RIGHT: password + deactivate --}}
    <div class="col-12 col-xl-4">
      <div class="zv-form-card zv-form-card--side">

        <form method="POST" action="{{ route('coach.profile.password') }}" class="m-0">
          @csrf @method('PUT')

          <div class="zv-form-header">
            <div>
              <div class="zv-eyebrow">{{ __('Security') }}</div>
              <h6 class="mb-0">{{ __('Update password') }}</h6>
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

        <form method="POST"
              action="{{ route('coach.profile.deactivate') }}"
              class="mt-3 m-0"
              onsubmit="return confirm('{{ __('Are you sure you want to deactivate your account?') }}')">
          @csrf @method('DELETE')
          <button class="btn-3d btn-danger-soft w-100" type="submit">
            {{ __('Deactivate Account') }}
          </button>
        </form>

      </div>
    </div>

  </div>
</div>


{{-- Avatar crop modal --}}
<div class="modal fade" id="coachAvatarCropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content zv-cropper-modal">
      <div class="modal-header">
        <h6 class="modal-title mb-0">{{ __('Adjust your photo') }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <div class="zv-cropper-wrap">
          <img id="coach-avatar-cropper-image" src="" alt="Crop preview">
        </div>
        <small class="text-muted d-block mt-2">
          {{ __('Drag to reposition, scroll to zoom in/out.') }}
        </small>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn-3d btn-plain" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn-3d btn-dark-elev" id="coach-avatar-crop-save">
          {{ __('Use this photo') }}
        </button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://unpkg.com/cropperjs@1.5.13/dist/cropper.min.js"></script>
<script>
/* ---------- Avatar preview & filename ---------- */
const coachAvatarInput      = document.getElementById('avatar-input');
  const coachAvatarPreview    = document.getElementById('avatar-preview');
  const coachAvatarFilename   = document.getElementById('avatar-filename');
  const coachAvatarCropped    = document.getElementById('avatar-cropped');
  const coachCropperImg       = document.getElementById('coach-avatar-cropper-image');
  const coachCropSaveBtn      = document.getElementById('coach-avatar-crop-save');
  const coachCropModalEl      = document.getElementById('coachAvatarCropModal');

  let coachAvatarCropper      = null;
  let coachAvatarModalInstance = null;

  if (coachCropModalEl && typeof bootstrap !== 'undefined') {
    coachAvatarModalInstance = new bootstrap.Modal(coachCropModalEl, {
      backdrop: 'static',
      keyboard: false
    });
  }

  coachAvatarInput?.addEventListener('change', function () {
    const file = this.files?.[0];

    if (!file) {
      coachAvatarFilename.textContent = '{{ __("No file chosen") }}';
      return;
    }

    coachAvatarFilename.textContent = `${file.name} (${Math.round(file.size/1024)} KB)`;

    const reader = new FileReader();
    reader.onload = function (e) {
      coachCropperImg.src = e.target.result;

      coachCropperImg.onload = function () {
        if (coachAvatarCropper) {
          coachAvatarCropper.destroy();
          coachAvatarCropper = null;
        }

        coachAvatarCropper = new Cropper(coachCropperImg, {
          aspectRatio: 1,
          viewMode: 1,
          autoCropArea: 1,
          dragMode: 'move',
          background: false,
          minCropBoxWidth: 150,
          minCropBoxHeight: 150
        });

        coachAvatarModalInstance?.show();
      };
    };
    reader.readAsDataURL(file);
  });

  coachCropSaveBtn?.addEventListener('click', function () {
    if (!coachAvatarCropper) return;

    const canvas = coachAvatarCropper.getCroppedCanvas({
      width: 400,
      height: 400,
      imageSmoothingQuality: 'high'
    });

    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

    // preview on page
    coachAvatarPreview.src = dataUrl;

    // send to backend via hidden input
    coachAvatarCropped.value = dataUrl;

    coachAvatarModalInstance?.hide();
    coachAvatarCropper.destroy();
    coachAvatarCropper = null;
  });
/* ---------- Tag makers (languages + service areas) ---------- */
function setupTagField(addBtnId, inputId, wrapId, inputName) {
  const addBtn = document.getElementById(addBtnId);
  const inp    = document.getElementById(inputId);
  const wrap   = document.getElementById(wrapId);

  addBtn?.addEventListener('click', () => {
    const val = (inp.value || '').trim();
    if (!val) return;

    const tag = document.createElement('span');
    tag.className = 'zv-tag';
    tag.innerHTML =
      `<span class="zv-tag-text">${val}</span>` +
      `<button type="button" class="zv-tag-x" aria-label="Remove">&times;</button>`;
    wrap.appendChild(tag);

    const hid = document.createElement('input');
    hid.type = 'hidden';
    hid.name = inputName;
    hid.value = val;
    wrap.appendChild(hid);

    inp.value = '';
  });

  wrap?.addEventListener('click', e => {
    if (e.target.classList.contains('zv-tag-x')) {
      const tag = e.target.closest('.zv-tag');
      const val = tag.querySelector('.zv-tag-text').textContent.trim();
      tag.remove();
      [...wrap.parentElement.querySelectorAll(`input[name="${inputName}"]`)]
        .forEach(h => { if (h.value === val) h.remove(); });
    }
  });
}

setupTagField('lang-add','lang-input','lang-tags','languages[]');
setupTagField('area-add','area-input','area-tags','service_areas[]');

/* ---------- Qualifications repeater ---------- */
const qList = document.getElementById('qual-list');

document.getElementById('qual-add')?.addEventListener('click', () => {
  const row = document.createElement('div');
  row.className = 'zv-qual-row';
  row.innerHTML = `
    <div><input type="text" class="zv-input" name="qual_title[]" placeholder="{{ __('Title') }}"></div>
    <div><input type="date" class="zv-input" name="qual_date[]"></div>
    <div><input type="text" class="zv-input" name="qual_desc[]" placeholder="{{ __('Description') }}"></div>
    <button type="button" class="zv-qual-remove" aria-label="{{ __('Remove') }}">&times;</button>
  `;
  qList.appendChild(row);
});

qList?.addEventListener('click', e => {
  if (e.target.classList.contains('zv-qual-remove')) {
    e.target.parentElement.remove();
  }
});

/* ---------- Gallery preview grid ---------- */

// ===== gallery preview grid with remove =====
/* ---------- Simple Gallery preview grid (no DataTransfer) ---------- */
const gInput = document.getElementById('gallery-input');
const gWrap  = document.getElementById('gallery-preview');

gInput?.addEventListener('change', function () {
  // remove previous NEW previews (keep existing-from-DB items)
  gWrap.querySelectorAll('.zv-gallery-item[data-new="1"]').forEach(el => el.remove());

  [...this.files].forEach(file => {
    const item = document.createElement('div');
    item.className = 'zv-gallery-item';
    item.dataset.new = '1';

    item.innerHTML = `
      <button type="button" class="zv-gallery-remove" aria-label="{{ __('Remove') }}">&times;</button>
      <img src="" alt="">
      <div class="zv-gallery-name" title="${file.name}">${file.name}</div>
    `;

    const img = item.querySelector('img');
    const reader = new FileReader();
    reader.onload = e => img.src = e.target.result;
    reader.readAsDataURL(file);

    gWrap.appendChild(item);
  });

  // IMPORTANT: do NOT touch this.files or this.value here
});

// Click handler for remove buttons
gWrap?.addEventListener('click', function (e) {
  if (!e.target.classList.contains('zv-gallery-remove')) return;

  const item = e.target.closest('.zv-gallery-item');
  if (!item) return;

  const isExisting = item.dataset.existing === '1';

  if (isExisting) {
    const path = item.dataset.path || '';
    if (path) {
      const delInput = document.createElement('input');
      delInput.type  = 'hidden';
      delInput.name  = 'gallery_delete[]';
      delInput.value = path;
      gWrap.appendChild(delInput);
    }
    const existingHidden = item.querySelector('input[name="gallery_existing[]"]');
    if (existingHidden) existingHidden.remove();
    item.remove();
  } else {
    // New uploads: we can only remove the preview.
    // Browser won't let JS remove a single file from <input>.
    // If you really want to clear ALL new files:
    // gInput.value = '';
    item.remove();
  }
});




/* ---------- Country / City / Phone using same API as client ---------- */
(function () {
  const API_COUNTRIES = '{{ route("cc.countries") }}';
  const API_CITIES    = '{{ route("cc.cities") }}';
  const API_CODES     = '{{ route("cc.codes") }}';

  const countrySel = document.querySelector('.js-country-profile');
  const citySel    = document.querySelector('.js-city-profile');
  const phoneSel   = document.querySelector('.js-phone-profile');

  if (!countrySel || !citySel) return;

  function setDisabled(el, on){ if (el) el.disabled = !!on; }

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

  function setOptions(el, items, placeholder, preselect = '') {
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

        opt.value = name;
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

/* ---------- Character counters ---------- */
function attachCounter(fieldId, counterId, maxLen) {
  const field   = document.getElementById(fieldId);
  const counter = document.getElementById(counterId);
  if (!field || !counter) return;

  const update = () => {
    const len = (field.value || '').length;
    counter.textContent = `${len} / ${maxLen}`;
  };

  field.addEventListener('input', update);
  update();
}

attachCounter('short_bio', 'short-bio-counter', 150);
attachCounter('description', 'description-counter', 600);
</script>
@endpush
@endsection
