@php
  $meId = $meId ?? auth()->id();
@endphp

@foreach($room->messages()->with(['user','attachments'])->orderBy('id')->limit(200)->get() as $m)
  @php
    $isMe = (int)$m->user_id === (int)$meId;

    // normalize role for css classes
    $roleRaw = strtolower((string)($m->user->role ?? 'user'));
    $role    = str_replace([' ', '-'], '_', $roleRaw);

    $rowClass    = $isMe ? 'me' : 'other';
    $bubbleClass = $isMe ? 'bubble-me' : 'bubble-other';

    // role tint class (OTHER only)
    $bubbleRole  = !$isMe ? ('role-'.$role) : '';

    // role label shown in UI
    $roleLabel = $m->user->role_label ?? ucfirst($roleRaw);

    // sender timezone for message time
    $tz = $m->user->timezone ?? config('app.timezone', 'UTC');

    $timeLocal = $m->created_at
      ? $m->created_at->copy()->timezone($tz)->format('h:i A')
      : '';
  @endphp

  <div class="sc-msg-row {{ $rowClass }}">
    <div class="sc-msg">
      <div class="sc-bubble {{ $bubbleClass }} {{ $bubbleRole }}">

        {{-- header (name + role label + time) --}}
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

        {{-- body --}}
        @if($m->body)
          <div class="sc-text" style="white-space:pre-wrap;">{{ $m->body }}</div>
        @endif

        {{-- attachments --}}
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
