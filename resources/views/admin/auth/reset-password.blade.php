<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reset Password — ZAIVIAS</title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-reset-password.css') }}">
</head>
<body>
    <main class="auth">
        <section class="auth-card">
            <header class="auth-head">
                <img src="{{ asset('assets/logo.png') }}" class="logo" alt="ZAIVIAS">
                <h1 class="title">Reset Password</h1>
                <p class="subtitle text-capitalize">
                    Set a new strong password for your staff account.
                </p>
            </header>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="form" method="POST" action="{{ route('admin.password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field">
                    <label for="email" class="label">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="input input-readonly"
                        value="{{ old('email', $email) }}"
                        readonly
                    >
                </div>

                <div class="field">
                    <label for="password" class="label">New password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="input"
                        required
                        autocomplete="new-password"
                        placeholder="Enter new password"
                    >
                </div>

                <p class="help text-capitalize">
                    Use at least 12 characters with uppercase, lowercase, number, and symbol.
                </p>

                <div class="field">
                    <label for="password_confirmation" class="label">Confirm password</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        class="input"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm new password"
                    >
                </div>

                <button type="submit" class="btn">
                    Reset Password
                </button>
            </form>

            <div class="links">
                <a href="{{ route('admin.login') }}" class="link text-capitalize">Back to staff sign in</a>
            </div>

            <footer class="foot">
                <span>ZAIVIAS • Staff Portal</span>
            </footer>
        </section>
    </main>
</body>
</html>