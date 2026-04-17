@php
  $meId = $meId ?? auth()->id();
@endphp

@foreach($room->messages()->with(['user','attachments'])->orderBy('id')->limit(200)->get() as $m)
  @php
    $isMe = (int)$m->user_id === (int)$meId;

    $roleRaw = strtolower((string)($m->user->role ?? 'user'));
    $role    = str_replace([' ', '-'], '_', $roleRaw);

    $isSuperAdmin = $roleRaw === 'superadmin';

    $rowClass    = $isMe ? 'me' : 'other';
    $bubbleClass = $isMe ? 'bubble-me' : 'bubble-other';
    $bubbleRole  = !$isMe ? ('role-'.$role) : '';
    $roleLabel   = $m->user->role_label ?? ucfirst($roleRaw);

    $tz = $m->user->timezone ?? config('app.timezone', 'UTC');

  $timeLocal = $m->created_at
  ? $m->created_at->copy()->timezone($tz)->format('d M Y, h:i A')
  : '';
  @endphp

  <div class="sc-msg-row {{ $rowClass }}">
    <div class="sc-msg">
      <div class="sc-bubble {{ $bubbleClass }} {{ $bubbleRole }} {{ $isSuperAdmin && !$isMe ? 'superadmin-announcement' : '' }}">

        <div class="sc-bubble-head">
          <div class="sc-head-left">
            <span class="sc-name">
              {{ $isMe ? 'You' : ($m->user->username ?? $m->user->name ?? 'User') }}
            </span>

            <span class="sc-role-tag {{ !$isMe ? 'role-'.$role : 'role-me' }}">
              {{ $roleLabel }}
            </span>

          
          </div>

          <span class="sc-meta">{{ $timeLocal }}</span>
        </div>

        @if($m->body)
          <div class="sc-text {{ $isSuperAdmin && !$isMe ? 'fw-bold' : '' }}" style="">
            {{ $m->body }}
          </div>
        @endif

        @if($m->attachments && $m->attachments->count())
          <div class="sc-att-wrap">
            @foreach($m->attachments as $a)
              @php
                $mime  = (string)($a->mime ?? '');
                $isImg = str_starts_with($mime, 'image/');
                $url   = \Illuminate\Support\Facades\Storage::disk($a->disk ?? 'public')->url($a->path);
              @endphp

              @if($isImg)
                <a href="{{ $url }}" target="_blank" class="sc-att-img">
                  <img src="{{ $url }}" alt="{{ $a->name }}">
                </a>
              @else
                <a href="{{ $url }}" target="_blank" class="sc-att-file">
                  <i class="bi bi-file-earmark-text"></i>
                  <span class="text-truncate">{{ $a->name }}</span>
                  <span class="sc-size">{{ number_format(($a->size ?? 0)/1024, 0) }} KB</span>
                </a>
              @endif
            @endforeach
          </div>
        @endif

      </div>
    </div>
  </div>
@endforeach