@extends('layouts.admin')
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
  $threadRole = $conversation->scope_role ?? $conversation->user_type ?? 'client';
  $threadRole = in_array($threadRole, ['client','coach']) ? $threadRole : 'client';

  $staff = auth()->user();

  // ✅ staff roles
  $isAgent   = (($staff->role ?? '') === 'admin');
  $isManager = (($staff->role ?? '') === 'manager');

  // ✅ Option A ownership (single owner: admin OR manager)
  $ownerId   = (int)($conversation->assigned_staff_id ?? 0);
  $ownerRole = (string)($conversation->assigned_staff_role ?? '');

  $isOwner   = $ownerId > 0 && $ownerId === (int)$staff->id;
  $canSend   = ($conversation->status === 'open') && $isOwner;

  // ✅ Escalation flags (still using your existing columns)
  $managerRequested = !is_null($conversation->manager_requested_at);

  // If requested and NOT yet assigned to anyone -> waiting/unassigned
  $waitingManagerUnassigned = $managerRequested && empty($conversation->assigned_staff_id);

  // If requested and assigned to a manager (auto)
  $managerAssigned = $managerRequested
    && !empty($conversation->assigned_staff_id)
    && (($conversation->assigned_staff_role ?? '') === 'manager');

  // If assigned to manager + open => manager handling (active)
  $managerActive = $managerAssigned && ($conversation->status === 'open');

  // Names
  $assignedStaffName = $conversation->assignedStaff
  ? ($conversation->assignedStaff->username ?? $conversation->assignedStaff->email)
  : null;


  $adminTz = $adminTz ?? (auth()->user()->timezone ?? config('app.timezone'));
@endphp

@section('content')
<section class="card zv-chat-admin-card">

  {{-- ✅ View-only banner --}}
  @if($conversation->status === 'open' && !$canSend)
    <div class="zv-chat-lock-banner {{ $managerActive ? 'is-active' : ($managerAssigned ? 'is-assigned' : ($waitingManagerUnassigned ? 'is-waiting' : '')) }}">
      <div class="left">
        <i class="bi bi-shield-lock"></i>
        <div class="txt">
          <div class="t">View Only</div>
          <div class="s">
            This conversation is handled by
            <strong>
              {{ $assignedStaffName ?: 'another staff member' }}
            </strong>
            @if($ownerRole)
            @php
  $showRole = $ownerRole;

  // If not escalated and assigned staff is manager, treat as agent in UI
  if (!$managerRequested && $ownerRole === 'manager') {
      $showRole = 'agent';
  }
