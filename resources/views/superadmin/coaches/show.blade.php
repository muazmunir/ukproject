{{-- resources/views/admin/coaches/show.blade.php --}}

@extends('superadmin.layout')

@section('title', 'Coach Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-coach-show.css') }}">
@endpush

@section('content')
<section class="card coach-show">
  <div class="coach-show__topbar">
    <a href="{{ route('superadmin.coaches.index', ['status' => 'all']) }}" class="back-link">
      ← Back to Coaches
    </a>

   <div class="top-actions">
  @php
    $kyc = (string)($user->coach_verification_status ?? '');
    $isDeleted = (bool) $user->deleted_at;

    // "Pending" if empty or pending
    $isPendingKyc = ($kyc === '' || $kyc === 'pending');
    $isApprovedKyc = ($kyc === 'approved');
    $isRejectedKyc = ($kyc === 'rejected');
  @endphp

  {{-- Status pill --}}
  @if($isDeleted)
    <span class="pill pill-deleted">Deleted</span>
  @else
    @php
      $statusLbl = $user->is_approved == 1 ? 'Active' : ($user->is_approved == 0 ? 'Pending' : 'Rejected');
      $statusCls = $user->is_approved == 1 ? 'ok' : ($user->is_approved == 0 ? 'pending' : 'danger');
    @endphp
    <span class="pill pill-{{ $statusCls }}">{{ $statusLbl }}</span>
  @endif

  {{-- ✅ Actions (only if not deleted AND still pending) --}}
  @if(!$isDeleted && $isPendingKyc)
    <button class="btn btn-success"
            data-open="#mApprove"
            data-id="{{ $user->id }}"
            data-name="{{ trim(($user->first_name.' '.$user->last_name)) ?: ($user->username ?: 'Coach') }}">
      <i class="bi bi-check2"></i> Approve
    </button>

    <button class="btn btn-warning"
            data-open="#mReject"
            data-id="{{ $user->id }}"
            data-name="{{ trim(($user->first_name.' '.$user->last_name)) ?: ($user->username ?: 'Coach') }}">
      <i class="bi bi-x-lg"></i> Reject
    </button>
  @endif
</div>

  </div>

  <div class="coach-show__header">
   @php
  $fullName = trim(($user->first_name.' '.$user->last_name));
  $nameForAvatar = $fullName ?: ($user->username ?: 'U');
  $letter = strtoupper(mb_substr($nameForAvatar, 0, 1));
  $avatar = $user->avatar_path ? asset('storage/'.$user->avatar_path) : null;
@endphp

<div class="avatar-wrap avatar-wrap-lg">
  @if($avatar)
    <img
      src="{{ $avatar }}"
      alt="{{ $nameForAvatar }}"
      class="avatar-lg avatar-img"
      onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
    >
  @endif

  <div class="avatar-fallback avatar-fallback-lg" style="{{ $avatar ? 'display:none;' : '' }}">
    {{ $letter }}
  </div>
