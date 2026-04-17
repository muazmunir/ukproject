<!doctype html>
<html>
  <body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#111">
    <h2>Verify your email</h2>
    <p>Hi {{ $user->full_name ?: $user->email }},</p>
    <p>Your verification code is:</p>
    <div style="font-size:28px;font-weight:800;letter-spacing:6px;background:#f7f7f7;padding:12px 16px;display:inline-block;border-radius:8px;">
      {{ $code }}
    </div>
    <p style="margin-top:14px;">This code expires in <strong>10 minutes</strong>.</p>
    <p>Thanks,<br>ZAIVIAS Team</p>
  </body>
</html>
