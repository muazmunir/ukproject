@php
  use Illuminate\Support\Str;

  $text = Str::limit($msg->body, 160);

  // optional: simple highlight of keyword
  if (!empty($q)) {
      $pattern = '/(' . preg_quote($q, '/') . ')/i';
      $text = preg_replace($pattern, '<mark>$1</mark>', e($msg->body));
      $text = Str::limit($text, 200, '…');
  }
@endphp

<div class="zv-chat-search-hit" data-jump-id="{{ $msg->id }}">
  <div class="zv-chat-search-hit-meta small text-muted">
    {{ $msg->created_at->format('d M Y, H:i') }} · {{ $senderLabel }}
  </div>
  <div class="zv-chat-search-hit-body">
    {!! $text !!}
  </div>
</div>
