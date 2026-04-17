@extends('layouts.role-dashboard')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/coach-services-edit.css') }}">
@endpush
@section('role-content')


<form id="serviceEditForm" method="POST" action="{{ route('coach.services.update', $service) }}" enctype="multipart/form-data" class="zv-form-card">
  @csrf @method('PUT')

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div>
      <h5 class="mb-0">{{ __('Edit Service') }}</h5>
      <small class="text-muted text-capitalize">{{ __('Update info and packages/FAQ.') }}</small>
    </div>
    <a href="{{ route('coach.services.index') }}" class="btn btn-light">{{ __('Back') }}</a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-8">
      <label class="zv-label">{{ __('Service Title') }} *</label>
      <input type="text" name="title" class="zv-input @error('title') is-invalid @enderror" value="{{ old('title',$service->title) }}">
      @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror

      <label class="zv-label mt-2">{{ __('Service Description') }} *</label>
      <textarea name="description" rows="5" class="zv-input @error('description') is-invalid @enderror">{{ old('description',$service->description) }}</textarea>
      @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror

      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="zv-label">{{ __('Select Category') }} *</label>
          <select name="category_id" class="zv-input @error('category_id') is-invalid @enderror">
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" @selected(old('category_id',$service->category_id)==$cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
          @error('category_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="zv-label">{{ __('Service Level') }} *</label>
          <select name="service_level" class="zv-input">
            @foreach($levels as $lv)
              <option value="{{ $lv }}" @selected(old('service_level',$service->service_level)===$lv)>{{ ucfirst($lv) }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="zv-label">{{ __('Change Thumbnail') }}</label>
        
          <input type="file" name="thumbnail" accept="image/*" class="form-control">
        
          @if($service->thumbnail_path)
            <div class="zv-thumb-preview mt-2">
              <div class="thumb-box"
                   style="background-image: url('{{ asset('storage/'.$service->thumbnail_path) }}');">
              </div>
              <small class="text-muted mt-1 d-block">{{ __('Current Thumbnail') }}</small>
            </div>
          @endif
        
          @error('thumbnail')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
        
        <div class="col-md-6">
          <label class="zv-label">{{ __('Add Images') }}</label>
          <input type="file" name="images[]" accept="image/*" class="form-control" multiple>
          @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
      </div>

      @if($service->images)
      <div class="mt-3">
        <label class="zv-label text-capitalize">{{ __('Existing Gallery (select to remove)') }}</label>
        <div class="row g-2">
          @foreach($service->images as $img)
            <div class="col-6 col-md-4">
              <div class="border rounded-3 p-2 h-100">
                <div class="ratio ratio-4x3 rounded" style="background:#f3f3f3 url('{{ asset('storage/'.$img) }}') center/cover no-repeat;"></div>
                <label class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" name="remove_images[]" value="{{ $img }}">
                  <span class="small text-capitalize">{{ __('Remove this image') }}</span>
                </label>
              </div>
            </div>
          @endforeach
        </div>
      </div>
      @endif

      <div class="mt-3">
        <label class="zv-label">{{ __('Environment') }} *</label>
        <div class="row row-cols-1 row-cols-md-2 g-2">
          @foreach($envOpts as $opt)
            <label class="form-check ">
              <input type="checkbox" name="environments[]" value="{{ $opt }}" class="form-check-input me-2"
                @checked(collect(old('environments',$service->environments ?? []))->contains($opt))>
              <span>{{ $opt }}</span>
            </label>
          @endforeach
        </div>
        <textarea name="environment_other" rows="2" class="zv-input mt-2 text-capitalize" placeholder="{{ __('Other (optional)') }}">{{ old('environment_other',$service->environment_other) }}</textarea>
      </div>

      <div class="mt-3">
        <label class="zv-label">{{ __('Accessibility') }} *</label>
        <div class="row row-cols-1 row-cols-md-2 g-2">
          @foreach($accOpts as $opt)
            <label class="form-check ">
              <input type="checkbox" name="accessibility[]" value="{{ $opt }}" class="form-check-input me-2"
                @checked(collect(old('accessibility',$service->accessibility ?? []))->contains($opt))>
              <span>{{ $opt }}</span>
            </label>
          @endforeach
        </div>
        <textarea name="accessibility_other" rows="2" class="zv-input mt-2" placeholder="{{ __('Other (optional)') }}">{{ old('accessibility_other',$service->accessibility_other) }}</textarea>
      </div>

      <div class="mt-3">
        <label class="zv-label d-block">{{ __('Disability') }} *</label>
        <div class="d-flex gap-3">
          <label class="form-check">
            <input class="form-check-input" type="radio" name="disability_accessible" value="1"
              @checked(old('disability_accessible', (int)$service->disability_accessible) == 1)>
            <span>{{ __('Accessible') }}</span>
          </label>
          <label class="form-check">
            <input class="form-check-input" type="radio" name="disability_accessible" value="0"
              @checked(old('disability_accessible', (int)$service->disability_accessible) == 0)>
            <span>{{ __('Not Accessible') }}</span>
          </label>
        </div>
      </div>
    </div>

    {{-- Right column --}}
    <div class="col-12 col-xl-4">
      <div class="zv-panel p-3 rounded-4 shadow-sm">
        <h6 class="mb-2">{{ __('Packages') }}</h6>
        <div id="pkg-list">
          @php
            $p = old('pkg_name', $service->packages->pluck('name')->toArray() ?: ['']);
          @endphp
          @foreach($p as $i => $name)
            <div class="pkg-row border rounded-3 p-2 mb-2">
              <input type="hidden" name="pkg_id[]" value="{{ old("pkg_id.$i", optional($service->packages[$i] ?? null)->id) }}">

              <input class="zv-input mb-2" name="pkg_name[]" placeholder="{{ __('Package Name') }}" value="{{ $name }}">
            {{-- Numbers (with labels) --}}
<div class="row g-2 mb-2">

  {{-- Label row --}}
  <div class="col-6">
    <label class="zv-label mb-1">{{ __('Hours / Day') }}</label>
  </div>
  <div class="col-6">
    <label class="zv-label mb-1">{{ __('Total Days') }}</label>
  </div>

  {{-- Inputs row --}}
  <div class="col-6">
    <input class="zv-input js-hours-day"
           type="number" step="0.25" min="0"
           name="pkg_hours_day[]"
           placeholder="{{ __('e.g. 1.5') }}"
           value="{{ old("pkg_hours_day.$i", optional($service->packages[$i] ?? null)->hours_per_day) }}">
  </div>
  <div class="col-6">
    <input class="zv-input js-days"
           type="number" step="1" min="0"
           name="pkg_days[]"
           placeholder="{{ __('e.g. 10') }}"
           value="{{ old("pkg_days.$i", optional($service->packages[$i] ?? null)->total_days) }}">
  </div>

  {{-- Label row --}}
  <div class="col-6">
    <label class="zv-label mb-1">{{ __('Total Hours') }}</label>
  </div>
  <div class="col-6">
    <label class="zv-label mb-1">{{ __('Hourly Rate') }}</label>
  </div>

  {{-- Inputs row --}}
  <div class="col-6">
    <input class="zv-input js-hours-total"
           type="number" step="0.25" min="0"
           name="pkg_hours_total[]"
           placeholder="{{ __('Auto') }}"
           value="{{ old("pkg_hours_total.$i", optional($service->packages[$i] ?? null)->total_hours) }}"
           readonly>
  </div>
  <div class="col-6">
    <input class="zv-input js-rate-hour"
           type="number" step="0.01" min="0"
           name="pkg_rate_hour[]"
           placeholder="{{ __('e.g. 25') }}"
           value="{{ old("pkg_rate_hour.$i", optional($service->packages[$i] ?? null)->hourly_rate) }}">
  </div>

  {{-- Total price label + input --}}
  <div class="col-12">
    <label class="zv-label mb-1">{{ __('Total Price') }}</label>
    <input class="zv-input js-total-price"
           type="number" step="0.01" min="0"
           name="pkg_total[]"
           placeholder="{{ __('Auto') }}"
           value="{{ old("pkg_total.$i", optional($service->packages[$i] ?? null)->total_price) }}"
           readonly>
  </div>

</div>


              <input class="zv-input mb-2" name="pkg_equipment[]" placeholder="{{ __('Add Equipments') }}" value="{{ old("pkg_equipment.$i", optional($service->packages[$i] ?? null)->equipments) }}">
              <textarea class="zv-input" rows="2" name="pkg_desc[]" placeholder="{{ __('Package Description') }}">{{ old("pkg_desc.$i", optional($service->packages[$i] ?? null)->description) }}</textarea>

              @if($loop->last)
                <button type="button" class="btn btn-sm btn-light mt-2 w-100" id="pkg-add">
                  <i class="bi bi-plus-lg me-1"></i>{{ __('Add More Package') }}
                </button>
              @else
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100 pkg-remove">
                  <i class="bi bi-x-lg me-1"></i>{{ __('Remove') }}
                </button>
              @endif
            </div>
          @endforeach
        </div>
      </div>

      <div class="zv-panel p-3 rounded-4 shadow-sm mt-3">
        <h6 class="mb-2">{{ __('FAQs') }}</h6>
        <div id="faq-list">
          @php
            $fq = old('faq_q', $service->faqs->pluck('question')->toArray() ?: ['']);
          @endphp
          @foreach($fq as $i => $q)
            <div class="faq-row border rounded-3 p-2 mb-2">
              <input class="zv-input mb-2" name="faq_q[]" placeholder="{{ __('Write Question') }}" value="{{ $q }}">
              <textarea class="zv-input" rows="2" name="faq_a[]" placeholder="{{ __('Write Answer') }}">{{ old("faq_a.$i", optional($service->faqs[$i] ?? null)->answer) }}</textarea>

              @if($loop->last)
                <button type="button" class="btn btn-sm btn-light mt-2 w-100" id="faq-add">
                  <i class="bi bi-plus-lg me-1"></i>{{ __('Add More FAQ') }}
                </button>
              @else
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100 faq-remove">
                  <i class="bi bi-x-lg me-1"></i>{{ __('Remove') }}
                </button>
              @endif
            </div>
          @endforeach
        </div>
      </div>

      <div class="text-end mt-3">
       <button class="btn btn-dark btn-lg px-4" type="button" id="saveBtn">
  {{ __('Save Changes') }}
</button>

      </div>
    </div>
  </div>

  <!-- Review Confirmation Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Send For Review') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2 text-capitalize">
          {{ __('After saving changes, your service will go under review again.') }}
        </p>
        <ul class="small text-muted mb-0 text-capitalize">
          <li>{{ __('Your service will be hidden from clients') }}</li>
          <li>{{ __('Bookings will be disabled until approval') }}</li>
          <li>{{ __('Admin approval is required to go live again') }}</li>
        </ul>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn btn-dark" id="confirmSave">
          {{ __('Yes, Save & Send For Review') }}
        </button>
      </div>
    </div>
  </div>
</div>

</form>

@push('scripts')
<script>
(function(){
  const wireRepeater = (addId, listId, removeClass) => {
    document.addEventListener('click', e=>{
      if (e.target.closest('#'+addId)) {
        const row = e.target.closest('.border.rounded-3');
        const clone = row.cloneNode(true);
       clone.querySelectorAll('input,textarea').forEach(el => {
  if (el.name === 'pkg_id[]' || el.name === 'faq_id[]') {
    el.value = ''; // ✅ new row must have no ID
    return;
  }
  if (el.type === 'checkbox' || el.type === 'radio') {
    el.checked = false;
    return;
  }
  el.value = '';
});

        row.querySelector('#'+addId).outerHTML =
          `<button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100 ${removeClass}">
             <i class="bi bi-x-lg me-1"></i>{{ __('Remove') }}
           </button>`;
        document.getElementById(listId).appendChild(clone);
      }
      if (e.target.closest('.'+removeClass)) {
        e.target.closest('.border.rounded-3').remove();
      }
    });
  };
  wireRepeater('pkg-add','pkg-list','pkg-remove');
  wireRepeater('faq-add','faq-list','faq-remove');
})();




document.getElementById('saveBtn')?.addEventListener('click', function () {
  const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
  modal.show();
});

document.getElementById('confirmSave')?.addEventListener('click', function () {
  document.getElementById('serviceEditForm').submit();

});




</script>
<script>
(function () {
  function toNum(v) {
    const n = parseFloat(String(v ?? '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  }
  function round2(n) {
    return Math.round((n + Number.EPSILON) * 100) / 100;
  }

  const pkgList = document.getElementById('pkg-list');

  function computePkg(row) {
    const hoursDay = toNum(row.querySelector('.js-hours-day')?.value);
    const days     = toNum(row.querySelector('.js-days')?.value);
    const rateHour = toNum(row.querySelector('.js-rate-hour')?.value);

    const totalHours = round2(hoursDay * days);
    const totalPrice = round2(totalHours * rateHour);

    const hoursTotalEl = row.querySelector('.js-hours-total');
    const totalPriceEl = row.querySelector('.js-total-price');

    if (hoursTotalEl) hoursTotalEl.value = totalHours ? totalHours : '';
    if (totalPriceEl) totalPriceEl.value = totalPrice ? totalPrice : '';
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

  // bind all existing rows on load
  pkgList?.querySelectorAll('.pkg-row').forEach(bindPkgRow);

  // IMPORTANT: when you clone/add a row, bind it too
  document.addEventListener('click', (e) => {
    if (e.target.closest('#pkg-add')) {
      // your repeater already appended the clone at this point, so bind the last row
      const rows = pkgList.querySelectorAll('.pkg-row');
      const last = rows[rows.length - 1];
      if (last) {
        // make sure computed fields start empty for new row
        const ht = last.querySelector('.js-hours-total');
        const tp = last.querySelector('.js-total-price');
        if (ht) ht.value = '';
        if (tp) tp.value = '';
        bindPkgRow(last);
      }
    }
  });
})();
</script>

@endpush
@endsection
