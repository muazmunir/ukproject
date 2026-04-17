<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; line-height:1.6;">
  <h2>Hello {{ $coach->first_name ?? 'Coach' }},</h2>

  <p>Your service <strong>{{ $service->title }}</strong> has been <strong>disabled</strong> by admin and is currently not visible to clients.</p>

  @if(!empty($reason))
    <p><strong>Reason:</strong> {{ $reason }}</p>
  @else
    <p>If you believe this is a mistake, please contact support.</p>
  @endif

  <p>
    <a href="{{ route('login') }}"
       style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:8px;">
      Log in to ZAIVIAS
    </a>
  </p>

  <p style="color:#666;font-size:12px;">— ZAIVIAS Team</p>
</body>
</html>
