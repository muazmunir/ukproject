@extends('layouts.admin')
@section('title','Absence Review')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support_absence_review.css') }}">
@endpush

@section('content')
<div class="abs-review container py-3">

  <div class="card abs-card">
    <div class="card__head">
      <div>
        <div class="card__title">Absence Review</div>
        <div class="muted">Review admin requests • approve as Authorized or reject as Unauthorized</div>
      </div>
    </div>

    {{-- TOP: Segmented filters --}}
    @php
      $state = request('state', $state ?? 'pending');  // pending|approved|cancelled|all
      $range = request('range', $range ?? 'month');    // day|week|month|year|lifetime
      $q     = request('q', $q ?? '');
      $counts = $counts ?? [
        'pending' => null,
        'approved' => null,
        'cancelled' => null,
        'all' => null,
      ];
    @endphp

    <div class="segmented" role="tablist" aria-label="Absence filters">

      {{-- State --}}
      <input type="radio" id="st_pending" name="__state" {{ $state==='pending' ? 'checked' : '' }}>
      <label for="st_pending" data-set="state" data-value="pending">
        Pending
        @if(!is_null($counts['pending']))
          <span class="badge warn">{{ $counts['pending'] }}</span>
        @endif
      </label>

      <input type="radio" id="st_approved" name="__state" {{ $state==='approved' ? 'checked' : '' }}>
      <label for="st_approved" data-set="state" data-value="approved">
        Approved
        @if(!is_null($counts['approved']))
          <span class="badge ok">{{ $counts['approved'] }}</span>
        @endif
      </label>

      <input type="radio" id="st_cancelled" name="__state" {{ $state==='cancelled' ? 'checked' : '' }}>
      <label for="st_cancelled" data-set="state" data-value="cancelled">
        Cancelled
        @if(!is_null($counts['cancelled']))
          <span class="badge">{{ $counts['cancelled'] }}</span>
        @endif
      </label>

      <input type="radio" id="st_all" name="__state" {{ $state==='all' ? 'checked' : '' }}>
      <label for="st_all" data-set="state" data-value="all">
        All
        @if(!is_null($counts['all']))
          <span class="badge">{{ $counts['all'] }}</span>
        @endif
      </label>

      <div class="seg-divider"></div>

      {{-- Range --}}
      <input type="radio" id="rg_day" name="__range" {{ $range==='day' ? 'checked' : '' }}>
      <label for="rg_day" data-set="range" data-value="day">Day</label>

      <input type="radio" id="rg_week" name="__range" {{ $range==='week' ? 'checked' : '' }}>
      <label for="rg_week" data-set="range" data-value="week">Week</label>

      <input type="radio" id="rg_month" name="__range" {{ $range==='month' ? 'checked' : '' }}>
      <label for="rg_month" data-set="range" data-value="month">Month</label>

      <input type="radio" id="rg_year" name="__range" {{ $range==='year' ? 'checked' : '' }}>
      <label for="rg_year" data-set="range" data-value="year">Year</label>

      <input type="radio" id="rg_life" name="__range" {{ $range==='lifetime' ? 'checked' : '' }}>
      <label for="rg_life" data-set="range" data-value="lifetime">Lifetime</label>
    </div>

    {{-- Tools: Search + per page --}}
    <div class="tools">
      <form class="search-form" method="GET" action="">
        <span class="search-icon">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M10.5 19a8.5 8.5 0 1 1 0-17 8.5 8.5 0 0 1 0 17Z" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </span>

        <input type="hidden" name="state" value="{{ $state }}">
        <input type="hidden" name="range" value="{{ $range }}">

        <input type="search" name="q" value="{{ $q }}" placeholder="Search by ID, admin username, reason…">
      </form>

      <form class="per-form" method="GET" action="">
        <label class="per-label">
          Per page
          <select name="per" onchange="this.form.submit()">
            @php $per = (int) request('per', 20); @endphp
            @foreach([10,20,30,50] as $n)
              <option value="{{ $n }}" {{ $per===$n ? 'selected' : '' }}>{{ $n }}</option>
            @endforeach
          </select>
        </label>

        <input type="hidden" name="state" value="{{ $state }}">
        <input type="hidden" name="range" value="{{ $range }}">
        <input type="hidden" name="q" value="{{ $q }}">
      </form>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
      <table class="zv-table">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:220px;">Admin</th>
            <th>Window</th>
            <th style="width:160px;">State</th>
            <th style="width:190px;">Decision</th>
            <th class="ta-center" style="width:120px;">Details</th>
          </tr>
        </thead>

        <tbody>
        @forelse($requests as $req)
          @php
            $adminName = optional($req->agent)->username ?? ('User#'.$req->agent_id);
            $stateLower = strtolower((string)$req->state);
            $typeLower  = strtolower((string)($req->type ?? ''));
            $files = $req->files ?? collect();

            $statePill =
              $stateLower === 'pending' ? 'pending' :
              ($stateLower === 'approved' ? 'ok' : '');

            $decisionPill =
              $typeLower === 'authorized' ? 'ok' :
              ($typeLower === 'unauthorized' ? 'danger' : '');

          $agentTz = optional($req->agent)->timezone ?: 'UTC';

