@extends('superadmin.layout')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-disputes-show.css') }}">
@endpush

@section('content')
@php
  // ------------------------------------------------------------
  // SAFE VALUES + FALLBACKS
  // ------------------------------------------------------------
  $status = strtolower((string)($dispute->status ?? 'open'));

  $pill = match ($status) {
    'resolved'  => 'ok',
    'rejected'  => 'danger',
    'in_review' => 'warn',
    default     => 'neutral',
  };

  // Role (superadmin)
  $myRole = (string)(auth()->user()->role ?? 'superadmin');

  // Assignment (still displayed)
  $assignedId = (int)($dispute->assigned_staff_id ?? 0);
  $isAssigned = $assignedId > 0;

  $meId   = (int)auth()->id();
  $isMine = $isAssigned && $assignedId === $meId;

  // Final status
  $finalized = !empty($dispute->resolved_at) || in_array($status, ['resolved','rejected'], true);

  // Day counter for in_review
  $daysInReview = null;
  if ($status === 'in_review' && !empty($dispute->in_review_started_at)) {
    $start = $dispute->in_review_started_at instanceof \Carbon\CarbonInterface
        ? $dispute->in_review_started_at
        : \Carbon\Carbon::parse($dispute->in_review_started_at);

    $daysInReview = max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
  }

  // ------------------------------------------------------------
  // ACTION PERMISSIONS (SUPERADMIN = FULL ACCESS)
  // ------------------------------------------------------------
  // Messaging + finalize:
  // - superadmin can act in any dispute (unless finalized)
  $blockedMsgFinalize = $finalized;

  // Close-to-queue (close conversation -> in_review)
  // - superadmin can close any dispute (unless finalized)
  $blockedClose = $finalized;

  // ------------------------------------------------------------
  // Reservation + users
  // ------------------------------------------------------------
  $reservation = $dispute->reservation;
  $service     = $reservation?->service;

  $clientUser = $reservation?->client;
  $coachUser  = $service?->coach;

  $clientName = $clientUser?->username
    ?: ($clientUser?->email ?: ($reservation?->client_id ? ('Client #'.$reservation->client_id) : 'Client'));

  $coachName = $coachUser?->username
    ?: ($coachUser?->email ?: ($service?->coach_id ? ('Coach #'.$service->coach_id) : 'Coach'));

  $serviceTitle = $service?->title ?? '—';

  // Dispute title label
  $disputeTitle = (string)($dispute->title_label ?? 'Dispute');

  // Assigned staff label (prefer controller variable else relation)
  $assignedStaffUser = $dispute->assignedStaff ?? null;
  $assignedByName = $takenByName
      ?? ($assignedStaffUser?->username ?: ($assignedStaffUser?->email ?? null));

  // Helper for label
  $whoLabel = function ($m) use ($clientName, $coachName) {
    $role = (string)($m->sender_role ?? 'user');

    if (in_array($role, ['admin','manager','superadmin'], true)) {
        return $m->sender?->username
            ?: ($m->sender?->email ?? ucfirst($role));
    }

    return match ($role) {
        'client' => $clientName ?: 'Client',
        'coach'  => $coachName ?: 'Coach',
        default  => ucfirst($role ?: 'User'),
    };
  };

  // Normalize message collections (works even if null)
  $clientMessages = collect($clientMessages ?? []);
  $coachMessages  = collect($coachMessages  ?? []);

  // ------------------------------------------------------------
  // FINAL DECISION (for finalized disputes)
  // ------------------------------------------------------------
  $decisionAction = $dispute->decision_action ?? null;
  $decisionNote   = $dispute->decision_note ?? null;
  $decidedAt      = $dispute->decided_at ?? $dispute->resolved_at ?? null;

  $decisionById   = (int)($dispute->decided_by_staff_id ?? $dispute->resolved_by_staff_id ?? 0);
  $decisionByName = $decisionById > 0
    ? (\App\Models\Users::find($decisionById)?->username ?? ('Staff #'.$decisionById))
    : null;

  $prettyAction = match((string)$decisionAction) {
    'refund_full'    => 'Refund Full Amount',
    'refund_service' => 'Refund Service Only',
    'pay_coach'      => 'Paid Coach',
    default          => $decisionAction ? ucwords(str_replace('_',' ', (string)$decisionAction)) : '—',
  };
