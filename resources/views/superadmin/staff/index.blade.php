@extends('superadmin.layout')
@section('title','Staff Management')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-staff.css') }}">

@endpush

@section('content')
@php
  $q = request('q','');
  $role = request('role','all');
  $per = (int) request('per', $staff->perPage());
  $per = in_array($per,[10,20,50,100],true) ? $per : 10;
@endphp

<section class="card">
  <div>
      <div class="card__title">Staff</div>
      <div class="muted text-center">Agents & Managers — Internal access</div>
    </div>
  <div class="card__head">
    

    <div class="actions">
      <form method="get" class="per-form">
        <input type="hidden" name="q" value="{{ $q }}">
        <input type="hidden" name="role" value="{{ $role }}">
        <label class="per-label">Show
          <select name="per" onchange="this.form.submit()">
            @foreach([10,20,50,100] as $n)
              <option value="{{ $n }}" @selected($per==$n)>{{ $n }}</option>
            @endforeach
          </select>
          Entries
        </label>
      </form>

      <form method="get" class="zv-search" role="search">
        <input type="hidden" name="per" value="{{ $per }}">
        <input type="hidden" name="role" value="{{ $role }}">
        <span class="zv-search__icon"><i class="bi bi-search"></i></span>
        <input class="zv-search__input" type="search" name="q" value="{{ $q }}" placeholder="Search Staff…">
        @if($q)
          <a class="zv-search__clear" href="{{ route('superadmin.staff.index', ['role'=>$role,'per'=>$per]) }}" title="Clear">
            <i class="bi bi-x-lg"></i>
          </a>
        @endif
      </form>

      {{-- ✅ must be type="button" --}}
    <a href="{{ route('superadmin.staff.create') }}" class="btn btn-dark bg-black">
  <i class="bi bi-plus-lg"></i> Add Staff
</a>

    </div>
  </div>

  {{-- role pills --}}
  <div class="my-2" style="display:flex; gap:10px; justify-content:center; padding: 0 1rem 1rem;">
    @php
      $tabs = [
        'all' => ['All',$counts['all']],
        'admin' => ['Agents',$counts['admin']],
        'manager' => ['Managers',$counts['manager']],
      ];
    @endphp

    @foreach($tabs as $k=>$t)
      <a class="pill {{ $role===$k ? 'ok' : '' }}"
         href="{{ route('superadmin.staff.index', ['role'=>$k,'q'=>$q,'per'=>$per]) }}">
        {{ $t[0] }} <span class="muted">({{ $t[1] }})</span>
      </a>
    @endforeach
  </div>

  {{-- ✅ show errors so "Save Changes" never feels like nothing --}}
  @if($errors->any())
    <div class="zv-alert zv-alert--danger" style="margin:0 1rem 1rem">
      <strong>Please fix:</strong>
      <ul style="margin:8px 0 0; padding-left:18px">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(session('ok'))
    <div class="zv-alert zv-alert--ok" style="margin:0 1rem 1rem">{{ session('ok') }}</div>
  @endif
  @if(session('error'))
    <div class="zv-alert zv-alert--danger" style="margin:0 1rem 1rem">{{ session('error') }}</div>
  @endif

  <div class="table-wrapp">
    <table class="zv-table">
      <thead>
        <tr>
          <th>ID</th>
    <th>Username</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Passkeys</th>
          <th>Invite</th>
          <th>Status</th>
          <th>Created</th>
          <th style="width:260px">Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($staff as $u)
          @php
            $inv = $u->latestStaffInvite ?? null;

            if(!$inv) {
              $inviteState = '—';
            } elseif($inv->used_at) {
              $inviteState = 'Used';
            } elseif(now()->greaterThan($inv->expires_at)) {
              $inviteState = 'Expired';
            } else {
              $inviteState = 'Pending';
            }

            $locked = (bool)($u->is_locked ?? false);
          @endphp

          <tr>
            <tr>
  {{-- ✅ ID --}}
  <td>#{{ $u->id }}</td>

  {{-- ✅ Username --}}
  <td>{{ $u->username ?? '—' }}</td>
            <td>
              <strong>{{ trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: '—' }}</strong>
            </td>

            <td>{{ $u->email }}</td>

            <td class="text-capitalize">
              <span class="pill {{ $u->role === 'admin' ? 'ok' : '' }}">
                {{ $u->role_label }}
              </span>
            </td>

            <td>
    @if($u->webauthn_credentials_count > 0)
        <span class="pill ok" title="Registered passkeys">
            <i class="bi bi-key"></i> {{ $u->webauthn_credentials_count }}
        </span>
    @else
        <span class="pill" title="No passkeys registered">
            <i class="bi bi-key"></i> 0
        </span>
    @endif
