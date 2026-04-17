@extends('layouts.admin')
@section('title','My Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-profile.css') }}">
@endpush

@section('content')
@php
  $u = $user;

  $fullName = trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: '—';
  $avatarUrl = $u->avatar_path ? asset('storage/'.$u->avatar_path) : asset('images/avatar-placeholder.png');
  $locked = (bool)($u->is_locked ?? false);

  // viewer timezone (as per your preference: blade conversion)
    $viewerTz = auth()->user()->timezone ?? config('app.timezone');

  $tzfmt = function($dt) use ($viewerTz){
    if(!$dt) return '—';
    return $dt->copy()->setTimezone('UTC')->setTimezone($viewerTz)->format('M d, Y · H:i');
  };

  // ✅ started date + manager/team (from controller)
  $startedAt = $u->started_at ?? $u->created_at; // fallback

  $managerName = (isset($myManager) && $myManager)
      ? (trim(($myManager->first_name ?? '').' '.($myManager->last_name ?? '')) ?: ($myManager->username ?? '—'))
      : '—';

  $teamName = (isset($myTeam) && $myTeam)
      ? ($myTeam->name ?? '—')
      : '—';

  // Documents (view-only)
  $govDocs = \App\Models\StaffDocument::query()
      ->where('user_id', $u->id)
      ->where('category','government_id')
      ->whereNotNull('file_path')
      ->orderByDesc('id')->get();

  $additionalRequirements = \App\Models\StaffDocument::query()
      ->where('user_id', $u->id)
      ->where('category','additional')
      ->whereNotNull('value_text')
      ->orderByDesc('id')->get();

  $additionalDocs = \App\Models\StaffDocument::query()
      ->where('user_id', $u->id)
      ->where('category','additional')
      ->whereNotNull('file_path')
      ->orderByDesc('id')->get();
@endphp

<div class="ap-wrap">

  <div class="ap-head">
    <div class="ap-head__left">
      <div class="ap-title">My Profile</div>
      <div class="ap-sub text-capitalize">
        View your details and documents. You can only update <strong>photo</strong> and <strong>password</strong>.
      </div>

      <div class="ap-badges">
        @if($locked)
          <span class="ap-pill danger"><i class="bi bi-shield-lock-fill"></i> Locked</span>
        @else
          <span class="ap-pill ok"><i class="bi bi-shield-check"></i> Active</span>
        @endif

        <span class="ap-pill"><i class="bi bi-person-badge"></i> {{ $u->role === 'admin' ? 'Agent' : 'Manager' }}</span>
      </div>
    </div>

    <div class="ap-head__right d-flex gap-2">
  

  <a class="btn ghost" href="{{ url('admin') }}">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>
  </div>

  

  @if($locked)
    <div class="ap-lock">
      <i class="bi bi-shield-lock-fill"></i>
      Your account is locked. Profile changes are disabled.
    </div>
  @endif

  @if($errors->any())
    <div class="ap-alert danger">
      <div class="ap-alert__t"><strong>Please Fix:</strong></div>
      <ul class="ap-alert__list text-capitalize">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  @if(session('ok')) <div class="ap-alert ok">{{ session('ok') }}</div> @endif
  @if(session('error')) <div class="ap-alert danger">{{ session('error') }}</div> @endif

  <div class="ap-grid">

    {{-- LEFT: View-only (details + documents) --}}
    <div class="ap-stack">

      {{-- Account details --}}
      <div class="ap-card">
        <div class="ap-card__head">
          <div>
            <div class="h">Account Details</div>
            <div class="m">View Only</div>
          </div>
          <div class="ap-mini">
            <div class="ap-mini__k">Created</div>
            <div class="ap-mini__v">{{ $tzfmt($u->created_at) }}</div>
          </div>
        </div>

       <div class="ap-kv">
  <div class="k">User ID</div><div class="v">#{{ $u->id }}</div>

  <div class="k">Real Name</div><div class="v">{{ $fullName }}</div>

  <div class="k">Assigned Username</div><div class="v">{{ $u->username ?: '—' }}</div>

  <div class="k">Role</div><div class="v text-capitalize">{{ $u->role_label ?: '—' }}</div>

  <div class="k">Manager</div><div class="v">{{ $managerName }}</div>

  <div class="k">Team</div><div class="v">{{ $teamName }}</div>

  <div class="k">Started With Business</div><div class="v">{{ $tzfmt($startedAt) }}</div>

  <div class="k">Time With Business</div>
  <div class="v">
    <span id="tenure_text" data-start="{{ optional($startedAt)->toIso8601String() }}">
      {{ $startedAt ? $startedAt->diffForHumans(null, true) : '—' }}
    </span>
  </div>

  <div class="k">Email</div><div class="v">{{ $u->email ?: '—' }}</div>
  <div class="k">Phone</div><div class="v">{{ $u->phone ?: '—' }}</div>

  <div class="k">Emergency Contact</div>
  <div class="v">{{ $u->emergency_contact_name ?: '—' }} · {{ $u->emergency_contact_phone ?: '—' }}</div>

  <div class="k">Next Of Kin Name</div>
