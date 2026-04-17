@php
  $start = \Carbon\Carbon::parse($slot->start_utc)->timezone('UTC');
@endphp

<p>Hello,</p>

<p>This is a reminder that your ZAIVIAS session starts in 15 minutes.</p>

<p>
  <b>Date/Time (UTC):</b> {{ $start->format('d M Y, H:i') }}<br>
  <b>Booking ID:</b> {{ $res->id }}<br>
</p>

<p>You can check-in starting 5 minutes before the session start time.</p>

<p>Thanks,<br>ZAIVIAS</p>
