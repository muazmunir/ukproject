<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify Passkey — ZAIVIAS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.esm-importmap')

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-webauthn-verify.css') }}">
</head>
<body>
    <main class="auth">
        <section class="auth-card">
            <header class="auth-head">
                <img src="{{ asset('assets/logo.png') }}" class="logo" alt="ZAIVIAS">
                <h1 class="title text-capitalize">Verify your sign in</h1>
                <p class="subtitle text-capitalize">
                    Use your registered passkey to continue to the admin dashboard.
                </p>
            </header>

            <div class="verify-account">
                <span class="verify-account__label text-capitalize">Signed in as</span>
                <strong class="verify-account__value">{{ $email }}</strong>
            </div>

            <div class="verify-actions">
                <button id="verify-passkey-btn" type="button" class="btn text-capitalize">
                    Verify with passkey
                </button>

                <div
                    id="verify-status"
                    class="status-box text-capitalize"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="verify-footer">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="link-btn text-capitalize">Use another account</button>
                    </form>
                </div>
            </div>

            <footer class="foot">
                <span>ZAIVIAS • Staff Portal</span>
            </footer>
        </section>
    </main>

    <script>
        window.AdminWebAuthnConfig = {
            registerOptionsUrl: @json(route('admin.webauthn.register.options')),
            registerStoreUrl: @json(route('admin.webauthn.register.store')),
            verifyOptionsUrl: @json(route('admin.webauthn.verify.options')),
            verifyStoreUrl: @json(route('admin.webauthn.verify.store')),
            dashboardUrl: @json(route('admin.dashboard')),
            csrfToken: @json(csrf_token()),
            mode: 'verify',
        };
    </script>

    <script type="module" src="{{ asset('js/admin-webauthn.js') }}"></script>
</body>
</html>