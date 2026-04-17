{{-- resources/views/admin/auth/login.blade.php --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Staff Sign in — ZAIVIAS</title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-auth.css') }}">
</head>
<body>
    <main class="auth">
        <section class="auth-card">
            <header class="auth-head">
                <img src="{{ asset('assets/logo.png') }}" class="logo" alt="ZAIVIAS">
                <h1 class="title">Staff Sign in</h1>
                <p class="subtitle text-capitalize">
                    Use your staff email and password to continue. Passkey verification will be required next.
                </p>
            </header>

            @if ($errors->any())
                <div class="alert alert-danger text-center text-capitalize" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success text-center text-capitalize" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success text-capitalize text-center" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form class="form" method="post" action="{{ route('admin.login') }}" autocomplete="on">
                @csrf

                <div class="field">
                    <label for="email" class="label">Email</label>
                    <input
                        id="email"
                        class="input @error('email') is-invalid @enderror"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="admin@zaivias.com"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="password" class="label">Password</label>

                    <div class="input-wrap">
                        <input
                            id="password"
                            class="input input-password @error('password') is-invalid @enderror"
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle"
                            aria-label="Show password"
                            aria-controls="password"
                            aria-pressed="false"
                            data-toggle-pass
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="row">
                    {{-- <label class="check">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>Remember me</span>
                    </label> --}}

                    <a class="link" href="{{ route('admin.password.request') }}" aria-label="Forgot password">Forgot?</a>
                </div>

                <button class="btn" type="submit">Sign in</button>

                <footer class="foot">
                    <span>ZAIVIAS • Staff Portal</span>
                </footer>
            </form>
        </section>
    </main>

    <script>
        (function () {
            const btn = document.querySelector('[data-toggle-pass]');
            const input = document.getElementById('password');

            if (!btn || !input) return;

            btn.addEventListener('click', function () {
                const showingPassword = input.type === 'text';

                input.type = showingPassword ? 'password' : 'text';
                btn.setAttribute('aria-label', showingPassword ? 'Show password' : 'Hide password');
                btn.setAttribute('aria-pressed', showingPassword ? 'false' : 'true');
                btn.classList.toggle('on', !showingPassword);
            });
        })();
    </script>
</body>
</html>