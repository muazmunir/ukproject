<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; line-height:1.6;">
  <h2>Hello {{ $user->first_name }},</h2>

  <p>Great news — your <strong>ZAIVIAS coach account</strong> has been approved.</p>

  <p>You can now log in and start publishing your services and accepting bookings.</p>

  <p>
    <a href="{{ route('login') }}"
       style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:8px;">
      Log in to ZAIVIAS
    </a>
  </p>

  <p style="color:#666;font-size:12px;">— ZAIVIAS Team</p>
</body>
</html>
