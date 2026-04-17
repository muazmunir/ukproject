@extends('layouts.role-dashboard')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/coach-service-wizard.css') }}">
@endpush

@section('role-content')


<div class="zv-wizard mb-3">
  <div class="container-narrow">
    <div class="zv-steps">
      <div class="zv-step is-current" data-step="1">
        <div class="zv-step-dot">1</div>
        <div class="zv-step-label">{{ __('Basics') }}</div>
      </div>
      <div class="zv-step is-upcoming" data-step="2">
        <div class="zv-step-dot">2</div>
        <div class="zv-step-label">{{ __('Media') }}</div>
      </div>
      <div class="zv-step is-upcoming" data-step="3">
        <div class="zv-step-dot">3</div>
        <div class="zv-step-label">{{ __('Details') }}</div>
      </div>
      <div class="zv-step is-upcoming" data-step="4">
        <div class="zv-step-dot">4</div>
        <div class="zv-step-label">{{ __('Packages & FAQ') }}</div>
      </div>
      <div class="zv-step is-upcoming" data-step="5">
        <div class="zv-step-dot">5</div>
        <div class="zv-step-label">{{ __('Review & Submit') }}</div>
      </div>
    </div>
    <div class="zv-progress">
      <div id="zvProgressBar"></div>
    </div>
  </div>
</div>