</td>

            <td>
              @if($inviteState === 'Pending')
                <span class="pill ok"><i class="bi bi-envelope-check"></i> Pending</span>
              @elseif($inviteState === 'Expired')
                <span class="pill"><i class="bi bi-envelope-exclamation"></i> Expired</span>
              @elseif($inviteState === 'Used')
                <span class="pill ok"><i class="bi bi-check2-circle"></i> Used</span>
              @else
                <span class="muted">—</span>
              @endif
            </td>

            {{-- ✅ LOCK-ONLY STATUS --}}
            <td>
              @if($u->deleted_at)
                <span class="pill danger" title="Soft Deleted">
                  <i class="bi bi-trash3"></i> Deleted
                </span>
              @elseif($locked)
                <span class="pill danger" title="{{ $u->locked_reason ?: 'locked' }}">
                  <i class="bi bi-shield-lock-fill"></i> Locked
                </span>
              @else
                <span class="pill ok">
                  <i class="bi bi-shield-check"></i> Active
                </span>
              @endif
            </td>

            <td class="muted">{{ optional($u->created_at)->format('Y-m-d') }}</td>

            {{-- ✅ actions: ONLY real submit actions stay inside forms --}}
            <td>
              <div class="row-actions compact" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; align-items:center;">

                <a class="btn icon ghost"
                   href="{{ route('superadmin.staff.info', $u->id) }}"
                   title="View Details / Audit">
                  <i class="bi bi-info-circle"></i>
                </a>

                <a class="btn icon ghost"
   href="{{ route('superadmin.staff.webauthn.index', $u->id) }}"
   title="Manage Passkeys">
    <i class="bi bi-key"></i>
</a>

                @if(!$u->deleted_at)

                  {{-- ✅ Edit (no submit ever) --}}
                 <a class="btn icon ghost"
   href="{{ route('superadmin.staff.edit', $u->id) }}"
   title="Edit">
  <i class="bi bi-pencil-square"></i>
</a>


                  {{-- ✅ Soft Delete (no submit ever) --}}
                  <button type="button" class="btn icon danger"
                          data-open="#modalDelete"
                          data-id="{{ $u->id }}"
                          data-name="{{ trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->email }}"
                          title="Delete (Soft)">
                    <i class="bi bi-trash3"></i>
                  </button>

                  {{-- Resend Invite --}}
                  <form method="post" action="{{ route('superadmin.staff.resendInvite', $u) }}" class="inline">
                    @csrf
                    <button class="btn icon ghost" type="submit" title="Resend Invite">
                      <i class="bi bi-envelope"></i>
                    </button>
                  </form>

                  {{-- Lock / Unlock --}}
                  @if($locked)
                    <form method="post" action="{{ route('superadmin.security.locked_staff.unlock', $u) }}" class="inline">
                      @csrf
                      <button class="btn icon ok" type="submit" title="Unlock">
                        <i class="bi bi-shield-check"></i>
                      </button>
                    </form>
                @else
  <button
    type="button"
    class="btn icon danger js-open-lock-modal"
    data-action="{{ route('superadmin.security.locked_staff.lock', $u) }}"
    data-name="{{ trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->email }}"
    title="Lock"
  >
    <i class="bi bi-shield-lock"></i>
  </button>
@endif

                @else
                  {{-- Restore --}}
                  <form method="post" action="{{ route('superadmin.staff.restore', $u->id) }}" class="inline">
                    @csrf
                    <button class="btn icon ok" type="submit" title="Restore">
                      <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>

        @empty
          <tr><td colspan="9" class="muted ta-center">No staff found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pager">{{ $staff->links() }}</div>
</section>