@endphp

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
    </div>
  @endif

  {{-- ✅ Manager attention banner (only if I'm the assigned manager-owner) --}}
  @if($isOwner && $ownerRole === 'manager' && $managerRequested && $conversation->status === 'open')
    <div class="zv-chat-lock-banner is-active">
      <div class="left">
        <i class="bi bi-shield-exclamation text-danger"></i>
        <div class="txt">
          <div class="t text-danger">Escalation Assigned To You</div>
          <div class="s">You are currently responsible for this escalated conversation.</div>
        </div>
      </div>
    </div>
  @endif

  <div class="zv-chat-admin-header">
    <div class="left">
      <div class="avatar">
        @php $uname = $conversation->user?->username ?: ($conversation->user?->email ?: 'U'); @endphp
<span>{{ strtoupper(mb_substr($uname, 0, 1)) }}</span>

      </div>
      <div>
        <div class="name">
          {{ $conversation->user?->username ?: ($conversation->user?->email ?: 'Unknown user') }}

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

      {{-- 🔍 Conversation search --}}
      <form id="zvChatSearchForm" class="zv-chat-search-form me-3">
        <div class="zv-chat-search-row">
          <div class="zv-chat-search-box">
            <input type="text" name="q" class="form-control zv-chat-search-input" placeholder="Search Messages…">
            <button type="button" class="zv-chat-search-btn" id="btnSearchKeyword">
              <i class="bi bi-search"></i>
            </button>
          </div>
          <div class="zv-chat-date-box">
            <input type="date" name="on_date" class="form-control zv-chat-date-input">
            <button type="button" class="zv-chat-search-btn" id="btnSearchDate">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </div>
        <div id="zvChatSearchMeta" class="small text-muted mt-1 d-none"></div>
      </form>

      {{-- ✅ End (only owner) --}}
      @if($isOwner && $conversation->status === 'open')
        <form method="post" action="{{ route('admin.support.conversations.adminEnd', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn danger small" type="submit">End Conversation</button>
        </form>
      @endif

      {{-- ✅ Resolve (rating required) — same route for admin/manager now --}}
      @if($isOwner && $conversation->status === 'open')
        <form method="post" action="{{ route('admin.support.conversations.adminResolve', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn primary small" type="submit">Resolve (Require Rating)</button>
        </form>
      @endif

      {{-- ✅ Escalate to Manager (only owner AND not already requested) --}}
      @if($isOwner && $conversation->status === 'open' && !$managerRequested)
        <form method="post" action="{{ route('admin.support.conversations.requestManager', $conversation) }}" class="inline ms-2">
          @csrf
          <button class="btn ghost small" type="submit">Escalate To Manager</button>
        </form>
      @endif

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
        @else
          {{-- ✅ system events --}}
     @php
  /** @var \App\Models\SupportMessage $sysMsg */
  $sysMsg  = $event->model;           // real SupportMessage
  $sysMeta = (array) ($sysMsg->meta ?? []);

  // normalize keys for _system partial
  if (!isset($sysMeta['agent_name']) && isset($sysMeta['staff_name'])) $sysMeta['agent_name'] = $sysMeta['staff_name'];
  if (!isset($sysMeta['agent_id'])   && isset($sysMeta['staff_id']))   $sysMeta['agent_id']   = $sysMeta['staff_id'];
  if (!isset($sysMeta['agent_role']) && isset($sysMeta['staff_role'])) $sysMeta['agent_role'] = $sysMeta['staff_role'];

  // ✅ prefer username if present in meta
  // if controller already stores username as *_username then prefer it
  if (isset($sysMeta['agent_username']) && $sysMeta['agent_username']) {
    $sysMeta['agent_name'] = $sysMeta['agent_username'];
  }
  if (isset($sysMeta['staff_username']) && $sysMeta['staff_username']) {
    $sysMeta['agent_name'] = $sysMeta['staff_username'];
  }

  // Optional: manager_name normalization
  if (!isset($sysMeta['manager_name']) && ($sysMeta['agent_role'] ?? '') === 'manager') {
    $sysMeta['manager_name'] = $sysMeta['agent_name'] ?? null;
  }

  // overwrite message meta so _system reads username
  $sysMsg->meta = $sysMeta;
@endphp

@include('support._system', [
  'msg' => $sysMsg,
  'adminTz' => $adminTz,
])




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
              @php
  $roleUi = $ownerRole;
  if (!$managerRequested && $ownerRole === 'manager') $roleUi = 'agent';
@endphp

<span class="text-muted">— {{ ucfirst($roleUi) }}</span>

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
    <form action="{{ route('admin.support.conversations.message.store', $conversation) }}"
          method="POST"
          class="zv-chat-form"
          id="zvAdminChatForm">
      @csrf
      <div class="zv-chat-input-shell">
        <textarea name="body"
                  class="zv-chat-textarea"
                  rows="1"
                  @if(!$canSend) disabled @endif
                  placeholder="{{ $conversation->status !== 'open'
                    ? 'Conversation Is Closed — You Cannot Reply.'
                    : (!$canSend ? 'View Only — This chat Is Owned By Another Staff Member.' : 'Type Your Reply…') }}"></textarea>

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
    const $messages      = $('#zvChatMessages');
    const $form          = $('#zvAdminChatForm');
    const $textarea      = $form.find('.zv-chat-textarea');

    const $searchForm    = $('#zvChatSearchForm');
    const $searchResults = $('#zvChatSearchResults');
    const $searchMeta    = $('#zvChatSearchMeta');
    const $keywordInput  = $searchForm.find('input[name="q"]');
    const $dateInput     = $searchForm.find('input[name="on_date"]');
    const $btnSearchKeyword = $('#btnSearchKeyword');
    const $btnSearchDate    = $('#btnSearchDate');

    function scrollToBottom() { $messages.scrollTop($messages.prop('scrollHeight')); }
    function getLastMessageId() {
      const $last = $messages.find('[data-message-id]').last();
      return $last.length ? parseInt($last.data('message-id'), 10) : null;
    }
    scrollToBottom();

    const markReadUrl = "{{ route('admin.support.conversations.read', $conversation) }}";
    function markRead(lastId){
      if(!lastId) return;
      $.ajax({
        url: markReadUrl,
        method: 'POST',
        dataType: 'json',
        data: { last_id: lastId, _token: "{{ csrf_token() }}" },
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
    }

    $form.on('submit', function (e) {
      e.preventDefault();
      if ($textarea.prop('disabled')) return;

      const body = $textarea.val().trim();
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
        lastSeenId = getLastMessageId() || lastSeenId;
markRead(lastSeenId);

        }
      }).always(function () {
        @if($canSend)
          $textarea.prop('disabled', false).focus();
        @endif
      });
    });

 const pollUrl = "{{ route('admin.support.messages.latest') }}";