<form method="POST"
      action="{{ route('coach.services.store') }}"
      enctype="multipart/form-data"
      class="zv-form-card container-narrow"
      id="serviceWizardForm">
  @csrf

  <div class="zv-form-header mb-3">
    <div>
      <h5 class="mb-1">{{ __('Add a Service') }}</h5>
      <small class="text-muted text-capitalize">{{ __('Move step-by-step. You can go back anytime.') }}</small>
    </div>
    <a href="{{ route('coach.services.index') }}" class="btn-3d btn-plain">
      {{ __('Back To Services') }}
    </a>
  </div>

  {{-- STEP 1: BASICS --}}
  <section class="zv-step-pane is-active" data-step="1">
    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <label class="zv-label">{{ __('Service Title') }} *</label>
        <input data-required
               type="text"
               name="title"
               class="zv-input @error('title') is-invalid @enderror"
               value="{{ old('title') }}"
               placeholder="{{ __('Write a Title') }}">
        @error('title') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

        <label class="zv-label mt-3">{{ __('Service Description') }} *</label>
        <textarea data-required
                  name="description"
                  rows="5"
                  class="zv-input @error('description') is-invalid @enderror"
                  placeholder="{{ __('Describe Your Service') }}">{{ old('description') }}</textarea>
        @error('description') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="zv-label">{{ __('Select Category') }} *</label>
            <select data-required
                    name="category_id"
                    class="zv-input @error('category_id') is-invalid @enderror">
              <option value="">{{ __('- Select Category -') }}</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id')==$cat->id)>
                  {{ $cat->name }}
                </option>
              @endforeach
            </select>
            @error('category_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="zv-label">{{ __('Service Level') }} *</label>
            <select data-required
                    name="service_level"
                    class="zv-input @error('service_level') is-invalid @enderror">
              @foreach($levels as $lv)
                <option value="{{ $lv }}" @selected(old('service_level','beginner')==$lv)>
                  {{ ucfirst($lv) }}
                </option>
              @endforeach
            </select>
            @error('service_level') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- STEP 2: MEDIA --}}
  <section class="zv-step-pane" data-step="2">
    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <label class="zv-label">{{ __('Thumbnail') }}</label>
        <input type="file" name="thumbnail" accept="image/*" class="form-control">
        <small class="zv-muted d-block text-capitalize">
          {{ __('Minimum 600×400px, JPG or PNG. This is what clients see first.') }}
        </small>
        @error('thumbnail') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

        <label class="zv-label mt-3">{{ __('Gallery Images') }}</label>
        <input type="file" name="images[]" accept="image/*" class="form-control" multiple>
        <small class="zv-muted d-block text-capitalize">
          {{ __('Up to 10 images recommended to showcase your work.') }}
        </small>
        @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
      </div>
    </div>
  </section>

  {{-- STEP 3: DETAILS --}}
  <section class="zv-step-pane" data-step="3">
    <div class="row g-3">
      <div class="col-12 col-xl-8">
        {{-- Environment --}}
        <div class="mt-1">
          <label class="zv-label">{{ __('Environment') }} *</label>
          <div class="zv-pill-grid" data-required-group="environments[]">
            @foreach($envOpts as $opt)
              <label class="zv-pill-check">
                <input type="checkbox"
                       name="environments[]"
                       value="{{ $opt }}"
                       @checked(collect(old('environments',[]))->contains($opt))>
                <span>{{ $opt }}</span>
              </label>
            @endforeach
          </div>
          <textarea name="environment_other"
                    rows="2"
                    class="zv-input mt-2"
                    placeholder="{{ __('Other Environments (Optional)') }}">{{ old('environment_other') }}</textarea>
          @error('environments') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>

        {{-- Accessibility --}}
        <div class="mt-3">
          <label class="zv-label">{{ __('Accessibility') }} *</label>
          <div class="zv-pill-grid" data-required-group="accessibility[]">
            @foreach($accOpts as $opt)
              <label class="zv-pill-check">
                <input type="checkbox"
                       name="accessibility[]"
                       value="{{ $opt }}"
                       @checked(collect(old('accessibility',[]))->contains($opt))>
                <span>{{ $opt }}</span>
              </label>
            @endforeach
          </div>
          <textarea name="accessibility_other"
                    rows="2"
                    class="zv-input mt-2"
                    placeholder="{{ __('Other Accessibility Notes (Optional)') }}">{{ old('accessibility_other') }}</textarea>
          @error('accessibility') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>

        {{-- Disability --}}
        <div class="mt-3">
          <label class="zv-label d-block">{{ __('Disability') }} *</label>
          <div class="zv-radio-row" data-required-group="disability_accessible">
            <label class="form-check zv-radio-pill">
              <input class="form-check-input"
                     type="radio"
                     name="disability_accessible"
                     value="1"
                     @checked(old('disability_accessible','1')=='1')>
              <span>{{ __('Accessible') }}</span>
            </label>
            <label class="form-check zv-radio-pill">
              <input class="form-check-input"
                     type="radio"
                     name="disability_accessible"
                     value="0"
                     @checked(old('disability_accessible')==='0')>
              <span>{{ __('Not Accessible') }}</span>
            </label>
          </div>
          @error('disability_accessible') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
      </div>
    </div>
  </section>

  {{-- STEP 4: PACKAGES & FAQ --}}
  <section class="zv-step-pane" data-step="4">
    <div class="row g-3">
      {{-- Packages --}}
      <div class="col-12 col-xl-6">
        <div class="zv-panel">
          <div class="zv-panel-header">
            <h6 class="mb-0">{{ __('Packages') }}</h6>
            <small class="zv-muted text-capitalize">{{ __('Create time-based pricing clients can choose from.') }}</small>
          </div>

          <div id="pkg-list" class="zv-panel-body">
            <div class="pkg-row border rounded-3 p-2 mb-2">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <input class="zv-input flex-grow-1 me-2"
                       name="pkg_name[]"
                       placeholder="{{ __('Package Name (e.g. 4-Week Program)') }}">
                <button type="button"
                        class="btn btn-sm btn-outline-danger pkg-remove d-none">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>

              <div class="row g-2 mb-2">
                <div class="col-6">
                  <input class="zv-input js-hours-day"
                         type="number"
                         step="0.25"
                         min="0"
                         name="pkg_hours_day[]"
                         placeholder="{{ __('Hours / Day') }}"
                         inputmode="decimal">
                </div>
                <div class="col-6">
                  <input class="zv-input js-days"
                         type="number"
                         step="1"
                         min="0"
                         name="pkg_days[]"
                         placeholder="{{ __('Total Days') }}"
                         inputmode="numeric">
                </div>
                <div class="col-6">
                  <input class="zv-input js-hours-total"
                         type="number"
                         step="0.25"
                         min="0"
                         name="pkg_hours_total[]"
                         placeholder="{{ __('Total Hours') }}"
                         readonly>
                </div>
                <div class="col-6">
                  <input class="zv-input js-rate-hour"
                         type="number"
                         step="0.01"
                         min="0"
                         name="pkg_rate_hour[]"
                         placeholder="{{ __('Hourly Rate') }}"
                         inputmode="decimal">
                </div>
                <div class="col-12">
                  <input class="zv-input js-total-price"
                         type="number"
                         step="0.01"
                         min="0"
                         name="pkg_total[]"
                         placeholder="{{ __('Total Price') }}"
                         readonly>
                </div>
              </div>

              <input class="zv-input mb-2"
                     name="pkg_equipment[]"
                     placeholder="{{ __('Equipment (Optional)') }}">
              <textarea class="zv-input"
                        rows="2"
                        name="pkg_desc[]"
                        placeholder="{{ __('Package Description') }}"></textarea>
            </div>
          </div>

          <div class="zv-panel-footer">
            <button type="button" class="btn-3d btn-plain w-100" id="pkg-add">
              <i class="bi bi-plus-lg me-1"></i>{{ __('Add Package') }}
            </button>
          </div>
        </div>
      </div>

      {{-- FAQ --}}
      <div class="col-12 col-xl-6">
        <div class="zv-panel">
          <div class="zv-panel-header">
            <h6 class="mb-0">{{ __('FAQ') }}</h6>
            <small class="zv-muted text-capitalize">{{ __('Answer common questions upfront.') }}</small>
          </div>

          <div id="faq-list" class="zv-panel-body">
            <div class="faq-row border rounded-3 p-2 mb-2">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <input class="zv-input flex-grow-1 me-2"
                       name="faq_q[]"
                       placeholder="{{ __('Question') }}">
                <button type="button"
                        class="btn btn-sm btn-outline-danger faq-remove d-none">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
              <textarea class="zv-input"
                        rows="2"
                        name="faq_a[]"
                        placeholder="{{ __('Answer') }}"></textarea>
            </div>
          </div>

          <div class="zv-panel-footer">
            <button type="button" class="btn-3d btn-plain w-100" id="faq-add">
              <i class="bi bi-plus-lg me-1"></i>{{ __('Add FAQ') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- STEP 5: REVIEW --}}
  <section class="zv-step-pane" data-step="5">
    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <div class="zv-panel">
          <div class="zv-panel-header">
            <h6 class="mb-1">{{ __('Quick Checklist') }}</h6>
            <small class="zv-muted text-capitalize">
              {{ __('Make sure everything looks good before you submit.') }}
            </small>
          </div>
          <div class="zv-panel-body">
            <ul class="mb-0 zv-checklist text-capitalize">
              <li>{{ __('Title & description are filled.') }}</li>
              <li>{{ __('Category and level are chosen.') }}</li>
              <li>{{ __('At least one environment & accessibility option selected.') }}</li>
              <li>{{ __('Media added (optional but highly recommended).') }}</li>
              <li>{{ __('At least one package configured with price (recommended).') }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- NAV actions --}}
  <div class="zv-actions mt-4">
    <div class="d-flex justify-content-between align-items-center">
      <button type="button" class="btn btn-light" id="zvPrev">{{ __('Back') }}</button>
      <div class="d-flex gap-2">
        <button type="button" class="btn-3d btn-dark-elev" id="zvNext">{{ __('Next') }}</button>
        <button type="submit" class="btn-3d btn-dark-elev d-none" id="zvSubmit">
          {{ __('Submit') }}
        </button>
      </div>
    </div>
  </div>
