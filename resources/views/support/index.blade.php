@extends('layouts.role-dashboard')

@section('title', __('Support messages'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/support.css') }}">

<style>
/* ===============================
   MESSAGE BUBBLES (STRICT ROLES)
================================ */
.msg-row.me .msg-bubble{
  background:#16a34a;
  color:#fff;
}

.msg-row.agent .msg-bubble{
  background:#ffffff;
  color:#0f172a;
  border:1px solid #e5e7eb;
}

.msg-row.manager .msg-bubble{
  background:#fee2e2;
  color:#7f1d1d;
  border:1px solid #fecaca;
}

.system-pill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:#000;
  border:1px solid #000;
  padding:10px 14px;
  border-radius:14px;
  font-size:13px;
  color:#fff;
  font-weight:600;
}

.sla-notice{
  background:#f8fafc;
  border:1px dashed #cbd5e1;
  padding:10px 14px;
  border-radius:12px;
  font-size:.82rem;
  color:#475569;
  margin-bottom:10px;
}
</style>
@endpush

@php
  $viewerTz = auth()->user()->timezone ?? config('app.timezone');

  $hasConversation = !empty($conversation);

  $status = (string)($conversation->status ?? '');
  $ratingRequired = (int)($conversation->rating_required ?? 0) === 1;

  $blockSend = ($status === 'resolved' && $ratingRequired);

  $placeholder = !$hasConversation
    ? __('Write Your First Message To Start Support…')
    : ($blockSend ? __('Please Rate To Continue This Chat.') : __('Write Your Message…'));

  $escalationAtUtc = ($hasConversation && !empty($conversation->manager_requested_at))
      ? \Carbon\Carbon::parse($conversation->manager_requested_at)->utc()
      : null;

  $isEscalatedNow = !is_null($escalationAtUtc);
@endphp

@section('role-content')
<div class="container-fluid">
  <div class="zv-support-page">
    <div class="msg-main">

      {{-- HEADER --}}
      <header class="msg-header">
        <div>
          <div class="msg-header-name">{{ __('Support messages') }}</div>
          <div class="zv-support-sub">
            {{ __('This Is Not a Live Chat. Replies Usually Arrive Within 24–48 Hours.') }}
          </div>
          <div class="small text-muted mt-1 text-capitalize">
            {{ $scopeRole }} {{ __('Support') }}
          </div>
        </div>
      </header>

      {{-- THREAD --}}
      <div class="msg-body">
        @php $lastDate = null; @endphp

        @if(!$hasConversation)
          <div class="text-center text-muted py-5">
            <div class="mb-2">
              <i class="bi bi-chat-dots" style="font-size:2rem;"></i>
            </div>
            <div class="fw-semibold text-capitalize">{{ __('No messages yet') }}</div>
            <div class="small text-capitalize">{{ __('Send your first message below to start a support thread.') }}</div>
          </div>
        @endif

        @foreach($events as $event)
          @php
            $at = $event->at?->timezone($viewerTz);
            $date = $at?->toDateString();
          @endphp

          @if($date !== $lastDate)
            <div class="zv-support-date">
              <span>{{ $at->format('d F Y') }}</span>
            </div>
            @php $lastDate = $date; @endphp
          @endif

          @if($event->type === 'message')
            @php
              $m = $event->model;
              $isMe = (int)$m->sender_id === (int)auth()->id();
              $senderType = strtolower((string)($m->sender_type ?? ''));

              $stamp = $at ? $at->format('d M Y • H:i') : '';

              $rowClass = 'agent';
              $roleText = __('Agent');
              $username = __('support');

              if ($isMe) {
                  $rowClass = 'me';
                  $roleText = __('You');
                  $username = auth()->user()->username ?? __('you');
              } else {
                  if ($senderType === 'manager') {
                      $rowClass = 'manager';
                      $roleText = __('Manager');
                  }

                  $username =
                      $m->sender_username
                      ?? ($m->sender?->username ?? null)
                      ?? __('support');
              }
            @endphp

            <div class="msg-row {{ $rowClass }}">
              <div class="msg-bubble">
                {!! nl2br(e($m->body)) !!}
              </div>
              <div class="msg-time">
                {{ $stamp }}
                · <strong>{{ $username }}</strong>
                <span class="text-muted">({{ $roleText }})</span>
              </div>
            </div>

            @elseif($event->type === 'rating')
@php
  $rating = $event->model;
  $stars = (int) $rating->stars;
@endphp

<div class="zv-support-system-row">
  <div class="system-pill">
    <i class="bi bi-star-fill"></i>
    {{ __('You Rated This Conversation') }}
    <strong>{{ $stars }}/5</strong>
  </div>
</div>
          @else
            @php
              $sys = (string)($event->type ?? 'system');
              $sysMeta = (array)($event->model ?? []);

              $sysName =
                $sysMeta['username']
                ?? $sysMeta['agent_username']
                ?? $sysMeta['manager_username']
                ?? $sysMeta['staff_username']
                ?? __('support');

              $isAgentAssignEvent = in_array($sys, [
                'agent_assigned',
                'admin_assign',
                'manager_assigned',
                'agent_joined',
                'staff_assign',
                'agent_taken',
              ], true);

              $isManagerEvent = in_array($sys, [
                'manager_joined',
                'manager_assigned',
                'manager_assigned_auto',
              ], true);

              $isEscalatedNow = !empty($conversation?->manager_requested_at);
            @endphp

            @if($isAgentAssignEvent)
              <div class="zv-support-system-row">
                <div class="system-pill text-capitalize">
                  <i class="bi bi-person-workspace"></i>
                  {{ __('This conversation is handled by') }}
                  <strong>{{ __('Agent') }}: {{ $sysName ?? __('Support') }}</strong>
                </div>
              </div>
            @elseif($sys === 'manager_requested')
              <div class="zv-support-system-row">
                <div class="system-pill text-capitalize">
                  <i class="bi bi-shield-exclamation"></i>
                  {{ __('We’re Connecting You To a Senior Manager.') }}
                </div>
              </div>
            @elseif($isManagerEvent && $isEscalatedNow)
              <div class="zv-support-system-row">
                <div class="system-pill text-capitalize">
                  <i class="bi bi-shield-check"></i>
                  {{ __('A Senior Manager Has Joined This Conversation.') }}
                  @if($sysName)
                    <strong>{{ $sysName }}</strong>
                  @endif
                </div>
              </div>
            @elseif($sys === 'manager_ended')
              <div class="zv-support-system-row">
                <div class="system-pill">
                  <i class="bi bi-shield-x"></i>
                  {{ __('The manager session has concluded.') }}
                </div>
              </div>
            @endif
          @endif
        @endforeach
      </div>

      {{-- FOOTER --}}
      <footer class="msg-footer">

        {{-- ✅ Rating stays in footer, not above messages --}}
        @if($hasConversation && $canRateNow)
          <div class="support-rate-mini">
            <div class="support-rate-mini__top">
              <div class="support-rate-mini__title text-capitalize">{{ __('Rate Your Support Experience') }}</div>
              <div class="support-rate-mini__text text-capitalize">
                {{ __('Please rate this conversation before starting a new one.') }}
              </div>
            </div>

            <form method="POST" action="{{ route('support.conversation.rate', $conversation) }}">
              @csrf
              <input type="hidden" name="stars" id="rating-stars" value="">

              <div class="support-rate-mini__row">
                <div class="support-rate-mini__stars text-capitalize" id="supportRatingStars">
                  @for($i = 1; $i <= 5; $i++)
                    <button type="button"
                            class="support-rate-star"
                            data-star="{{ $i }}"
                            aria-label="{{ __('Rate :n star(s)', ['n' => $i]) }}">
                      <i class="bi bi-star-fill"></i>
                    </button>
                  @endfor
                </div>

                <div class="support-rate-mini__selected d-none" id="ratingSelectedText">
                  <span>{{ __('Selected') }}:</span>
                  <strong><span id="ratingSelectedNumber">0</span>/5</strong>
                </div>
              </div>

              <div id="rating-comment-box" class="support-rate-mini__comment d-none">
                <textarea name="feedback"
                          class="form-control support-rate-mini__textarea"
                          rows="3"
                          placeholder="{{ __('Optional Feedback...') }}"></textarea>

                <div class="support-rate-mini__actions">
                  <button class="btn btn-dark btn-sm px-3">
                    {{ __('Submit Rating') }}
                  </button>
                </div>
              </div>
            </form>
          </div>
        @endif

        <div class="sla-notice">
          {{ __('For Quality And Compliance Reasons, This Conversation Is Handled Asynchronously.') }}
        </div>

        <form method="POST" action="{{ route('support.message.store') }}">
          @csrf

          @if(!empty($threadId))
            <input type="hidden" name="thread_id" value="{{ $threadId }}">
          @endif

          @if($hasConversation && ($conversation->status ?? '') === 'open')
            <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
          @endif

          <div class="msg-input-wrap">
            <textarea name="body"
                      required
                      class="form-control msg-input"
                      rows="3"
                      @if($blockSend) disabled @endif
                      placeholder="{{ $placeholder }}"></textarea>

            <button class="btn btn-dark msg-send-btn" @if($blockSend) disabled @endif>
              {{ __('Send') }}
            </button>
          </div>
        </form>
      </footer>

    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
  const stars = document.querySelectorAll(".support-rate-star");
  const input = document.getElementById("rating-stars");
  const commentBox = document.getElementById("rating-comment-box");
  const selectedText = document.getElementById("ratingSelectedText");
  const selectedNumber = document.getElementById("ratingSelectedNumber");

  if (!stars.length) return;

  let selected = 0;

  function paintStars(value) {
    stars.forEach(star => {
      const n = parseInt(star.dataset.star, 10);
      star.classList.toggle("active", n <= value);
    });
  }

  stars.forEach(star => {
    star.addEventListener("mouseenter", function () {
      paintStars(parseInt(this.dataset.star, 10));
    });

    star.addEventListener("click", function () {
      selected = parseInt(this.dataset.star, 10);
      input.value = selected;
      paintStars(selected);

      selectedNumber.textContent = selected;
      selectedText.classList.remove("d-none");
      commentBox.classList.remove("d-none");
    });
  });

  const wrap = document.getElementById("supportRatingStars");
  if (wrap) {
    wrap.addEventListener("mouseleave", function () {
      paintStars(selected);
    });
  }
});
</script>
@endpush