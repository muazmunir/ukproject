@extends('layouts.role-dashboard')
@section('title', __('Dispute'))

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/client-dispute-chat.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/buttons.css') }}">
@endpush

@section('role-content')
@php
  // ------------------------------------------------------------
  // STATUS / FLAGS
  // ------------------------------------------------------------
  $status  = strtolower((string)($dispute->status ?? 'open'));
  if (!in_array($status, ['open','opened','in_review','resolved','rejected'], true)) $status = 'open';

  $isFinal = !empty($dispute->resolved_at) || in_array($status, ['resolved','rejected'], true);

  // client can chat in open/opened/in_review unless finalized
  $canChat = !$isFinal && in_array($status, ['open','opened','in_review'], true);

  // ------------------------------------------------------------
  // ASSIGNMENT
  // ------------------------------------------------------------
  $agent = $dispute->assignedStaff ?? null;
  $agentName = $agent?->username ?: ($agent?->email ?: null);

  // ------------------------------------------------------------
  // FINAL DECISION (schema)
  // ------------------------------------------------------------
  $decisionAction = $dispute->decision_action ?? null;
  $decisionNote   = $dispute->decision_note ?? null;
  $decidedAt      = $dispute->decided_at ?? $dispute->resolved_at ?? null;

  $decidedBy      = $dispute->decidedBy ?? null;   // decided_by_staff_id relation
  $resolvedBy     = $dispute->resolvedBy ?? null;  // resolved_by_staff_id relation
  $decisionActor  = $decidedBy ?: $resolvedBy;

  $decisionActorName = $decisionActor?->username
    ?: ($decisionActor?->email ?: null);

  $decisionLabel = (str_replace('_',' ', (string)($decisionAction ?: $status)));

  // ------------------------------------------------------------
  // IN_REVIEW DAY COUNTER
  // ------------------------------------------------------------
  $daysInReview = null;
  if ($status === 'in_review' && !empty($dispute->in_review_started_at)) {
    $start = $dispute->in_review_started_at instanceof \Carbon\CarbonInterface
      ? $dispute->in_review_started_at
      : \Carbon\Carbon::parse($dispute->in_review_started_at);

    $daysInReview = max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
  }

  // ------------------------------------------------------------
  // MESSAGE SENDER LABEL (username under message)
  // ------------------------------------------------------------
  $senderName = function ($m) use ($agentName) {
    $role = strtolower((string)($m->sender_role ?? 'user'));

    // if message has sender relation loaded
    $u = $m->sender ?? null;
    $uName = $u?->username ?: ($u?->email ?: null);

    return match ($role) {
      'client' => 'You',
      'admin', 'manager' => ($uName ?: ucfirst($role)),
      'system' => 'System',
      default  => ($uName ?: ucfirst($role ?: 'User')),
    };
  };

  $messages = ($dispute->messages ?? collect())->sortBy('id');
@endphp

