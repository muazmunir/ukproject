@extends('superadmin.layout')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-disputes.css') }}">
@endpush

@section('content')
<div class="ad-page">
  <div class="ad-card">
    <div>
        <div class="ad-title">Disputes</div>
        <div class="ad-sub text-capitalize ">All client & coach raised disputes</div>
      </div>
    <div class="ad-head">
      

      <div class="ad-head__right">
        <a href="{{ route('superadmin.disputes.index') }}" class="ad-btn ad-btn--ghost">
          <i class="bi bi-arrow-clockwise"></i>
          <span>Refresh</span>
        </a>
      </div>
    </div>

    {{-- Filters --}}
    <div class="ad-tools">
      <div class="ad-seg">
        @php $s = request('status'); @endphp

        @foreach(['open','in_review','resolved'] as $st)
          @php
            $label = $st === 'in_review'
              ? 'Pending / Unresolved'
              : ucfirst(str_replace('_',' ',$st));
          @endphp

          <button type="button"
                  class="ad-seg__btn {{ $s===$st ? 'is-active' : '' }}"
                  onclick="location='?status={{ $st }}{{ request('q') ? '&q='.urlencode(request('q')) : '' }}'">
            {{ $label }}
          </button>
        @endforeach

        <button type="button"
                class="ad-seg__btn {{ !$s ? 'is-active' : '' }}"
                onclick="location='{{ route('superadmin.disputes.index', array_filter(['q'=>request('q')])) }}'">
          All
        </button>
      </div>

      <form class="ad-search" method="GET" action="{{ route('superadmin.disputes.index') }}">
        @if(request('status'))
          <input type="hidden" name="status" value="{{ request('status') }}">
        @endif

        <i class="bi bi-search ad-search__icon"></i>
        <input class="ad-search__input"
               type="text"
               name="q"
               value="{{ request('q') }}"
               placeholder="Search By Dispute ID, Booking ID, Username, Service...">
        <button class="ad-btn ad-btn--primary" type="submit">Search</button>
      </form>
    </div>

    {{-- Table --}}
    <div class="ad-tablewrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>Booking</th>
            <th>Participants</th>
            <th>Status</th>
            <th style="width:140px;">Created</th>
            <th style="width:140px;">In Review</th>
            <th style="width:180px;">Assigned To</th>
            <th style="width:180px;">SLA</th>
            <th style="width:140px;" class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
        @forelse($rows as $row)
          @php
            /** @var \App\Models\Dispute $row */
            $res = $row->reservation;

            $serviceTitle = $res?->service?->title ?? '—';
            $status = strtolower((string)($row->status ?? 'open'));

            $pillClass = match($status) {
              'resolved'  => 'ok',
              'rejected'  => 'danger',
              'in_review' => 'warn',
              default     => 'neutral',
            };

            $chatUrl = route('superadmin.disputes.show', $row->id);
          @endphp

          <tr>
            <td class="ad-mono">#{{ $row->id }}</td>

            <td>
              <div class="ad-namecol">
                <div class="ad-namecol__top">
                  <strong class="ad-mono">Booking #{{ $res?->id ?? '—' }}</strong>
                  {{-- <span class="ad-muted">•</span>
                  <span class="ad-muted">{{ $serviceTitle }}</span> --}}
                </div>

                <div class="ad-namecol__sub ad-muted">
                  {{ ucfirst((string)($row->opened_by_role ?? 'user')) }} dispute
                </div>
              </div>
            </td>

            <td>
              <div class="ad-who">
                <div class="ad-person">
                  <span class="ad-badge">Client</span>
                  <span class="ad-person__name">{{ $res?->client?->username ?: ($res?->client?->email ?: '—') }}</span>
                </div>

                <div class="ad-person">
                  <span class="ad-badge">Coach</span>
                  <span class="ad-person__name">{{ $res?->service?->coach?->username ?: ($res?->service?->coach?->email ?: '—') }}</span>
                </div>
              </div>
            </td>

            <td>
              <span class="ad-pill {{ $pillClass }}">{{ $row->status_label }}</span>
            </td>

            <td class="ad-muted">
              {{ optional($row->created_at)->format('d M Y') }}
            </td>

            <td>
              @php
                $daysInReview = null;
                if ($status === 'in_review' && !empty($row->in_review_started_at)) {
                  $start = $row->in_review_started_at instanceof \Carbon\CarbonInterface
                    ? $row->in_review_started_at
                    : \Carbon\Carbon::parse($row->in_review_started_at);

                  $daysInReview = max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
                }
              @endphp

              @if($status === 'in_review' && !is_null($daysInReview))
                <span class="ad-badge ad-badge--solid">
                  {{ $daysInReview === 0 ? 'Today' : ($daysInReview.' day'.($daysInReview>1?'s':'')) }}
                </span>
              @else
                <span class="ad-muted">—</span>
              @endif
            </td>

            <td>
              @php
                $staffId   = (int)($row->assigned_staff_id ?? 0);
                $staffUser = $row->assignedStaff ?? null;
                $staffName = $staffUser?->username ?: ($staffUser?->email ?? null);
              @endphp

              @if($staffId > 0)
                <div class="ad-taken">
                  <span class="ad-taken__name">
                    {{ $staffName ?: ('Staff #'.$staffId) }}
                  </span>
                </div>
              @else
                <span class="ad-muted">Queue</span>
              @endif
            </td>

            <td>
              @php
                $mins = null;
                if (!empty($row->sla_started_at)) {
                  $mins = (int) \Carbon\Carbon::parse($row->sla_started_at)->diffInMinutes(now());
                }

                $slaClass = 'badge--sla-dead';
                if (!is_null($mins)) {
                  if ($mins <= 5) $slaClass = 'badge--sla-ok';
                  elseif ($mins == 6) $slaClass = 'badge--sla-warn';
                  elseif ($mins == 7) $slaClass = 'badge--sla-bad';
                  else $slaClass = 'badge--sla-dead';
                }
              @endphp

              @if(is_null($mins))
                <span class="badge badge--sla-dead">—</span>
              @else
                <span class="badge {{ $slaClass }}">{{ $mins }} m</span>
              @endif
            </td>

            <td class="text-end">
              <div class="ad-actions">
                <a href="{{ $chatUrl }}#chat"
                   class="ad-iconbtn ad-iconbtn--primary"
                   title="Open chat">
                  <i class="bi bi-chat-dots"></i>
                </a>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="ad-empty">
              <div class="ad-empty__title text-capitalize">No disputes found</div>
              <div class="ad-empty__sub text-capitalize">Try changing filters or search keywords.</div>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="ad-pager">
      {{ $rows->links() }}
    </div>

  </div>
</div>
@endsection