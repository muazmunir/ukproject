<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; line-height:1.6;">
  <h2>Hello {{ $coach->first_name ?? 'Coach' }},</h2>

  <p>Your service <strong>{{ $service->title }}</strong> was reviewed and unfortunately it has been <strong>rejected</strong>.</p>

  @if(!empty($reason))
    <p><strong>Reason:</strong> {{ $reason }}</p>
  @else
    <p>Please review your service details and submit again with correct information.</p>
  @endif

  <p>
    <a href="{{ route('login') }}"
       style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:8px;">
      Update Service on ZAIVIAS
    </a>
  </p>

  <p style="color:#666;font-size:12px;">— ZAIVIAS Team</p>
</body>
</html>
