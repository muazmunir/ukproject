<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register Passkey — ZAIVIAS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-webauthn-register.css') }}">
</head>
<body>
    <main class="auth">
        <section class="auth-card">
            <header class="auth-head">
                <img src="{{ asset('assets/logo.png') }}" class="logo" alt="ZAIVIAS">
                <h1 class="title text-capitalize">Set up your passkey</h1>
                <p class="subtitle text-capitalize">
                    Add a passkey to this staff account so future admin sign-ins can be verified securely.
                </p>
            </header>

            <div class="passkey-account">
                <span class="passkey-account__label">Account</span>
                <strong class="passkey-account__value">{{ $user->email ?? '' }}</strong>
            </div>

            <div class="form">
                <div class="field">
                    <label for="alias" class="label text-capitalize text-center">
                        Device name <span class="optional">(optional)</span>
                    </label>

                    <input
                        type="text"
                        id="alias"
                        class="input"
                        placeholder="Work MacBook / Office iPhone / Windows Hello"
                        maxlength="100"
                        autocomplete="off"
                    >

                    <p class="help text-capitalize text-center">
                        This helps you recognize the device later.
                    </p>
                </div>

                <button id="register-passkey-btn" type="button" class="btn text-capitalize">
                    Register passkey
                </button>

                <div
                    id="register-status"
                    class="status-box text-capitalize"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="passkey-footer">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="link-btn">Sign out</button>
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
            mode: 'register',
        };
    </script>

    @vite(['resources/js/admin-webauthn.js'])
</body>
</html>