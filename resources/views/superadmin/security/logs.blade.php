@php
  $viewerTz = auth()->user()->timezone ?? config('app.timezone');
  $q       = $q ?? request('q', '');
  $action  = $action ?? request('action', 'all');

  $range   = $range ?? request('range', 'lifetime');
  $date    = $date ?? request('date', '');
  $from    = $from ?? request('from', '');
  $to      = $to ?? request('to', '');
  $startAt = $startAt ?? request('start_at', '');
  $endAt   = $endAt ?? request('end_at', '');

  $roleUi = [
    'admin'       => 'Agent',
    'manager'     => 'Manager',
    'client'      => 'Client',
    'coach'       => 'Coach',
    'super_admin' => 'Super Admin',
  ];

  $actionLabels = [
    'delete_user'          => 'User Deleted',
    'delete_service'       => 'Service Deleted',
    'restore_service'      => 'Service Restored',
    'restore_user_client'  => 'Client Restored',
    'restore_user_coach'   => 'Coach Restored',
    'hard_locked'          => 'Account Locked',
    'hard_unlocked'        => 'Account Unlocked',
    'soft_locked'          => 'Soft Locked',
    'soft_unlocked'        => 'Soft Unlocked',
    'payment_toggle'       => 'Payment Toggle',
  ];

  $reasonFallback = function ($meta) {
    $m = (array) ($meta ?? []);
    if (!empty($m['reason_label'])) return $m['reason_label'];
    if (!empty($m['reason'])) return ucwords(str_replace('_', ' ', $m['reason']));
    return null;
  };
@endphp

@extends('superadmin.layout')

@section('title', 'Security · Action Logs')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/superadmin-security-logs.css') }}">
@endpush

