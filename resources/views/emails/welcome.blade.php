<!doctype html>
<html>
  <body style="font-family: Arial, sans-serif; line-height:1.6;">
    <h2>Welcome {{ $user->first_name }} 👋</h2>

    <p>Your ZAIVIAS account has been verified successfully.</p>

    <p>You can now login and start using the platform as a <b>{{ ucfirst($user->role) }}</b>.</p>

    <p>
      <a href="{{ route('login') }}"
         style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:8px;">
        Go to Login
      </a>
    </p>

    <p style="color:#666;font-size:12px;">
      If you didn’t create this account, you can ignore this email.
    </p>
  </body>
</html>
