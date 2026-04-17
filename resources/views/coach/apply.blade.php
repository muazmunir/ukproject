@extends('layouts.app')
@section('title', __('Coach Verification'))

@push('styles')
<style>
/* =========================================
   Coach KYC Scoped Styles (no conflicts)
   Prefix: ckyc-
   ========================================= */

/* wrapper */
.ckyc-wrap{
  font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Noto Sans";
}

/* heading */
.ckyc-heading{ text-align:center; margin-bottom:14px; }
.ckyc-title{ margin:0 0 6px; font-weight:600; font-size:20px; color:#0f172a; }
.ckyc-sub{ font-size:13px; color:black; text-transform:capitalize; }

/* card (optional override - still using bootstrap card) */
.ckyc-card{
  border-radius:16px;
  border:1px solid rgba(0,0,0,.06);
  box-shadow:0 10px 24px rgba(0,0,0,.06);
  overflow:hidden;
}
.ckyc-card-body{ padding:18px; }
@media (min-width:768px){
  .ckyc-card-body{ padding:26px; }
}

/* info box */
.ckyc-info{
  margin:14px 0 18px;
  background:#f8fafc;
  border:1px solid rgba(0,0,0,.08);
  border-radius:14px;
  padding:14px;
}
.ckyc-info-title{
  font-weight:600;
  font-size:14px;
  margin:0 0 8px;
  color:#0f172a;
}
.ckyc-info-subtitle{
  font-weight:600;
  font-size:13px;
  margin:12px 0 6px;
  color:black;
}
.ckyc-info-list{
  margin:0;
  padding-left:18px;
}
.ckyc-info-list li{
  margin:4px 0;
  font-size:12px;
  line-height:1.55;
  color:black;
  text-transform:capitalize;
}

/* form labels/inputs (scoped but compatible with bootstrap) */
.ckyc-label{
  font-size:13px;
  font-weight:600;
  color:#0f172a;
}
.ckyc-input,
.ckyc-select{
  border-radius:12px !important;
  border:1.5px solid #e2e8f0 !important;
  padding:10px 12px !important;
  font-size:14px !important;
  outline:none;
  transition:.2s ease-in-out;
}
.ckyc-input:focus,
.ckyc-select:focus{
  border-color:#111 !important;
  box-shadow:0 0 0 3px rgba(0,0,0,.06) !important;
}

/* error text (you still use bootstrap invalid-feedback) */
.ckyc-error{
  font-size:12px;
  color:#dc2626;
  margin-top:6px;
}

/* button polish */
.ckyc-btn{
  border-radius:12px !important;
  font-size:15px !important;
  font-weight:600 !important;
  background:#000 !important;
  border:none !important;
  transition:.2s ease;
}
.ckyc-btn:hover{
  background:#111827 !important;
  transform:translateY(-1px);
}

/* helpers */
.ckyc-max{ max-width:720px; }
.ckyc-hide{ display:none !important; }
</style>
@endpush

@section('content')
<div class="container my-4 my-md-5 ckyc-wrap">
  <div class="mx-auto ckyc-max">

@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif
    <div class="ckyc-heading">
      <h4 class="ckyc-title">{{ __('Coach Verification') }}</h4>
      <div class="ckyc-sub text-capitalize">
        {{ __('Upload Your Photo And One Government-Issued Document (Passport or Driving Licence)') }}
      </div>
    </div>

    <div class="card border-0 shadow-sm ckyc-card">
      <div class="card-body ckyc-card-body">

        {{-- Requirements / Instructions --}}
        <div class="ckyc-info">
          <div class="ckyc-info-title">{{ __('Document Requirements') }}</div>

          <ul class="ckyc-info-list">
            <li><strong>{{ __('Photo:') }}</strong> {{ __('Your face must be clearly visible.') }}</li>
            <li><strong>{{ __('Full Name:') }}</strong> {{ __('Must match your account details.') }}</li>
            <li><strong>{{ __('Date of Birth:') }}</strong> {{ __('Must be legible.') }}</li>
            <li><strong>{{ __('ID Number:') }}</strong> {{ __('The unique identifier on the document.') }}</li>
            <li><strong>{{ __('Expiry Date:') }}</strong> {{ __('The document must not be expired.') }}</li>
            <li><strong>{{ __('Security Features:') }}</strong> {{ __('Holograms or other features should be visible, not obscured.') }}</li>
          </ul>

          <div class="ckyc-info-subtitle">{{ __('How to submit it:') }}</div>
          <ul class="ckyc-info-list">
            <li><strong>{{ __('Clear, Color Photo:') }}</strong> {{ __('Take a photo of the original document, not a screenshot or photocopy.') }}</li>
            <li><strong>{{ __('No Glare/Blur:') }}</strong> {{ __('Ensure all info is sharp and readable.') }}</li>
            <li><strong>{{ __('Photo:') }}</strong> {{ __('Your photo must match your ID, removing glasses, hats, or masks for clarity.') }}</li>
          </ul>
        </div>

        <form method="POST" action="{{ route('coach.kyc.store') }}" enctype="multipart/form-data" novalidate>
          @csrf

          <div class="mb-3">
            <label class="form-label small fw-semibold ckyc-label">{{ __('Profile Photo (Required)') }}</label>
            <input type="file" class="form-control ckyc-input" name="profile_photo" accept="image/*" required>
            @error('profile_photo')<div class="invalid-feedback d-block ckyc-error">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold ckyc-label">{{ __('Select ID Type') }}</label>
            <select name="id_type" id="idType" class="form-select ckyc-select" required>
              <option value="">{{ __('Select') }}</option>
              <option value="passport" {{ old('id_type') === 'passport' ? 'selected' : '' }}>{{ __('Passport') }}</option>
              <option value="driving_license" {{ old('id_type') === 'driving_license' ? 'selected' : '' }}>{{ __('Driving Licence') }}</option>
            </select>
            @error('id_type')<div class="invalid-feedback d-block ckyc-error">{{ $message }}</div>@enderror
          </div>

          <div id="passportBox" class="mb-3 d-none">
            <label class="form-label small fw-semibold ckyc-label">{{ __('Passport (Full Double Spread - 1 Image)') }}</label>
            <input type="file" class="form-control ckyc-input" name="passport_image" accept="image/*">
            @error('passport_image')<div class="invalid-feedback d-block ckyc-error">{{ $message }}</div>@enderror
          </div>

          <div id="dlBox" class="mb-3 d-none">
            <label class="form-label small fw-semibold ckyc-label">{{ __('Driving Licence') }}</label>

            <div class="mb-2">
              <label class="form-label small ckyc-label" style="font-weight:600;">{{ __('Front') }}</label>
              <input type="file" class="form-control ckyc-input" name="dl_front" accept="image/*">
              @error('dl_front')<div class="invalid-feedback d-block ckyc-error">{{ $message }}</div>@enderror
            </div>

            <div>
              <label class="form-label small ckyc-label" style="font-weight:600;">{{ __('Back') }}</label>
              <input type="file" class="form-control ckyc-input" name="dl_back" accept="image/*">
              @error('dl_back')<div class="invalid-feedback d-block ckyc-error">{{ $message }}</div>@enderror
            </div>
          </div>

          <button class="btn btn-dark w-100 py-2 ckyc-btn" type="submit">
            {{ __('Submit For Review') }}
          </button>

        </form>

      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const idType = document.getElementById('idType');
  const passportBox = document.getElementById('passportBox');
  const dlBox = document.getElementById('dlBox');

  function toggle() {
    const v = idType.value;
    passportBox.classList.toggle('d-none', v !== 'passport');
    dlBox.classList.toggle('d-none', v !== 'driving_license');
  }

  idType.addEventListener('change', toggle);
  toggle();
});
</script>
@endpush
