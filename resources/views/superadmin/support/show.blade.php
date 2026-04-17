@extends('superadmin.layout')
@section('title','Support Conversation')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/admin-support-show.css') }}">
<style>
  .zv-chat-lock-banner.is-waiting { border-left:4px solid #f59e0b; background:#fff7ed; }
  .zv-chat-lock-banner.is-assigned{ border-left:4px solid #f59e0b; background:#fef3c7; }
  .zv-chat-lock-banner.is-active  { border-left:4px solid #dc2626; background:#fee2e2; }
</style>
@endpush

@php
  $sa = auth()->user();
  $adminTz = $adminTz ?? ($sa->timezone ?? config('app.timezone'));

  $threadRole = $conversation->scope_role ?? $conversation->user_type ?? 'client';
  $threadRole = in_array($threadRole, ['client','coach'], true) ? $threadRole : 'client';

  // ✅ new ownership model
  $ownerId   = (int)($conversation->assigned_staff_id ?? 0);
  $ownerRole = (string)($conversation->assigned_staff_role ?? '');
  $isOwner   = $ownerId > 0 && $ownerId === (int)$sa->id;

  // superadmin can send only if owner + open
  $canSend   = ($conversation->status === 'open') && $isOwner;

  // escalation flags (same logic as admin show)
  $managerRequested = !is_null($conversation->manager_requested_at);

  $waitingManagerUnassigned = $managerRequested && empty($conversation->assigned_staff_id);

  $managerAssigned = $managerRequested
    && !empty($conversation->assigned_staff_id)
    && (($conversation->assigned_staff_role ?? '') === 'manager');

  $managerActive = $managerAssigned && ($conversation->status === 'open');

  $assignedStaffName = $conversation->assignedStaff
    ? ($conversation->assignedStaff->username ?? $conversation->assignedStaff->email)
    : null;

  // If not escalated but assigned staff role is manager, show as agent in UI (same as admin blade)
  $showRole = $ownerRole;
  if (!$managerRequested && $ownerRole === 'manager') $showRole = 'agent';
@endphp

@section('content')
<section class="card zv-chat-admin-card">

  {{-- ✅ SUPERADMIN BANNER --}}
  <div class="zv-chat-lock-banner">
    <div class="left">
      <i class="bi bi-shield-check"></i>
      <div class="txt">
        <div class="t">Super Admin View</div>
        <div class="s">Full visibility enabled. Actions are audited.</div>
      </div>
    </div>
  </div>

  {{-- ✅ View-only banner if open but not owner --}}
  @if($conversation->status === 'open' && !$canSend)
    <div class="zv-chat-lock-banner {{ $managerActive ? 'is-active' : ($managerAssigned ? 'is-assigned' : ($waitingManagerUnassigned ? 'is-waiting' : '')) }}">
      <div class="left">
        <i class="bi bi-shield-lock"></i>
        <div class="txt">
          <div class="t">View Only</div>
          <div class="s">
            This conversation is handled by
            <strong>{{ $assignedStaffName ?: 'another staff member' }}</strong>
            @if($showRole)
              <span class="text-muted">({{ ucfirst($showRole) }})</span>
            @endif

            @if($waitingManagerUnassigned)
              <span class="ms-2">Escalated — waiting for manager assignment.</span>
            @elseif($managerAssigned)
              <span class="ms-2">Escalated — assigned to a manager.</span>
            @endif
          </div>
        </div>
      </div>

      {{-- ✅ allow take-over if unassigned --}}
      @if(!$conversation->assigned_staff_id && in_array($conversation->status, ['open','waiting_manager'], true))
        <div class="right">
          <form method="post" action="{{ route('superadmin.support.conversations.assignMe', $conversation) }}" class="inline">
            @csrf
            <button class="btn primary small" type="submit">
              Assign To Me
            </button>
          </form>
        </div>
      @endif
    </div>
  @endif

  {{-- HEADER --}}
  <div class="zv-chat-admin-header">
    <div class="left">
      <div class="avatar">
        @php
          $uname = $conversation->user?->username
            ?: (trim(($conversation->user?->first_name ?? '').' '.($conversation->user?->last_name ?? '')) ?: ($conversation->user?->email ?: 'U'));
        @endphp
        <span>{{ strtoupper(mb_substr($uname, 0, 1)) }}</span>
      </div>
      <div>
        <div class="name">
          {{ $conversation->user?->username ?: (trim(($conversation->user?->first_name ?? '').' '.($conversation->user?->last_name ?? '')) ?: ($conversation->user?->email ?: 'Unknown user')) }}
        </div>
        <div class="muted small">{{ $conversation->user?->email }}</div>
        <div class="small muted text-capitalize">{{ $threadRole }} Thread</div>
      </div>
    </div>

    <div class="right">
      <div class="zv-admin-thread-pill {{ $threadRole === 'coach' ? 'is-coach' : 'is-client' }} me-3">
        <i class="bi bi-person-badge me-1"></i>
        {{ $threadRole === 'coach' ? 'Coach Support' : 'Client Support' }}
      </div>

      {{-- ✅ Assign to me (if unassigned) --}}
      @if(!$conversation->assigned_staff_id && in_array($conversation->status, ['open','waiting_manager'], true))
        <form method="post" action="{{ route('superadmin.support.conversations.assignMe', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn primary small" type="submit">Assign To Me</button>
        </form>
      @endif

      {{-- ✅ Escalate to manager (only owner + open + not already requested) --}}
      @if($isOwner && $conversation->status === 'open' && !$managerRequested)
        <form method="post" action="{{ route('superadmin.support.conversations.requestManager', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn ghost small" type="submit">Escalate To Manager</button>
        </form>
      @endif

      {{-- ✅ Manager Join / End (super routes) --}}
      @if($managerRequested && !$managerActive && $conversation->status === 'waiting_manager')
        <form method="post" action="{{ route('superadmin.support.conversations.managerJoin', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn primary small" type="submit">Manager Join (Override)</button>
        </form>
      @endif

      @if($managerRequested && $conversation->manager_joined_at && !$conversation->manager_ended_at)
        <form method="post" action="{{ route('superadmin.support.conversations.managerEnd', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn danger small" type="submit">End Manager Session</button>
        </form>
      @endif

      {{-- ✅ Status override using updateStatus route --}}
      <form method="post" action="{{ route('superadmin.support.conversations.status', $conversation) }}" class="inline ms-2">
        @csrf
        <input type="hidden" name="status" value="closed">
        <button class="btn danger small" type="submit"
          @if(in_array($conversation->status, ['closed','resolved','auto_closed'], true)) disabled @endif>
          Force Close
        </button>
      </form>

    </div>
  </div>

  <div class="zv-chat-admin-layout">

    {{-- LEFT: messages --}}
    <div class="zv-chat-thread" id="zvChatMessages">
      @php $lastDate = null; @endphp

      @forelse($events as $event)
        @php
          $eventAt   = $event->at?->timezone($adminTz);
          $eventDate = $eventAt?->toDateString();
        @endphp

        @if($eventDate !== $lastDate)
          @php $lastDate = $eventDate; @endphp
          <div class="zv-chat-date-separator" data-date="{{ $eventDate }}">
            <span>{{ $eventAt->format('jS F Y, l') }}</span>
          </div>
        @endif

        @if($event->type === 'message')
          @php $msg = $event->model; @endphp
          @include('support._message', [
            'msg' => $msg,
            'viewerIsAdmin' => true,
            'adminTz' => $adminTz,
          ])
        @elseif($event->type === 'system')
          {{-- ✅ use same system partial as admin --}}
          @php
            /** @var \App\Models\SupportMessage $sysMsg */
            $sysMsg  = $event->model;
            $sysMeta = (array) ($sysMsg->meta ?? []);

            // normalize keys for _system partial
            if (!isset($sysMeta['agent_name']) && isset($sysMeta['staff_name'])) $sysMeta['agent_name'] = $sysMeta['staff_name'];
            if (!isset($sysMeta['agent_id'])   && isset($sysMeta['staff_id']))   $sysMeta['agent_id']   = $sysMeta['staff_id'];
            if (!isset($sysMeta['agent_role']) && isset($sysMeta['staff_role'])) $sysMeta['agent_role'] = $sysMeta['staff_role'];

            if (isset($sysMeta['agent_username']) && $sysMeta['agent_username']) $sysMeta['agent_name'] = $sysMeta['agent_username'];
            if (isset($sysMeta['staff_username']) && $sysMeta['staff_username']) $sysMeta['agent_name'] = $sysMeta['staff_username'];

            $sysMsg->meta = $sysMeta;
          @endphp

          @include('support._system', [
            'msg' => $sysMsg,
            'adminTz' => $adminTz,
          ])

        @elseif($event->type === 'rating')
          @php $r = $event->model; @endphp
          <div class="zv-chat-system-row">
            <div class="zv-chat-system-pill">
              <i class="bi bi-star-fill me-1"></i>
              Rated
              <strong>{{ $r->ratedAdmin?->username ?: ($r->ratedAdmin?->email ?: 'Support') }}</strong>
              {{ $r->stars }}/5
              @if($r->feedback) – “{{ $r->feedback }}” @endif
            </div>
          </div>
        @endif
      @empty
        <div class="zv-chat-empty">
          <div class="icon"><i class="bi bi-chat-dots"></i></div>
          <div class="title">No messages yet</div>
          <div class="subtitle">User has not started the chat.</div>
        </div>
      @endforelse
    </div>

    <div class="zv-chat-search-results" id="zvChatSearchResults"></div>

    {{-- RIGHT: meta --}}
    <aside class="zv-chat-aside">
      <div class="aside-section">
        <div class="aside-title">Conversation</div>

        <div class="aside-row">
          <span class="muted small">Status</span>
          <span class="pill {{ $conversation->status === 'open' ? 'ok' : '' }}">
            {{ ucfirst(str_replace('_',' ', $conversation->status)) }}
          </span>
        </div>

        <div class="aside-row">
          <span class="muted small">Assigned Staff</span>
          <span class="small">
            {{ $assignedStaffName ?: 'Unassigned (auto-assign pending)' }}
            @if($ownerRole)
              <span class="text-muted">— {{ ucfirst($showRole) }}</span>
            @endif
          </span>
        </div>

        <div class="aside-row">
          <span class="muted small">Escalation</span>
          <span class="small">
            @if($waitingManagerUnassigned)
              Requested (waiting assignment)
            @elseif($managerAssigned)
              Assigned to manager
            @elseif($managerRequested)
              Requested
            @else
              —
            @endif
          </span>
        </div>

        <div class="aside-row">
          <span class="muted small">Started</span>
          <span class="small">
            {{ $conversation->created_at?->timezone($adminTz)->format('d M Y, H:i') ?? '—' }}
          </span>
        </div>

        <div class="aside-row">
          <span class="muted small">Last Message</span>
          <span class="small">
            {{ $conversation->last_message_at
              ? $conversation->last_message_at->timezone($adminTz)->diffForHumans()
              : '—' }}
          </span>
        </div>
      </div>
    </aside>

  </div>

  {{-- Composer --}}
  <footer class="zv-chat-admin-composer">
    <form action="{{ route('superadmin.support.conversations.message.store', $conversation) }}"
          method="POST"
          class="zv-chat-form"
          id="zvSuperChatForm">
      @csrf
      <div class="zv-chat-input-shell">
        <textarea name="body"
                  class="zv-chat-textarea"
                  rows="1"
                  @if(!$canSend) disabled @endif
                  placeholder="{{ $conversation->status !== 'open'
                    ? 'Conversation is closed — you cannot reply.'
                    : (!$canSend ? 'View only — assign to yourself to reply.' : 'Type your reply…') }}"></textarea>

        <div class="zv-chat-actions">
          <button class="zv-chat-send" type="submit" @if(!$canSend) disabled @endif>
            <span class="label d-none d-sm-inline">Send</span>
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
      </div>
    </form>
  </footer>

</section>
@endsection

@push('scripts')
<script>
  $(function () {
    const $messages = $('#zvChatMessages');
    const $form     = $('#zvSuperChatForm');
    const $textarea = $form.find('.zv-chat-textarea');

    function scrollToBottom() { $messages.scrollTop($messages.prop('scrollHeight')); }
    function getLastMessageId() {
      const $last = $messages.find('[data-message-id]').last();
      return $last.length ? parseInt($last.data('message-id'), 10) : null;
    }

    scrollToBottom();

    // SEND (AJAX)
    $form.on('submit', function (e) {
      e.preventDefault();
      if ($textarea.prop('disabled')) return;

      const body = ($textarea.val() || '').trim();
      if (!body) return;

      const url  = $form.attr('action');
      const data = $form.serialize();

      $textarea.prop('disabled', true);

      $.ajax({
        url: url,
        method: 'POST',
        data: data,
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).done(function (res) {
        if (res && res.ok && res.html) {
          $messages.find('.zv-chat-empty').remove();
          $messages.append(res.html);
          $textarea.val('');
          scrollToBottom();
          if (res.last) lastSeenId = parseInt(res.last, 10);
        }
      }).always(function () {
        @if($canSend)
          $textarea.prop('disabled', false).focus();
        @endif
      });
    });

    // POLL
    const pollUrl = "{{ route('superadmin.support.messages.latest') }}";
    const convId  = "{{ $conversation->id }}";

    let lastSeenId = getLastMessageId() || 0;

    function poll() {
      $.ajax({
        url: pollUrl,
        method: 'GET',
        dataType: 'json',
        data: {
          conversation_id: convId,
          after_id: lastSeenId || '',
          mark_read: 1
        },
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).done(function (res) {
        if (res && res.ok && res.html) {
          $messages.append(res.html);
          scrollToBottom();
          if (res.last) lastSeenId = parseInt(res.last, 10);
        }
      }).always(function () {
        setTimeout(poll, 4000);
      });
    }

    poll();
  });
</script>
@endpush