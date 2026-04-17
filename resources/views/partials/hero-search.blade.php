<link rel="stylesheet" href="{{ asset('assets/css/hero.css') }}">
<section class="zv-hero2">
  <div class="zv-hero2__bg"></div>
  <div class="zv-hero2__overlay"></div>

  <div class="container zv-hero2__content">
    <form class="zv-sbar"
          method="GET"
          action="{{ route('services.index') }}"
          data-loc-url="{{ route('cc.locations.search') }}"
          data-cat-url="{{ route('cc.categories.search') }}"
          autocomplete="off">

      <div class="zv-sbar__wrap">

        {{-- =========================
            TOP MAIN BAR (Desktop pill)
        ========================== --}}
        <div class="zv-sbar__top">
          <div class="zv-pillrow zv-pillrow--top">

            {{-- Activity --}}
          {{-- Activity --}}
<div class="zv-pillseg zv-pillseg--activity">
  <div class="zv-pillseg__icon"><i class="bi bi-search"></i></div>

  <input id="zvActInput" class="zv-pillseg__input" type="text" name="q"
         placeholder="Search Activities" value="{{ request('q') }}" autocomplete="off">

  <span class="zv-trail">
    <img src="assets/Z.png" alt="Z">
  </span>

  <input type="hidden" id="zvCatId" name="category_id" value="{{ request('category_id') }}">
  <div class="zv-actdrop" id="zvActDrop" hidden></div>
</div>



            {{-- Duration --}}
           

            {{-- Dates --}}
           <div class="zv-pillseg zv-pillseg--dates">
  <div class="zv-pillseg__icon"><i class="bi bi-calendar3"></i></div>

  <input id="zvStartDisp" class="zv-pillseg__input" type="text" placeholder="Dates"
         readonly
         value="{{ request('start_date') && request('end_date') ? (request('start_date').' → '.request('end_date')) : '' }}">

  <span class="zv-trail">
    <img src="assets/A.png" alt="A">
  </span>

  <input type="hidden" id="zvStart" name="start_date" value="{{ request('start_date') }}">
  <input type="hidden" id="zvEnd"   name="end_date"   value="{{ request('end_date') }}">
  <input id="zvEndDisp" class="zv-pillseg__ghost" type="text" tabindex="-1" aria-hidden="true">
</div>

           <div class="zv-pillseg zv-pillseg--select">
  <div class="zv-pillseg__icon"><i class="bi bi-clock"></i></div>

  <select class="zv-bctl__select dur" name="duration">
    <option value="">Duration</option>
    <option value="30"  @selected(request('duration')=='30')>30 min</option>
    <option value="60"  @selected(request('duration')=='60')>1 hour</option>
    <option value="90"  @selected(request('duration')=='90')>1.5 hours</option>
    <option value="120" @selected(request('duration')=='120')>2 hours</option>
    <option value="120_plus" @selected(request('duration')=='120_plus')>2+ Hours</option>
  </select>

  <span class="zv-trail">
    <img src="assets/I.png" alt="I">
  </span>
</div>


            {{-- Location --}}
            <div class="zv-pillseg zv-pillseg--loc">
  <div class="zv-pillseg__icon"><i class="bi bi-geo-alt"></i></div>

  <input id="zvLocInput" class="zv-pillseg__input" type="text" name="location"
         placeholder="Location" value="{{ request('location') }}" autocomplete="off">

  <span class="zv-trail">
    <img src="assets/V.png" alt="V">
  </span>

  <input type="hidden" id="zvCity" name="city" value="{{ request('city') }}">
  <input type="hidden" id="zvCountry" name="country" value="{{ request('country') }}">
  <div class="zv-locdrop" id="zvLocDrop" hidden></div>
</div>


            {{-- Desktop Search --}}
            <button type="submit" class="zv-pillseg zv-pillseg--search zv-only-desktop" aria-label="Search">
              <i class="bi bi-search"></i>
              <span>Search</span>
            </button>

          </div>
        </div>

        {{-- =========================
            BOTTOM ROW (Desktop layout unchanged)
        ========================== --}}
        <div class="zv-sbar__bottom zv-sbar__bottom--pro2">

          <div class="zv-bgroup">

            {{-- Environment --}}
           <div class="zv-bctl zv-bctl--select">
  <i class="bi bi-brightness-high"></i>

  <select name="environment" class="zv-bctl__select">
    <option value="" @selected(!request('environment')) disabled>Environment</option>
    <option value="">Any environment</option>
    <option value="Indoor"  @selected(request('environment')=='Indoor')>Indoor</option>
    <option value="Outdoor" @selected(request('environment')=='Outdoor')>Outdoor</option>
    <option value="Hybrid"  @selected(request('environment')=='Hybrid')>Hybrid</option>
    <option value="Online"  @selected(request('environment')=='Online')>Online</option>
  </select>

  <span class="zv-trail">
    <img src="assets/I.png" alt="I">
  </span>
