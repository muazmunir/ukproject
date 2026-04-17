@php
  $isMe = (int)$m->user_id === (int)$meId;
  $role = strtolower((string) optional($m->user)->role_label);
    $tz = auth()->user()->timezone ?? config('app.timezone', 'UTC');
@endphp

<div class="msg-row {{ $isMe ? 'me' : 'them' }} role-{{ $role }}">
 <div class="msg-bubble">

  {{-- HEADER --}}
  <div class="msg-meta">
    <span class="msg-name">{{ optional($m->user)->username }}</span>
    <span class="msg-role text-capitalize">{{ $role }}</span>
    <span class="msg-time">
  {{ $m->created_at ? $m->created_at->copy()->timezone($tz)->format('H:i') : '' }}
</span>

  </div>

  <div class="msg-divider"></div>

  @php $atts = $m->attachments ?? collect(); @endphp

  {{-- ATTACHMENTS --}}
  @if($atts->count())
    <div class="msg-section">
      <div class="msg-section-title">
        <i class="bi bi-paperclip"></i>
        Attachments ({{ $atts->count() }})
      </div>

      <div class="msg-attachments">
        @foreach($atts as $a)
          @php
            $url   = \Illuminate\Support\Facades\Storage::disk('public')->url($a->path);
            $mime  = (string) ($a->mime ?? '');
            $isImg = str_starts_with($mime, 'image/');
          @endphp

          @if($isImg)
            <a href="{{ $url }}" target="_blank" class="att att-img">
              <img src="{{ $url }}" alt="{{ $a->name ?? 'image' }}">
              <div class="att-cap">
                <span class="att-name">{{ $a->name ?? 'Image' }}</span>
              </div>
            </a>
          @else
            <a class="att att-file" href="{{ $url }}" target="_blank">
              <div class="att-ico"><i class="bi bi-file-earmark"></i></div>
              <div class="att-info">
                <div class="att-name">{{ $a->name ?? 'Download file' }}</div>
                <div class="att-sub">
                  <span class="att-mime">{{ $mime ?: 'file' }}</span>
                  @if(!empty($a->size))
                    <span class="att-dot">•</span>
                    <span class="att-size">{{ round($a->size/1024, 1) }} KB</span>
                  @endif
                </div>
              </div>
              <div class="att-open"><i class="bi bi-box-arrow-up-right"></i></div>
            </a>
          @endif
        @endforeach
      </div>
    </div>

    <div class="msg-divider"></div>
  @endif

  {{-- MESSAGE TEXT --}}
  @if(trim((string)$m->body) !== '')
    <div class="msg-section">
      <div class="msg-text" style="white-space:pre-wrap">{{ $m->body }}</div>
    </div>
  @endif

</div>

</div>

