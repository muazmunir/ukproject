@php
  $start = \Carbon\Carbon::parse($slot->start_utc)->timezone('UTC');
@endphp

<p>Hello,</p>

<p>
  Your session has started, but the other party is waiting.
  <b>Nudge (Attempt {{ $attempt }} of 2)</b>.
</p>

<p>
  <b>Session time (UTC):</b> {{ $start->format('d M Y, H:i') }}<br>
  <b>Booking ID:</b> {{ $res->id }}<br>
</p>

<p>Please join as soon as possible.</p>

<p>Thanks,<br>ZAIVIAS</p>