{{-- =========================
    CREATE MODAL
========================= --}}
<div id="modalCreate" class="modal" aria-hidden="true">
  <div class="modal__dialog">
    <form method="post"
          action="{{ route('superadmin.staff.store') }}"
          class="modal__card"
          enctype="multipart/form-data">
      @csrf

      <div class="modal__head">
        <div class="title">Add Staff</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body grid2">
        <label>First Name
          <input type="text" name="first_name" required maxlength="60">
        </label>

        <label>Last Name
          <input type="text" name="last_name" required maxlength="60">
        </label>

        <label>Contact Number
          <input type="text" name="phone" required maxlength="30">
        </label>

        <label>Email
          <input type="email" name="email" required maxlength="255">
        </label>

        {{-- Profile Picture --}}
        <div class="col-span">
          <div class="label">Profile Picture</div>

          <div class="filebox">
            <div class="filebox__left">
              <i class="bi bi-image"></i>
              <div>
                <div class="filebox__title">Upload profile photo</div>
                <div class="filebox__sub muted">JPG, PNG, WEBP · Max 5MB</div>
              </div>
            </div>

            <label class="filebox__btn">
              Choose File
              <input class="hidden-file" type="file" name="profile_pic" id="c_profile_pic" accept="image/*">
            </label>
          </div>

          <div class="filehint text-capitalize" id="c_profile_pic_hint">No File Selected</div>
        </div>

        <label class="col-span">Role
          <select name="role" required>
            <option value="admin">Agent</option>
            <option value="manager">Manager</option>
          </select>
        </label>

        <div class="col-span" style="border-top:1px solid rgba(0,0,0,.10); padding-top:12px;"></div>

        <label>Emergency Contact Name
          <input type="text" name="emergency_contact_name" required maxlength="120">
        </label>

        <label>Emergency Contact Number
          <input type="text" name="emergency_contact_phone" required maxlength="40">
        </label>

        <label>Next Of Kin Name <span class="muted">(Optional)</span>
          <input type="text" name="next_of_kin_name" maxlength="120">
        </label>

        <label>Next Of Kin Number <span class="muted">(Optional)</span>
          <input type="text" name="next_of_kin_phone" maxlength="40">
        </label>

        <div class="col-span" style="border-top:1px solid rgba(0,0,0,.10); padding-top:12px;"></div>

        {{-- Government ID Docs --}}
        <div class="col-span">
          <div class="label text-capitalize">Government ID Documents (upload multiple)</div>

          <div class="filebox">
            <div class="filebox__left">
              <i class="bi bi-file-earmark-text"></i>
              <div>
                <div class="filebox__title text-capitalize">Upload government documents</div>
                <div class="filebox__sub muted">Images / PDF · Max 50MB each · Multiple Allowed</div>
              </div>
            </div>

            <label class="filebox__btn">
              Upload Files
              <input class="hidden-file"
                     type="file"
                     name="government_id_docs[]"
                     id="c_gov_docs"
                     multiple
                     accept="image/*,application/pdf">
            </label>
          </div>

          <div class="filehint" id="c_gov_docs_hint">No Files Selected</div>
          <div class="muted text-capitalize" style="margin-top:6px">
            Passport, National ID front/back, driving license, etc.
          </div>
        </div>

        <div class="col-span" style="border-top:1px solid rgba(0,0,0,.10); padding-top:12px;"></div>

        {{-- Additional requirement --}}
        

        {{-- Additional requirement --}}
<div class="col-span">
  <div class="label text-capitalize">
    Additional Government Requirements
    <span class="muted">(add as many as needed)</span>
  </div>

  <div id="additionalWrap" style="margin-top:8px; display:grid; gap:10px">
    <div class="grid2 additional-row" style="gap:10px; align-items:center">
      <input type="text"
             name="additional_label[]"
             placeholder="Label e.g. National Insurance Number"
             maxlength="160">

      <div style="display:flex; gap:8px">
        <input type="text"
               name="additional_value[]"
               placeholder="Value e.g. QQ123456C"
               maxlength="2000"
               style="flex:1">

        <button type="button" class="btn danger ghost remove-row" title="Remove">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  </div>

  <button type="button"
          class="btn ghost text-capitalize"
          id="addAdditionalRow"
          style="margin-top:10px">
    <i class="bi bi-plus-lg"></i> Add another requirement
  </button>

  <div class="muted small" style="margin-top:6px">
    Example: National Insurance Number, Tax ID, Residence Permit, etc.
  </div>
</div>