<div class="v">{{ $u->next_of_kin_name ?: '—' }}</div>

<div class="k">Next Of Kin Contact Number</div>
<div class="v">{{ $u->next_of_kin_phone ?: '—' }}</div>

<div class="k">Next Of Kin Address</div>
<div class="v">{{ $u->next_of_kin_address ?: '—' }}</div>
</div>
        </div>
      </div>

      {{-- Documents --}}
      <div class="ap-card">
        <div class="ap-card__head">
          <div>
            <div class="h">Verification Documents</div>
            <div class="m">View Only</div>
          </div>
          <div class="ap-chipRow">
            <span class="ap-chip"><i class="bi bi-file-earmark-text"></i> Government: {{ $govDocs->count() }}</span>
            <span class="ap-chip"><i class="bi bi-card-text"></i> Requirements: {{ $additionalRequirements->count() }}</span>
            <span class="ap-chip"><i class="bi bi-folder2-open"></i> Additional: {{ $additionalDocs->count() }}</span>
          </div>
        </div>

        <div class="ap-docsWrap">

          {{-- Government ID docs --}}
          <div class="ap-docBlock">
            <div class="ap-docBlock__title">
              <i class="bi bi-shield-check"></i> Government ID Documents
            </div>

            @if($govDocs->count())
              <div class="ap-docGrid">
                @foreach($govDocs as $d)
                  @php
                    $url = $d->file_path ? asset('storage/'.$d->file_path) : null;
                    $name = $d->file_original_name ?: ($d->label ?: 'Document');
                    $mime = strtolower((string)($d->file_mime ?? ''));
                    $isPdf = str_contains($mime,'pdf') || str_ends_with(strtolower((string)$name),'.pdf');
                    $size = $d->file_size ? number_format($d->file_size/1024, 0).' KB' : null;
                  @endphp

                  <a class="ap-doc" href="{{ $url }}" target="_blank" rel="noopener">
                    <div class="ap-doc__ico">
                      @if($isPdf)
                        <i class="bi bi-file-earmark-pdf"></i>
                      @else
                        <i class="bi bi-file-earmark-image"></i>
                      @endif
                    </div>
                    <div class="ap-doc__meta">
                      <div class="ap-doc__name" title="{{ $name }}">{{ $name }}</div>
                      <div class="ap-doc__sub">
                        {{ $d->file_mime ?: 'file' }}
                        @if($size) · {{ $size }} @endif
                        · {{ $tzfmt($d->created_at) }}
                      </div>
                    </div>
                    <div class="ap-doc__go"><i class="bi bi-box-arrow-up-right"></i></div>
                  </a>
                @endforeach
              </div>
            @else
              <div class="ap-empty text-capitalize">No government ID documents uploaded.</div>
            @endif
          </div>

          <div class="ap-sep"></div>

          {{-- Additional requirements (text) --}}
          <div class="ap-docBlock">
            <div class="ap-docBlock__title">
              <i class="bi bi-card-text"></i> Additional Requirements
            </div>

            @if($additionalRequirements->count())
              <div class="ap-reqList">
                @foreach($additionalRequirements as $r)
                  <div class="ap-req">
                    <div class="ap-req__top">
                      <div class="ap-req__label">{{ $r->label ?: 'Requirement' }}</div>
                      <div class="ap-req__date">{{ $tzfmt($r->created_at) }}</div>
                    </div>
                    <div class="ap-req__val">{{ $r->value_text ?: '—' }}</div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="ap-empty text-capitalize">No additional requirements provided.</div>
            @endif
          </div>

          <div class="ap-sep"></div>

          {{-- Additional docs files --}}
          <div class="ap-docBlock">
            <div class="ap-docBlock__title">
              <i class="bi bi-folder2-open"></i> Additional Documents
            </div>

            @if($additionalDocs->count())
              <div class="ap-docGrid">
                @foreach($additionalDocs as $d)
                  @php
                    $url = $d->file_path ? asset('storage/'.$d->file_path) : null;
                    $name = $d->file_original_name ?: ($d->label ?: 'Document');
                    $mime = strtolower((string)($d->file_mime ?? ''));
                    $isPdf = str_contains($mime,'pdf') || str_ends_with(strtolower((string)$name),'.pdf');
                    $size = $d->file_size ? number_format($d->file_size/1024, 0).' KB' : null;
                  @endphp

                  <a class="ap-doc" href="{{ $url }}" target="_blank" rel="noopener">
                    <div class="ap-doc__ico">
                      @if($isPdf)
                        <i class="bi bi-file-earmark-pdf"></i>
                      @else
                        <i class="bi bi-file-earmark"></i>
                      @endif
                    </div>
                    <div class="ap-doc__meta">
                      <div class="ap-doc__name" title="{{ $name }}">{{ $name }}</div>
                      <div class="ap-doc__sub">
                        {{ $d->file_mime ?: 'file' }}
                        @if($size) · {{ $size }} @endif
                        · {{ $tzfmt($d->created_at) }}
                      </div>
                    </div>
                    <div class="ap-doc__go"><i class="bi bi-box-arrow-up-right"></i></div>
                  </a>
                @endforeach
              </div>
            @else
              <div class="ap-empty text-capitalize">No additional documents uploaded.</div>
            @endif
          </div>

        </div>
      </div>

    </div>

    {{-- RIGHT: Editable (photo + password only) --}}
    <div class="ap-card ap-sticky">
      <div class="ap-card__head">
        <div>
          <div class="h">Update Profile</div>
          <div class="m">Photo + Password</div>
        </div>
      </div>

      <form method="post" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Photo --}}
        <div class="ap-section">
          <div class="ap-sec-title">Profile Photo</div>

          <div class="ap-avatarRow">
            <div class="ap-avatar">
              <img id="ap_avatar_img" src="{{ $avatarUrl }}" alt="avatar">
            </div>

            <div class="ap-avatarActions">
              <label class="btn ghost ap-fileBtn" style="{{ $locked ? 'pointer-events:none;opacity:.55' : '' }}">
                <i class="bi bi-image"></i> Choose Photo
                <input type="file" name="profile_pic" id="ap_profile_pic" accept="image/*" hidden {{ $locked ? 'disabled' : '' }}>
              </label>

              <button type="button" class="btn danger ghost" id="ap_remove_btn"
                      {{ $locked ? 'disabled style=opacity:.55' : '' }}>
                <i class="bi bi-trash3"></i> Remove
              </button>

              <div class="ap-hint" id="ap_file_hint">
                {{ $u->avatar_path ? ('Current: '.$u->avatar_path) : 'No photo uploaded' }}
              </div>

              <input type="hidden" name="remove_avatar" id="ap_remove_avatar" value="0">
            </div>
          </div>
        </div>

        <div class="ap-divider"></div>

        {{-- Password --}}
        <div class="ap-section">
          <div class="ap-sec-title">Change Password</div>
          <div class="ap-sec-sub text-capitalize">You must enter your current password.</div>

          <label class="ap-label">Current Password
            <input type="password" name="current_password" autocomplete="current-password" {{ $locked ? 'disabled' : '' }}>
          </label>

          <label class="ap-label">New Password
            <input type="password" name="new_password" autocomplete="new-password" {{ $locked ? 'disabled' : '' }}>
          </label>

          <label class="ap-label">Confirm New Password
            <input type="password" name="new_password_confirmation" autocomplete="new-password" {{ $locked ? 'disabled' : '' }}>
          </label>

          <div class="ap-actions">
            <button class="btn bg-black text-white" type="submit" {{ $locked ? 'disabled style=opacity:.55' : '' }}>
              Save Changes
            </button>
          </div>

          <div class="ap-footNote text-capitalize">
            <i class="bi bi-info-circle"></i>
            For security, all other fields are locked and managed by Super Admin.
          </div>
        </div>
      </form>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
  // Photo preview + remove
  const file = document.getElementById('ap_profile_pic');
  const img  = document.getElementById('ap_avatar_img');
  const hint = document.getElementById('ap_file_hint');
  const rmBtn = document.getElementById('ap_remove_btn');
  const rmFlag = document.getElementById('ap_remove_avatar');

  const originalUrl = img?.src || '';
  const originalText = hint?.textContent || '';

  if (file) {
    file.addEventListener('change', () => {
      const f = file.files && file.files[0] ? file.files[0] : null;
      if (!f) return;

      if (rmFlag) rmFlag.value = '0';
      if (img) img.src = URL.createObjectURL(f);
      if (hint) hint.textContent = `New: ${f.name}`;
    });
  }

  if (rmBtn) {
    rmBtn.addEventListener('click', () => {
      if (img) img.src = "{{ asset('images/avatar-placeholder.png') }}";
      if (hint) hint.textContent = "Will be removed on save";
      if (rmFlag) rmFlag.value = '1';
      if (file) file.value = '';
    });
  }
    // ✅ Live "Time With Business" updater (updates every minute)
  (function () {
    const el = document.getElementById('tenure_text');
    if (!el) return;

    const startIso = el.getAttribute('data-start');
    if (!startIso) return;

    const start = new Date(startIso);
    if (isNaN(start.getTime())) return;

    function fmt(ms) {
      const sec = Math.floor(ms / 1000);
      const min = Math.floor(sec / 60);
      const hr  = Math.floor(min / 60);
      const day = Math.floor(hr / 24);

      const years  = Math.floor(day / 365);
      const months = Math.floor((day % 365) / 30);
      const days   = day % 30;

      const hours = hr % 24;
      const mins  = min % 60;

      const parts = [];
      if (years) parts.push(`${years}y`);
      if (months) parts.push(`${months}mo`);
      if (days) parts.push(`${days}d`);
      parts.push(`${hours}h`);
      parts.push(`${mins}m`);
      return parts.join(' ');
    }

    function tick() {
      const now = new Date();
      const diff = now - start;
      el.textContent = diff >= 0 ? fmt(diff) : '—';
    }

    tick();
    setInterval(tick, 60000);
  })();
</script>
@endpush