<div class="dv-page">
  <div class="card dv-chatCard">

    {{-- HEADER --}}
    <div class="dv-chatHead">
      <div class="dv-headLeft">
        <h2 class="dv-title">{{ $dispute->title_label }}</h2>

        <div class="dv-subline">
          <span class="dv-status dv-status--{{ $status }}">
            {{ $dispute->status_label }}
          </span>

          @if($status === 'in_review' && !is_null($daysInReview))
            <span class="dv-sep">•</span>
            <span class="dv-agent dv-agent--muted">
              In review: <b>{{ $daysInReview === 0 ? 'Today' : ($daysInReview.' day'.($daysInReview>1?'s':'')) }}</b>
            </span>
          @endif

          <span class="dv-sep">•</span>

          {{-- Handled by / queue message --}}
          @if($agentName)
            <span class="dv-agent">
              Handled By: <b>{{ '@'.$agentName }}</b>
            </span>
          @else
            @if($status === 'in_review')
              <span class="dv-agent dv-agent--muted text-capitalize">
                Temporarily Closed For Review (Awaiting Your Evidence / reply)
              </span>
            @elseif($status === 'open')
              <span class="dv-agent dv-agent--muted">
                In Queue (Awaiting Agent)
              </span>
            @else
              <span class="dv-agent dv-agent--muted">—</span>
            @endif
          @endif

          {{-- Final decision (show who decided) --}}
          @if($isFinal)
            <span class="dv-sep">•</span>
            <span class="dv-decision text-capitalize">
              Final Decision: <b>{{ $decisionLabel }}</b>

              @if($decisionActorName)
                <span class="dv-decision__meta">• by <b>{{ '@'.$decisionActorName }}</b></span>
              @endif

              @if($decidedAt)
                <span class="dv-decision__meta">
                  • {{ $decidedAt->timezone(auth()->user()->timezone ?? 'Asia/Karachi')->format('d M Y, H:i') }}
                </span>
              @endif
            </span>
          @endif
        </div>

        {{-- Decision note block (only if finalized + note exists) --}}
        @if($isFinal && !empty($decisionNote))
          <div class="dv-decisionNote">
            <div class="dv-decisionNote__title">
              Resolution note
              @if($decisionActorName)
                <span class="dv-decisionNote__by">• by {{ '@'.$decisionActorName }}</span>
              @endif
            </div>
            <div class="dv-decisionNote__text">{{ $decisionNote }}</div>
          </div>
        @endif

      </div>

      <a class="btn btn--ghost back" href="{{ route('client.disputes.index') }}">Back</a>
    </div>

    {{-- CHAT --}}
    <div class="dv-chat" id="dvChat">

      {{-- System banner --}}
      <div class="dv-sys text-capitalize">
        @if($isFinal)
          This dispute has been finalized. Messaging is disabled.
        @else
          @if($agentName)
            This Conversation Is Handled By <b>{{ '@'.$agentName }}</b>.
          @else
            @if($status === 'in_review')
              This dispute is in review. You can still send evidence/messages. When you send a message, it will reopen and return to queue.
              @if(!is_null($daysInReview))
                <div class="dv-sys__sub">
                  In review for <b>{{ $daysInReview === 0 ? 'Today' : ($daysInReview.' day'.($daysInReview>1?'s':'')) }}</b>.
                </div>
              @endif
            @elseif($status === 'open')
              This dispute is currently in queue. A staff member will be assigned soon.
            @else
              This dispute is active.
            @endif
          @endif
        @endif
      </div>

      @forelse($messages as $m)
        @php
          $senderRole = strtolower((string)($m->sender_role ?? 'user'));

          $msgClass = match ($senderRole) {
            'client' => 'me',
            'admin', 'manager' => 'admin',
            'system' => 'system',
            default => 'them',
          };

          $nameLine = $senderName($m);
          $timeLine = optional($m->created_at)->diffForHumans();
        @endphp

        <div class="dv-msg {{ $msgClass }}">
          <div class="dv-bubble">

            @if(!empty($m->message))
              <div class="dv-text">{{ $m->message }}</div>
            @endif

            @if($m->attachments && $m->attachments->count())
              <div class="dv-media">
                @foreach($m->attachments as $a)
                  @php
                    $mime = (string)($a->mime ?? '');
                    // assumes model has url() helper (your code uses $a->url())
                    $url  = $a->url();
                  @endphp

                  @if(str_starts_with($mime, 'image/'))
                    <a href="{{ $url }}" target="_blank" rel="noopener">
                      <img src="{{ $url }}" alt="">
                    </a>
                  @else
                    <a class="dv-file" href="{{ $url }}" target="_blank" rel="noopener">
                      {{ $a->filename ?? 'Attachment' }}
                    </a>
                  @endif
                @endforeach
              </div>
            @endif

            {{-- ✅ meta: username + time --}}
            <div class="dv-meta">
              {{ $nameLine }} • {{ $timeLine }}
            </div>

          </div>
        </div>
      @empty
        <div class="dv-closed">No Messages Yet.</div>
      @endforelse
    </div>

    {{-- INPUT --}}
    @if($canChat)
      <form class="dv-send"
            method="POST"
            action="{{ route('client.disputes.message', $dispute) }}"
            enctype="multipart/form-data">
        @csrf

        <textarea name="message" class="dv-input" rows="1" placeholder="Type Your Message…">{{ old('message') }}</textarea>

        <label class="dv-attach" title="Attach files" aria-label="Attach files">
          <input type="file" name="files[]" id="dvFiles" multiple hidden>
          <svg class="dv-attachIcon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M16.5 6.5l-7.78 7.78a3 3 0 0 0 4.24 4.24l8.49-8.49a5 5 0 0 0-7.07-7.07L6.2 11.84a7 7 0 0 0 9.9 9.9l3.54-3.54a1 1 0 1 0-1.41-1.41l-3.54 3.54a5 5 0 1 1-7.07-7.07l8.49-8.49a3 3 0 1 1 4.24 4.24l-8.49 8.49a1 1 0 0 1-1.41-1.41l7.78-7.78a1 1 0 1 0-1.41-1.41z"/>
          </svg>
        </label>

        <button class="btn btn--primary bg-black text-white rounded-pill" type="submit">Send</button>

        <div class="dv-previews" id="dvPreviews"></div>
      </form>
    @else
      <div class="dv-closed">
        @if($isFinal)
          This Dispute Has Been Finalized.
        @else
          This Dispute Is Temporarily Closed For Review.
        @endif
      </div>
    @endif

  </div>
</div>
@endsection

@push('scripts')
<script>
  // scroll to bottom
  (function(){
    const el = document.getElementById('dvChat');
    if(el){ el.scrollTop = el.scrollHeight; }
  })();

  // attachment preview
  (function(){
    const input = document.getElementById('dvFiles');
    const previewBox = document.getElementById('dvPreviews');
    if(!input || !previewBox) return;

    input.addEventListener('change', function () {
      previewBox.innerHTML = '';

      [...input.files].forEach((file, index) => {
        const type = file.type || '';

        if (type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = e => {
            const item = document.createElement('div');
            item.className = 'dv-previewItem';
            item.innerHTML = `
              <img src="${e.target.result}" alt="">
              <button type="button" class="dv-remove" data-i="${index}">×</button>
            `;
            previewBox.appendChild(item);
          };
          reader.readAsDataURL(file);
          return;
        }

        const item = document.createElement('div');
        item.className = 'dv-fileItem';
        item.innerHTML = `
          <span class="dv-fileIcon">${type.startsWith('video/') ? '🎥' : '📄'}</span>
          <span class="dv-fileName">${file.name}</span>
          <button type="button" class="dv-remove dv-remove--file" data-i="${index}">×</button>
        `;
        previewBox.appendChild(item);
      });
    });

    previewBox.addEventListener('click', function (e) {
      const btn = e.target.closest('.dv-remove');
      if (!btn) return;

      const removeIndex = Number(btn.dataset.i);
      const dt = new DataTransfer();

      [...input.files].forEach((file, i) => {
        if (i !== removeIndex) dt.items.add(file);
      });

      input.files = dt.files;
      input.dispatchEvent(new Event('change'));
    });
  })();
</script>
@endpush