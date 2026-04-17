<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Session Locked — ZAIVIAS Staff</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/css/admin-softlock.css') }}">
</head>
<body>
  <div class="lock-wrap">
    <div class="lock-card">
      <div class="lock-badge">🔒 Session Locked</div>

      <h1>Enter Your Password To Continue</h1>
      <p class="muted text-capitalize">You were inactive for 1 minute. For security, your admin session is locked.</p>

      <form method="post" action="{{ route('admin.locked.soft.unlock') }}">
        @csrf

        <label class="lbl">Password</label>
        <input type="password" name="password" class="inp" autocomplete="current-password" autofocus>

        @error('password')
          <div class="err">{{ $message }}</div>
        @enderror

        <button class="btn" type="submit">Unlock</button>

        <div class="hint text-capitalize">
          If You’re Having Trouble, Contact Your Line Manager / Admin.
        </div>
      </form>
    </div>
  </div>
</body>
</html>
