@extends('layouts.app')
@section('title', ($role ?? request('role')) === 'coach' ? __('Sign Up As a Coach') : __('Sign Up As a Client'))

@section('content')
<div class="container my-4 my-md-5">
  <div class="mx-auto" style="max-width: 720px;">
    {{-- Logo --}}
    <div class="text-center my-3">
      <img class="w-25 h-25" src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS" >
    </div>

    {{-- Title + Toggle --}}
    <div class="text-center mb-3">
    
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
        <form class="search-card" method="POST" action="{{ route('register') }}" novalidate>
          @csrf
          {{-- Hidden role field --}}
          <input type="hidden" name="role" id="roleInput" value="{{ request('role') === 'coach' ? 'coach' : 'client' }}">

          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label small fw-semibold">{{ __('First Name') }}</label>
              <input type="text" name="first_name" class="form-control" placeholder="{{ __('First Name') }}" value="{{ old('first_name') }}">
              <div class="form-text">{{ __('Be Sure This Matches The Name On Your ID!') }}</div>
              @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
              <label class="form-label small fw-semibold">{{ __('Last Name') }}</label>
              <input type="text" name="last_name" class="form-control" placeholder="{{ __('Last Name') }}" value="{{ old('last_name') }}">
              <div class="form-text">{{ __('Be Sure This Matches The Name On Your ID!') }}</div>
              @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('Username') }}</label>
              <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}">
              @error('username')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">{{ __('Date Of Birth') }}</label>
              <input 
    type="date" 
    name="dob" 
    class="form-control"
    value="{{ old('dob') }}"
    max="{{ \Carbon\Carbon::now()->subYears(18)->toDateString() }}"
>

              @error('dob')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">{{ __('Email') }}</label>
              <input type="email" name="email" class="form-control" placeholder="name@example.com" value="{{ old('email') }}">
              @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

           <div class="col-md-6">
  <label class="form-label small fw-semibold">{{ __('Country') }}</label>
  <select class="form-select w-100 js-country" name="country" data-selected="{{ old('country') }}">
    <option value="">Select</option>
  </select>
  @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="col-md-6">
  <label class="form-label small fw-semibold">{{ __('City') }}</label>
  <select class="form-select w-100 js-city" name="city" data-selected="{{ old('city') }}">
    <option value="">Select</option>
  </select>
  @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

              
              <div class="col-md-5">
                
                <label class="form-label small fw-semibold">{{ __('Code') }}</label>
                <div class="input-group">
                {{-- <div class="input-group"> --}}
                  <select class="js-phone" name="phone_code" data-selected="{{ old('phone_code') }}" >
                    <option value="">Select</option>
                  </select>
                </div>
              </div>
                <div class="col-md-7">
                  <label class="form-label small fw-semibold">{{ __('Phone Number') }}</label>
                  <input type="tel" class="form-control" name="phone" placeholder="{{ __('Phone') }}" value="{{ old('phone') }}">
                </div>
                @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

           <div class="col-md-12">
  <label class="form-label small fw-semibold">{{ __('Password') }}</label>
  <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}">

  {{-- Password strength meter --}}
  <div class="mt-2">
    <div class="progress" style="height: 4px;">
      <div id="passwordStrengthBar" class="progress-bar" role="progressbar"
           style="width: 0%;" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <div class="small mt-1" id="passwordStrengthText"></div>
  </div>

  @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>


            <div class="col-md-12">
              <label class="form-label small fw-semibold">{{ __('Confirm Password') }}</label>
              <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Confirm Password') }}">
            </div>

            <div class="col-12 my-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="accept" name="accept" value="1" {{ old('accept') ? 'checked' : '' }}>
                <label class="form-check-label small" for="accept">
                  {{ __('Accept our') }} <a href="#" class="text-decoration-underline">{{ __('Privacy Policy') }}</a>?
                </label>
              </div>
              @error('accept')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
              <button id="signupBtn" class="btn btn-dark w-100 py-2" type="submit" disabled>
    {{ __('Sign Up') }}
</button>

            </div>

            <div class="col-12 text-center small text-muted">
              {{ __('Already Have An Account?') }}
              <a href="{{ route('login') }}">{{ __('Log in') }}</a>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btnClient = document.getElementById('btnClient');
  const btnCoach  = document.getElementById('btnCoach');
  const roleInput = document.getElementById('roleInput');
  const roleTitle = document.getElementById('roleTitle'); // may be null

  if (!btnClient || !btnCoach || !roleInput) return; // ❗ remove roleTitle from here

  function setBtnStyles(active) {
    const set = (btn, on) => {
      btn.classList.toggle('btn-dark', on);
      btn.classList.toggle('btn-outline-dark', !on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    };
    set(btnClient, active === 'client');
    set(btnCoach,  active === 'coach');
  }

  function setActiveRole(role) {
    const r = (role === 'coach') ? 'coach' : 'client';
    roleInput.value = r;

    if (roleTitle) {
      roleTitle.textContent = r === 'coach' ? 'Coach' : 'Client';
    }

    document.title = r === 'coach'
      ? 'Sign up as a Coach'
      : 'Sign up as a Client';

    setBtnStyles(r);
  }

  setActiveRole(roleInput.value || 'client');

  btnClient.addEventListener('click', () => setActiveRole('client'));
  btnCoach .addEventListener('click', () => setActiveRole('coach'));
});

</script>
@endpush