$startAtUtc = \Carbon\Carbon::parse($req->start_at)->utc();
$endAtUtc   = \Carbon\Carbon::parse($req->end_at)->utc();

$startAt = $startAtUtc->copy()->tz($agentTz);
$endAt   = $endAtUtc->copy()->tz($agentTz);

            $decidedAt = $req->decided_at ? \Carbon\Carbon::parse($req->decided_at) : null;
          @endphp

          <tr>
            <td class="fw-bold">#{{ $req->id }}</td>

            <td>
              <div class="namecol">
                <div class="fw-bold">{{ $adminName }}</div>
                <div class="uname">Agent ID: {{ $req->agent_id }}</div>
              </div>
            </td>

            <td>
              <div class="namecol">
                <div><span class="uname">Start</span> — <strong>{{ $startAt->format('d M Y, H:i') }}</strong></div>
<div><span class="uname">End</span> — <strong>{{ $endAt->format('d M Y, H:i') }}</strong></div>
<div class="uname" style="margin-top:2px;">
  Timezone: <strong>{{ $agentTz }}</strong>
</div>

              </div>
            </td>

            <td>
              <span class="pill {{ $statePill }}">
                <i class="bi {{ $stateLower==='pending' ? 'bi-hourglass-split' : ($stateLower==='approved' ? 'bi-check2-circle' : 'bi-slash-circle') }}"></i>
                {{ ucfirst($req->state) }}
              </span>
            </td>

            <td class="text-capitalize">
              @if($req->type)
                <span class="pill {{ $decisionPill }}">
                  <i class="bi {{ $typeLower==='authorized' ? 'bi-shield-check' : 'bi-shield-x' }}"></i>
                  {{ $req->type }}
                </span>
              @else
                <span class="muted">—</span>
              @endif
            </td>

            <td class="ta-center">
              <div class="row-actions compact">
                <button type="button"
                        class="btn icon info"
                        data-open="mgrAbsModal-{{ $req->id }}"
                        title="View details">
                  <i class="bi bi-info-circle"></i>
                </button>
              </div>
            </td>
          </tr>

          {{-- Modal (details + approve/reject if pending) --}}
          <div class="modal" id="mgrAbsModal-{{ $req->id }}" aria-hidden="true">
            <div class="modal__dialog">
              <div class="modal__card">

                <div class="modal__head">
                  <div>
                    <div class="title">Absence Request #{{ $req->id }}</div>
                    <div class="muted">
                      Admin: <strong>{{ $adminName }}</strong>
                      <span class="pill {{ $statePill }}" style="margin-left:8px;">{{ ucfirst($req->state) }}</span>
                      @if($req->type)
                        <span class="pill {{ $decisionPill }}" style="margin-left:6px;">{{ ucfirst($req->type) }}</span>
                      @endif
                    </div>
                  </div>

                  <button class="x" type="button" data-close="mgrAbsModal-{{ $req->id }}">
                    <i class="bi bi-x"></i>
                  </button>
                </div>

                <div class="modal__body">

                  <div class="abs-kv">
                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Window</div>
                      <div class="abs-kv__v">
                       <div><strong>Start:</strong> {{ $startAt->format('d M Y, H:i') }}</div>
<div><strong>End:</strong> {{ $endAt->format('d M Y, H:i') }}</div>
<div class="muted" style="margin-top:6px;">
  Displayed in agent timezone: <strong>{{ $agentTz }}</strong>
