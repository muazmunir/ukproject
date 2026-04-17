<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title> Admin Sign in — ZAIVIAS</title>

  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="{{ asset('assets/css/superadmin-auth.css') }}">
</head>

<body>
    <main class="auth-wrap">
        <div class="auth-card">
        <img src="{{ asset('assets/logo.png') }}" class="auth-logo" alt="ZAIVIAS">

      <header class="auth-head">
        <h1 class="auth-title"> Admin Login</h1>
        <p class="auth-subtitle">Restricted Access Area</p>
      </header>

      {{-- Alerts --}}
      @if($errors->any())
        <div class="alert alert-danger">
          {{ $errors->first() }}
        </div>
      @endif

      @if(session('success'))
        <div class="alert alert-success">
          {{ session('success') }}
        </div>
      @endif

      <form class="auth-form" action="{{ route('superadmin.login.submit') }}" method="POST" autocomplete="on">
        @csrf

        <div class="field">
          <label class="label" for="email">Email</label>
          <input
            id="email"
            type="email"
            class="input"
            name="email"
            value="{{ old('email') }}"
            placeholder="you@example.com"
            required
            autofocus
          >
        </div>

        <div class="field">
          <label class="label" for="password">Password</label>

          <div class="input-wrap">
            <input
              id="password"
              type="password"
              class="input input-password"
              name="password"
              placeholder="••••••••"
              required
            >

            <button type="button" class="toggle-pass" aria-label="Show password" data-toggle-pass>
              <!-- eye icon (inline svg) -->
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/>
                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="row">
          <label class="check">
            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
            <span>Remember me</span>
          </label>
        </div>

        <button type="submit" class="btn-primary">
          Login
        </button>

        <div class="auth-foot">
          <span>ZAIVIAS •Admin Portal</span>
        </div>
      </form>

    </div>
  </main>

  <script>
    // Show/Hide password (no dependency)
    (function () {
      const btn = document.querySelector('[data-toggle-pass]');
      const input = document.getElementById('password');
      if (!btn || !input) return;

      btn.addEventListener('click', function () {
        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
        btn.classList.toggle('is-on', isPass);
      });
    })();
  </script>
</body>
</html>
