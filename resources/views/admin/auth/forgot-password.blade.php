<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password — ZAIVIAS</title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/staff-forgot-password.css') }}">
</head>
<body>
    <main class="auth">
        <section class="auth-card">
            <header class="auth-head">
                <img src="{{ asset('assets/logo.png') }}" class="logo" alt="ZAIVIAS">
                <h1 class="title text-capitalize">Forgot Password</h1>
                <p class="subtitle text-capitalize">
                    Enter your staff email address. If the account is eligible, a secure reset link will be sent to your inbox.
                </p>
            </header>

            @if (session('status'))
                <div class="alert alert-success text-center text-capitalize" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger text-center text-capitalize" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="form" method="POST" action="{{ route('admin.password.email') }}">
                @csrf

                <div class="field">
                    <label for="email" class="label text-center">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="input"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="Enter Your Staff Email"
                    >
                </div>

                <button type="submit" class="btn">
                    Send Reset Link
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