<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; line-height:1.6;">
  <h2>Hello {{ $coach->first_name ?? 'Coach' }},</h2>

  <p>Great news — your service <strong>{{ $service->title }}</strong> has been approved and is now live on <strong>ZAIVIAS</strong>.</p>

  <p>You can now start receiving bookings for this service.</p>

  <p>
    <a href="{{ route('login') }}"
       style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:8px;">
      Log in to ZAIVIAS
    </a>
  </p>

  <p style="color:#666;font-size:12px;">— ZAIVIAS Team</p>
</body>
</html>
