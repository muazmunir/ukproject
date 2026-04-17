@php
  $viewerTz = auth()->user()->timezone ?? config('app.timezone');

  $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '—';
  $locked   = (bool)($user->is_locked ?? false);
  $username = $user->username ?? '—';

  // Profile pic path fallback (adjust keys to match your DB column)
  $profilePath = $user->profile_pic_path
    ?? $user->profile_pic
    ?? $user->avatar_path
    ?? null;

  $profileUrl = $profilePath ? asset('storage/'.$profilePath) : asset('images/avatar-placeholder.png');

  $tzfmt = function($dt) use ($viewerTz){
    if(!$dt) return '—';
    return $dt->setTimezone('UTC')->setTimezone($viewerTz)->format('M d, Y · H:i');
  };

  // Invite state
  $inv = $user->latestStaffInvite ?? null;
  if(!$inv) $inviteState = '—';
  elseif($inv->used_at) $inviteState = 'Used';
  elseif(now()->greaterThan($inv->expires_at)) $inviteState = 'Expired';
  else $inviteState = 'Pending';
@endphp

@extends('superadmin.layout')
@section('title','Staff Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-staff.css') }}">
<style>
  /* You can move this into superadmin-staff.css (CSS provided below) */
</style>
@endpush

@section('content')
<section class="card info-wrap">
  <div class="card__head info-head">
    <div class="info-left">
      <div class="info-avatar">
        <img src="{{ $profileUrl }}" alt="profile" loading="lazy">
      </div>

      <div class="info-meta">
        <div class="info-title">Staff Details</div>
        <div class="info-sub">
          <strong>{{ $fullName }}</strong>
          <span class="dot">·</span>
  <span class="muted">{{ '@' . $username }}</span>
          <span class="dot">·</span> {{ $user->email }}
          <span class="dot">·</span> <span class="text-capitalize">{{ $user->role_label }}</span>
        </div>

        <div class="info-badges">
          @if($user->deleted_at)
            <span class="pill danger" title="Soft Deleted"><i class="bi bi-trash3"></i> Deleted</span>
          @elseif($locked)
            <span class="pill danger" title="{{ $user->locked_reason ?: 'Locked' }}"><i class="bi bi-shield-lock-fill"></i> Locked</span>
          @else
            <span class="pill ok"><i class="bi bi-shield-check"></i> Active</span>
          @endif

          <span class="pill">
            <i class="bi bi-envelope"></i> Invite: {{ $inviteState }}
          </span>
        </div>
      </div>
    </div>

    <div class="info-actions">
      <a href="{{ route('superadmin.staff.index') }}" class="btn ghost">
        <i class="bi bi-arrow-left"></i> Back
      </a>

      @if($user->deleted_at)
        <form method="post" action="{{ route('superadmin.staff.restore', $user->id) }}">
          @csrf
          <button class="btn ok" type="submit">
            <i class="bi bi-arrow-counterclockwise"></i> Restore
          </button>
        </form>
      @endif
    </div>
  </div>

  @if(session('ok'))
    <div class="zv-alert zv-alert--ok" style="margin:0 1rem 1rem">{{ session('ok') }}</div>
  @endif
  @if(session('error'))
    <div class="zv-alert zv-alert--danger" style="margin:0 1rem 1rem">{{ session('error') }}</div>
  @endif

  <div class="info-body">
    {{-- ===== Top cards ===== --}}
    <div class="info-grid">
      {{-- Basic --}}
      <div class="info-card">
        <div class="info-card__head">
          <div class="h">Basic Info</div>
          <div class="muted small text-capitalize">Account & contact</div>
        </div>

        <div class="kv">
          <div class="k">User ID</div><div class="v">#{{ $user->id }}</div>
          <div class="k">Full Name</div><div class="v">{{ $fullName }}</div>
          <div class="k">Email</div><div class="v">{{ $user->email ?: '—' }}</div>
          <div class="k">Phone</div><div class="v">{{ $user->phone ?: '—' }}</div>
          <div class="k">Role</div><div class="v text-capitalize">{{ $user->role_label ?: '—' }}</div>
          
        </div>
      </div>

      {{-- Timeline / status --}}
      <div class="info-card">
        <div class="info-card__head">
          <div class="h">Status & Timeline</div>
          <div class="muted small text-capitalize">Created, deleted, lock</div>
        </div>

        <div class="kv">
          <div class="k">Created</div><div class="v">{{ $tzfmt($user->created_at) }}</div>
          <div class="k">Deleted At</div><div class="v">{{ $tzfmt($user->deleted_at) }}</div>
          <div class="k">Deleted By</div><div class="v">{{ $performedBy ?: '—' }}</div>

          <div class="k">Locked</div><div class="v">{{ $locked ? 'Yes' : 'No' }}</div>
          <div class="k">Lock Reason</div><div class="v">{{ $user->locked_reason ?: '—' }}</div>

          @if($inv)
            <div class="k">Invite Created</div><div class="v">{{ $tzfmt($inv->created_at ?? null) }}</div>
            <div class="k">Invite Expires</div><div class="v">{{ $tzfmt($inv->expires_at ?? null) }}</div>
            <div class="k">Invite Used</div><div class="v">{{ $tzfmt($inv->used_at ?? null) }}</div>
          @endif
        </div>
      </div>
    </div>

    {{-- ===== Emergency & next of kin ===== --}}
    <div class="info-grid">
      <div class="info-card">
        <div class="info-card__head">
          <div class="h">Emergency Contact</div>
          <div class="muted small">For safety & compliance</div>
        </div>

        <div class="kv">
          <div class="k">Name</div><div class="v">{{ $user->emergency_contact_name ?: '—' }}</div>
          <div class="k">Phone</div><div class="v">{{ $user->emergency_contact_phone ?: '—' }}</div>
        </div>
      </div>

      <div class="info-card">
        <div class="info-card__head">
          <div class="h">Next Of Kin</div>
          
        </div>

       <div class="kv">
  <div class="k">Name</div><div class="v">{{ $user->next_of_kin_name ?: '—' }}</div>
  <div class="k">Phone</div><div class="v">{{ $user->next_of_kin_phone ?: '—' }}</div>
  <div class="k">Address</div><div class="v">{{ $user->next_of_kin_address ?: '—' }}</div>