</div>

                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Reason</div>
                      <div class="abs-kv__v">
                        <div class="abs-box">{{ $req->reason }}</div>
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Comments</div>
                      <div class="abs-kv__v">
                        @if($req->comments)
                          <div class="abs-box">{{ $req->comments }}</div>
                        @else
                          <div class="muted">—</div>
                        @endif
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Proofs</div>
                      <div class="abs-kv__v">
                        @if($files->count())
                          <div class="abs-proof-list">
                            @foreach($files as $f)
                              <a class="abs-proof-link"
                                 href="{{ route('admin.support.absence.review_request_file.download', $f) }}">
                                <i class="bi bi-paperclip me-1"></i>
                                <span class="abs-proof-name">{{ $f->original_name ?? 'Attachment' }}</span>
                                <span class="abs-proof-meta">
                                  @if($f->size) • {{ number_format($f->size/1024,0) }} KB @endif
                                </span>
                              </a>
                            @endforeach
                          </div>
                        @else
                          <div class="muted">No proofs found.</div>
                        @endif
                      </div>
                    </div>

                    <div class="abs-kv__row">
                      <div class="abs-kv__k">Decision</div>
                      <div class="abs-kv__v">
                        @if($req->decided_by || $req->decided_at)
                          <div class="abs-trans">
                            <div class="abs-trans__row">
                              <div class="abs-trans__k">Decided By</div>
                              <div class="abs-trans__v">
                                {{ optional($req->decider)->username ?? ('User#'.$req->decided_by) }}
                              </div>
                            </div>
                            <div class="abs-trans__row">
                              <div class="abs-trans__k">Decided At</div>
                              <div class="abs-trans__v">{{ $decidedAt ? $decidedAt->format('d M Y, H:i') : '—' }}</div>
                            </div>
                            <div class="abs-trans__row">
                              <div class="abs-trans__k">Note</div>
                              <div class="abs-trans__v">
                                @if($req->decision_note)
                                  <div class="abs-box">{{ $req->decision_note }}</div>
                                @else
                                  <div class="muted">—</div>
                                @endif
                              </div>
                            </div>
                          </div>
                        @else
                          <div class="muted">No decision yet.</div>
                        @endif
                      </div>
                    </div>

                    {{-- Approve/Reject actions (ONLY if pending) --}}
                    @if(strtolower((string)$req->state) === 'pending')
                      <div class="abs-kv__row">
                        <div class="abs-kv__k">Take Action</div>
                        <div class="abs-kv__v">
                          <form method="POST"
                                action="{{ route('admin.support.absence.decide', $req) }}"
                                onsubmit="return confirm('Apply this decision?')"
                                class="abs-actionbox">
                            @csrf

                            <input type="text"
                                   name="note"
                                   class="abs-note"
                                   maxlength="255"
                                   placeholder="Optional note (max 255)">

                            <div class="abs-actionbtns">
                              <button class="btn success" type="submit" name="decision" value="approve">
                                <i class="bi bi-check2-circle me-1"></i> Approve (Authorized)
                              </button>
                              <button class="btn danger" type="submit" name="decision" value="reject">
                                <i class="bi bi-x-circle me-1"></i> Reject (Unauthorized)
                              </button>
                            </div>

                            <div class="muted" style="margin-top:8px;">
                              Manager cannot upload files here. Only admin proofs are allowed.
                            </div>
                          </form>
                        </div>
                      </div>
                    @endif

                  </div>
                </div>

                <div class="modal__foot">
                  <div class="muted">
                    Submitted: {{ \Carbon\Carbon::parse($req->created_at)->format('d M Y, H:i') }}
                  </div>
                  <button class="btn" type="button" data-close="mgrAbsModal-{{ $req->id }}">Close</button>
                </div>

              </div>
            </div>
          </div>

        @empty
          <tr>
            <td colspan="6" class="ta-center muted" style="padding:18px;">
              No requests found for this filter.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="pager">
      {{ $requests->appends(request()->query())->links() }}
    </div>
  </div>

</div>

@push('scripts')
<script>
(function(){
  // segmented: click label => update URL params + reload
  function setParam(key, val){
    const url = new URL(window.location.href);
    url.searchParams.set(key, val);
    url.searchParams.delete('page'); // reset paging when filters change
    window.location.href = url.toString();
  }

  document.querySelectorAll('.segmented [data-set]').forEach(el => {
    el.addEventListener('click', function(){
      setParam(this.getAttribute('data-set'), this.getAttribute('data-value'));
    });
  });

  // modal open/close
  function openModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.classList.add('open');
    el.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.classList.remove('open');
    el.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function(e){
    const openBtn = e.target.closest('[data-open]');
    if(openBtn){
      e.preventDefault();
      openModal(openBtn.getAttribute('data-open'));
      return;
    }
    const closeBtn = e.target.closest('[data-close]');
    if(closeBtn){
      e.preventDefault();
      closeModal(closeBtn.getAttribute('data-close'));
      return;
    }
    const modal = e.target.classList && e.target.classList.contains('modal') ? e.target : null;
    if(modal && modal.id){
      closeModal(modal.id);
    }
  });

  document.addEventListener('keydown', function(e){
    if(e.key !== 'Escape') return;
    const open = document.querySelector('.modal.open');
    if(open && open.id) closeModal(open.id);
  });
})();
</script>
@endpush

@endsection
