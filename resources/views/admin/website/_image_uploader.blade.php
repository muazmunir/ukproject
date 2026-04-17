@php
  $id = $id ?? $name;
  $accept = $accept ?? 'image/*';
  $max = $max ?? '4MB';
  $labelText = $labelText ?? 'Image';
  $currentUrl = $currentPath ? asset('storage/'.$currentPath) : null;
@endphp

<div class="zv-up" data-uploader
     data-has-current="{{ $currentUrl ? '1' : '0' }}"
     data-current-url="{{ $currentUrl ?? '' }}">

  <div class="zv-up__top">
    <div class="zv-up__label">
      <div class="zv-up__title">{{ $labelText }}</div>
      <div class="zv-up__meta">JPG, PNG, WEBP · Max {{ $max }}</div>
    </div>

    <div class="zv-up__actions">
      <button type="button" class="btn ghost small" data-clear-selected hidden>Clear Selected</button>

      @if($currentUrl && $deleteRoute)
        <button type="button" class="btn danger small" data-open-delete>
          Remove Current
        </button>
      @endif
    </div>
  </div>

  <div class="zv-up__box">
    <input type="file" name="{{ $name }}" id="{{ $id }}" accept="{{ $accept }}" hidden data-file>

    <label class="zv-up__drop" for="{{ $id }}" data-drop>
      <div class="zv-up__icon">📷</div>
      <div class="zv-up__txt">
        <div><strong>Choose An Image</strong> Or Drag & Drop</div>
        <div class="muted" data-filename>Nothing Selected</div>
      </div>
      <div class="zv-up__chip" data-filesize></div>
    </label>

    <div class="zv-up__preview" data-preview-wrap {{ $currentUrl ? '' : 'hidden' }}>
      <img src="{{ $currentUrl ?? '' }}" alt="preview" data-preview>
      <button type="button" class="zv-up__zoom" data-zoom title="Preview">🔍</button>
    </div>
  </div>

  {{-- optional: “remove via update form” (no route call) --}}
  @if(!empty($removeField))
    <input type="hidden" name="{{ $removeField }}" value="0" data-remove-flag>
  @endif

  {{-- delete current confirm (posts to delete route) --}}
  @if($currentUrl && $deleteRoute)
    <div class="zv-modal" data-delete-modal hidden>
      <div class="zv-modal__backdrop" data-close></div>
      <div class="zv-modal__card">
        <div class="zv-modal__title text-capitalize">Remove current image?</div>
        <div class="zv-modal__desc text-capitalize">This will permanently delete the current image from storage.</div>

        <div class="zv-modal__btns">
  <button type="button" class="btn ghost" data-close>Cancel</button>

  <button type="button"
          class="btn danger"
          data-confirm-delete
          data-delete-action="{{ $deleteRoute }}">
    Yes, Remove
  </button>
</div>

      </div>
    </div>
  @endif

  {{-- lightbox preview --}}
  <div class="zv-lightbox" data-lightbox hidden>
    <div class="zv-lightbox__backdrop" data-lightbox-close></div>
    <img class="zv-lightbox__img" src="" alt="full preview" data-lightbox-img>
  </div>

</div>
