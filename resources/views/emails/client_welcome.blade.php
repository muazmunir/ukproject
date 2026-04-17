<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; line-height:1.6;">
  <h2>Welcome {{ $user->first_name }} 👋</h2>

  <p>Your ZAIVIAS account has been verified successfully.</p>

  <p>You can now log in and start booking sessions with professional coaches.</p>

  <p>
    <a href="{{ route('login') }}"
       style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:8px;">
      Log in to ZAIVIAS
    </a>
  </p>

  <p style="color:#666;font-size:12px;">
    Thank you for joining ZAIVIAS.
  </p>
</body>
</html>