const convId  = "{{ $conversation->id }}";

// ✅ keep cursor OUTSIDE so it doesn't reset
let lastSeenId = getLastMessageId() || 0;

function poll() {
  $.ajax({
    url: pollUrl,
    method: 'GET',
    dataType: 'json',
    data: { conversation_id: convId, after_id: lastSeenId || '' },
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).done(function (res) {
    if (res && res.ok && res.html) {
      $messages.append(res.html);
      scrollToBottom();

      // ✅ IMPORTANT: advance cursor
      if (res.last) lastSeenId = parseInt(res.last, 10);

      markRead(lastSeenId);
    }
  }).always(function () {
    setTimeout(poll, 4000);
  });
}

// start polling once
markRead(lastSeenId);
poll();




    // SEARCH (same as yours)
    const searchUrl = "{{ route('admin.support.conversations.search', $conversation) }}";
    let searchXhr = null;

    $btnSearchKeyword.on('click', function () {
      const q = $.trim($keywordInput.val());
      $dateInput.val('');

      if (!q) {
        $searchResults.empty();
        $searchMeta.addClass('d-none').text('');
        return;
      }

      if (searchXhr && searchXhr.readyState !== 4) searchXhr.abort();

      searchXhr = $.ajax({
        url: searchUrl,
        method: 'GET',
        dataType: 'json',
        data: { q: q },
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).done(function (res) {
        if (!res || !res.ok) return;

        if (res.count > 0) {
          $searchResults.html(res.html);
          $searchMeta.removeClass('d-none').text(res.count + ' match' + (res.count > 1 ? 'es' : '') + ' found');
        } else {
          $searchResults.html('<div class="small text-muted py-2 px-3">No messages found.</div>');
          $searchMeta.removeClass('d-none').text('0 matches');
        }
      });
    });

    $btnSearchDate.on('click', function () {
      const onDate = $dateInput.val();
      $keywordInput.val('');

      if (!onDate) {
        $searchResults.empty();
        $searchMeta.addClass('d-none').text('');
        return;
      }

      const $sep = $messages.find('.zv-chat-date-separator[data-date="' + onDate + '"]');

      if ($sep.length) {
        const currentScroll = $messages.scrollTop();
        const offset        = $sep.position().top + currentScroll - 40;

        $messages.animate({ scrollTop: offset }, 300);

        $sep.addClass('zv-chat-highlight');
        setTimeout(function () { $sep.removeClass('zv-chat-highlight'); }, 1800);

        $searchResults.empty();
        $searchMeta.removeClass('d-none').text('Jumped to messages on ' + onDate);
      } else {
        $searchResults.html('<div class="small text-muted py-2 px-3">No messages on this date.</div>');
        $searchMeta.removeClass('d-none').text('0 messages on ' + onDate);
      }
    });

    $searchResults.on('click', '.zv-chat-search-hit', function () {
      const id = $(this).data('jump-id');
      if (!id) return;

      const $target = $messages.find('[data-message-id="' + id + '"]');
      if (!$target.length) return;

      const currentScroll = $messages.scrollTop();
      const offset        = $target.position().top + currentScroll - 40;

      $messages.animate({ scrollTop: offset }, 300);

      $target.addClass('zv-chat-highlight');
      setTimeout(function () { $target.removeClass('zv-chat-highlight'); }, 1800);
    });

  });
</script>
@endpush
