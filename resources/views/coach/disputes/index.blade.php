@extends('layouts.role-dashboard')
@section('title', __('Disputes'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/coach-disputes.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
@endpush

@section('role-content')
@php
  $decisionText = function ($action, $fallbackStatus) {
    return match ((string) $action) {
     
      'refund_full'    => 'Refund Full',
      'refund_service' => 'Refund Service Only',
      'pay_coach'      => 'Pay Coach',
      default          => strtoupper(str_replace('_',' ', (string)($action ?: $fallbackStatus))),
    };
  };
@endphp

<div class="dv-page">
  <div class="card">
    <div class="card__head">
      <div>
        <h2 class="h2">My Disputes</h2>
        <p class="muted text-capitalize">You Can Only See Disputes That You Opened.</p>
      </div>

      <a class="btn btn--ghost text-capitalize bg-black text-white rounded-pill" href="{{ route('coach.bookings',['tab'=>'my']) }}">
        Back to bookings
      </a>
    </div>

    <div class="dv-tableWrap">
      <table class="dv-table">
        <thead>
          <tr>
            <th class="dv-col-id">ID</th>
            <th>Title</th>
            <th class="dv-col-res">Reservation</th>
            <th class="dv-col-status">Status</th>
            <th class="dv-col-actions">Action</th>
          </tr>
        </thead>

        <tbody>
          @forelse($disputes as $d)
            @php
              $showUrl = route('coach.disputes.show', $d);

              $statusKey = strtolower(trim((string)($d->status ?? 'open')));
              if (!in_array($statusKey, ['open','opened','in_review','resolved','rejected'], true)) {
                $statusKey = 'open';
              }

              $isFinal = !empty($d->resolved_at) || in_array($statusKey, ['resolved','rejected'], true);

              $agent = $d->assignedStaff ?? null;
              $agentName = $agent?->username ?: ($agent?->email ?: null);

              // ✅ correct field name
              $decisionAction = $d->decision_action ?? null;

              $title = (string)($d->title_label ?? 'Dispute');
              $lastUpdateHuman = optional($d->last_message_at)->diffForHumans() ?? '—';
            @endphp

            <tr class="dv-row"
                data-href="{{ $showUrl }}"
                tabindex="0"
                role="link"
                aria-label="Open dispute #{{ $d->id }}">
              <td class="dv-id">#{{ $d->id }}</td>

              <td class="dv-titleCell">
                <div class="dv-title">{{ $title }}</div>

                <div class="dv-meta">
                  <span class="dv-metaItem text-capitalize">
                    Last update: <b>{{ $lastUpdateHuman }}</b>
                  </span>

                  <span class="dv-dot">•</span>

                  {{-- handled by / queue --}}
                  @if($agentName)
                    <span class="dv-metaItem">
                      Handled by: <b>{{ '@'.$agentName }}</b>
                    </span>
                  @else
                    @if($statusKey === 'open')
                      <span class="dv-metaItem dv-metaItem--muted">In Queue</span>
                    @elseif($statusKey === 'in_review')
                      <span class="dv-metaItem dv-metaItem--muted">In Review</span>
                    @else
                      <span class="dv-metaItem dv-metaItem--muted">—</span>
                    @endif
                  @endif

                  {{-- final decision --}}
                  @if($isFinal)
                    <span class="dv-dot">•</span>
                    <span class="dv-metaItem">
                      Final decision: <b>{{ $decisionText($decisionAction, $statusKey) }}</b>
                    </span>
                  @endif
                </div>
              </td>

              <td class="dv-resCell">
                <span class="dv-resStatic">
                  Reservation #{{ $d->reservation_id }}
                </span>
              </td>

              <td class="dv-statusCell">
                <span class="dv-chip dv-chip--{{ $statusKey }}">
                  {{ $d->status_label }}
                </span>

                @if($isFinal)
                  <span class="dv-chip dv-chip--final">Final</span>
                @endif
              </td>

              <td class="dv-actions">
                <a class="dv-actionBtn"
                   href="{{ $showUrl }}"
                   title="Open dispute chat"
                   aria-label="Open dispute chat">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4.5 3.4A1 1 0 0 1 3 19.9V6a2 2 0 0 1 2-2z"/>
                  </svg>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="dv-emptyCell">No Disputes Yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="dv-pager">{{ $disputes->links() }}</div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Row click navigation
  document.addEventListener('click', function (e) {
    const row = e.target.closest('.dv-row');
    if (!row) return;
    if (e.target.closest('a,button')) return;

    const href = row.getAttribute('data-href');
    if (href) window.location.href = href;
  });

  // Keyboard access
  document.addEventListener('keydown', function (e) {
    const row = document.activeElement?.closest?.('.dv-row');
    if (!row) return;

    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      const href = row.getAttribute('data-href');
      if (href) window.location.href = href;
    }
  });
</script>
@endpush