{{-- resources/views/profile/_scripts.blade.php --}}
<script>
/* ===================== Avatar crop (shared) ===================== */
const avatarInput    = document.getElementById('avatar-input');
const avatarPreview  = document.getElementById('avatar-preview');
const avatarFilename = document.getElementById('avatar-filename');
const avatarCropped  = document.getElementById('avatar-cropped');

const cropperImg  = document.getElementById('avatar-cropper-image');
const cropSaveBtn = document.getElementById('avatar-crop-save');
const cropModalEl = document.getElementById('avatarCropModal');

let avatarCropper = null;
let modalInstance = null;

if (cropModalEl && typeof bootstrap !== 'undefined') {
  modalInstance = new bootstrap.Modal(cropModalEl, { backdrop: 'static', keyboard: false });
}

avatarInput?.addEventListener('change', function () {
  const file = this.files?.[0];
  if (!file) {
    if (avatarFilename) avatarFilename.textContent = window.PROFILE_I18N?.noFile || 'No file chosen';
    return;
  }

  if (avatarFilename) avatarFilename.textContent = `${file.name} (${Math.round(file.size/1024)} KB)`;

  const reader = new FileReader();
  reader.onload = function (e) {
    cropperImg.src = e.target.result;

    cropperImg.onload = function () {
      if (avatarCropper) { avatarCropper.destroy(); avatarCropper = null; }

      avatarCropper = new Cropper(cropperImg, {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
        dragMode: 'move',
        background: false,
        minCropBoxWidth: 150,
        minCropBoxHeight: 150
      });

      modalInstance?.show();
    };
  };
  reader.readAsDataURL(file);
});

cropSaveBtn?.addEventListener('click', function () {
  if (!avatarCropper) return;

  const canvas = avatarCropper.getCroppedCanvas({
    width: 400,
    height: 400,
    imageSmoothingQuality: 'high'
  });

  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

  if (avatarPreview) avatarPreview.src = dataUrl;
  if (avatarCropped) avatarCropped.value = dataUrl;

  modalInstance?.hide();
  avatarCropper.destroy();
  avatarCropper = null;
});


/* ===================== Tag helpers (shared) ===================== */
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
/* ===================== Gallery preview grid (keep previous + add more) ===================== */

const gInput = document.getElementById('gallery-input');
const gWrap  = document.getElementById('gallery-preview');

if (gInput && gWrap) {
  gInput.addEventListener('change', function () {
    // clear only NEW previews (keep existing from DB)
    [...gWrap.querySelectorAll('.zv-gallery-item[data-new="1"]')].forEach(el => el.remove());

    const files = [...this.files];
    files.forEach(file => {
      const item = document.createElement('div');
      item.className = 'zv-gallery-item';
      item.dataset.new = '1';

      item.innerHTML = `
        <img src="" alt="">
        <div class="zv-gallery-name" title="${file.name}">${file.name}</div>
      `;

      const img = item.querySelector('img');
      const reader = new FileReader();
      reader.onload = e => img.src = e.target.result;
      reader.readAsDataURL(file);

      gWrap.appendChild(item);
    });
  });
}




/* ===================== Country / City / Phone (shared) ===================== */
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

/* ===================== Counters (shared) ===================== */
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

attachCounter('short_bio', 'short-bio-counter', 160);
attachCounter('description', 'description-counter', 600);
</script>