</div>


            {{-- Accessibility --}}
           <div class="zv-bctl zv-bctl--select">
  <i class="bi bi-universal-access"></i>

  <select name="accessibility" class="zv-bctl__select">
    <option value="" @selected(!request('accessibility')) disabled>Accessibility</option>
    <option value="">Any accessibility</option>
    <option value="Wheelchair access" @selected(request('accessibility')=='Wheelchair access')>Wheelchair access</option>
    <option value="Sign language"     @selected(request('accessibility')=='Sign language')>Sign language</option>
    <option value="Visual assistance" @selected(request('accessibility')=='Visual assistance')>Visual assistance</option>
  </select>

  <span class="zv-trail">
    <img src="assets/A.png" alt="A">
  </span>
</div>


            {{-- Disability Friendly --}}
           <div class="zv-bctl zv-bctl--toggle" role="group" aria-label="Disability friendly">
  <i class="bi bi-heart-pulse"></i>
  <span class="zv-bctl__select">Disability Friendly</span>

  <label class="zv-toggle zv-toggle--mini ms-auto">
    <input type="checkbox" name="disability_friendly" value="1" @checked(request('disability_friendly'))>
    <span class="zv-toggle__ui"></span>
  </label>

  <span class="zv-trail">
    <img src="assets/S.png" alt="S">
  </span>
