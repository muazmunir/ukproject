@php
  /** @var \App\Models\SupportMessage $msg */
  $viewerIsAdmin = $viewerIsAdmin ?? false;

  $viewerTz = $adminTz
      ?? (auth()->user()->timezone ?? null)
      ?? config('app.timezone');

  $msgAt = $msg->created_at?->timezone($viewerTz);

  $senderType = strtolower((string)($msg->sender_type ?? ''));

  // ✅ strict roles
  $isManager  = ($senderType === 'manager');  // escalation manager messages
  $isAgent    = ($senderType === 'admin');    // admin + manager-as-agent should be saved as 'admin'
  $isCustomer = in_array($senderType, ['client','coach'], true) || (!$isAgent && !$isManager);

  // ✅ me logic
  if ($viewerIsAdmin) {
    $isMe = in_array($senderType, ['admin','manager'], true) && (int)$msg->sender_id === (int)auth()->id();
  } else {
    $isMe = (int)$msg->sender_id === (int)auth()->id();
  }

  // ✅ username resolver (no first/last)
  $username =
      $msg->sender_username
      ?? ($msg->sender?->username ?? null);

  if (!$username || trim((string)$username) === '') {
      $username = $isMe ? (auth()->user()->username ?? 'you') : 'support';
  } else {
      $username = trim((string)$username);
  }

  // ✅ label (role)
  if ($isManager) {
    $roleLabel = __('Manager');
  } elseif ($isAgent) {
    $roleLabel = __('Agent');
  } else {
    $roleLabel = ($senderType === 'coach') ? __('Coach') : __('Client');
    if (!$viewerIsAdmin && $isMe) $roleLabel = __('You');
  }

  // ✅ show username everywhere
  // Admin views: show role + username. Customer views: show "You" for self else username.
  if ($viewerIsAdmin) {
      $senderLabel = "{$roleLabel} · {$username}";
  } else {
      $senderLabel = $isMe ? __('You') : $username;
  }

  $bubbleClass = $isManager ? 'is-manager' : ($isAgent ? 'is-agent' : 'is-user');

  $stamp = $msgAt ? $msgAt->format('d M Y • H:i') : '—';
@endphp

<div class="zv-chat-message
            {{ $isMe ? 'is-me' : 'is-them' }}
            {{ $bubbleClass }}"
     data-message-id="{{ $msg->id }}">

  <div class="zv-chat-bubble">

    <div class="zv-chat-meta text-capitalize">
      {{ $senderLabel }}
    </div>

    <div class="zv-chat-text">
      {!! nl2br(e($msg->body)) !!}
    </div>

    <div class="zv-chat-time">
      {{ $stamp }}
    </div>

  </div>
</div>