@push('scripts')
<script>
(function(){
const API_COUNTRIES = '{{ route("cc.countries") }}';
const API_CITIES    = '{{ route("cc.cities") }}';
const API_CODES     = '{{ route("cc.codes") }}';

// ---- Generic helpers (same style as hero-search) ----
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
      // e.g. ["Pakistan", "India", ...]
      opt.value = item;
      opt.textContent = item;
      opt.dataset.name = item;
    } else if (item && typeof item === 'object') {
      // matches countries() output: { name: "Austria", code: "AT" }
      const name = item.name || item.code || '';
      const code = item.code || '';

      opt.value = name;         // like hero-search
      opt.textContent = name;
      opt.dataset.name = name;
      if (code) opt.dataset.iso2 = String(code).toUpperCase(); // for phone sync
    }

    frag.appendChild(opt);
  });

  el.innerHTML = '';
  el.appendChild(frag);

  if (preselect) {
    // Try by value first
    el.value = preselect;

    // If no match, try by visible text / data-name
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

// ---- Phone codes (extra for register) ----
let phoneCodesPromise = null; // cache map iso2 -> dial

function loadPhoneCodesOnce(selectEl) {
  if (!phoneCodesPromise) {
    phoneCodesPromise = (async () => {
      const rows = await fetchJSON(API_CODES).catch(e => {
        console.warn('codes fetch failed', e);
        return [];
      });

      // Fill the phone select if present
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

// ---- Init one register form (same behaviour as hero-search + phone) ----
async function initForm(form) {
  const countrySel = form.querySelector('.js-country');
  const citySel    = form.querySelector('.js-city');
  const phoneSel   = form.querySelector('.js-phone');

  if (!countrySel || !citySel) return;

  // 1) Load countries (same style as hero-search)
  setDisabled(countrySel, true);
  const countries = await fetchJSON(API_COUNTRIES).catch(() => []);
  setOptions(
    countrySel,
    countries,
    'Select',
    countrySel.dataset.selected || ''
  );
  setDisabled(countrySel, false);

  // 2) Preload cities (same logic as hero-search)
  const selectedCountryValue = countrySel.value || countrySel.dataset.selected || '';
  if (selectedCountryValue) {
    const opt  = countrySel.options[countrySel.selectedIndex];
    const name = opt?.text || selectedCountryValue; // send name to API
    await loadCities(
      name,
      citySel,
      citySel.dataset.selected || ''
    );
  }

  // 3) Phone codes
  let phoneMap = null;
  if (phoneSel) {
    phoneMap = await loadPhoneCodesOnce(phoneSel);
    const opt = countrySel.options[countrySel.selectedIndex];
    if (opt && opt.dataset.iso2) {
      syncPhoneByIso(phoneMap, phoneSel, opt.dataset.iso2);
    }
  }

  // 4) When country changes → reload cities (hero behaviour) + sync phone
  countrySel.addEventListener('change', async () => {
const opt  = countrySel.options[countrySel.selectedIndex];
const name = opt?.text || countrySel.value;   // this is what hero sends
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

// Handle back/forward cache
window.addEventListener('pageshow', (e) => {
  if (e.persisted) init();
});
})();
</script>



<script>
document.addEventListener('DOMContentLoaded', () => {
  const pwdInput = document.querySelector('input[name="password"]');
  const bar      = document.getElementById('passwordStrengthBar');
  const textEl   = document.getElementById('passwordStrengthText');

  if (!pwdInput || !bar || !textEl) return;

  function scorePassword(pwd) {
    let score = 0;

    if (!pwd) return 0;

    // 1) Length checks
    if (pwd.length >= 8)  score++;  // minimum decent length
    if (pwd.length >= 12) score++;  // extra for long passwords

    // 2) Character variety
    if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++; // both lower + upper
    if (/\d/.test(pwd))                           score++; // numbers
    if (/[^A-Za-z0-9]/.test(pwd))                 score++; // special chars

    // Max score = 5
    return Math.min(score, 5);
  }

  function updateStrength() {
    const val   = pwdInput.value;
    const score = scorePassword(val);

    if (!val) {
      bar.style.width   = '0%';
      bar.className     = 'progress-bar';
      textEl.textContent = '';
      return;
    }

    let percent = 0;
    let label   = 'Very weak';
    let klass   = 'bg-danger';

    switch (score) {
      case 1:
        percent = 20;
        label   = 'Very weak';
        klass   = 'bg-danger';
        break;
      case 2:
        percent = 40;
        label   = 'Weak';
        klass   = 'bg-danger';
        break;
      case 3:
        percent = 60;
        label   = 'Fair';
        klass   = 'bg-warning';
        break;
      case 4:
        percent = 80;
        label   = 'Strong';
        klass   = 'bg-success';
        break;
      case 5:
        percent = 100;
        label   = 'Very strong';
        klass   = 'bg-success';
        break;
    }

    bar.style.width = percent + '%';
    bar.className   = 'progress-bar ' + klass;
    textEl.textContent = label + ' password';
  }

  pwdInput.addEventListener('input', updateStrength);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const accept = document.getElementById('accept');
    const btn    = document.getElementById('signupBtn');

    if (!accept || !btn) return;

    function toggleButton() {
        btn.disabled = !accept.checked;
    }

    // Initial state
    toggleButton();

    // Whenever user checks/unchecks
    accept.addEventListener('change', toggleButton);
});
</script>

@endpush