</div>
      </div>
    </div>

    {{-- ===== Documents ===== --}}
    <div class="info-grid">
      <div class="info-card col-span">
        <div class="info-card__head">
          <div class="h">Government ID Documents</div>
          <div class="muted small">Passport / National ID / Driving License etc.</div>
        </div>

        @if(isset($govDocs) && $govDocs->count())
          <div class="docs">
            @foreach($govDocs as $d)
            @php
  $url  = $d->file_path ? asset('storage/'.$d->file_path) : null;
  $name = $d->file_original_name ?: ($d->label ?: 'document');
  $size = $d->file_size ?? null;
  $mime = $d->file_mime ?? null;
@endphp

              <a class="doc" href="{{ $url }}" target="_blank" rel="noopener">
                <div class="doc__icon">
                  <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="doc__meta">
                  <div class="doc__name">{{ $name }}</div>
                  <div class="doc__sub muted">
                    {{ $mime ?: '—' }}
                    @if($size) · {{ number_format($size/1024,0) }} KB @endif
                  </div>
                </div>
                <div class="doc__go"><i class="bi bi-box-arrow-up-right"></i></div>
              </a>
            @endforeach
          </div>
        @else
          <div class="muted">No government ID docs uploaded.</div>
        @endif
      </div>
    </div>

    <div class="info-grid">
      <div class="info-card col-span">
        <div class="info-card__head">
          <div class="h">Additional Requirement</div>
          <div class="muted small text-capitalize">Varies by country</div>
        </div>

      @if(isset($additionalRequirements) && $additionalRequirements->count())
  <div class="req-list">
    @foreach($additionalRequirements as $req)
      <div class="req">
        <div class="req__left">
          <div class="req__label">{{ $req->label ?: '—' }}</div>
          <div class="req__value muted">{{ $req->value_text ?: '—' }}</div>
        </div>
      </div>
    @endforeach
  </div>
@else
  <div class="muted text-capitalize">No additional requirements added.</div>
@endif


        <div style="margin-top:12px">
          <div class="muted small" style="margin-bottom:8px">Additional Documents</div>

          @if(isset($additionalDocs) && $additionalDocs->count())
            <div class="docs">
              @foreach($additionalDocs as $d)
            @php
  $url  = $d->file_path ? asset('storage/'.$d->file_path) : null;
  $name = $d->file_original_name ?: ($d->label ?: 'document');
  $size = $d->file_size ?? null;
  $mime = $d->file_mime ?? null;
@endphp


                <a class="doc" href="{{ $url }}" target="_blank" rel="noopener">
                  <div class="doc__icon">
                    <i class="bi bi-file-earmark"></i>
                  </div>
                  <div class="doc__meta">
                    <div class="doc__name">{{ $name }}</div>
                    <div class="doc__sub muted">
                      {{ $mime ?: '—' }}
                      @if($size) · {{ number_format($size/1024,0) }} KB @endif
                    </div>
                  </div>
                  <div class="doc__go"><i class="bi bi-box-arrow-up-right"></i></div>
                </a>
              @endforeach
            </div>
          @else
            <div class="muted">No additional documents uploaded.</div>
          @endif
        </div>
      </div>
    </div>

    {{-- ===== Audit (deletion) ===== --}}
    <div class="info-grid">
      <div class="info-card col-span">
        <div class="info-card__head">
          <div class="h">Deletion Audit</div>
          <div class="muted small">Reason + Attachments</div>
        </div>

        <div class="muted small" style="margin-bottom:8px">Reason</div>
        <div class="reasonBox">{{ $latest?->reason ?: '—' }}</div>

        <div style="margin-top:14px">
          <div class="muted small" style="margin-bottom:8px">Audit Images</div>

          @if(isset($images) && $images->count())
            <div class="gallery">
              @foreach($images as $img)
                <a class="shot" href="{{ asset('storage/'.$img->image_path) }}" target="_blank" rel="noopener">
                  <img src="{{ asset('storage/'.$img->image_path) }}" alt="audit" loading="lazy">
                  <div class="cap">
                    {{ $img->image_original_name ?: 'image' }}
                    @if($img->image_size) · {{ number_format($img->image_size/1024, 0) }} KB @endif
                  </div>
                </a>
              @endforeach
            </div>
          @else
            <div class="muted text-capitalize">No images attached.</div>
          @endif
        </div>

        <details class="tech">
          <summary class="muted">Technical</summary>
          <div class="muted small tech__body">
            <div><strong>IP:</strong> {{ $latest?->ip ?: '—' }}</div>
            <div style="margin-top:6px"><strong>User Agent:</strong> {{ $latest?->user_agent ?: '—' }}</div>
          </div>
        </details>
      </div>
    </div>

  </div>
</section>
@endsection
