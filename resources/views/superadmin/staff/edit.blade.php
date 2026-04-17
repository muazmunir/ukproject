{{-- resources/views/superadmin/staff/edit.blade.php --}}
@extends('superadmin.layout')
@section('title','Edit Staff')

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
  .doc-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-top:10px; }
  @media(max-width:980px){ .doc-grid{ grid-template-columns: repeat(2, 1fr); } }
  @media(max-width:560px){ .doc-grid{ grid-template-columns: 1fr; } }
  .doc-card{ border:1px solid rgba(0,0,0,.12); border-radius:14px; padding:10px; background:#fff; }
  .doc-top{ display:flex; gap:10px; align-items:center; }
  .thumb{ width:52px; height:52px; border-radius:12px; border:1px solid rgba(0,0,0,.12); overflow:hidden; background:#fafafa; display:flex; align-items:center; justify-content:center; }
  .thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
  .doc-name{ font-weight:800; font-size:13px; }
  .doc-meta{ font-size:12px; color:rgba(0,0,0,.62); margin-top:3px; }
  .doc-actions{ display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
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
        <div class="form-title">Edit Staff</div>
        <div class="form-sub">{{ $user->email }} · {{ $user->role === 'admin' ? 'Agent' : 'Manager' }}</div>
      </div>
      <div class="actions">
        <a href="{{ route('superadmin.staff.index') }}" class="btn ghost">Back</a>
        <a href="{{ route('superadmin.staff.info', $user->id) }}" class="btn ghost">
          <i class="bi bi-info-circle"></i> View Details
        </a>
      </div>
    </div>

    {{-- ✅ ONLY form on page for update (no nested forms inside) --}}
    <form method="post" action="{{ route('superadmin.staff.update', $user->id) }}" enctype="multipart/form-data">
      @csrf @method('PUT')

      <div class="form-body grid2">

        <div class="col-span">
          <div class="muted small" style="margin-bottom:6px">Email (locked)</div>
          <div style="padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:12px;background:#fff">
            {{ $user->email }}
          </div>
          <div class="muted small text-capitalize" style="margin-top:6px">
            Email cannot be changed to protect identity, invites, and audit logs.
          </div>
        </div>

        <label>First Name
          <input type="text" name="first_name" required maxlength="60" value="{{ old('first_name', $user->first_name) }}">
        </label>

        <label>Last Name
          <input type="text" name="last_name" maxlength="60" value="{{ old('last_name', $user->last_name) }}">
        </label>
        <label>Username
  <input type="text" name="username" required maxlength="60" value="{{ old('username', $user->username) }}">
</label>


        <label>Contact Number
          <input type="text" name="phone" required maxlength="30" value="{{ old('phone', $user->phone) }}">
        </label>

        <label class="col-span">Role
          <select name="role" required>
            <option value="admin" @selected(old('role',$user->role)==='admin')>Agent</option>
            <option value="manager" @selected(old('role',$user->role)==='manager')>Manager</option>
          </select>
        </label>
        @php
  // shift_days: stored as array cast, but be safe
  $savedDays = old('shift_days', $user->shift_days ?? []);
  if (!is_array($savedDays)) $savedDays = [];
  $savedDays = array_map('intval', $savedDays);

  $shiftEnabledOld = old('shift_enabled', ($user->shift_enabled ?? false) ? '1' : '0');
  $shiftEnabledChecked = (string)$shiftEnabledOld === '1';

  $dayNames = [
    1 => 'Mon',
    2 => 'Tue',
    3 => 'Wed',
    4 => 'Thu',
    5 => 'Fri',
    6 => 'Sat',
    7 => 'Sun',
  ];
@endphp

<div class="col-span" style="padding:14px; border:1px solid rgba(0,0,0,.10); border-radius:14px; background:#fafafa;">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
    <div>
      <div style="font-weight:900">Working Hours (Shift)</div>
      <div class="muted small" style="margin-top:4px">
        Logs will be saved only within these hours (based on staff timezone).
      </div>
    </div>

    <label style="display:flex; align-items:center; gap:10px; margin:0; font-weight:800;">
      <input type="checkbox" name="shift_enabled" value="1" id="shift_enabled"
             @checked($shiftEnabledChecked)
             style="width:18px; height:18px; margin:0;">
      Enable Shift
    </label>
  </div>

  <div id="shiftFields" style="margin-top:12px; display: {{ $shiftEnabledChecked ? 'block' : 'none' }};">
    <div class="grid2">
      <label>Shift Start
       <input type="time" name="shift_start"
  value="{{ old('shift_start', $user->shift_start ? substr($user->shift_start, 0, 5) : '') }}">
      </label>

      <label>Shift End
        <input type="time" name="shift_end"
  value="{{ old('shift_end', $user->shift_end ? substr($user->shift_end, 0, 5) : '') }}">
      </label>

      <div class="col-span">
        <div class="muted small" style="margin-bottom:8px">Working Days</div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          @foreach($dayNames as $k=>$name)
            <label style="display:flex; align-items:center; gap:8px; font-weight:800; margin:0; padding:8px 10px; border:1px solid rgba(0,0,0,.12); border-radius:12px; background:#fff;">
              <input type="checkbox"
                     name="shift_days[]"
                     value="{{ $k }}"
                     @checked(in_array($k, $savedDays, true))
                     style="width:16px; height:16px; margin:0;">
              {{ $name }}
            </label>
          @endforeach
        </div>

        <div class="muted small" style="margin-top:8px">
          If disabled, the system will not log statuses at all (recommended to keep enabled).
        </div>
      </div>
    </div>
  </div>
</div>


        <div class="col-span divider"></div>

        {{-- Avatar --}}
        <div class="col-span">
          <div class="muted small" style="margin-bottom:8px">Profile Picture</div>

          <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
            <div class="avatar-prev">
              <img id="e_avatar_img"
                   src="{{ $user->avatar_path ? asset('storage/'.$user->avatar_path) : asset('images/avatar-placeholder.png') }}"
                   alt="avatar">
            </div>

            <div style="flex:1; min-width:240px">
              <div class="filebox">
                <div class="filebox__left">
                  <i class="bi bi-image"></i>
                  <div>
                    <div style="font-weight:800">Replace Profile Photo</div>
                    <div class="muted">JPG, PNG, WEBP · Max 5MB</div>
                  </div>
                </div>
                <label class="btn ghost" style="margin:0; cursor:pointer">
                  Choose File
                  <input type="file" name="profile_pic" id="e_profile_pic" accept="image/*" style="display:none">
                </label>
              </div>

              <div class="filehint" id="e_profile_pic_hint">
                {{ $user->avatar_path ? ('Current: '.$user->avatar_path) : 'No photo' }}
              </div>

              <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap">
                <button type="button" class="btn danger" id="e_avatar_remove_btn">
                  <i class="bi bi-trash3"></i> Remove Photo
                </button>
                <button type="button" class="btn ghost" id="e_avatar_clear_new_btn" style="display:none">
                  Clear New
                </button>
              </div>

              <input type="hidden" name="remove_avatar" id="e_remove_avatar" value="0">
            </div>
          </div>
        </div>

        <div class="col-span divider"></div>

        <label>Emergency Contact Name
          <input type="text" name="emergency_contact_name" required maxlength="120"
                 value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}">
        </label>

        <label>Emergency Contact Number
          <input type="text" name="emergency_contact_phone" required maxlength="40"
                 value="{{ old('emergency_contact_phone', $user->emergency_contact_phone) }}">
        </label>

        <label>Next Of Kin Name <span class="muted"></span>
          <input type="text" name="next_of_kin_name" maxlength="120"
                 value="{{ old('next_of_kin_name', $user->next_of_kin_name) }}">
        </label>

        <label>Next Of Kin Number <span class="muted"></span>
          <input type="text" name="next_of_kin_phone" maxlength="40"
                 value="{{ old('next_of_kin_phone', $user->next_of_kin_phone) }}">
        </label>

        <label class="col-span">Next Of Kin Address <span class="muted">(Optional)</span>
  <input type="text" name="next_of_kin_address" maxlength="255"
         value="{{ old('next_of_kin_address', $user->next_of_kin_address) }}">
</label>

        <div class="col-span divider"></div>

        {{-- Existing Gov Docs --}}
        <div class="col-span">
          <div style="font-weight:900">Government ID Documents</div>

          @if($govDocs->count())
            <div class="doc-grid">
              @foreach($govDocs as $d)
                @php
                  $isPdf = str_contains((string)$d->file_mime, 'pdf') || str_ends_with(strtolower((string)$d->file_original_name), '.pdf');
                  $url = $d->file_path ? asset('storage/'.$d->file_path) : null;
                @endphp
                <div class="doc-card">
                  <div class="doc-top">
                    <div class="thumb">
                      @if(!$isPdf && $url)
                        <img src="{{ $url }}" alt="doc">
                      @else
                        <i class="bi bi-file-earmark-pdf" style="font-size:22px"></i>
                      @endif
                    </div>
                    <div style="min-width:0">
                      <div class="doc-name" title="{{ $d->file_original_name }}">{{ $d->file_original_name ?: 'Document' }}</div>
                      <div class="doc-meta">{{ $d->file_mime ?: 'file' }} · {{ $d->created_at?->format('Y-m-d') }}</div>
                    </div>
                  </div>

                  <div class="doc-actions">
                    @if($url)
                      <a class="btn ghost" href="{{ $url }}" target="_blank" rel="noopener">Preview</a>
                    @endif

                    {{-- ✅ no nested form --}}
                    <button type="button"
                            class="btn danger"
                            data-doc-delete="{{ route('superadmin.staff_documents.destroy', $d->id) }}"
                            data-confirm="Delete This Document?">
                      Delete
                    </button>
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <div class="muted text-capitalize" style="margin-top:8px">No government documents uploaded yet.</div>
          @endif

          {{-- add more --}}
          <div class="filebox" style="margin-top:12px">
            <div class="filebox__left">
              <i class="bi bi-upload"></i>
              <div>
                <div style="font-weight:800">Upload more government documents</div>
                <div class="muted">Images / PDF · Multiple allowed</div>
              </div>
            </div>
            <label class="btn ghost" style="margin:0; cursor:pointer">
              Upload Files
              <input type="file" name="government_id_docs[]" id="e_gov_docs" multiple accept="image/*,application/pdf" style="display:none">
            </label>
          </div>
          <div class="filehint" id="e_gov_docs_hint">No Files Selected</div>
        </div>

        <div class="col-span divider"></div>

        {{-- Existing Additional Requirements --}}
        <div class="col-span">
          <div style="font-weight:900">Additional Government Requirements</div>

          @if($additionalRequirements->count())
            <div style="margin-top:10px; display:grid; gap:10px">
              @foreach($additionalRequirements as $req)
                <div class="doc-card">
                  <div style="font-weight:800">{{ $req->label ?: 'Requirement' }}</div>
                  <div class="muted" style="margin-top:6px; word-break:break-word">{{ $req->value_text }}</div>

                  <div class="doc-actions" style="margin-top:10px">
                    {{-- ✅ no nested form --}}
                    <button type="button"
                            class="btn danger"
                            data-doc-delete="{{ route('superadmin.staff_documents.destroy', $req->id) }}"
                            data-confirm="Delete this requirement?">
                      Delete
                    </button>
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <div class="muted text-capitalize" style="margin-top:8px">No requirements added yet.</div>
          @endif

          {{-- add more requirements --}}
          <div style="margin-top:12px">
            <div class="muted small text-capitalize">Add new requirements (optional)</div>

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
          </div>
        </div>

        <div class="col-span divider"></div>

        {{-- Existing Additional Docs --}}
        <div class="col-span">
          <div style="font-weight:900">Additional Documents</div>

          @if($additionalDocs->count())
            <div class="doc-grid">
              @foreach($additionalDocs as $d)
                @php
                  $isPdf = str_contains((string)$d->file_mime, 'pdf') || str_ends_with(strtolower((string)$d->file_original_name), '.pdf');
                  $url = $d->file_path ? asset('storage/'.$d->file_path) : null;
                @endphp
                <div class="doc-card">
                  <div class="doc-top">
                    <div class="thumb">
                      @if(!$isPdf && $url)
                        <img src="{{ $url }}" alt="doc">
                      @else
                        <i class="bi bi-file-earmark-pdf" style="font-size:22px"></i>
                      @endif
                    </div>
                    <div style="min-width:0">
                      <div class="doc-name" title="{{ $d->file_original_name }}">{{ $d->file_original_name ?: 'Document' }}</div>
                      <div class="doc-meta">{{ $d->file_mime ?: 'file' }} · {{ $d->created_at?->format('Y-m-d') }}</div>
                    </div>
                  </div>

                  <div class="doc-actions">
                    @if($url)
                      <a class="btn ghost" href="{{ $url }}" target="_blank" rel="noopener">Preview</a>
                    @endif

                    {{-- ✅ no nested form --}}
                    <button type="button"
                            class="btn danger"
                            data-doc-delete="{{ route('superadmin.staff_documents.destroy', $d->id) }}"
                            data-confirm="Delete This Document?">
                      Delete
                    </button>
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <div class="muted" style="margin-top:8px">No additional documents uploaded yet.</div>
          @endif

          {{-- add more --}}
          <div class="filebox" style="margin-top:12px">
            <div class="filebox__left">
              <i class="bi bi-upload"></i>
              <div>
                <div style="font-weight:800">Upload More Additional Documents</div>
                <div class="muted">Images / PDF · Multiple Allowed</div>
              </div>
            </div>
            <label class="btn ghost" style="margin:0; cursor:pointer">
              Upload Files
              <input type="file" name="additional_docs[]" id="e_add_docs" multiple accept="image/*,application/pdf" style="display:none">
            </label>
          </div>
          <div class="filehint" id="e_add_docs_hint">No Files Selected</div>
        </div>

        <div class="col-span" style="display:flex; justify-content:flex-end; gap:10px; padding-top:6px">
          <a href="{{ route('superadmin.staff.index') }}" class="btn ghost">Cancel</a>
          <button type="submit" class="btn bg-black">Save Changes</button>
        </div>

      </div>
    </form>

    {{-- ✅ hidden container for dynamically created DELETE forms (prevents nested forms) --}}
    <div id="deleteForms" style="display:none"></div>

  </div>
</div>


{{-- Delete Confirmation Modal --}}
<div id="confirmDeleteModal" class="modal" aria-hidden="true">
  <div class="modal__overlay"></div>

  <div class="modal__dialog">
    <div class="modal__card">

      <div class="modal__head">
        <div class="title">Confirm Deletion</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body">
        <p id="confirmDeleteText" style="margin:0">
          Are you sure you want to delete this item?
        </p>
      </div>

      <div class="modal__footer p-4" style="display:flex; justify-content:flex-end; gap:10px">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="button" class="btn danger" id="confirmDeleteBtn">
          Yes, Delete
        </button>
      </div>

    </div>
  </div>
</div>

{{-- container for generated delete forms --}}


@endsection

@push('scripts')

<script>
(() => {
  // ----------------------------
  // Helpers
  // ----------------------------
  const $ = (sel, root=document) => root.querySelector(sel);

  const getCsrfToken = () => {
    return (
      $('meta[name="csrf-token"]')?.getAttribute('content') ||
      $('input[name="_token"]')?.value ||
      ''
    );
  };

  // ----------------------------
  // Confirm Delete Modal (NO nested forms)
  // ----------------------------
  let pendingDeleteUrl = null;

  const modal = $('#confirmDeleteModal');
  const modalText = $('#confirmDeleteText');
  const confirmBtn = $('#confirmDeleteBtn');

  const openModal = () => {
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  const closeModal = () => {
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    pendingDeleteUrl = null;
  };

  // Open modal when clicking any delete button
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-doc-delete]');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    pendingDeleteUrl = btn.getAttribute('data-doc-delete');
    const msg = btn.getAttribute('data-confirm') || 'Are you sure you want to delete this item?';
    if (modalText) modalText.textContent = msg;

    openModal();
  });

  // Confirm and submit DELETE
  if (confirmBtn) {
    confirmBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      if (!pendingDeleteUrl) return;

      const csrf = getCsrfToken();
      if (!csrf) {
        alert('CSRF token missing. Please refresh the page.');
        return;
      }

      const form = document.createElement('form');
      form.method = 'POST';
      form.action = pendingDeleteUrl;
      form.style.display = 'none';
      form.innerHTML = `
        <input type="hidden" name="_token" value="${csrf}">
        <input type="hidden" name="_method" value="DELETE">
      `;

      // Append to body (most reliable)
      document.body.appendChild(form);
      form.submit();
    });
  }

  // Close modal (overlay / close buttons)
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (
        e.target.classList.contains('modal__overlay') ||
        e.target.hasAttribute('data-close')
      ) {
        closeModal();
      }
    });
  }

  // ESC closes modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });

  // ----------------------------
  // Avatar preview + remove
  // ----------------------------
  const file = $('#e_profile_pic');
  const hint = $('#e_profile_pic_hint');
  const img  = $('#e_avatar_img');
  const rmBtn = $('#e_avatar_remove_btn');
  const clearBtn = $('#e_avatar_clear_new_btn');
  const rmFlag = $('#e_remove_avatar');

  const originalUrl = img ? img.src : '';
  const originalText = hint ? hint.textContent : '';

  if (file) {
    file.addEventListener('change', () => {
      const f = file.files && file.files[0] ? file.files[0] : null;
      if (!f) return;

      if (rmFlag) rmFlag.value = '0';
      if (hint) hint.textContent = `New: ${f.name}`;
      if (img) img.src = URL.createObjectURL(f);

      if (clearBtn) {
        clearBtn.style.display = '';
        clearBtn.onclick = () => {
          file.value = '';
          if (img) img.src = originalUrl;
          if (hint) hint.textContent = originalText;
          if (rmFlag) rmFlag.value = '0';
          clearBtn.style.display = 'none';
        };
      }
    });
  }

  if (rmBtn) {
    rmBtn.onclick = () => {
      if (img) img.src = "{{ asset('images/avatar-placeholder.png') }}";
      if (hint) hint.textContent = "Current: — (will be removed)";
      if (rmFlag) rmFlag.value = '1';
      if (file) file.value = '';
      if (clearBtn) clearBtn.style.display = 'none';
    };
  }

  // ----------------------------
  // Multi-file inputs hint
  // ----------------------------
  const bindMulti = (idInput, idHint, emptyText) => {
    const i = document.getElementById(idInput);
    const h = document.getElementById(idHint);
    if (!i || !h) return;

    i.addEventListener('change', () => {
      const n = i.files ? i.files.length : 0;
      h.textContent = n
        ? (n === 1 ? i.files[0].name : `${n} Files Selected`)
        : emptyText;
    });
  };

  bindMulti('e_gov_docs', 'e_gov_docs_hint', 'No Files Selected');
  bindMulti('e_add_docs', 'e_add_docs_hint', 'No Files Selected');

  // ----------------------------
  // Dynamic additional requirements rows
  // ----------------------------
  const addBtn = $('#addAdditionalRow');
  const wrap = $('#additionalWrap');

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
})();

  // ----------------------------
  // Shift enable toggle
  // ----------------------------
  const shiftToggle = document.getElementById('shift_enabled');
  const shiftFields = document.getElementById('shiftFields');

  if (shiftToggle && shiftFields) {
    shiftToggle.addEventListener('change', () => {
      shiftFields.style.display = shiftToggle.checked ? 'block' : 'none';

      // Optional: clear fields when disabled (keeps DB clean if you want)
      if (!shiftToggle.checked) {
        const start = document.querySelector('input[name="shift_start"]');
        const end   = document.querySelector('input[name="shift_end"]');
        const days  = document.querySelectorAll('input[name="shift_days[]"]');
        if (start) start.value = '';
        if (end) end.value = '';
        days.forEach(d => d.checked = false);
      }
    });
  }

</script>


@endpush