{{-- Additional Documents (optional) --}}
<div class="col-span" style="margin-top:12px">
  <div class="label text-capitalize">Additional Documents (Optional)</div>

  <div class="filebox" style="margin-top:8px">
    <div class="filebox__left">
      <i class="bi bi-file-earmark"></i>
      <div>
        <div class="filebox__title">Upload additional documents</div>
        <div class="filebox__sub muted">Images / PDF · Multiple allowed</div>
      </div>
    </div>

    <label class="filebox__btn">
      Upload Files
      <input class="hidden-file"
             type="file"
             name="additional_docs[]"
             id="c_add_docs"
             multiple
             accept="image/*,application/pdf">
    </label>
  </div>

  <div class="filehint" id="c_add_docs_hint">No Files Selected</div>
</div>

<p class="muted col-span text-capitalize" style="margin:0">
  A secure password setup link will be sent by email (valid for 24 hours).
</p>

      </div>

      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn bg-black">Create</button>
      </div>
    </form>
  </div>
</div>

{{-- =========================
    EDIT MODAL
========================= --}}
{{-- =========================
    EDIT MODAL (FIXED: no nested forms)
========================= --}}
<div id="modalEdit" class="modal" aria-hidden="true"
     data-update-url-template="{{ route('superadmin.staff.update', ['user'=>'__ID__']) }}"
     data-lock-url-template="{{ route('superadmin.security.locked_staff.lock', ['user'=>'__ID__']) }}"
     data-unlock-url-template="{{ route('superadmin.security.locked_staff.unlock', ['user'=>'__ID__']) }}">

  <div class="modal__dialog">

    {{-- MAIN EDIT FORM --}}
    <form method="post" id="editForm" class="modal__card" enctype="multipart/form-data">
      @csrf @method('PUT')

      <div class="modal__head">
        <div class="title">Edit Staff</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body grid2">
        <div class="col-span">
          <div class="muted small" style="margin-bottom:6px">Email (locked)</div>
          <div id="e_email" style="padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:12px;background:#fff">—</div>
          <div class="muted small" style="margin-top:6px">
            Email cannot be changed to protect identity, invites, and audit logs.
          </div>
        </div>

        <label>First name
          <input type="text" name="first_name" id="e_first" required maxlength="60">
        </label>

        <label>Last name
          <input type="text" name="last_name" id="e_last" maxlength="60">
        </label>

        <label>Phone
          <input type="text" name="phone" id="e_phone" required maxlength="30">
        </label>

        <div class="col-span">
          <div class="muted small" style="margin-bottom:8px">Profile Photo</div>

          <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
            <div style="width:64px;height:64px;border-radius:16px;overflow:hidden;border:1px solid rgba(0,0,0,.12);background:#fff">
              <img id="e_avatar_img"
                   src="{{ asset('images/avatar-placeholder.png') }}"
                   alt="avatar"
                   style="width:100%;height:100%;object-fit:cover;display:block">
            </div>

            <div style="flex:1; min-width:220px">
              <div class="muted small" id="e_avatar_name">Current: —</div>

              <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap">
                <label class="btn ghost" style="cursor:pointer; margin:0">
                  <i class="bi bi-upload"></i> Choose New Photo
                  <input class="hidden-file" type="file"
                         name="profile_pic"
                         id="e_avatar_file"
                         accept="image/*"
                         style="display:none">
                </label>

                <button type="button" class="btn danger" id="e_avatar_remove_btn">
                  <i class="bi bi-trash3"></i> Remove Photo
                </button>

                <button type="button" class="btn ghost" id="e_avatar_clear_new_btn" style="display:none">
                  Clear New
                </button>
              </div>

              <div class="muted small" style="margin-top:8px">
                Upload JPG/PNG/WEBP. Removing will delete the existing profile photo.
              </div>
            </div>
          </div>

          <input type="hidden" name="remove_avatar" id="e_remove_avatar" value="0">
        </div>

        <label class="col-span">Role
          <select name="role" id="e_role" required>
            <option value="admin">Agent</option>
            <option value="manager">Manager</option>
          </select>
        </label>

        <div class="col-span" style="border-top:1px solid rgba(0,0,0,.10); padding-top:12px;"></div>

        <label>Emergency Contact Name
          <input type="text" name="emergency_contact_name" id="e_em_name" required maxlength="120">
        </label>

        <label>Emergency Contact Phone
          <input type="text" name="emergency_contact_phone" id="e_em_phone" required maxlength="40">
        </label>

        <label>Next of Kin Name <span class="muted">(Optional)</span>
          <input type="text" name="next_of_kin_name" id="e_kin_name" maxlength="120">
        </label>

        <label>Next of Kin Phone <span class="muted">(Optional)</span>
          <input type="text" name="next_of_kin_phone" id="e_kin_phone" maxlength="40">
        </label>

        <div class="col-span" style="border-top:1px solid rgba(0,0,0,.10); padding-top:12px;"></div>

        {{-- SECURITY (buttons only; forms live OUTSIDE editForm to avoid nesting) --}}
        <div class="col-span" id="e_lock_wrap">
          <div class="muted small" style="margin-bottom:8px">Security</div>

          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
            <span class="pill" id="e_lock_badge">
              <i class="bi bi-shield-check"></i> Active
            </span>

            <button type="button" class="btn danger" id="e_lock_btn" style="display:none">
              <i class="bi bi-shield-lock"></i> Lock
            </button>

            <button type="button" class="btn ok" id="e_unlock_btn" style="display:none">
              <i class="bi bi-shield-check"></i> Unlock
            </button>
          </div>

          <div id="e_lock_reason_wrap" style="margin-top:10px; display:none">
            <label style="display:block">
              Lock reason (required)
              <input type="text" id="e_lock_reason" maxlength="255"
                     placeholder="e.g. suspicious activity, manual_lock">
            </label>

            <div class="muted small" style="margin-top:6px">
              This will immediately block staff access. This is separate from “Save Changes”.
            </div>
          </div>

          <div class="muted small" id="e_lock_note" style="margin-top:10px; display:none"></div>
        </div>

      </div>

      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn bg-black">Save Changes</button>
      </div>
    </form>

    {{-- OUTSIDE editForm: no nesting --}}
    <form method="post" id="e_lock_form" style="display:none">
      @csrf
      <input type="hidden" name="reason" id="e_lock_reason_hidden" value="">
    </form>

    <form method="post" id="e_unlock_form" style="display:none">
      @csrf
    </form>

  </div>