</div>

    <div class="headline">
      <h1 class="name">
        {{ trim(($user->first_name.' '.$user->last_name)) ?: 'Unnamed coach' }}
      </h1>
      <div class="subtitle">
        <span class="role-badge">{{ ucfirst($user->role ?? 'coach') }}</span>
        @if($user->username)
          <span class="muted">· {{ '@'.$user->username }}</span>
        @endif
      </div>
      <div class="meta-row">
        <span class="meta-item">
          <strong>Email:</strong>
          <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
        </span>
        @if($user->phone)
        <span class="meta-item">
          <strong>Phone:</strong>
          <a href="tel:{{ $user->phone }}">{{ $user->phone }}</a>
        </span>
        @endif
        @if($user->timezone)
        <span class="meta-item">
          <strong>Timezone:</strong> {{ $user->timezone }}
        </span>
        @endif
      </div>

      <div class="meta-row small">
        <span class="meta-item">
          <strong>Joined:</strong>
          {{ optional($user->created_at)->format('d M Y') ?? '—' }}
        </span>
        @if($user->approved_at)
          <span class="meta-item">
            <strong>Approved:</strong>
            {{ $user->approved_at->format('d M Y') }}
          </span>
        @endif
        @if($user->deleted_at)
          <span class="meta-item danger">
            <strong>Deleted:</strong>
            {{ $user->deleted_at->format('d M Y H:i') }}
          </span>
        @endif
      </div>
    </div>
  </div>

  @php
    $languages    = (array) ($user->languages ?? []);
    $serviceAreas = (array) ($user->coach_service_areas ?? []);
    $quals        = (array) ($user->coach_qualifications ?? []);
    $gallery      = (array) ($user->coach_gallery ?? []);
  @endphp

  <div class="coach-show__layout">
    {{-- LEFT COLUMN --}}
    <div class="col col-main">
      {{-- Overview --}}
      <section class="block">
        <h2 class="block-title">Overview</h2>
        <dl class="meta-list">
          <div class="row">
            <dt>Short Bio</dt>
            <dd>{{ $user->short_bio ?: '—' }}</dd>
          </div>
          <div class="row">
            <dt>Description</dt>
            <dd>{{ $user->description ?: '—' }}</dd>
          </div>
          <div class="row">
            <dt>Location</dt>
            <dd>
              @if($user->city || $user->country)
                {{ $user->city }}{{ $user->city && $user->country ? ', ' : '' }}{{ $user->country }}
              @else
                —
              @endif
            </dd>
          </div>
          <div class="row">
            <dt>Date Of Birth</dt>
            <dd>{{ $user->dob ?: '—' }}</dd>
          </div>
          <div class="row">
            <dt>Onboarding</dt>
            <dd>
              @if($user->onboarding_completed)
                <span class="chip chip-ok">Completed</span>
              @else
                <span class="chip chip-warn">Incomplete</span>
              @endif
            </dd>
          </div>
        </dl>
      </section>

      {{-- Coaching profile --}}
      <section class="block">
        <h2 class="block-title">Coaching Profile</h2>
        <div class="pill-group">
          <div class="pill-group__label">Languages</div>
          <div class="pill-group__items">
            @forelse($languages as $lang)
              <span class="tag">{{ $lang }}</span>
            @empty
              <span class="muted">Not Set</span>
            @endforelse
          </div>
        </div>

        <div class="pill-group">
          <div class="pill-group__label">Service Areas</div>
          <div class="pill-group__items">
            @forelse($serviceAreas as $area)
              <span class="tag">{{ $area }}</span>
            @empty
              <span class="muted">Not Set</span>
            @endforelse
          </div>
        </div>

        <div class="pill-group">
  <div class="pill-group__label">Qualifications</div>
  <div class="pill-group__items">
    @forelse($quals as $q)
      @php
          // Normalize each qualification to a string
          $label = is_array($q)
              ? ($q['label']        // e.g. { label: 'MBA' }
                  ?? $q['name']     // or { name: 'MBA' }
                  ?? implode(', ', array_filter($q))) // fallback: join values
              : $q;
      @endphp
      <span class="tag tag-outline">{{ $label }}</span>
    @empty
      <span class="muted">Not Set</span>
    @endforelse
  </div>
</div>

      </section>
    </div>

    {{-- RIGHT COLUMN --}}
    <div class="col col-side">
      {{-- Account status --}}
      <section class="block small">
        <h2 class="block-title">Account</h2>
        {{-- KYC Documents --}}
<section class="block">
  <h2 class="block-title">KYC Documents</h2>

  @php
    $submitted = (bool) ($user->coach_kyc_submitted ?? false);
    $kycStatus = (string) ($user->coach_verification_status ?? 'not_submitted');
    $idType    = (string) ($user->coach_id_type ?? '');
  @endphp

  <ul class="plain-list small">
    <li>
      <span>Submitted</span>
      <span class="value">
        @if($submitted)
          <span class="chip chip-ok">Yes</span>
        @else
          <span class="chip chip-warn">No</span>
        @endif
      </span>
    </li>
    <li>
      <span>Status</span>
      <span class="value">
        @if($kycStatus === 'approved')
          <span class="chip chip-ok">Approved</span>
        @elseif($kycStatus === 'pending')
          <span class="chip chip-warn">Pending</span>
        @elseif($kycStatus === 'rejected')
          <span class="chip chip-danger">Rejected</span>
        @else
          <span class="chip chip-warn">Not Submitted</span>
        @endif
      </span>
    </li>
    <li>
      <span>ID type</span>
      <span class="value">{{ $idType ?: '—' }}</span>
    </li>
  </ul>

  @if(!$submitted)
    <div class="muted mt-2 text-capitalize">No documents uploaded yet.</div>
  @else
   <div class="kyc-grid">

      {{-- Profile photo --}}
      <div class="kyc-item">
        <div class="muted small mb-1">Profile Photo</div>
        @if($user->coach_profile_photo)
          <a href="{{ asset('storage/'.$user->coach_profile_photo) }}" target="_blank">
            <img src="{{ asset('storage/'.$user->coach_profile_photo) }}"
                 alt="Profile photo"
                 style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
          </a>
        @else
          <div class="muted">—</div>
        @endif
      </div>

      {{-- Passport --}}
      @if($idType === 'passport')
        <div class="kyc-item">
          <div class="muted small mb-1">Passport Image</div>
          @if($user->coach_passport_image)
            <a href="{{ asset('storage/'.$user->coach_passport_image) }}" target="_blank">
              <img src="{{ asset('storage/'.$user->coach_passport_image) }}"
                   alt="Passport"
                   style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
            </a>
          @else
            <div class="muted">—</div>
          @endif
        </div>
      @endif

      {{-- Driving license --}}
      @if($idType === 'driving_license')
        <div class="kyc-item">
          <div class="muted small mb-1">Driving License (Front)</div>
          @if($user->coach_dl_front)
            <a href="{{ asset('storage/'.$user->coach_dl_front) }}" target="_blank">
              <img src="{{ asset('storage/'.$user->coach_dl_front) }}"
                   alt="DL front"
                   style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
            </a>
          @else
            <div class="muted">—</div>
          @endif
        </div>

        <div class="kyc-item">
          <div class="muted small mb-1">Driving License (Back)</div>
          @if($user->coach_dl_back)
            <a href="{{ asset('storage/'.$user->coach_dl_back) }}" target="_blank">
              <img src="{{ asset('storage/'.$user->coach_dl_back) }}"
                   alt="DL back"
                   style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
            </a>
          @else
            <div class="muted">—</div>
          @endif
        </div>
      @endif
    </div>
  @endif
