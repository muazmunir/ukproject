<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Booking Confirmed</title>
</head>
<body style="margin:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#111;">
  @php
    $service   = $reservation->service;
    $coach     = $reservation->coach;
    $currency  = $reservation->currency ?? 'USD';

    $serviceTitle = $reservation->service_title_snapshot ?: ($service->title ?? 'Service');
    $packageName  = $reservation->package_name_snapshot ?: ($reservation->package->name ?? 'Package');

    $img = $service?->thumbnail_url ?? null;
    if (!$img && !empty($service?->thumbnail_path)) $img = asset('storage/'.ltrim($service->thumbnail_path,'/'));
    if (!$img && !empty($service?->images) && is_array($service->images) && count($service->images)) $img = asset('storage/'.ltrim($service->images[0],'/'));
    if (!$img) $img = asset('assets/placeholder-service.png');

    $subtotal = ((int)($reservation->subtotal_minor ?? 0)) / 100; // service price
    $clientFee = ((int)($reservation->fees_minor ?? 0)) / 100;    // client fee
    $total = ((int)($reservation->total_minor ?? 0)) / 100;       // what client paid

    $environment = $reservation->environment ?: '—';
    $note = trim((string)($reservation->note ?? ''));
    $coachName = trim(($coach->first_name ?? '').' '.($coach->last_name ?? ''));
    $coachName = $coachName ?: 'Coach';

    $bookedAt = $reservation->booked_at ? $reservation->booked_at->format('d M Y, h:i A') : null;
  @endphp

  <div style="max-width:720px;margin:0 auto;padding:24px;">
    <div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px rgba(0,0,0,.06);">

      {{-- Header --}}
      <div style="padding:18px 22px;border-bottom:1px solid #eee;">
        <div style="font-weight:700;font-size:16px;letter-spacing:.2px;">
          {{ config('app.name','Zaivias') }}
        </div>
        <div style="margin-top:6px;font-size:22px;font-weight:800;">
          Booking confirmed ✅
        </div>
        <div style="margin-top:6px;color:#666;font-size:13px;">
          Booking ID: <strong>#{{ $reservation->id }}</strong>
          @if($bookedAt)
            · Booked: <strong>{{ $bookedAt }}</strong>
          @endif
        </div>
      </div>

      {{-- Hero --}}
      <div style="padding:0;">
        <img src="{{ $img }}" alt="" style="display:block;width:100%;height:auto;">
      </div>

      {{-- Content --}}
      <div style="padding:20px 22px;">
        <div style="font-size:18px;font-weight:800;margin-bottom:6px;">
          {{ $serviceTitle }}
        </div>
        <div style="color:#555;font-size:13px;margin-bottom:14px;">
          Package: <strong>{{ $packageName }}</strong> · Coach: <strong>{{ $coachName }}</strong>
        </div>

        {{-- Booking details --}}
        <div style="display:block;border:1px solid #eee;border-radius:14px;padding:14px 14px;margin-bottom:14px;">
          <div style="font-weight:800;margin-bottom:10px;">Booking details</div>

          <div style="font-size:13px;margin-bottom:8px;">
            <span style="color:#666;">Environment selected:</span>
            <strong>{{ $environment }}</strong>
          </div>

          @if($note !== '')
            <div style="font-size:13px;">
              <div style="color:#666;margin-bottom:6px;">Client note:</div>
              <div style="background:#f6f7fb;border:1px solid #eee;border-radius:12px;padding:10px 12px;line-height:1.45;">
                {{ $note }}
              </div>
            </div>
          @endif
        </div>

        {{-- Slots --}}
        <div style="border:1px solid #eee;border-radius:14px;padding:14px 14px;margin-bottom:14px;">
          <div style="font-weight:800;margin-bottom:6px;">Your scheduled sessions</div>
          <div style="color:#666;font-size:12px;margin-bottom:10px;">
            Times shown in <strong>{{ $tzLabel }}</strong>
          </div>

          <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">
            <thead>
              <tr>
                <th align="left" style="font-size:12px;color:#666;padding:8px 6px;border-bottom:1px solid #eee;">Date</th>
                <th align="left" style="font-size:12px;color:#666;padding:8px 6px;border-bottom:1px solid #eee;">Time</th>
              </tr>
            </thead>
            <tbody>
              @foreach($slotsLocal as $s)
                <tr>
                  <td style="padding:10px 6px;font-size:13px;border-bottom:1px solid #f1f1f1;">
                    <strong>{{ $s['date'] }}</strong>
                  </td>
                  <td style="padding:10px 6px;font-size:13px;border-bottom:1px solid #f1f1f1;">
                    {{ $s['start'] }} – {{ $s['end'] }}
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Price --}}
        <div style="border:1px solid #eee;border-radius:14px;padding:14px 14px;">
          <div style="font-weight:800;margin-bottom:10px;">Price details</div>

          <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">
            <tr>
              <td style="padding:6px 0;color:#444;font-size:13px;">Service price</td>
              <td align="right" style="padding:6px 0;color:#111;font-size:13px;">
                {{ number_format($subtotal,2) }} {{ $currency }}
              </td>
            </tr>
            <tr>
              <td style="padding:6px 0;color:#444;font-size:13px;">Platform fee</td>
              <td align="right" style="padding:6px 0;color:#111;font-size:13px;">
                {{ number_format($clientFee,2) }} {{ $currency }}
              </td>
            </tr>
            <tr>
              <td colspan="2" style="padding:10px 0;border-top:1px solid #eee;"></td>
            </tr>
            <tr>
              <td style="padding:6px 0;font-weight:800;font-size:14px;">Total paid</td>
              <td align="right" style="padding:6px 0;font-weight:800;font-size:14px;">
                {{ number_format($total,2) }} {{ $currency }}
              </td>
            </tr>
          </table>
        </div>

        <div style="margin-top:16px;color:#666;font-size:12px;line-height:1.45;">
          Need help? Reply to this email or contact support inside {{ config('app.name','Zaivias') }}.
        </div>
      </div>

      {{-- Footer --}}
      <div style="padding:16px 22px;border-top:1px solid #eee;color:#888;font-size:12px;background:#fafafa;">
        © {{ date('Y') }} {{ config('app.name','Zaivias') }} · All rights reserved
      </div>
    </div>
  </div>
</body>
</html>
