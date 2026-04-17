@php
  $isMe = ((int)$m->sender_id === (int)$meId);

  $senderName = $isMe ? 'You' : ($m->sender->username ?? $m->sender->name ?? 'Staff');
  $senderRole = $m->sender->role_label ?? ucwords(str_replace('_',' ', strtolower($m->sender->role ?? 'staff')));

  $viewerTz = auth()->user()->timezone ?: config('app.timezone', 'UTC');
  $time = $m->created_at->copy()->timezone($viewerTz)->format('H:i');
@endphp

<div class="dm-msgrow {{ $isMe ? 'me' : 'them' }}" data-id="{{ $m->id }}">
  <div class="dm-bubble">
    <div class="dm-meta">
      <span class="dm-name">{{ $senderName }} <span class="dm-role">{{ $senderRole }}</span></span>
      <span class="dm-time">{{ $time }}</span>
    </div>
    <div class="dm-body">{!! nl2br(e($m->body)) !!}</div>
  </div>
</div>
