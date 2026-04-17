@php
  $r = $reservation;

  $serviceTitle = $r->service_title_snapshot ?: ($r->service->title ?? 'Service');
  $packageName  = $r->package_name_snapshot ?: ($r->package->name ?? 'Package');

  // image (prefer service image if you have it)
  $img = null;
  if (!empty($r->service->cover_image)) {
    $img = asset('storage/'.$r->service->cover_image);
  } elseif (!empty($r->service->image_path)) {
    $img = asset('storage/'.$r->service->image_path);
  } else {
    $img = asset('assets/logo.png'); // fallback
  }

  $currency = $r->currency ?? 'USD';
  $total    = number_format(($r->total_minor ?? 0) / 100, 2);
  $subtotal = number_format(($r->subtotal_minor ?? 0) / 100, 2);
  $fees     = number_format(($r->fees_minor ?? 0) / 100, 2);

  $environment = $r->environment ?: '—';
  $note = $r->note ?: '—';

  $coachName = trim(($r->coach->first_name ?? '').' '.($r->coach->last_name ?? '')) ?: 'Coach';
@endphp

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;background:#f6f7fb;font-family:Inter,Arial,sans-serif;color:#111;">
  <div style="max-width:640px;margin:0 auto;padding:24px;">
    <div style="text-align:center;margin-bottom:14px;">
      <img src="{{ asset('assets/logo.png') }}" alt="ZAIVIAS" height="28" style="display:inline-block;">
    </div>

    <div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.06);">
      {{-- header image --}}
      <div>
        <img src="{{ $img }}" alt="" style="width:100%;height:220px;object-fit:cover;display:block;">
      </div>

      <div style="padding:22px;">
        <div style="font-size:14px;color:#6b7280;margin-bottom:6px;">
          Booking Confirmed
        </div>

        <div style="font-size:22px;font-weight:700;line-height:1.2;margin-bottom:10px;">
          {{ $serviceTitle }}
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
          <span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#f3f4f6;">
            Package: <strong>{{ $packageName }}</strong>
          </span>
          <span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#f3f4f6;">
            Coach: <strong>{{ $coachName }}</strong>
          </span>
          <span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#f3f4f6;">
            Timezone: <strong>{{ $tzLabel }}</strong>
          </span>
        </div>

        {{-- sessions --}}
        <div style="margin:18px 0 8px;font-weight:700;">Your sessions</div>
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

        {{-- environment + note --}}
        <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap;">
          <div style="flex:1;min-width:220px;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">Environment selected</div>
            <div style="font-weight:600;">{{ $environment }}</div>
          </div>
          <div style="flex:1;min-width:220px;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">Client note</div>
            <div style="font-weight:500;white-space:pre-line;">{{ $note }}</div>
          </div>
        </div>

        {{-- pricing --}}
        <div style="margin-top:18px;">
          <div style="font-weight:700;margin-bottom:8px;">Price details</div>
          <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="display:flex;justify-content:space-between;padding:6px 0;">
              <span style="color:#6b7280;">Subtotal</span>
              <strong>{{ $currency }} {{ $subtotal }}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;">
              <span style="color:#6b7280;">Fees</span>
              <strong>{{ $currency }} {{ $fees }}</strong>
            </div>
            <div style="height:1px;background:#e5e7eb;margin:10px 0;"></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:16px;">
              <span style="font-weight:700;">Total</span>
              <span style="font-weight:800;">{{ $currency }} {{ $total }}</span>
            </div>
          </div>
        </div>

        {{-- CTA --}}
        <div style="margin-top:18px;text-align:center;">
          <a href="{{ route('client.home', ['tab'=>'bookings']) }}"
             style="display:inline-block;background:#111;color:#fff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700;">
            View booking
          </a>
        </div>

        <div style="margin-top:18px;font-size:12px;color:#6b7280;line-height:1.5;">
          If you have any questions, you can message your coach inside ZAIVIAS.
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-top:14px;font-size:12px;color:#9ca3af;">
      © {{ date('Y') }} ZAIVIAS. All rights reserved.
    </div>
  </div>
</body>
</html>