</div>


     

{{-- =========================
    DELETE MODAL
========================= --}}
<div id="modalDelete" class="modal" aria-hidden="true"
     data-destroy-url-template="{{ route('superadmin.staff.destroy', ['id'=>'__ID__']) }}">
  <div class="modal__dialog">
    <form method="post" id="deleteForm" class="modal__card" enctype="multipart/form-data">
      @csrf @method('DELETE')

      <div class="modal__head">
        <div class="title">Soft Delete Staff</div>
        <button type="button" class="x" data-close>×</button>
      </div>

      <div class="modal__body">
        <div class="zv-alert zv-alert--danger text-capitalize" style="margin-bottom:12px">
          You are about to soft delete: <strong id="d_name">—</strong>
          <div class="muted text-capitalize" style="margin-top:6px">This action is reversible via restore.</div>
        </div>

        <label style="display:block">
          Reason (required)
          <textarea name="reason" rows="6" required style="width:100%; resize:vertical"
                    placeholder="Write The Full Reason For Audit (No Word Limit)"></textarea>
        </label>

       <label class="col-span" style="display:block; margin-top:12px">
  <div class="label text-capitalize">Upload Audit Images (unlimited)</div>

  <div class="filebox">
    <div class="filebox__left">
      <i class="bi bi-images"></i>
      <div>
        <div class="filebox__title text-capitalize">Upload audit images</div>
        <div class="filebox__sub muted">Images · Multiple Allowed</div>
      </div>
    </div>

    <label class="filebox__btn">
      Upload Files
      <input class="hidden-file"
             type="file"
             name="images[]"
             id="d_images"
             multiple
             accept="image/*">
    </label>
  </div>

  <div class="filehint" id="d_images_hint">No Files Selected</div>

  <div class="muted text-capitalize" style="margin-top:6px">
    You can select multiple images. Repeat uploads if needed.
  </div>
</label>


       
      </div>

      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="submit" class="btn danger">Yes, Soft Delete</button>
      </div>
    </form>
  </div>
</div>




