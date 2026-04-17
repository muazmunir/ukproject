@extends('superadmin.layout')
@section('title','Create Staff')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-staff.css') }}">
<style>
  .page-wrap{ max-width:1100px; margin: 1.25rem auto; padding:0 14px; }
  .form-card{ background:#fff; border:1px solid rgba(0,0,0,.12); border-radius:18px; box-shadow:0 10px 26px rgba(0,0,0,.06); overflow:hidden; }
  .form-head{ padding:16px 18px; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; border-bottom:1px solid rgba(0,0,0,.10); background:linear-gradient(180deg,#fff, #fafafa); }
  .form-title{ font-size:18px; font-weight:800; }
  .form-sub{ color:rgba(0,0,0,.62); margin-top:4px; }
  .form-body{ padding:18px; }
  .grid2{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  .col-span{ grid-column: 1 / -1; }
  @media(max-width:860px){ .grid2{ grid-template-columns:1fr; } }
  label{ display:block; font-size:13px; font-weight:700; }
  input,select,textarea{
    width:100%; margin-top:6px; padding:10px 12px; border:1px solid rgba(0,0,0,.14);
    border-radius:12px; outline:none; background:#fff;
  }
  input:focus,select:focus,textarea:focus{ border-color:#000; box-shadow:0 0 0 3px rgba(0,0,0,.06); }
  .divider{ border-top:1px solid rgba(0,0,0,.10); margin: 10px 0; }
  .filebox{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px; border:1px dashed rgba(0,0,0,.18); border-radius:14px; background:#fafafa; margin-top:8px; }
  .filebox__left{ display:flex; gap:10px; align-items:center; }
  .filehint{ margin-top:8px; font-size:13px; color:rgba(0,0,0,.65); }
  .avatar-prev{ width:72px;height:72px;border-radius:16px; overflow:hidden; border:1px solid rgba(0,0,0,.12); background:#fff; }
  .avatar-prev img{ width:100%; height:100%; object-fit:cover; display:block; }
  .actions{ display:flex; gap:10px; flex-wrap:wrap; }
</style>
@endpush

@section('content')
<div class="page-wrap">

  @if($errors->any())
    <div class="zv-alert zv-alert--danger" style="margin-bottom:12px">
      <strong>Please Fix:</strong>
      <ul style="margin:8px 0 0; padding-left:18px">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  @if(session('ok')) <div class="zv-alert zv-alert--ok" style="margin-bottom:12px">{{ session('ok') }}</div> @endif
  @if(session('error')) <div class="zv-alert zv-alert--danger" style="margin-bottom:12px">{{ session('error') }}</div> @endif

  <div class="form-card">
    <div class="form-head">
      <div>
        <div class="form-title">Create Staff</div>
        <div class="form-sub text-capitalize">Agents & Managers — invite-based access, documents stored for compliance.</div>
      </div>
      <div class="actions">
        <a href="{{ route('superadmin.staff.index') }}" class="btn ghost">Back</a>
      </div>
    </div>

    <form method="post" action="{{ route('superadmin.staff.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="form-body grid2">

        <label>First Name
          <input type="text" name="first_name" required maxlength="60" value="{{ old('first_name') }}">
        </label>

        <label>Last Name
          <input type="text" name="last_name" required maxlength="60" value="{{ old('last_name') }}">
        </label>
        <label>Username
  <input type="text" name="username" required maxlength="60" value="{{ old('username') }}">
</label>

        <label>Contact Number
          <input type="text" name="phone" required maxlength="30" value="{{ old('phone') }}">
        </label>

        <label>Email
          <input type="email" name="email" required maxlength="255" value="{{ old('email') }}">
        </label>

        <label class="">Role
          <select name="role" required>
            <option value="admin" @selected(old('role')==='admin')>Agent</option>
            <option value="manager" @selected(old('role')==='manager')>Manager</option>
          </select>
        </label>



        {{-- =========================
   WORKING HOURS (SHIFT)
========================= --}}
<div class="col-span" style="border-top:1px solid rgba(0,0,0,.10); padding-top:12px;"></div>

<div class="col-span">
  <div style="font-weight:900" class="text-capitalize">Working Hours (Shift)</div>
  <div class="muted small text-capitalize" style="margin-top:4px">
    “09:00–17:00” means 09:00–17:00 in the staff member’s own timezone (not superadmin timezone).
  </div>

  <div style="margin-top:10px">
    <label style="display:flex; gap:10px; align-items:center; font-weight:700">
      <input type="checkbox" name="shift_enabled" value="1"
        {{ old('shift_enabled', 1) ? 'checked' : '' }}>
      <span class="text-capitalize">Enable shift restriction</span>
    </label>
  </div>

  <div class="grid2" style="margin-top:12px">
    <label class="text-capitalize">Start (local)
      <input type="time" name="shift_start"
             value="{{ old('shift_start', '09:00') }}">
    </label>

    <label class="text-capitalize">End (local)
      <input type="time" name="shift_end"
             value="{{ old('shift_end', '17:00') }}">
    </label>
  </div>

  @php
    $days = old('shift_days', [1,2,3,4,5]);
    $labels = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
  @endphp

  <div style="margin-top:12px">
    <div class="muted small text-capitalize">Working days</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px">
      @foreach($labels as $k=>$lbl)
        <label class="pill" style="cursor:pointer; padding:8px 12px; border:1px solid rgba(0,0,0,.12); border-radius:999px; background:#fff">
          <input type="checkbox" name="shift_days[]" value="{{ $k }}" style="margin-right:6px"
            {{ in_array($k, $days, true) ? 'checked' : '' }}>
          {{ $lbl }}
        </label>
      @endforeach
    </div>
  </div>

  
</div>

        <div class="col-span divider"></div>

        <div class="col-span">
          <div class="muted small" style="margin-bottom:8px">Profile Picture</div>

          <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
            <div class="avatar-prev">
              <img id="c_avatar_img" src="{{ asset('images/avatar-placeholder.png') }}" alt="avatar">
            </div>

            <div style="flex:1; min-width:240px">
              <div class="filebox">
                <div class="filebox__left">
                  <i class="bi bi-image"></i>
                  <div>
                    <div style="font-weight:800">Upload Profile Photo</div>
                    <div class="muted">JPG, PNG, WEBP · Max 5MB</div>
                  </div>
                </div>
                <label class="btn ghost" style="margin:0; cursor:pointer">
                  Choose File
                  <input type="file" name="profile_pic" id="c_profile_pic" accept="image/*" style="display:none">
                </label>
              </div>
              <div class="filehint" id="c_profile_pic_hint">No File Selected</div>
            </div>
          </div>
        </div>

        <div class="col-span divider"></div>

        <label>Emergency Contact Name
          <input type="text" name="emergency_contact_name" required maxlength="120" value="{{ old('emergency_contact_name') }}">
        </label>

        <label>Emergency Contact Number
          <input type="text" name="emergency_contact_phone" required maxlength="40" value="{{ old('emergency_contact_phone') }}">
        </label>

        <label>Next Of Kin Name <span class="muted"></span>
          <input type="text" name="next_of_kin_name" maxlength="120" value="{{ old('next_of_kin_name') }}">
        </label>

        <label>Next Of Kin Number <span class="muted"></span>
          <input type="text" name="next_of_kin_phone" maxlength="40" value="{{ old('next_of_kin_phone') }}">
        </label>

        <label class="col-span">Next Of Kin Address <span class="muted">(Optional)</span>
  <input type="text" name="next_of_kin_address" maxlength="255" value="{{ old('next_of_kin_address') }}">
</label>

        <div class="col-span divider"></div>

        <div class="col-span">
          <div style="font-weight:900">Government ID Documents</div>
          <div class="filebox">
            <div class="filebox__left">
              <i class="bi bi-file-earmark-text"></i>
              <div>
                <div style="font-weight:800">Upload Government Documents</div>
                <div class="muted">Images / PDF · Max 50MB Each · Multiple Allowed</div>
              </div>
            </div>
            <label class="btn ghost" style="margin:0; cursor:pointer">
              Upload Files
              <input type="file" name="government_id_docs[]" id="c_gov_docs" multiple accept="image/*,application/pdf" style="display:none">
            </label>
          </div>
          <div class="filehint text-capitalize" id="c_gov_docs_hint">No files selected</div>
        </div>

        <div class="col-span divider"></div>

        <div class="col-span">
          <div style="font-weight:900 text-capitalize">Additional Government Requirements <span class="muted text-capitalize">(add as many as needed)</span></div>

          <div id="additionalWrap" style="margin-top:10px; display:grid; gap:10px">
            <div class="grid2 additional-row" style="gap:10px; align-items:center">
              <input type="text" name="additional_label[]" placeholder="Label e.g. National Insurance Number" maxlength="160">
              <div style="display:flex; gap:8px">
                <input type="text" name="additional_value[]" placeholder="Value e.g. QQ123456C" maxlength="2000" style="flex:1">
                <button type="button" class="btn danger ghost remove-row" title="Remove">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
          </div>

          <button type="button" class="btn ghost" id="addAdditionalRow" style="margin-top:10px">
            <i class="bi bi-plus-lg"></i> Add Another Requirement
          </button>

          <div class="muted small" style="margin-top:6px">
            Example: National Insurance Number, Tax ID, Residence Permit, etc.
          </div>
        </div>

        <div class="col-span divider"></div>

        <div class="col-span">
          <div style="font-weight:900">Additional Documents <span class="muted">(Optional)</span></div>

          <div class="filebox" style="margin-top:8px">
            <div class="filebox__left">
              <i class="bi bi-file-earmark"></i>
              <div>
                <div style="font-weight:800">Upload Additional Documents</div>
                <div class="muted">Images / PDF · Multiple Allowed</div>
              </div>
            </div>
            <label class="btn ghost" style="margin:0; cursor:pointer">
              Upload Files
              <input type="file" name="additional_docs[]" id="c_add_docs" multiple accept="image/*,application/pdf" style="display:none">
            </label>
          </div>

          <div class="filehint text-capitalize" id="c_add_docs_hint">No files selected</div>
        </div>

        <p class="muted col-span text-capitalize" style="margin:0">
          A secure password setup link will be sent by email (valid for 24 hours).
        </p>

        <div class="col-span" style="display:flex; justify-content:flex-end; gap:10px; padding-top:6px">
          <a href="{{ route('superadmin.staff.index') }}" class="btn ghost">Cancel</a>
          <button type="submit" class="btn bg-black">Create Staff</button>
        </div>

      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // profile pic preview
  const pic = document.getElementById('c_profile_pic');
  const hint = document.getElementById('c_profile_pic_hint');
  const img = document.getElementById('c_avatar_img');

  if (pic) {
    pic.addEventListener('change', () => {
      const f = pic.files && pic.files[0] ? pic.files[0] : null;
      hint.textContent = f ? f.name : 'No file selected';
      if (f) img.src = URL.createObjectURL(f);
    });
  }

  function bindMulti(idInput, idHint, emptyText){
    const i = document.getElementById(idInput);
    const h = document.getElementById(idHint);
    if(!i||!h) return;
    i.addEventListener('change', () => {
      const n = i.files ? i.files.length : 0;
      h.textContent = n ? (n===1 ? i.files[0].name : `${n} files selected`) : emptyText;
    });
  }
  bindMulti('c_gov_docs','c_gov_docs_hint','No files selected');
  bindMulti('c_add_docs','c_add_docs_hint','No files selected');

  // dynamic additional requirements
  const addBtn = document.getElementById('addAdditionalRow');
  const wrap = document.getElementById('additionalWrap');

  if (addBtn && wrap) {
    addBtn.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'grid2 additional-row';
      row.style.gap = '10px';
      row.style.alignItems = 'center';
      row.innerHTML = `
        <input type="text" name="additional_label[]" placeholder="Label e.g. National Insurance Number" maxlength="160">
        <div style="display:flex; gap:8px">
          <input type="text" name="additional_value[]" placeholder="Value e.g. QQ123456C" maxlength="2000" style="flex:1">
          <button type="button" class="btn danger ghost remove-row" title="Remove"><i class="bi bi-x-lg"></i></button>
        </div>
      `;
      wrap.appendChild(row);
    });

    wrap.addEventListener('click', (e) => {
      if (e.target.closest('.remove-row')) {
        const rows = wrap.querySelectorAll('.additional-row');
        if (rows.length > 1) e.target.closest('.additional-row').remove();
      }
    });
  }
</script>
@endpush
