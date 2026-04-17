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
        <button type="button" class="btn-3d btn-plain" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn-3d btn-dark-elev" id="avatar-crop-save">{{ __('Use this photo') }}</button>
      </div>
    </div>
  </div>
</div>
