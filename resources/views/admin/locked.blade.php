<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Account Locked</title>
  <link rel="stylesheet" href="{{ asset('assets/css/locked-account.css') }}">
</head>
<body class="zv-locked-page">
  <div class="zv-locked-card">
    <div class="zv-locked-icon">!</div>
    <h1>Your account is locked</h1>
    <p>Your line manager will reach out to you shortly. If you believe this is a mistake, contact support.</p>
    <a class="zv-locked-btn" href="{{ route('login') }}">Back to login</a>
  </div>
</body>
</html>