</section>

        <ul class="plain-list">
          <li>
            <span>Status</span>
            <span class="value">
              @if($user->deleted_at)
                <span class="chip chip-danger">Deleted</span>
              @elseif($user->is_approved == 1)
                <span class="chip chip-ok">Active</span>
              @elseif($user->is_approved == 0)
                <span class="chip chip-warn">Pending</span>
              @else
                <span class="chip chip-danger">Rejected</span>
              @endif
            </span>
          </li>
          <li>
            <span>Email Verified</span>
            <span class="value">
              @if($user->email_verified_at)
                <span class="dot dot-ok"></span> Yes
              @else
                <span class="dot dot-warn"></span> No
              @endif
            </span>
          </li>
          <li>
            <span>Wallet Balance</span>
            <span class="value">${{ number_format($user->wallet_balance ?? 0, 2) }}</span>
          </li>
        </ul>
      </section>

      {{-- Social links --}}
      <section class="block small">
        <h2 class="block-title">Social Links</h2>
        <ul class="plain-list">
          <li>
            <span>Facebook</span>
            <span class="value">
              @if($user->facebook_url)
                <a href="{{ $user->facebook_url }}" target="_blank">View</a>
              @else
                <span class="muted">Not set</span>
              @endif
            </span>
          </li>
          <li>
            <span>Instagram</span>
            <span class="value">
              @if($user->instagram_url)
                <a href="{{ $user->instagram_url }}" target="_blank">View</a>
              @else
                <span class="muted">Not set</span>
              @endif
            </span>
          </li>
          <li>
            <span>LinkedIn</span>
            <span class="value">
              @if($user->linkedin_url)
                <a href="{{ $user->linkedin_url }}" target="_blank">View</a>
              @else
                <span class="muted">Not set</span>
              @endif
            </span>
          </li>
          <li>
            <span>Twitter</span>
            <span class="value">
              @if($user->twitter_url)
                <a href="{{ $user->twitter_url }}" target="_blank">View</a>
              @else
                <span class="muted">Not set</span>
              @endif
            </span>
          </li>
          <li>
            <span>YouTube</span>
            <span class="value">
              @if($user->youtube_url)
                <a href="{{ $user->youtube_url }}" target="_blank">View</a>
              @else
                <span class="muted">Not set</span>
              @endif
            </span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</section>


{{-- Approve --}}
<div id="mApprove" class="modal">
  <div class="modal__dialog">
    <form method="post" id="approveForm" class="modal__card" action="">
      @csrf
      <div class="modal__head">
        <div class="title">Approve Coach</div>
        <button type="button" class="x" data-close aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal__body text-capitalize">
        <p>Approve <strong id="approveName"></strong> as a coach?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn success" type="submit">Approve</button>
      </div>
    </form>
  </div>
</div>

{{-- Reject --}}
<div id="mReject" class="modal">
  <div class="modal__dialog">
    <form method="post" id="rejectForm" class="modal__card" action="">
      @csrf
      <div class="modal__head">
        <div class="title">Reject Coach</div>
        <button type="button" class="x" data-close aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal__body text-capitalize">
        <p>Reject <strong id="rejectName"></strong>?</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button class="btn warning" type="submit">Reject</button>
      </div>
    </form>
  </div>
</div>


@push('scripts')
<script>
  const base = "{{ url('superadmin/coaches') }}";

  function wire(openerSel, modalId, formSel, nameSel, endpoint){
    document.querySelectorAll(openerSel).forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const name = btn.dataset.name || '';
        const modal = document.querySelector(modalId);
        modal?.classList.add('open');
        document.querySelector(nameSel).textContent = name;

        const form = document.querySelector(formSel);
        form.action = `${base}/${id}/${endpoint}`;
      });
    });
  }

  wire('[data-open="#mApprove"]', '#mApprove', '#approveForm', '#approveName', 'approve');
  wire('[data-open="#mReject"]',  '#mReject',  '#rejectForm',  '#rejectName',  'reject');

  document.querySelectorAll('[data-close]').forEach(x =>
    x.addEventListener('click', ()=> x.closest('.modal')?.classList.remove('open'))
  );
  document.querySelectorAll('.modal').forEach(m =>
    m.addEventListener('click', (e)=>{ if(e.target===m) m.classList.remove('open'); })
  );
</script>
@endpush
@endsection





