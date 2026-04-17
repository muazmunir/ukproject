<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'stripe/webhook',
        'api/stripe/webhook',

        // WebAuthn
        'admin/webauthn/register/options',
        'admin/webauthn/register/store',
        'admin/webauthn/verify/options',
        'admin/webauthn/verify/store',
    ];
}