<div class="zv-modal" id="lockReasonModal" aria-hidden="true">
    <div class="zv-modal__backdrop"></div>

    <div class="zv-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="lockReasonModalTitle">
        <div class="zv-modal__head">
            <h3 id="lockReasonModalTitle" class="zv-modal__title">Lock Staff Account</h3>
            <button type="button" class="zv-modal__close text-capitalize" id="closeLockReasonModal" aria-label="Close modal">
                &times;
            </button>
        </div>

        <form method="post" id="lockReasonForm">
            @csrf

            <div class="zv-modal__body">
                <p class="zv-modal__text text-capitalize">
                    Please enter the reason for locking
                    <strong id="lockTargetName">this account</strong>.
                </p>

                <div class="zv-field">
                    <label for="lock_reason">
                        Reason
                        <span class="zv-required">*</span>
                    </label>

                    <textarea
                        id="lock_reason"
                        name="reason"
                        rows="4"
                        class="zv-textarea"
                        placeholder="Enter At Least 3 Words..."
                        required
                    >{{ old('reason') }}</textarea>

                    <div class="zv-word-help">
                        Minimum 3 Words Required.
                    </div>

                    <div class="zv-count-error" id="lockReasonError">
                        Reason Must Be At Least 3 Words.
                    </div>
                </div>
            </div>

            <div class="zv-modal__foot">
                <button type="button" class="btn ghost small" id="cancelLockReasonModal">Cancel</button>
                <button type="submit" class="btn danger small" id="confirmLockBtn">Confirm Lock</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
  const body = document.body;


  // ===== Additional Government Requirements =====
const addBtn = document.getElementById('addAdditionalRow');
const wrap   = document.getElementById('additionalWrap');

if (addBtn && wrap) {
  addBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'grid2 additional-row';
    row.style.gap = '10px';
    row.style.alignItems = 'center';

    row.innerHTML = `
      <input type="text"
             name="additional_label[]"
             placeholder="Label e.g. National Insurance Number"
             maxlength="160">

      <div style="display:flex; gap:8px">
        <input type="text"
               name="additional_value[]"
               placeholder="Value e.g. QQ123456C"
               maxlength="2000"
               style="flex:1">

        <button type="button" class="btn danger ghost remove-row">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    `;

    wrap.appendChild(row);
  });

  wrap.addEventListener('click', (e) => {
    if (e.target.closest('.remove-row')) {
      const rows = wrap.querySelectorAll('.additional-row');
      if (rows.length > 1) {
        e.target.closest('.additional-row').remove();
      }
    }
  });
}


  function openModal(el){
    if(!el) return;
    el.classList.add('open');
    el.setAttribute('aria-hidden','false');
    body.classList.add('modal-open');
  }
  function closeModal(el){
    if(!el) return;
    el.classList.remove('open');
    el.setAttribute('aria-hidden','true');
    body.classList.remove('modal-open');
  }

  function setHintText(hintEl, files, emptyText){
    if(!hintEl) return;
    const n = files ? files.length : 0;
    if(!n){ hintEl.textContent = emptyText; return; }
    if(n === 1){ hintEl.textContent = files[0].name; return; }
    hintEl.textContent = `${n} files selected`;
  }

  function bindFileHint(inputId, hintId, emptyText){
    const input = document.getElementById(inputId);
    const hint  = document.getElementById(hintId);
    if(!input || !hint) return;

    input.addEventListener('change', () => {
      setHintText(hint, input.files, emptyText);
    });

    const modal = input.closest('.modal');
    if(modal){
      modal.addEventListener('modal:reset', () => {
        input.value = '';
        hint.textContent = emptyText;
      });
    }
  }

 bindFileHint('c_profile_pic', 'c_profile_pic_hint', 'No File Selected');
bindFileHint('c_gov_docs', 'c_gov_docs_hint', 'No Files Selected');
bindFileHint('c_add_docs', 'c_add_docs_hint', 'No Files Selected');
bindFileHint('d_images', 'd_images_hint', 'No Files Selected'); // ✅ NEW