</div>


          </div>

          {{-- Desktop Clear --}}
          <button type="button"
                  class="zv-clearbtn zv-clearbtn--pro2 zv-only-desktop js-zv-clear"
                  id="zvClearBtn">
            Clear <span class="zv-clearbtn__chev">›</span>
          </button>

        </div>

        {{-- ✅ MOBILE ACTIONS (NOW AT VERY BOTTOM) --}}
        <div class="zv-actions zv-only-mobile">
          <button type="submit" class="zv-actionbtn zv-actionbtn--search">
            <i class="bi bi-search"></i>
            <span>Search</span>
          </button>

          <button type="button" class="zv-actionbtn zv-actionbtn--clear js-zv-clear" id="zvClearBtnMobile">
            <span>Clear</span>
            <span class="zv-actionbtn__chev">›</span>
          </button>
        </div>

      </div>
    </form>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const form = document.querySelector('form.zv-sbar');
  const clearBtns = document.querySelectorAll('.js-zv-clear');
    // ✅ put helpers FIRST (before Activity + Location use them)
  const debounce = (fn, ms=220) => {
    let t; return (...args) => { clearTimeout(t); t=setTimeout(()=>fn(...args), ms); };
  };

  function escapeHtml(str){
    return String(str ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }


  // ===== Activity dropdown (categories) =====
const actInput = document.getElementById('zvActInput');
const actDrop  = document.getElementById('zvActDrop');
const catIdH   = document.getElementById('zvCatId');
const catUrl   = form?.dataset?.catUrl || '';

function hideActDrop(){
  if (actDrop) { actDrop.hidden = true; actDrop.innerHTML = ''; }
}
function showActDrop(){ if (actDrop) actDrop.hidden = false; }

function renderCats(items){
  if (!actDrop) return;
  if (!items || !items.length) { hideActDrop(); return; }

  actDrop.innerHTML = items.slice(0, 8).map((x) => `
    <div class="zv-actitem" data-id="${escapeHtml(x.id)}" data-name="${escapeHtml(x.name)}">
      <div class="zv-actitem__ico">
        ${x.icon ? `<img src="${escapeHtml(x.icon)}" alt="">` : `<i class="bi bi-tag"></i>`}
      </div>
      <div class="zv-actitem__txt">
        <strong>${escapeHtml(x.name)}</strong>
      </div>
    </div>
  `).join('');

  showActDrop();
}

async function fetchCats(q){
  if (!catUrl) return [];
  const res = await fetch(`${catUrl}?q=${encodeURIComponent(q)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  if (!res.ok) return [];
  const json = await res.json().catch(() => null);
  return Array.isArray(json?.data) ? json.data : (Array.isArray(json) ? json : []);
}

const onActType = debounce(async () => {
  const q = (actInput?.value || '').trim();
  if (!actInput || q.length < 2) { hideActDrop(); return; }

  // if user types manually, clear selected category
  if (catIdH) catIdH.value = '';

  const items = await fetchCats(q).catch(() => []);
  renderCats(items);
}, 220);

if (actInput && actDrop){
  actInput.addEventListener('input', onActType);
  actInput.addEventListener('focus', onActType);

  actDrop.addEventListener('click', (e) => {
    const item = e.target.closest('.zv-actitem');
    if (!item) return;

    const id   = item.dataset.id || '';
    const name = item.dataset.name || '';

    actInput.value = name;
    if (catIdH) catIdH.value = id;

    hideActDrop();
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('.zv-pillseg--activity')) return;
    hideActDrop();
  });
}


  function clearDatesUI(){
    const sD = document.getElementById('zvStartDisp');
    const sH = document.getElementById('zvStart');
    const eH = document.getElementById('zvEnd');
    const eD = document.getElementById('zvEndDisp');
    if (sD) sD.value = '';
    if (eD) eD.value = '';
    if (sH) sH.value = '';
    if (eH) eH.value = '';
  }

  function hideLocDrop(){
    const locDrop  = document.getElementById('zvLocDrop');
    if (locDrop) { locDrop.hidden = true; locDrop.innerHTML = ''; }
  }

  function clearLocationUI(){
    const loc = document.getElementById('zvLocInput');
    const city = document.getElementById('zvCity');
    const country = document.getElementById('zvCountry');
    if (loc) loc.value = '';
    if (city) city.value = '';
    if (country) country.value = '';
    hideLocDrop();
  }

  // ✅ Clear works for BOTH desktop + mobile buttons
  if (form && clearBtns.length){
    clearBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        form.reset();
        clearDatesUI();
        clearLocationUI();
        if (window._zvFp) window._zvFp.clear();
      });
    });
  }

  // ===== Dates (range) =====
  if (window.flatpickr) {
    const startDisp = document.getElementById('zvStartDisp');
    const endDisp   = document.getElementById('zvEndDisp');
    const startH    = document.getElementById('zvStart');
    const endH      = document.getElementById('zvEnd');

    if (startDisp && endDisp && startH && endH) {
      const rp = window.rangePlugin || (window.flatpickr && window.flatpickr.rangePlugin);

     const isMobile = window.matchMedia("(max-width: 576px)").matches;

const anchor = document.querySelector('.zv-sbar__top'); // whole top pill row area

window._zvFp = flatpickr(startDisp, {
  mode: "range",
  minDate: "today",
  dateFormat: "Y-m-d",
  disableMobile: true,
  showMonths: isMobile ? 1 : 2,
  position: "below",
  positionElement: anchor, // ✅ anchor popup to the full top bar
  plugins: rp ? [new rp({ input: endDisp })] : [],
  onChange: (dates) => { 


    const s = dates?.[0] ? dates[0].toISOString().slice(0,10) : '';
    const e = dates?.[1] ? dates[1].toISOString().slice(0,10) : '';
    startH.value = s;
    endH.value = e;
    startDisp.value = (s && e) ? `${s} → ${e}` : (s || '');
    endDisp.value = e;
  }
});

      startDisp.addEventListener('click', () => window._zvFp.open());
    }
  }

  // ===== Location dropdown =====
  const locInput = document.getElementById('zvLocInput');
  const locDrop  = document.getElementById('zvLocDrop');
  const cityH    = document.getElementById('zvCity');
  const countryH = document.getElementById('zvCountry');
  const locUrl = form?.dataset?.locUrl || '';

 

  function showLocDrop(){ if (locDrop) locDrop.hidden = false; }

  
  function clearActivityUI(){
  const act = document.getElementById('zvActInput');
  const cat = document.getElementById('zvCatId');
  const drop = document.getElementById('zvActDrop');
  if (act) act.value = '';
  if (cat) cat.value = '';
  if (drop) { drop.hidden = true; drop.innerHTML = ''; }
}

  function renderLoc(items){
  if (!locDrop) return;
  if (!items || !items.length) { hideLocDrop(); return; }

  locDrop.innerHTML = items.slice(0, 8).map((x) => `
    <div class="zv-locitem" data-city="${escapeHtml(x.city)}" data-country="${escapeHtml(x.country)}">
      <div class="zv-locitem__ico"><i class="bi bi-geo-alt"></i></div>
      <div class="zv-locitem__txt">
        <strong>${escapeHtml(x.city || x.country)}</strong>
        ${x.city ? `<small>${escapeHtml(x.country)}</small>` : ``}
      </div>
    </div>
  `).join('');

  showLocDrop();
}

  async function fetchLoc(q){
    if (!locUrl) return [];
    const res = await fetch(`${locUrl}?q=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!res.ok) return [];
    const json = await res.json().catch(() => null);
    return Array.isArray(json) ? json : (json?.data || []);
  }

  const onLocType = debounce(async () => {
    const q = (locInput?.value || '').trim();
    if (!locInput || q.length < 2) { hideLocDrop(); return; }

    if (cityH) cityH.value = '';
    if (countryH) countryH.value = '';

    const items = await fetchLoc(q).catch(() => []);
    renderLoc(items);
  }, 220);

  if (locInput && locDrop){
    locInput.addEventListener('input', onLocType);
    locInput.addEventListener('focus', onLocType);

    locDrop.addEventListener('click', (e) => {
      const item = e.target.closest('.zv-locitem');
      if (!item) return;

      const city = item.dataset.city || '';
      const country = item.dataset.country || '';

      locInput.value = city ? `${city}, ${country}` : `${country}`;
      if (cityH) cityH.value = city;
      if (countryH) countryH.value = country;

      hideLocDrop();
    });

    document.addEventListener('click', (e) => {
      if (e.target.closest('.zv-pillseg--loc')) return;
      hideLocDrop();
    });

    if (cityH?.value && countryH?.value && !locInput.value) {
      locInput.value = `${cityH.value}, ${countryH.value}`;
    }
  }
});
</script>