</form>

@push('scripts')
<script>
(function () {
  const form    = document.getElementById('serviceWizardForm');
  const panes   = Array.from(document.querySelectorAll('.zv-step-pane'));
  const steps   = Array.from(document.querySelectorAll('.zv-step'));
  const total   = panes.length;
  const btnPrev = document.getElementById('zvPrev');
  const btnNext = document.getElementById('zvNext');
  const btnSub  = document.getElementById('zvSubmit');
  const bar     = document.getElementById('zvProgressBar');

  let current = 1;

  function scrollToTop() {
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function setProgress() {
    const pct = total > 1 ? ((current - 1) / (total - 1)) * 100 : 0;
    bar.style.width = pct + '%';
  }

  function updateSteps() {
    steps.forEach(step => {
      const n = Number(step.dataset.step);
      step.classList.remove('is-current', 'is-upcoming', 'is-done');
      step.removeAttribute('aria-current');

      if (n < current) {
        step.classList.add('is-done');
      } else if (n === current) {
        step.classList.add('is-current');
        step.setAttribute('aria-current', 'step');
      } else {
        step.classList.add('is-upcoming');
      }
    });
  }

  function showStep(n) {
    current = Math.min(Math.max(1, n), total);
    panes.forEach(pane => {
      pane.classList.toggle('is-active', Number(pane.dataset.step) === current);
    });

    btnPrev.disabled = current === 1;
    btnNext.classList.toggle('d-none', current === total);
    btnSub.classList.toggle('d-none', current !== total);

    updateSteps();
    setProgress();
    scrollToTop();
  }

  function validateStep(n) {
    const pane = panes.find(p => Number(p.dataset.step) === n);
    if (!pane) return true;

    let ok = true;
    pane.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

    // simple required fields
    pane.querySelectorAll('[data-required]').forEach(el => {
      if (!el.value || !String(el.value).trim()) {
        el.classList.add('is-invalid');
        ok = false;
      }
    });

    // required groups (at least one checked/filled)
    pane.querySelectorAll('[data-required-group]').forEach(group => {
      const name   = group.getAttribute('data-required-group');
      const inputs = document.querySelectorAll(`[name="${name}"]`);
      const any    = Array.from(inputs).some(i =>
        (i.type === 'checkbox' || i.type === 'radio') ? i.checked : !!i.value.trim()
      );
      if (!any) {
        const first = group.querySelector('input');
        if (first) first.classList.add('is-invalid');
        ok = false;
      }
    });

    return ok;
  }

  btnNext.addEventListener('click', () => {
    if (!validateStep(current)) return;
    showStep(current + 1);
  });

  btnPrev.addEventListener('click', () => {
    showStep(current - 1);
  });

  // allow clicking steps to jump
  steps.forEach(step => {
    step.addEventListener('click', () => {
      const target = Number(step.dataset.step);
      if (target > current && !validateStep(current)) return;
      showStep(target);
    });
  });

  // if server returned errors, go to that step
  window.addEventListener('load', () => {
    const invalid = document.querySelector('.is-invalid');
    if (invalid) {
      const owner = invalid.closest('.zv-step-pane');
      if (owner?.dataset.step) {
        current = Number(owner.dataset.step);
      }
    }
    showStep(current);
  });

  /** ---------- Packages & FAQ ---------- */
  function toNum(v) {
    const n = parseFloat(String(v).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  }
  function round2(n) {
    return Math.round((n + Number.EPSILON) * 100) / 100;
  }

  // --- Packages ---
  const pkgList     = document.getElementById('pkg-list');
  const pkgAddBtn   = document.getElementById('pkg-add');
  const pkgTemplate = pkgList ? pkgList.querySelector('.pkg-row') : null;

  function computePkg(row) {
    const hoursDay = toNum(row.querySelector('.js-hours-day')?.value);
    const days     = toNum(row.querySelector('.js-days')?.value);
    const rateHour = toNum(row.querySelector('.js-rate-hour')?.value);

    const totalHours = round2(hoursDay * days);
    const totalPrice = round2(totalHours * rateHour);

    const hoursTotalEl = row.querySelector('.js-hours-total');
    const totalPriceEl = row.querySelector('.js-total-price');

    if (hoursTotalEl) hoursTotalEl.value = totalHours || '';
    if (totalPriceEl) totalPriceEl.value = totalPrice || '';
  }

  function bindPkgRow(row) {
    ['.js-hours-day', '.js-days', '.js-rate-hour'].forEach(sel => {
      const el = row.querySelector(sel);
      if (!el) return;
      el.addEventListener('input', () => computePkg(row));
      el.addEventListener('change', () => computePkg(row));
    });
    computePkg(row);
  }

  function createPkgRow() {
    const clone = pkgTemplate.cloneNode(true);
    clone.querySelectorAll('input, textarea').forEach(el => {
      if (!el.readOnly) el.value = '';
      if (el.readOnly) el.value = '';
    });
    const rm = clone.querySelector('.pkg-remove');
    if (rm) rm.classList.remove('d-none');
    bindPkgRow(clone);
    return clone;
  }

  if (pkgTemplate) {
    bindPkgRow(pkgTemplate);

    pkgAddBtn?.addEventListener('click', () => {
      pkgList.appendChild(createPkgRow());
    });

    pkgList.addEventListener('click', e => {
      const btn = e.target.closest('.pkg-remove');
      if (!btn) return;
      const rows = pkgList.querySelectorAll('.pkg-row');
      if (rows.length <= 1) return; // keep at least one
      btn.closest('.pkg-row')?.remove();
    });
  }

  // --- FAQ ---
  const faqList     = document.getElementById('faq-list');
  const faqAddBtn   = document.getElementById('faq-add');
  const faqTemplate = faqList ? faqList.querySelector('.faq-row') : null;

  function createFaqRow() {
    const clone = faqTemplate.cloneNode(true);
    clone.querySelectorAll('input, textarea').forEach(el => el.value = '');
    const rm = clone.querySelector('.faq-remove');
    if (rm) rm.classList.remove('d-none');
    return clone;
  }

  if (faqTemplate) {
    faqAddBtn?.addEventListener('click', () => {
      faqList.appendChild(createFaqRow());
    });

    faqList.addEventListener('click', e => {
      const btn = e.target.closest('.faq-remove');
      if (!btn) return;
      const rows = faqList.querySelectorAll('.faq-row');
      if (rows.length <= 1) return;
      btn.closest('.faq-row')?.remove();
    });
  }
})();
</script>
@endpush
@endsection