const modalDelete = document.getElementById('modalDelete');
if (modalDelete) {
  modalDelete.addEventListener('modal:reset', () => {
    const ta = modalDelete.querySelector('textarea[name="reason"]');
    if (ta) ta.value = '';

    const f = document.getElementById('d_images');
    const h = document.getElementById('d_images_hint');
    if (f) f.value = '';
    if (h) h.textContent = 'No Files Selected';
  });
}



  // OPEN MODAL HANDLER
  document.querySelectorAll('[data-open]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const modalSel = btn.getAttribute('data-open');
      const modal = document.querySelector(modalSel);
      openModal(modal);

      // RESET create each open
      if(modal?.id === 'modalCreate'){
        modal.dispatchEvent(new Event('modal:reset'));
      }

      // DELETE modal wiring
      // DELETE modal wiring (✅ uses same reset system)
if(modal?.id === 'modalDelete'){
  const id   = btn.dataset.id;
  const name = btn.dataset.name || '—';
  const urlT = modal.getAttribute('data-destroy-url-template');

  const deleteForm = document.querySelector('#deleteForm');
  deleteForm.action = urlT.replace('__ID__', id);

  document.querySelector('#d_name').textContent = name;

  // ✅ reset textarea + files + hint
  modal.dispatchEvent(new Event('modal:reset'));
}


      // EDIT modal wiring
      if(modal?.id === 'modalEdit'){
        const id = btn.dataset.id;

        // update action
        const updateT = modal.getAttribute('data-update-url-template');
        document.querySelector('#editForm').action = updateT.replace('__ID__', id);

        // fields
        document.querySelector('#e_first').value = btn.dataset.first || '';
        document.querySelector('#e_last').value  = btn.dataset.last || '';
        document.querySelector('#e_phone').value = btn.dataset.phone || '';
        document.querySelector('#e_role').value  = btn.dataset.role || 'admin';

        const emailBox = document.querySelector('#e_email');
        if (emailBox) emailBox.textContent = btn.dataset.email || '—';

        document.querySelector('#e_em_name').value   = btn.dataset.emName || '';
        document.querySelector('#e_em_phone').value  = btn.dataset.emPhone || '';
        document.querySelector('#e_kin_name').value  = btn.dataset.kinName || '';
        document.querySelector('#e_kin_phone').value = btn.dataset.kinPhone || '';

        // avatar
        const img    = document.querySelector('#e_avatar_img');
        const nameEl = document.querySelector('#e_avatar_name');
        const fileEl = document.querySelector('#e_avatar_file');
        const rmBtn  = document.querySelector('#e_avatar_remove_btn');
        const clrBtn = document.querySelector('#e_avatar_clear_new_btn');
        const rmFlg  = document.querySelector('#e_remove_avatar');

        const originalUrl  = btn.dataset.avatar || '';
        const originalPath = btn.dataset.avatarPath || '';

        if (img) img.src = originalUrl || img.src;
        if (nameEl) nameEl.textContent = originalPath ? `Current: ${originalPath}` : 'Current: —';
        if (fileEl) fileEl.value = '';
        if (rmFlg) rmFlg.value = '0';
        if (clrBtn) clrBtn.style.display = 'none';

        if (rmBtn) {
          rmBtn.onclick = () => {
            if (img) img.src = "{{ asset('images/avatar-placeholder.png') }}";
            if (nameEl) nameEl.textContent = 'Current: — (will be removed)';
            if (rmFlg) rmFlg.value = '1';
            if (fileEl) fileEl.value = '';
            if (clrBtn) clrBtn.style.display = 'none';
          };
        }

        if (fileEl) {
          fileEl.onchange = () => {
            const f = fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
            if (!f) return;

            if (rmFlg) rmFlg.value = '0';
            if (nameEl) nameEl.textContent = `New: ${f.name}`;

            const url = URL.createObjectURL(f);
            if (img) img.src = url;

            if (clrBtn) {
              clrBtn.style.display = '';
              clrBtn.onclick = () => {
                fileEl.value = '';
                if (img) img.src = originalUrl || "{{ asset('images/avatar-placeholder.png') }}";
                if (nameEl) nameEl.textContent = originalPath ? `Current: ${originalPath}` : 'Current: —';
                if (rmFlg) rmFlg.value = '0';
                clrBtn.style.display = 'none';
              };
            }
          };
        }

        // LOCK / UNLOCK
        const isLocked = String(btn.dataset.locked || '0') === '1';
        const lockedReason = btn.dataset.lockedReason || '';

        const badge = document.querySelector('#e_lock_badge');
        const lockBtn = document.querySelector('#e_lock_btn');
        const unlockBtn = document.querySelector('#e_unlock_btn');
        const reasonWrap = document.querySelector('#e_lock_reason_wrap');
        const reasonInput = document.querySelector('#e_lock_reason');
        const note = document.querySelector('#e_lock_note');

        const lockForm = document.querySelector('#e_lock_form');
        const unlockForm = document.querySelector('#e_unlock_form');
        const reasonHidden = document.querySelector('#e_lock_reason_hidden');

        const lockUrlT = modal.getAttribute('data-lock-url-template');
        const unlockUrlT = modal.getAttribute('data-unlock-url-template');

        if (lockForm && lockUrlT) lockForm.action = lockUrlT.replace('__ID__', id);
        if (unlockForm && unlockUrlT) unlockForm.action = unlockUrlT.replace('__ID__', id);

        // reset
        if (reasonInput) reasonInput.value = '';
        if (reasonHidden) reasonHidden.value = '';
        if (reasonWrap) reasonWrap.style.display = 'none';
        if (note) { note.style.display = 'none'; note.textContent = ''; }

        function setLockButtonMode(mode){
          if (!lockBtn) return;
          lockBtn.dataset.mode = mode;
          lockBtn.innerHTML = (mode === 'confirm')
            ? `<i class="bi bi-shield-lock-fill"></i> Confirm Lock`
            : `<i class="bi bi-shield-lock"></i> Lock`;
        }

        if (isLocked) {
          if (badge) badge.innerHTML = `<i class="bi bi-shield-lock-fill"></i> Locked`;
          badge?.classList.remove('ok'); badge?.classList.add('danger');

          if (lockBtn) lockBtn.style.display = 'none';
          if (unlockBtn) unlockBtn.style.display = '';

          if (note) {
            note.style.display = '';
            note.textContent = lockedReason ? `Locked reason: ${lockedReason}` : 'This account is locked.';
          }
        } else {
          if (badge) badge.innerHTML = `<i class="bi bi-shield-check"></i> Active`;
          badge?.classList.remove('danger'); badge?.classList.add('ok');

          if (unlockBtn) unlockBtn.style.display = 'none';
          if (lockBtn) lockBtn.style.display = '';
          setLockButtonMode('lock');
        }

        if (lockBtn) {
          lockBtn.onclick = () => {
            const mode = lockBtn.dataset.mode || 'lock';

            if (mode === 'lock') {
              if (reasonWrap) reasonWrap.style.display = '';
              reasonInput?.focus();
              setLockButtonMode('confirm');
              return;
            }

            const r = (reasonInput?.value || '').trim();
            if (!r) { reasonInput?.focus(); return; }

            if (reasonHidden) reasonHidden.value = r;
            lockForm?.submit();
          };
        }

        if (unlockBtn) {
          unlockBtn.onclick = () => {
            unlockForm?.submit();
          };
        }
      }
    });
  });

  // CLOSE handlers
  document.querySelectorAll('[data-close]').forEach(x=>{
    x.addEventListener('click', ()=> closeModal(x.closest('.modal')));
  });

  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('click', (e)=>{ if(e.target===m) closeModal(m); });
  });
