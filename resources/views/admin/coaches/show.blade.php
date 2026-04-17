{{-- resources/views/admin/coaches/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Coach Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-coach-show.css') }}">
@endpush

@section('content')
@php
  $coachProfile = $user->coachProfile;

  $applicationStatus = (string) optional($coachProfile)->application_status;
  $isDeleted = (bool) $user->deleted_at;

  $isPendingKyc  = in_array($applicationStatus, ['draft', 'submitted', ''], true);
  $isApprovedKyc = $applicationStatus === 'approved';
  $isRejectedKyc = $applicationStatus === 'rejected';

  $documents = collect(optional($coachProfile)->documents ?? []);

  $profilePhoto = $documents->firstWhere('document_type', 'profile_photo');
  $passport     = $documents->firstWhere('document_type', 'passport');
  $dlFront      = $documents->firstWhere('document_type', 'driving_license_front');
  $dlBack       = $documents->firstWhere('document_type', 'driving_license_back');

  $hasPassport = !is_null($passport);
  $hasDrivingLicense = !is_null($dlFront) || !is_null($dlBack);

  $idType = $hasPassport ? 'passport' : ($hasDrivingLicense ? 'driving_license' : '');
  $submitted = $documents->isNotEmpty();

  $statusLbl = match ($applicationStatus) {
      'approved'  => 'Approved',
      'submitted' => 'Pending',
      'rejected'  => 'Rejected',
      'draft'     => 'Draft',
      default     => 'Not Submitted',
  };

  $statusCls = match ($applicationStatus) {
      'approved'  => 'ok',
      'submitted' => 'pending',
      'rejected'  => 'danger',
      'draft'     => 'pending',
      default     => 'pending',
  };
@endphp

<section class="card coach-show">
  <div class="coach-show__topbar">
    <a href="{{ route('admin.coaches.index', ['status' => 'all']) }}" class="back-link">
      ← Back to Coaches
    </a>

    <div class="top-actions">
      @if($isDeleted)
        <span class="pill pill-deleted">Deleted</span>
      @else
        <span class="pill pill-{{ $statusCls }}">{{ $statusLbl }}</span>
      @endif

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

        @if(optional($coachProfile)->applied_at)
          <span class="meta-item">
            <strong>Applied:</strong>
            {{ $coachProfile->applied_at->format('d M Y') }}
          </span>
        @endif

        @if(optional($coachProfile)->approved_at)
          <span class="meta-item">
            <strong>Approved:</strong>
            {{ $coachProfile->approved_at->format('d M Y') }}
          </span>
        @endif

        @if(optional($coachProfile)->rejected_at)
          <span class="meta-item danger">
            <strong>Rejected:</strong>
            {{ $coachProfile->rejected_at->format('d M Y H:i') }}
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
    <div class="col col-main">
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
            <dd>
              @php $dob = $user->dob; @endphp
              @if(empty($dob))
                —
              @else
                {{ \Carbon\Carbon::parse($dob)->format('d M Y') }}
              @endif
            </dd>
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

          <div class="row">
            <dt>Application Status</dt>
            <dd>
              <span class="chip chip-{{ $statusCls === 'ok' ? 'ok' : ($statusCls === 'danger' ? 'danger' : 'warn') }}">
                {{ $statusLbl }}
              </span>
            </dd>
          </div>

          @if(optional($coachProfile)->rejection_reason)
            <div class="row">
              <dt>Rejection Reason</dt>
              <dd>{{ $coachProfile->rejection_reason }}</dd>
            </div>
          @endif
        </dl>
      </section>

      <section class="block">
        <h2 class="block-title">Coaching Profile</h2>

        <div class="pill-group">
          <div class="pill-group__label">Languages</div>
          <div class="pill-group__items">
            @forelse($languages as $lang)
              <span class="tag">{{ ucfirst($lang) }}</span>
            @empty
              <span class="muted">Not Set</span>
            @endforelse
          </div>
        </div>

        <div class="pill-group">
          <div class="pill-group__label">Service Areas</div>
          <div class="pill-group__items">
            @forelse($serviceAreas as $area)
              <span class="tag">{{ ucfirst($area) }}</span>
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
                $label = is_array($q)
                    ? ($q['label'] ?? $q['name'] ?? implode(', ', array_filter($q)))
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

    <div class="col col-side">
      <section class="block small">
        <h2 class="block-title">Legal Documents</h2>

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
              @if($applicationStatus === 'approved')
                <span class="chip chip-ok">Approved</span>
              @elseif($applicationStatus === 'submitted')
                <span class="chip chip-warn">Pending</span>
              @elseif($applicationStatus === 'rejected')
                <span class="chip chip-danger">Rejected</span>
              @elseif($applicationStatus === 'draft')
                <span class="chip chip-warn">Draft</span>
              @else
                <span class="chip chip-warn">Not Submitted</span>
              @endif
            </span>
          </li>

          <li>
            <span>ID Type</span>
            <span class="value">
              @if($idType === 'passport')
                Passport
              @elseif($idType === 'driving_license')
                Driving License
              @else
                —
              @endif
            </span>
          </li>
        </ul>

        @if(!$submitted)
          <div class="muted mt-2 text-capitalize">No documents uploaded yet.</div>
        @else
          <div class="kyc-grid">
            <div class="kyc-item">
              <div class="muted small mb-1">Profile Photo</div>
              @if($profilePhoto)
                <a href="{{ asset('storage/'.$profilePhoto->storage_path) }}" target="_blank">
                  <img src="{{ asset('storage/'.$profilePhoto->storage_path) }}"
                       alt="Profile photo"
                       style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
                </a>
              @else
                <div class="muted">—</div>
              @endif
            </div>

            @if($idType === 'passport')
              <div class="kyc-item">
                <div class="muted small mb-1">Passport Image</div>
                @if($passport)
                  <a href="{{ asset('storage/'.$passport->storage_path) }}" target="_blank">
                    <img src="{{ asset('storage/'.$passport->storage_path) }}"
                         alt="Passport"
                         style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
                  </a>
                @else
                  <div class="muted">—</div>
                @endif
              </div>
            @endif

            @if($idType === 'driving_license')
              <div class="kyc-item">
                <div class="muted small mb-1">Driving License (Front)</div>
                @if($dlFront)
                  <a href="{{ asset('storage/'.$dlFront->storage_path) }}" target="_blank">
                    <img src="{{ asset('storage/'.$dlFront->storage_path) }}"
                         alt="DL front"
                         style="width:100%;max-width:320px;border-radius:10px;border:1px solid #e5e7eb;">
                  </a>
                @else
                  <div class="muted">—</div>
                @endif
              </div>

              <div class="kyc-item">
                <div class="muted small mb-1">Driving License (Back)</div>
                @if($dlBack)
                  <a href="{{ asset('storage/'.$dlBack->storage_path) }}" target="_blank">
                    <img src="{{ asset('storage/'.$dlBack->storage_path) }}"
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

      <section class="block small">
        <h2 class="block-title">Account</h2>
        <ul class="plain-list">
          <li>
            <span>Status</span>
            <span class="value">
              @if($user->deleted_at)
                <span class="chip chip-danger">Deleted</span>
              @elseif($applicationStatus === 'approved')
                <span class="chip chip-ok">Approved</span>
              @elseif($applicationStatus === 'submitted')
                <span class="chip chip-warn">Pending</span>
              @elseif($applicationStatus === 'rejected')
                <span class="chip chip-danger">Rejected</span>
              @elseif($applicationStatus === 'draft')
                <span class="chip chip-warn">Draft</span>
              @else
                <span class="chip chip-warn">Not Submitted</span>
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
            <span class="value">
              ${{ number_format(($user->withdrawable_minor ?? 0) / 100, 2) }} USD
            </span>
          </li>

          <li>
            <span>Bookings Enabled</span>
            <span class="value">
              @if(optional($coachProfile)->can_accept_bookings)
                <span class="chip chip-ok">Yes</span>
              @else
                <span class="chip chip-warn">No</span>
              @endif
            </span>
          </li>

          <li>
            <span>Payouts Enabled</span>
            <span class="value">
              @if(optional($coachProfile)->can_receive_payouts)
                <span class="chip chip-ok">Yes</span>
              @else
                <span class="chip chip-warn">No</span>
              @endif
            </span>
          </li>
        </ul>
      </section>

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

        <div class="mt-3">
          <label for="rejection_reason" class="form-label">Reason</label>
          <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" placeholder="Enter rejection reason"></textarea>
        </div>
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
  const base = "{{ url('admin/coaches') }}";

  function wire(openerSel, modalId, formSel, nameSel, endpoint) {
    document.querySelectorAll(openerSel).forEach(btn => {
      btn.addEventListener('click', () => {
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
    x.addEventListener('click', () => x.closest('.modal')?.classList.remove('open'))
  );

  document.querySelectorAll('.modal').forEach(m =>
    m.addEventListener('click', (e) => {
      if (e.target === m) m.classList.remove('open');
    })
  );
</script>
@endpush
@endsection