<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureAdminPasskeyVerified
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            Log::warning('Admin passkey middleware guest redirect', [
                'route' => $request->route()?->getName(),
                'session_id' => $request->session()->getId(),
                'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
                'panel' => $request->session()->get('panel'),
                'url' => $request->fullUrl(),
            ]);

            return redirect()->route('admin.login');
        }

        $user = auth()->user();

        Log::info('Admin passkey middleware hit', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'route' => $request->route()?->getName(),
            'session_id' => $request->session()->getId(),
            'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
            'panel' => $request->session()->get('panel'),
            'url' => $request->fullUrl(),
        ]);

        if (
            ! method_exists($user, 'isStaffPasskeyRole') ||
            ! method_exists($user, 'isActiveStaffAccount') ||
            ! $user->isStaffPasskeyRole() ||
            ! $user->isActiveStaffAccount()
        ) {
            Log::warning('Admin passkey middleware invalid staff account', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
                'route' => $request->route()?->getName(),
            ]);

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        $allowedRoutes = [
            'admin.webauthn.register',
            'admin.webauthn.verify',
            'admin.webauthn.register.options',
            'admin.webauthn.register.store',
            'admin.webauthn.verify.options',
            'admin.webauthn.verify.store',
            'admin.logout',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes, true)) {
            Log::info('Admin passkey middleware bypassed allowed route', [
                'user_id' => $user?->id,
                'route' => $request->route()?->getName(),
                'session_id' => $request->session()->getId(),
                'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
            ]);

            return $next($request);
        }

        if ($request->session()->get('staff_passkey_verified', false)) {
            Log::info('Admin passkey middleware passed', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'route' => $request->route()?->getName(),
                'session_id' => $request->session()->getId(),
                'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
                'panel' => $request->session()->get('panel'),
            ]);

            return $next($request);
        }

        $hasPasskeys = $this->userHasPasskeys($user);

        if (! $hasPasskeys) {
            Log::warning('Admin passkey middleware redirecting to register - no passkeys found', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'route' => $request->route()?->getName(),
                'session_id' => $request->session()->getId(),
            ]);

            return redirect()->route('admin.webauthn.register');
        }

        Log::warning('Admin passkey middleware redirecting to verify - passkeys exist but not verified', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'route' => $request->route()?->getName(),
            'session_id' => $request->session()->getId(),
            'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
            'panel' => $request->session()->get('panel'),
        ]);

        return redirect()->route('admin.webauthn.verify');
    }

    protected function userHasPasskeys($user): bool
    {
        try {
            if (method_exists($user, 'webauthnCredentials')) {
                return $user->webauthnCredentials()->exists();
            }

            if (method_exists($user, 'webAuthnCredentials')) {
                return $user->webAuthnCredentials()->exists();
            }

            if (method_exists($user, 'passkeys')) {
                return $user->passkeys()->exists();
            }
        } catch (\Throwable $e) {
            Log::error('Admin passkey middleware passkey existence check failed', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }
}