@section('content')
<section class="card zv-sec-card">
  <div class="zv-sec-head__content py-2">
    <div class="card__title text-center">Action Logs</div>
    <div class="muted text-center">Every security action is tracked here</div>
  </div>
  <div class="card__head zv-sec-head ">

    <div class="zv-sec-actions">
      <a class="btn ghost small" href="{{ route('superadmin.security.locked_staff') }}">Locked Staff</a>
      <a class="btn ghost small" href="{{ route('superadmin.security.events') }}">Security Events</a>
    </div>
  </div>

  <div class="zv-sec-toolbar">
    <form method="get" class="zv-sec-filters" id="logsFilterForm">
      <div class="zv-field">
        <label for="log_q">Search Staff</label>
        <input id="log_q" type="text" name="q" value="{{ $q }}" placeholder="Name or email">
      </div>

      <div class="zv-field">
        <label for="log_action">Action</label>
        <select id="log_action" name="action">
          <option value="all" {{ $action === 'all' ? 'selected' : '' }}>All</option>
          @foreach($actions as $a)
            <option value="{{ $a }}" {{ $action === $a ? 'selected' : '' }}>
              {{ $actionLabels[$a] ?? ucwords(str_replace('_', ' ', $a)) }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="zv-field">
        <label for="rangeSelect">
          Time Filter
          <span class="zv-inline-note">({{ $viewerTz }})</span>
        </label>

        <select id="rangeSelect" name="range">
          <option value="lifetime" {{ $range === 'lifetime' ? 'selected' : '' }}>Lifetime</option>
          <option value="daily" {{ $range === 'daily' ? 'selected' : '' }}>Daily (Today)</option>
          <option value="weekly" {{ $range === 'weekly' ? 'selected' : '' }}>Weekly</option>
          <option value="monthly" {{ $range === 'monthly' ? 'selected' : '' }}>Monthly</option>
          <option value="yearly" {{ $range === 'yearly' ? 'selected' : '' }}>Yearly</option>
          <option value="window" {{ $range === 'window' ? 'selected' : '' }}>Specific Date / Time Window</option>
          <option value="custom" {{ $range === 'custom' ? 'selected' : '' }}>Custom Range</option>
        </select>
      </div>

      <div class="zv-field" id="windowFields" @if($range !== 'window') hidden @endif>
        <label for="log_date">Date</label>
        <input id="log_date" type="date" name="date" value="{{ $date }}">
      </div>

      <div class="zv-field" id="windowFrom" @if($range !== 'window') hidden @endif>
        <label for="log_from">From</label>
        <input id="log_from" type="time" name="from" value="{{ $from }}">
      </div>

      <div class="zv-field" id="windowTo" @if($range !== 'window') hidden @endif>
        <label for="log_to">To</label>
        <input id="log_to" type="time" name="to" value="{{ $to }}">
      </div>

      <div class="zv-field" id="customStart" @if($range !== 'custom') hidden @endif>
        <label for="log_start_at">Start</label>
        <input id="log_start_at" type="datetime-local" name="start_at" value="{{ $startAt }}">
      </div>

      <div class="zv-field" id="customEnd" @if($range !== 'custom') hidden @endif>
        <label for="log_end_at">End</label>
        <input id="log_end_at" type="datetime-local" name="end_at" value="{{ $endAt }}">
      </div>

      <div class="zv-sec-filters__actions">
        <button class="btn primary small" type="submit">Apply</button>
        <a class="btn ghost small" href="{{ route('superadmin.security.logs') }}">Reset</a>
      </div>
    </form>
  </div>

  <div class="zv-table">
    <div class="zv-tr zv-th">
      <div>Time</div>
      <div>Admin</div>
      <div>Action</div>
      <div>Target</div>
      <div>IP</div>
    </div>

    @forelse($logs as $l)
      @php
        $m = (array) ($l->meta ?? []);

        $actionUi = $actionLabels[$l->action] ?? ucwords(str_replace('_', ' ', $l->action));
        $reasonUi = $reasonFallback($l->meta);

        $reasonWords = preg_split('/\s+/', trim((string) $reasonUi)) ?: [];
        $reasonPreview = $reasonUi
          ? (count($reasonWords) > 3
              ? implode(' ', array_slice($reasonWords, 0, 3)) . '...'
              : $reasonUi)
          : null;

        $isUserTarget = $l->target_type === \App\Models\Users::class;
        $isServiceTarget = $l->target_type === \App\Models\Service::class;

        $targetUser = $isUserTarget ? ($targetUsers[$l->target_id] ?? null) : null;
        $targetService = $isServiceTarget ? ($targetServices[$l->target_id] ?? null) : null;

        $metaName  = $m['target_name'] ?? $m['name'] ?? null;
        $metaEmail = $m['target_email'] ?? $m['email'] ?? null;
        $metaRole  = $m['target_role'] ?? $m['role'] ?? null;
      @endphp

      <div class="zv-tr">
        <div class="mono" data-label="Time">
          {{ $l->created_at->timezone($viewerTz)->format('M d, Y · H:i:s') }}
        </div>

        <div data-label="Admin">
          <div class="zv-strong">{{ trim(($l->admin->first_name ?? '') . ' ' . ($l->admin->last_name ?? '')) ?: '—' }}</div>
          <div class="muted">{{ $l->admin->email ?? '—' }}</div>
        </div>

        <div class="zv-log-action" data-label="Action">
          <span class="pill">{{ $actionUi }}</span>

          @if(in_array($l->action, ['hard_locked','soft_locked','hard_unlocked','soft_unlocked'], true) && $reasonUi)
            <span class="zv-reason-preview-wrap">
              <span class="zv-reason-preview">{{ $reasonPreview }}</span>
              <span class="zv-reason-tooltip">
                <span class="zv-reason-tooltip__label">Full Reason</span>
                <span class="zv-reason-tooltip__text">{{ $reasonUi }}</span>
              </span>
            </span>
          @endif
        </div>

        <div data-label="Target">
          @if($isUserTarget)
            @php
              $name = $targetUser
                ? (trim(($targetUser->first_name ?? '') . ' ' . ($targetUser->last_name ?? '')) ?: '—')
                : ($metaName ?: '—');

              $email = $targetUser?->email ?? $metaEmail ?? '—';
              $role = $targetUser?->role ?? $metaRole ?? null;
              $roleLabel = $role ? ($roleUi[$role] ?? ucwords(str_replace('_', ' ', $role))) : 'User';
            @endphp

            <div class="zv-target">
              <div class="zv-target__title">
                <span class="zv-strong">{{ $name }}</span>
                @if($targetUser && $targetUser->trashed())
                  <span class="pill danger">Deleted</span>
                @endif
              </div>

              <div class="muted">{{ $email }}</div>

              <div class="zv-target__meta">
                <span class="pill">{{ $roleLabel }}</span>
              </div>
            </div>

          @elseif($isServiceTarget)
            @php
              $title = $targetService?->title ?? $m['service_title'] ?? 'Service';
              $coachEmail = $m['coach_email'] ?? $targetService?->coach?->email ?? null;
            @endphp

            <div class="zv-target">
              <div class="zv-strong">{{ $title }}</div>

              @if($coachEmail)
                <div class="muted">{{ $coachEmail }}</div>
              @endif

              <div class="zv-target__meta">
                <span class="pill">Service</span>
              </div>
            </div>

          @else
            <div class="muted">—</div>
          @endif
        </div>

        <div class="mono muted" data-label="IP">
          {{ $l->ip ?: '—' }}
        </div>
      </div>
    @empty
      <div class="zv-empty">
        <div class="zv-empty__title">No logs</div>
        <div class="zv-empty__sub">Logs will appear as actions occur.</div>
      </div>
    @endforelse
  </div>

  <div class="zv-sec-pager">
    {{ $logs->links() }}
  </div>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    const range = document.getElementById('rangeSelect');

    const windowFields = [
      document.getElementById('windowFields'),
      document.getElementById('windowFrom'),
      document.getElementById('windowTo'),
    ];

    const customFields = [
      document.getElementById('customStart'),
      document.getElementById('customEnd'),
    ];

    function setVisible(el, yes) {
      if (!el) return;
      el.hidden = !yes;
    }

    function sync() {
      const v = (range.value || '').toLowerCase();

      windowFields.forEach(el => setVisible(el, v === 'window'));
      customFields.forEach(el => setVisible(el, v === 'custom'));
    }

    range.addEventListener('change', sync);
    sync();
  })();
</script>
@endpush