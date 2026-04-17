{{-- resources/views/auth/onboarding.blade.php --}}
@extends('layouts.app')
@section('title', __('Complete Your Profile'))

@section('content')
<div class="container my-4 my-md-5">
  <div class="mx-auto" style="max-width: 720px;">
    {{-- Logo --}}
    <div class="text-center my-3">
      <img class="w-25 h-25" src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS">
    </div>

    {{-- Title + Toggle --}}
    <div class="text-center mb-3">
      <h5 class="fw-bold text-capitalize mb-1">{{ __('Complete Your Profile') }}</h5>
      <div class=" small text-muted">{{ __('Sign Up As a') }}
        
      </div>
      <div class="btn-group mt-2 shadow-sm" role="group" aria-label="Role toggle">
        <button type="button" class="btn btn-dark px-4 role-toggle" id="btnClient">
          {{ __('Client') }}
        </button>
        <button type="button" class="btn btn-outline-dark px-4 role-toggle" id="btnCoach">
          {{ __('Coach') }}
        </button>
      </div>
    </div>

    {{-- Card --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3 p-md-4">
        <form class="search-card" method="POST" action="{{ route('onboarding.store') }}" novalidate>
          @csrf

          {{-- Hidden role field --}}
          <input type="hidden" name="role" id="roleInput" value="{{ $role === 'coach' ? 'coach' : 'client' }}">

          <div class="row g-3">
            {{-- First Name --}}
            <div class="col-md-12">
              <label class="form-label small fw-semibold">{{ __('First Name') }}</label>
              <input type="text" name="first_name" class="form-control"
                     placeholder="{{ __('First Name') }}"
                     value="{{ old('first_name', $user->first_name) }}">
              <div class="form-text text-capitalize">{{ __('Be Sure This Matches The Name On Your ID!') }}</div>
              @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Last Name --}}
            <div class="col-md-12">
              <label class="form-label small fw-semibold">{{ __('Last Name') }}</label>
              <input type="text" name="last_name" class="form-control"
                     placeholder="{{ __('Last Name') }}"
                     value="{{ old('last_name', $user->last_name) }}">
              <div class="form-text text-capitalize">{{ __('Be sure this matches the name on your ID!') }}</div>
              @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Username --}}
            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('Username') }}</label>
              <input type="text" name="username" class="form-control"
                     placeholder="{{ __('Username') }}"
                     value="{{ old('username', $user->username) }}">
              @error('username')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Date of Birth --}}
            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('Date of Birth') }}</label>
              <input type="date" name="dob" class="form-control"
                     value="{{ old('dob', $user->dob) }}">
              @error('dob')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Email (from Google) --}}
            <div class="col-12">
              <label class="form-label small fw-semibold">{{ __('Email') }}</label>
              <input type="email" name="email" class="form-control"
                     value="{{ old('email', $user->email) }}"
                     @if($user->google_id) readonly @endif>
              @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Country --}}
            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('Country') }}</label>
              <select class="js-country" name="country"
                      data-selected="{{ old('country', $user->country) }}">
                <option value="">{{ __('Select') }}</option>
              </select>
              @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- City --}}
            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('City') }}</label>
              <select class="js-city" name="city"
                      data-selected="{{ old('city', $user->city) }}">
                <option value="">{{ __('Select') }}</option>
              </select>
              @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Phone code + phone --}}
            <div class="col-md-5">
              <label class="form-label small fw-semibold">{{ __('Code') }}</label>
              <div class="input-group">
                <select class="js-phone" name="phone_code"
                        data-selected="{{ old('phone_code', $user->phone_code) }}">
                  <option value="">{{ __('Select') }}</option>
                </select>
              </div>
            </div>
            <div class="col-md-7">
              <label class="form-label small fw-semibold">{{ __('Phone Number') }}</label>
              <input type="tel" class="form-control" name="phone"
                     placeholder="{{ __('Phone') }}"
                     value="{{ old('phone', $user->phone) }}">
              @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            {{-- Submit --}}
            <div class="col-12">
              <button class="btn btn-dark w-100 py-2" type="submit">
                {{ __('Save & Continue') }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection

{{-- Role toggle script (same logic as register) --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btnClient = document.getElementById('btnClient');
  const btnCoach  = document.getElementById('btnCoach');
  const roleInput = document.getElementById('roleInput');

  if (!btnClient || !btnCoach || !roleInput) return;

  function setBtnStyles(activeRole) {
    // Client active
    btnClient.classList.toggle('btn-dark', activeRole === 'client');
    btnClient.classList.toggle('btn-outline-dark', activeRole !== 'client');

    // Coach active
    btnCoach.classList.toggle('btn-dark', activeRole === 'coach');
    btnCoach.classList.toggle('btn-outline-dark', activeRole !== 'coach');
  }

  function setActiveRole(role) {
    const selectedRole = role === 'coach' ? 'coach' : 'client';
    roleInput.value = selectedRole;
    setBtnStyles(selectedRole);
  }

  // Initialize based on existing hidden input value
  setActiveRole(roleInput.value || 'client');

  // Toggle buttons
  btnClient.addEventListener('click', () => setActiveRole('client'));
  btnCoach.addEventListener('click', () => setActiveRole('coach'));
});
</script>

@endpush

{{-- Country / City / Phone JS (same as your register form) --}}
@push('scripts')
<script>
(function(){
const API_COUNTRIES = '{{ route("cc.countries") }}';
const API_CITIES    = '{{ route("cc.cities") }}';
const API_CODES     = '{{ route("cc.codes") }}';

function refreshNiceSelect(el){ /* no-op (native select) */ }
function setDisabled(el, on){ el.disabled = !!on; refreshNiceSelect(el); }

async function fetchJSON(url, params = {}) {
  const qs   = new URLSearchParams(params).toString();
  const full = qs ? `${url}?${qs}` : url;

  const res  = await fetch(full, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
  const text = await res.text();
  let json   = null;
  try { json = JSON.parse(text); } catch {}

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
      const opts = Array.from(el.options);
      const match = opts.find(o => o.text === preselect || o.dataset.name === preselect);
      if (match) match.selected = true;
    }
  }
}

async function loadCities(countryName, citySel, preselect = '') {
  if (!countryName) {
    setOptions(citySel, [], 'Select a country first');
    setDisabled(citySel, true);
    return;
  }

  setDisabled(citySel, true);
  setOptions(citySel, [], 'Loading...');

  const cities = await fetchJSON(API_CITIES, { country: countryName }).catch(() => []);
  setOptions(citySel, cities, 'Select', preselect);
  setDisabled(citySel, false);
}

let phoneCodesPromise = null;

function loadPhoneCodesOnce(selectEl) {
  if (!phoneCodesPromise) {
    phoneCodesPromise = (async () => {
      const rows = await fetchJSON(API_CODES).catch(e => {
        console.warn('codes fetch failed', e);
        return [];
      });

      if (selectEl) {
        const frag = document.createDocumentFragment();
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = 'Select';
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

        selectEl.innerHTML = '';
        selectEl.appendChild(frag);

        const pre = selectEl.dataset.selected || '';
        if (pre) selectEl.value = pre;
      }

      const map = new Map(
        rows.map(r => [String(r.iso2 || '').toUpperCase(), String(r.code || '')])
      );
      if (map.has('GB')) map.set('UK',  map.get('GB'));
      if (map.has('US')) map.set('USA', map.get('US'));
      if (map.has('AE')) map.set('UAE', map.get('AE'));

      return map;
    })();
  }
  return phoneCodesPromise;
}

function syncPhoneByIso(map, phoneSel, iso2) {
  if (!phoneSel || !iso2) return;
  const dial = map.get(String(iso2).toUpperCase());
  if (!dial) return;

  const prev = phoneSel.value;
  phoneSel.value = dial;
  if (phoneSel.value !== dial) {
    phoneSel.value = prev || '';
  }
}

async function initForm(form) {
  const countrySel = form.querySelector('.js-country');
  const citySel    = form.querySelector('.js-city');
  const phoneSel   = form.querySelector('.js-phone');

  if (!countrySel || !citySel) return;

  setDisabled(countrySel, true);
  const countries = await fetchJSON(API_COUNTRIES).catch(() => []);
  setOptions(
    countrySel,
    countries,
    'Select',
    countrySel.dataset.selected || ''
  );
  setDisabled(countrySel, false);

  const selectedCountryValue = countrySel.value || countrySel.dataset.selected || '';
  if (selectedCountryValue) {
    const opt  = countrySel.options[countrySel.selectedIndex];
    const name = opt?.text || selectedCountryValue;
    await loadCities(
      name,
      citySel,
      citySel.dataset.selected || ''
    );
  }

  let phoneMap = null;
  if (phoneSel) {
    phoneMap = await loadPhoneCodesOnce(phoneSel);
    const opt = countrySel.options[countrySel.selectedIndex];
    if (opt && opt.dataset.iso2) {
      syncPhoneByIso(phoneMap, phoneSel, opt.dataset.iso2);
    }
  }

  countrySel.addEventListener('change', async () => {
    const opt  = countrySel.options[countrySel.selectedIndex];
    const name = opt?.text || countrySel.value;
    await loadCities(name, citySel, '');

    if (phoneMap && phoneSel && opt?.dataset.iso2) {
      syncPhoneByIso(phoneMap, phoneSel, opt.dataset.iso2);
    }
  });
}

function init() {
  document
    .querySelectorAll('form.search-card')
    .forEach(initForm);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}

window.addEventListener('pageshow', (e) => {
  if (e.persisted) init();
});
})();
</script>
@endpush