</script>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('lockReasonModal');
    const form = document.getElementById('lockReasonForm');
    const reasonInput = document.getElementById('lock_reason');
    const targetName = document.getElementById('lockTargetName');
    const errorBox = document.getElementById('lockReasonError');

    const openButtons = document.querySelectorAll('.js-open-lock-modal');
    const closeBtn = document.getElementById('closeLockReasonModal');
    const cancelBtn = document.getElementById('cancelLockReasonModal');
    const backdrop = modal?.querySelector('.zv-modal__backdrop');

    function wordCount(value) {
        return (value || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .length;
    }

    function validateReason() {
        const count = wordCount(reasonInput.value);
        const valid = count >= 3;
        errorBox.style.display = valid ? 'none' : 'block';
        return valid;
    }

    function openLockModal(action, name) {
        form.setAttribute('action', action);
        targetName.textContent = name || 'this account';
        reasonInput.value = '';
        errorBox.style.display = 'none';
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('zv-modal-open');

        setTimeout(() => {
            reasonInput.focus();
        }, 50);
    }

    function closeLockModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('zv-modal-open');
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', function () {
            openLockModal(this.dataset.action, this.dataset.name);
        });
    });

    closeBtn?.addEventListener('click', closeLockModal);
    cancelBtn?.addEventListener('click', closeLockModal);
    backdrop?.addEventListener('click', closeLockModal);

    reasonInput?.addEventListener('input', validateReason);

    form?.addEventListener('submit', function (e) {
        if (!validateReason()) {
            e.preventDefault();
            reasonInput.focus();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeLockModal();
        }
    });
});
</script>
@endpush
