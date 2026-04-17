@extends('superadmin.layout')
@section('title','Security · Events')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-security.css') }}">
@endpush

@section('content')
@php
  use Illuminate\Support\Str;

  $viewerTz = auth()->user()->timezone ?? config('app.timezone');

  $eventTypeLabels = [
    'mass_deletion_lock' => 'High Risk: Mass Deletion Detected',
    'suspicious_activity' => 'Suspicious Activity',
    'brute_force' => 'Brute Force Attempt',
    'manual_lock' => 'Manually Locked by Super Admin',
    'staff_password_reset_requested' => 'Staff Password Reset Requested',
    'staff_password_reset_completed' => 'Staff Password Reset Completed',
  ];

  $statusLabels = [
    'open'     => 'Open',
    'reviewed' => 'Reviewed',
    'closed'   => 'Closed',
  ];

  $typeOptions = [
    '' => 'All Types',
    'staff_password_reset_requested' => 'Reset Requested',
    'staff_password_reset_completed' => 'Reset Completed',
    'brute_force' => 'Brute Force',
    'suspicious_activity' => 'Suspicious Activity',
    'manual_lock' => 'Manual Lock',
    'mass_deletion_lock' => 'Mass Deletion',
  ];

  $severityMap = [
    'mass_deletion_lock' => 'danger',
    'suspicious_activity' => 'warning',
    'brute_force' => 'danger',
    'manual_lock' => 'info',
    'staff_password_reset_requested' => 'warning',
    'staff_password_reset_completed' => 'danger',
  ];
@endphp

<section class="card zv-sec-card">
  <div>
    <div class="card__title text-center">Security Events</div>
    <div class="muted text-capitalize text-center">Flags requiring superadmin review</div>
  </div>

  <div class="card__head zv-sec-head">
    <div class="zv-sec-actions">
      <a class="btn ghost small" href="{{ route('superadmin.security.locked_staff') }}">Locked Staff</a>
      <a class="btn ghost small" href="{{ route('superadmin.security.logs') }}">Action Logs</a>
    </div>
  </div>

  @if(session('ok'))
    <div class="zv-alert zv-alert--ok">{{ session('ok') }}</div>
  @endif

  <div class="zv-sec-toolbar">
    <form method="get" class="zv-sec-filters">
      <div class="zv-field">
        <label>Status</label>
        <select name="status">
          <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Open</option>
          <option value="reviewed" {{ $status === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
          <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Closed</option>
        </select>
      </div>

      <div class="zv-field">
        <label>Type</label>
        <select name="type">
          @foreach($typeOptions as $value => $label)
            <option value="{{ $value }}" {{ ($type ?? '') === $value ? 'selected' : '' }}>
              {{ $label }}
            </option>
          @endforeach
        </select>
      </div>

      <button class="btn primary small" type="submit">Apply</button>
      <a class="btn ghost small" href="{{ route('superadmin.security.events') }}">Reset</a>
    </form>
  </div>

  <div class="zv-sec-grid">
    @forelse($events as $e)
      @php
        $severity = $severityMap[$e->type] ?? 'info';

        $msg = $e->meta['reason_label'] ?? $e->message ?? null;
        if (!$msg && !empty($e->meta['reason'])) {
            $msg = ucwords(str_replace('_', ' ', $e->meta['reason']));
        }

        $role = $e->meta['role'] ?? null;
        $ip = $e->meta['ip'] ?? null;
        $userAgent = $e->meta['user_agent'] ?? null;
        $eventEmail = $e->meta['email'] ?? $e->admin?->email;
      @endphp

      <div class="zv-sec-item">
        <div class="zv-sec-top">
          <div>
            <div class="zv-sec-name">
              {{ $eventTypeLabels[$e->type] ?? ucwords(str_replace('_',' ', $e->type)) }}
            </div>

            <div class="zv-sec-sub">
              {{ $eventEmail ?: 'Unknown account' }}
              ·
              <span class="pill">
                {{ $statusLabels[$e->status] ?? ucfirst($e->status) }}
              </span>
            </div>
          </div>

          <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="zv-badge zv-badge--{{ $severity }}">
              {{ ucfirst($severity) }}
            </span>

            @if($e->status === 'open')
              <span class="zv-badge zv-badge--danger">Action Required</span>
            @elseif($e->status === 'reviewed')
              <span class="zv-badge zv-badge--ok">Reviewed</span>
            @else
              <span class="zv-badge zv-badge--ok">Closed</span>
            @endif
          </div>
        </div>

        <div class="zv-sec-meta">
          <div>
            <span>Time:</span>
            {{ optional($e->created_at)?->setTimezone($viewerTz)?->format('M d, Y · H:i') ?: '—' }}
          </div>

          <div class="text-capitalize">
            <span class="text-capitalize">Message:</span> {{ $msg ?: '—' }}
          </div>

          @if($role)
            <div>
              <span>Role:</span> {{ ucfirst($role) }}
            </div>
          @endif

          @if($ip)
            <div>
              <span>IP:</span> {{ $ip }}
            </div>
          @endif

          @if($userAgent)
            <div>
              <span>Device:</span> {{ Str::limit($userAgent, 100) }}
            </div>
          @endif

          @if($e->status === 'reviewed')
            <div>
              <span>Reviewed By:</span> {{ $e->reviewer?->email ?: '—' }}
            </div>

            <div>
              <span>Reviewed At:</span>
              {{ optional($e->reviewed_at)?->setTimezone($viewerTz)?->format('M d, Y · H:i') ?: '—' }}
            </div>
          @endif
        </div>

        @if($e->status === 'open')
          <form method="post" action="{{ route('superadmin.security.events.review', $e) }}">
            @csrf
            <button class="btn ok small" type="submit">Mark Reviewed</button>
          </form>
        @endif
      </div>
    @empty
      <div class="zv-empty">
        <div class="zv-empty__title">No Events</div>
        <div class="zv-empty__sub text-capitalize">When security rules trigger, items appear here.</div>
      </div>
    @endforelse
  </div>

  <div class="zv-sec-pager">
    {{ $events->links() }}
  </div>
</section>
@endsection