@endphp

<div class="ad-page" id="chat">
  <div class="ad-card">

    {{-- Header --}}
    <div class="ad-head ad-head--tight">
      <div>
        <div class="ad-title">
          Dispute #{{ $dispute->id }}
          <span class="ad-muted">•</span>
          <span class="ad-muted">Booking #{{ $dispute->reservation_id }}</span>
        </div>

        {{-- Title label + service + opened by --}}
        <div class="ad-sub">
          <b>{{ $disputeTitle }}</b>
          <span class="ad-muted">•</span>
          {{ $serviceTitle }}
          <span class="ad-muted">•</span>
          Opened by: <b>{{ ucfirst((string)($dispute->opened_by_role ?? 'user')) }}</b>
        </div>
      </div>

      <div class="ad-head__right">
        <span class="ad-pill {{ $pill }}">
          {{ $dispute->status_label }}
        </span>

        @if($status === 'in_review' && !is_null($daysInReview))
          <span class="ad-pill warn" style="margin-left:8px;">
            {{ $daysInReview === 0 ? 'Today' : ($daysInReview.' day'.($daysInReview>1?'s':'')) }}
          </span>
        @endif

        <a href="{{ route('superadmin.disputes.index') }}" class="ad-btn ad-btn--ghost">
          <i class="bi bi-arrow-left"></i>
          <span>Back</span>
        </a>
      </div>
    </div>

    {{-- Info bar --}}
    <div class="ad-bar">
      <div class="ad-bar__left">

        <div class="ad-kv">
          <div class="ad-k">Created</div>
          <div class="ad-v">
            {{ $dispute->created_at?->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M Y, H:i') }}
          </div>
        </div>

        @if($status === 'in_review' && !empty($dispute->in_review_started_at))
          <div class="ad-kv">
            <div class="ad-k">In Review</div>
            <div class="ad-v">
              {{ $dispute->in_review_started_at?->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M Y, H:i') }}
              <span class="ad-muted">•</span>
              <b>{{ $daysInReview === 0 ? 'Today' : ($daysInReview.' day'.($daysInReview>1?'s':'')) }}</b>
            </div>
          </div>
        @endif

        <div class="ad-kv">
          <div class="ad-k">Handled by</div>
          <div class="ad-v">
            @if($isAssigned)
              <b>{{ $assignedByName ?: ('Staff #'.$assignedId) }}</b>

              @if($isMine)
                <span class="ad-pill ok" style="margin-left:8px;">You</span>
              @else
                <span class="ad-pill neutral" style="margin-left:8px;">Assigned</span>
              @endif

            @else
              <span class="ad-muted">In Queue</span>
            @endif
          </div>
        </div>

        {{-- SLA --}}
        <div class="ad-kv">
          <div class="ad-k">SLA</div>
          <div class="ad-v">
            @php
              $slaMins = null;
              if (!empty($dispute->sla_started_at)) {
                $slaMins = (int) \Carbon\Carbon::parse($dispute->sla_started_at)->diffInMinutes(now());
              }

              $now = now();
              $resDue = $dispute->sla_resolution_due_at ?? null;
              $resBreached = (!$finalized && $resDue && $resDue->lte($now));

              $slaClass = 'badge--sla-dead';
              if (!is_null($slaMins)) {
                if ($slaMins <= 5) $slaClass = 'badge--sla-ok';
                elseif ($slaMins == 6) $slaClass = 'badge--sla-warn';
                elseif ($slaMins == 7) $slaClass = 'badge--sla-bad';
                else $slaClass = 'badge--sla-dead';
              }
            @endphp

            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
              <div>
                <span class="ad-muted">Elapsed:</span>
                @if(is_null($slaMins))
                  <span class="ad-muted">—</span>
                @else
                  <span class="badge bg-black {{ $slaClass }}">{{ $slaMins }} m</span>
                @endif
              </div>

              @if($resDue)
                <div class="{{ $resBreached ? 'text-danger' : '' }}">
                  <span class="ad-muted">Due:</span>
                  <b>{{ $resDue->diffForHumans() }}</b>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="ad-kv">
          <div class="ad-k">Participants</div>
          <div class="ad-v">
            <span class="ad-muted">Client:</span> <b>{{ $clientName }}</b>
            <span class="ad-muted">•</span>
            <span class="ad-muted">Coach:</span> <b>{{ $coachName }}</b>
          </div>
        </div>

      </div>

      <div class="ad-bar__right" style="gap:10px; display:flex; align-items:center; flex-wrap:wrap; justify-content:flex-end;">

        {{-- Close conversation (send to in_review + unassign) --}}
        <button type="button"
                class="ad-btn ad-btn--ghost"
                data-bs-toggle="modal"
                data-bs-target="#closeToQueueModal"
                {{ $blockedClose ? 'disabled' : '' }}>
          <i class="bi bi-arrow-repeat"></i>
          <span>Send to Queue</span>
        </button>

        {{-- Finalize only when NOT finalized --}}
        @if(!$finalized)
          <form method="POST"
                action="{{ route('superadmin.disputes.finalize', $dispute) }}"
                class="ad-inline"
                onsubmit="return confirm('Finalize this dispute? This will lock it permanently and cannot be changed.');">
            @csrf

            <select name="action" class="ad-select" {{ $blockedMsgFinalize ? 'disabled' : '' }} required>
              <option value="">Select Action</option>
              <option value="refund_full_amount"  {{ old('action')==='refund_full_amount' ? 'selected' : '' }}>Refund Full amount</option>
              <option value="refund_service_only" {{ old('action')==='refund_service_only' ? 'selected' : '' }}>Refund Service only</option>
              <option value="pay_coach"           {{ old('action')==='pay_coach' ? 'selected' : '' }}>Pay Coach</option>
              <option value="reject_dispute"      {{ old('action')==='reject_dispute' ? 'selected' : '' }}>Reject Dispute</option>
            </select>

            <input class="ad-input"
                   name="note"
                   type="text"
                   placeholder="Optional Note For Resolution…"
                   value="{{ old('note') }}"
                   {{ $blockedMsgFinalize ? 'disabled' : '' }}>

            <button class="ad-btn ad-btn--success"
                    type="submit"
                    {{ $blockedMsgFinalize ? 'disabled' : '' }}>
              <i class="bi bi-check2-circle"></i>
              <span>Finalize</span>
            </button>
          </form>
        @endif

      </div>
    </div>

    {{-- Final Decision box (only if finalized) --}}
    @if($finalized)
      <div class="ad-summary" style="margin-top:14px;">
        <div class="ad-summary__head">
          <div class="ad-summary__title">
            <i class="bi bi-shield-check"></i> Final Decision
          </div>
        </div>

        <div class="ad-summary__meta">
          <b>{{ $prettyAction }}</b>

          @if($decisionByName)
            <span class="ad-muted">•</span>
            <span class="ad-muted">By</span> <b>{{ $decisionByName }}</b>
          @endif

          @if($decidedAt)
            <span class="ad-muted">•</span>
            <span class="ad-muted">
              {{ $decidedAt?->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M Y, H:i') }}
            </span>
          @endif
        </div>

        @if(!empty($decisionNote))
          <div class="ad-summary__body">{!! nl2br(e($decisionNote)) !!}</div>
        @else
          <div class="ad-summary__empty text-capitalize">No note was added.</div>
        @endif
      </div>
    @endif

    {{-- Latest Summary --}}
    <div class="ad-summary">
      <div class="ad-summary__head">
        <div class="ad-summary__title">
          <i class="bi bi-journal-text"></i> Latest Summary
        </div>

        <button class="ad-btn ad-btn--ghost"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#summaryHistoryModal">
          <i class="bi bi-clock-history"></i>
          <span>History</span>
        </button>
      </div>

      @if(!empty($dispute->latest_summary))
        <div class="ad-summary__meta">
          <span class="ad-muted">By</span>
          <b>{{ $dispute->latestSummaryBy?->username ?? ('Staff #'.(int)$dispute->latest_summary_by_id) }}</b>
          <span class="ad-muted">•</span>
          <span class="ad-muted">
            {{ optional($dispute->latest_summary_at)->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M Y, H:i') }}
          </span>
        </div>

        <div class="ad-summary__body">{!! nl2br(e($dispute->latest_summary)) !!}</div>
      @else
        <div class="ad-summary__empty text-capitalize">
          No summary yet. Close conversation to add one for the next agent.
        </div>
      @endif
    </div>

    {{-- Split chat --}}
    <div class="ad-split">

      {{-- LEFT: Client thread --}}
      <section class="ad-chat">
        <header class="ad-chat__head">
          <div class="ad-chat__title">
            <i class="bi bi-person"></i> Client Messages
          </div>
          <div class="ad-muted">{{ $clientName }}</div>
        </header>

        <div class="ad-chat__body">
          @forelse($clientMessages as $m)
            @php
              $mine = in_array((string)($m->sender_role ?? ''), ['admin','manager','superadmin'], true);
              $bubble = $mine ? 'is-admin' : 'is-user';
            @endphp

            <div class="ad-msg {{ $bubble }}">
              <div class="ad-msg__meta">
                <span class="ad-msg__who">{{ $whoLabel($m) }}</span>
                <span class="ad-muted">•</span>
                <span class="ad-muted">{{ $m->created_at?->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M, H:i') }}</span>
              </div>

              <div class="ad-msg__bubble">{!! nl2br(e($m->message ?? '')) !!}</div>

              @if(($m->attachments ?? null) && $m->attachments->count())
                <div class="ad-msg__atts">
                  @foreach($m->attachments as $a)
                    @php
                      $path = $a->path ?? $a->file_path ?? null;
                      $url  = $path ? asset('storage/'.$path) : null;

                      $mime  = strtolower((string)($a->mime ?? $a->mime_type ?? ''));
                      $isImg = $mime ? str_starts_with($mime, 'image/') : preg_match('/\.(png|jpe?g|gif|webp)$/i', (string)$path);
                    @endphp

                    @if($url)
                      @if($isImg)
                        <a class="ad-att" href="{{ $url }}" target="_blank">
                          <img src="{{ $url }}" alt="Attachment">
                        </a>
                      @else
                        <a class="ad-att ad-att--file" href="{{ $url }}" target="_blank">
                          <i class="bi bi-paperclip"></i>
                          <span>{{ basename((string)$path) }}</span>
                        </a>
                      @endif
                    @endif
                  @endforeach
                </div>
              @endif
            </div>
          @empty
            <div class="ad-chat__empty text-capitalize">No messages yet for client thread.</div>
          @endforelse
        </div>

        <form class="ad-chat__composer"
              method="POST"
              action="{{ route('superadmin.disputes.message', $dispute) }}">
          @csrf
          <input type="hidden" name="target_role" value="client">

          <input class="ad-chat__input"
                 name="message"
                 type="text"
                 placeholder="Write a Message To Client…"
                 value="{{ old('message') }}"
                 autocomplete="off"
                 {{ $blockedMsgFinalize ? 'disabled' : '' }}>

          <button class="ad-btn ad-btn--primary"
                  type="submit"
                  {{ $blockedMsgFinalize ? 'disabled' : '' }}>
            <i class="bi bi-send"></i>
            <span>Send</span>
          </button>
        </form>
      </section>

      {{-- RIGHT: Coach thread --}}
      <section class="ad-chat">
        <header class="ad-chat__head">
          <div class="ad-chat__title">
            <i class="bi bi-person-badge"></i> Coach Messages
          </div>
          <div class="ad-muted">{{ $coachName }}</div>
        </header>

        <div class="ad-chat__body">
          @forelse($coachMessages as $m)
            @php
              $mine = in_array((string)($m->sender_role ?? ''), ['admin','manager','superadmin'], true);
              $bubble = $mine ? 'is-admin' : 'is-user';
            @endphp

            <div class="ad-msg {{ $bubble }}">
              <div class="ad-msg__meta">
                <span class="ad-msg__who">{{ $whoLabel($m) }}</span>
                <span class="ad-muted">•</span>
                <span class="ad-muted">{{ $m->created_at?->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M, H:i') }}</span>
              </div>

              <div class="ad-msg__bubble">{!! nl2br(e($m->message ?? '')) !!}</div>

              @if(($m->attachments ?? null) && $m->attachments->count())
                <div class="ad-msg__atts">
                  @foreach($m->attachments as $a)
                    @php
                      $path = $a->path ?? $a->file_path ?? null;
                      $url  = $path ? asset('storage/'.$path) : null;

                      $mime  = strtolower((string)($a->mime ?? $a->mime_type ?? ''));
                      $isImg = $mime ? str_starts_with($mime, 'image/') : preg_match('/\.(png|jpe?g|gif|webp)$/i', (string)$path);
                    @endphp

                    @if($url)
                      @if($isImg)
                        <a class="ad-att" href="{{ $url }}" target="_blank">
                          <img src="{{ $url }}" alt="Attachment">
                        </a>
                      @else
                        <a class="ad-att ad-att--file" href="{{ $url }}" target="_blank">
                          <i class="bi bi-paperclip"></i>
                          <span>{{ basename((string)$path) }}</span>
                        </a>
                      @endif
                    @endif
                  @endforeach
                </div>
              @endif
            </div>
          @empty
            <div class="ad-chat__empty">No messages yet for coach thread.</div>
          @endforelse
        </div>

        <form class="ad-chat__composer"
              method="POST"
              action="{{ route('superadmin.disputes.message', $dispute) }}">
          @csrf
          <input type="hidden" name="target_role" value="coach">

          <input class="ad-chat__input"
                 name="message"
                 type="text"
                 placeholder="Write a Message To Coach…"
                 value="{{ old('message') }}"
                 autocomplete="off"
                 {{ $blockedMsgFinalize ? 'disabled' : '' }}>

          <button class="ad-btn ad-btn--primary"
                  type="submit"
                  {{ $blockedMsgFinalize ? 'disabled' : '' }}>
            <i class="bi bi-send"></i>
            <span>Send</span>
          </button>
        </form>
      </section>

    </div>
  </div>
</div>

{{-- Close conversation modal (requires summary) --}}
<div class="modal fade" id="closeToQueueModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Close conversation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="{{ route('superadmin.disputes.close', $dispute) }}">
        @csrf

        <div class="modal-body">
          <div class="ad-muted" style="margin-bottom:10px;">
            Add a summary for the next agent. This is required.
          </div>

          <textarea name="summary"
                    class="ad-textarea"
                    rows="5"
                    required
                    placeholder="Write what happened, what's pending, next steps...">{{ old('summary') }}</textarea>

          <div class="ad-muted" style="margin-top:8px; font-size:12px;">
            This will move the dispute to <b>In Review</b> and unassign it (not finalized).
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="ad-btn ad-btn--ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="ad-btn ad-btn--primary" {{ $blockedClose ? 'disabled' : '' }}>Save summary & Send</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Summary history modal --}}
<div class="modal fade" id="summaryHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Summary History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        @forelse(($summaries ?? []) as $s)
          <div class="ad-summaryitem">
            <div class="ad-summaryitem__meta">
              <b>{{ $s->staff?->username ?? ('Staff #'.$s->staff_id) }}</b>
              <span class="ad-muted">•</span>
              <span class="ad-muted">
                {{ $s->created_at?->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M Y, H:i') }}
              </span>
            </div>
            <div class="ad-summaryitem__text">{!! nl2br(e($s->summary)) !!}</div>
          </div>
        @empty
          <div class="ad-muted text-capitalize">No summaries yet.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- State modal --}}
<div class="modal fade" id="stateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stateModalTitle">Notice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="stateModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="ad-btn ad-btn--ghost" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  let title = null;
  let body  = null;

@if(session('error'))
  title = 'Action blocked';
  body  = @json(session('error'));
@elseif(session('ok'))
  title = 'Success';
  body  = @json(session('ok'));
@elseif($errors->any())
  title = 'Please fix the following';
  body  = `{!! implode('<br>', array_map('e', $errors->all())) !!}`;
@endif

  if (title && body) {
    document.getElementById('stateModalTitle').innerText = title;
    document.getElementById('stateModalBody').innerHTML = body;
    new bootstrap.Modal(document.getElementById('stateModal')).show();
  }

  // If close summary validation fails, reopen close modal
  @if($errors->has('summary'))
    const closeEl = document.getElementById('closeToQueueModal');
    if (closeEl) new bootstrap.Modal(closeEl).show();
  @endif
});
</script>
@endsection