@php
  $r = $reservation;

  $serviceTitle = $r->service_title_snapshot ?: ($r->service->title ?? 'Service');
  $packageName  = $r->package_name_snapshot ?: ($r->package->name ?? 'Package');

  $img = !empty($r->service->cover_image)
    ? asset('storage/'.$r->service->cover_image)
    : asset('assets/logo.png');

  $currency = $r->currency ?? 'USD';
  $total    = number_format(($r->total_minor ?? 0) / 100, 2);

  $environment = $r->environment ?: '—';
  $note = $r->note ?: '—';

  $clientName = trim(($r->client->first_name ?? '').' '.($r->client->last_name ?? '')) ?: 'Client';
@endphp

<!doctype html>
<html>
<body style="margin:0;background:#f6f7fb;font-family:Inter,Arial,sans-serif;color:#111;">
  <div style="max-width:640px;margin:0 auto;padding:24px;">
    <div style="text-align:center;margin-bottom:14px;">
      <img src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS" height="28">
    </div>

    <div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.06);">
      <img src="{{ $img }}" alt="" style="width:100%;height:220px;object-fit:cover;display:block;">

      <div style="padding:22px;">
        <div style="font-size:14px;color:#6b7280;margin-bottom:6px;">
          New Booking Received
        </div>

        <div style="font-size:22px;font-weight:700;margin-bottom:10px;">
          {{ $serviceTitle }}
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
          <span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#f3f4f6;">
            Package: <strong>{{ $packageName }}</strong>
          </span>
          <span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#f3f4f6;">
            Client: <strong>{{ $clientName }}</strong>
          </span>
          <span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#f3f4f6;">
            Timezone: <strong>{{ $tzLabel }}</strong>
          </span>
        </div>

        <div style="margin:18px 0 8px;font-weight:700;">Scheduled sessions</div>
        <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <thead>
              <tr style="background:#fafafa;">
                <th align="left" style="padding:10px 12px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Date</th>
                <th align="left" style="padding:10px 12px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Time</th>
              </tr>
            </thead>
            <tbody>
              @foreach($slotsLocal as $s)
                <tr>
                  <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">{{ $s['date'] }}</td>
                  <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">{{ $s['start'] }} – {{ $s['end'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap;">
          <div style="flex:1;min-width:220px;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">Environment</div>
            <div style="font-weight:600;">{{ $environment }}</div>
          </div>
          <div style="flex:1;min-width:220px;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">Client note</div>
            <div style="font-weight:500;white-space:pre-line;">{{ $note }}</div>
          </div>
        </div>

        <div style="margin-top:18px;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
          <div style="display:flex;justify-content:space-between;">
            <span style="color:#6b7280;">Booking total</span>
            <strong>{{ $currency }} {{ $total }}</strong>
          </div>
        </div>

        <div style="margin-top:18px;text-align:center;">
          <a href="{{ route('coach.bookings') }}"
             style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700;">
            View booking
          </a>
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-top:14px;font-size:12px;color:#9ca3af;">
      © {{ date('Y') }} ZAIVIAS. All rights reserved.
    </div>
  </div>
</body>
</html>
