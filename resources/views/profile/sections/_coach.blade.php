{{-- Service Areas --}}
<section class="zv-section">
  <div class="zv-section-title">
    <h6 class="mb-0">{{ __('Service Areas') }}</h6>
  </div>

  <div class="zv-lang-wrap">
    <input type="text" id="area-input" class="zv-input text-capitalize" placeholder="{{ __('Type area & press Add') }}">
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
    <small class="text-muted text-capitalize">{{ __('Show relevant images from your work.') }}</small>
  </div>

  <label class="btn-3d btn-plain">
    <input type="file" id="gallery-input" name="gallery[]" accept="image/*" multiple hidden>
    <i class="bi bi-images me-1 text-capitalize"></i>{{ __('Choose images') }}
  </label>
  @error('gallery.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

  <div id="gallery-preview" class="zv-gallery-grid mt-2">
    @foreach((array) ($user->coach_gallery ?? []) as $g)
      <div class="zv-gallery-item" data-existing="1" data-path="{{ $g }}">
        <button type="button" class="zv-gallery-remove" aria-label="{{ __('Remove') }}">&times;</button>
        <img src="{{ asset('storage/'.$g) }}" alt="">
        <div class="zv-gallery-name" title="{{ basename($g) }}">{{ basename($g) }}</div>
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
