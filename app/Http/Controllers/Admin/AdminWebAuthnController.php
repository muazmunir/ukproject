<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminWebAuthnController extends Controller
{
    public function showRegister(Request $request)
    {
        $user = $this->resolveWebUser($request);

        Log::info('AdminWebAuthn showRegister hit', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'user_class' => $user ? get_class($user) : null,
            'session_id' => $request->session()->getId(),
            'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
            'panel' => $request->session()->get('panel'),
        ]);

        if (! $this->isAuthorizedStaff($user)) {
            Log::warning('AdminWebAuthn showRegister unauthorized', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
            ]);

            return redirect()->route('admin.login');
        }

        return view('admin.webauthn.register', [
            'user' => $user,
        ]);
    }

    public function showVerify(Request $request)
    {
        $user = $this->resolveWebUser($request);

        Log::info('AdminWebAuthn showVerify hit', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'user_class' => $user ? get_class($user) : null,
            'session_id' => $request->session()->getId(),
            'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
            'panel' => $request->session()->get('panel'),
            'url' => $request->fullUrl(),
        ]);

        if (! $this->isAuthorizedStaff($user)) {
            Log::warning('AdminWebAuthn showVerify unauthorized', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
            ]);

            return redirect()->route('admin.login');
        }

        if ($request->session()->get('staff_passkey_verified', false)) {
            Log::info('AdminWebAuthn showVerify redirecting verified user to dashboard', [
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
                'staff_passkey_verified' => $request->session()->get('staff_passkey_verified'),
            ]);

            return redirect()->route('admin.dashboard');
        }

        return view('admin.webauthn.verify', [
            'email' => $user->email,
            'user' => $user,
        ]);
    }

   public function registerOptions(AttestationRequest $request)
    {
        $user = $this->resolveWebUser($request);

        Log::info('AdminWebAuthn registerOptions hit', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'user_class' => $user ? get_class($user) : null,
            'session_id' => $request->session()->getId(),
            'origin' => $request->headers->get('origin'),
            'host' => $request->getHost(),
            'alias' => $request->input('alias'),
        ]);

        if (! $this->isAuthorizedStaff($user)) {
            Log::warning('AdminWebAuthn registerOptions unauthorized', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
            ]);

            return $this->jsonError('Unauthorized.', 403);
        }

        try {
            if (! method_exists($request, 'secureRegistration')) {
                throw new \RuntimeException('secureRegistration() is not available on the request.');
            }

            // Force the request to resolve the already-authenticated web user.
            $request->setUserResolver(fn ($guard = null) => $user);

            Log::info('AdminWebAuthn registerOptions generating ceremony', [
                'user_id' => $user->id,
                'email' => $user->email,
                'session_id' => $request->session()->getId(),
                'user_model' => get_class($user),
                'has_webauthn_relation' => method_exists($user, 'webauthnCredentials'),
                'has_staff_role_method' => method_exists($user, 'isStaffPasskeyRole'),
                'has_active_staff_method' => method_exists($user, 'isActiveStaffAccount'),
            ]);

            $response = $request
                ->secureRegistration()
                ->allowDuplicates()
                ->toCreate();

            Log::info('AdminWebAuthn registerOptions success', [
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('AdminWebAuthn registerOptions failed', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'user_class' => $user ? get_class($user) : null,
                'session_id' => $request->session()->getId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->jsonError(
                'Unable to generate passkey registration options.',
                500,
                $e
            );
        }
    }

    public function registerStore(AttestedRequest $request): JsonResponse
    {
        $user = $this->resolveWebUser($request);

        Log::info('AdminWebAuthn registerStore hit', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'user_class' => $user ? get_class($user) : null,
            'session_id' => $request->session()->getId(),
            'staff_passkey_verified_before' => $request->session()->get('staff_passkey_verified'),
            'panel_before' => $request->session()->get('panel'),
        ]);

        if (! $this->isAuthorizedStaff($user)) {
            Log::warning('AdminWebAuthn registerStore unauthorized', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
            ]);

            return $this->jsonError('Unauthorized.', 403);
        }

        $validated = $request->validate([
            'alias' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $request->setUserResolver(fn ($guard = null) => $user);

            $credential = $request->save([
                'alias' => $validated['alias'] ?? null,
            ]);

            $request->session()->put('panel', 'admin');
            $request->session()->put('staff_passkey_verified', true);
            $request->session()->forget('active_role');
            $request->session()->save();

            Log::info('AdminWebAuthn registerStore success', [
                'user_id' => $user->id,
                'email' => $user->email,
                'credential_id' => $credential->id ?? null,
                'session_id' => $request->session()->getId(),
                'staff_passkey_verified_after' => $request->session()->get('staff_passkey_verified'),
                'panel_after' => $request->session()->get('panel'),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Passkey registered successfully.',
                'credential_id' => $credential->id ?? null,
                'redirect' => route('admin.dashboard'),
            ]);
        } catch (Throwable $e) {
            Log::error('AdminWebAuthn registerStore failed', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'user_class' => $user ? get_class($user) : null,
                'session_id' => $request->session()->getId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->jsonError(
                'Unable to save registered passkey.',
                500,
                $e
            );
        }
    }

    public function verifyOptions(AssertionRequest $request)
    {
        $user = $this->resolveWebUser($request);

        Log::info('AdminWebAuthn verifyOptions hit', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'user_class' => $user ? get_class($user) : null,
            'session_id' => $request->session()->getId(),
            'staff_passkey_verified_before' => $request->session()->get('staff_passkey_verified'),
            'panel_before' => $request->session()->get('panel'),
            'origin' => $request->headers->get('origin'),
            'host' => $request->getHost(),
        ]);

        if (! $this->isAuthorizedStaff($user)) {
            Log::warning('AdminWebAuthn verifyOptions unauthorized', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
            ]);

            return $this->jsonError('Unauthorized.', 403);
        }

        try {
            if (! method_exists($request, 'secureLogin')) {
                throw new \RuntimeException('secureLogin() is not available on the request.');
            }

            $request->setUserResolver(fn ($guard = null) => $user);

            Log::info('AdminWebAuthn verifyOptions generating ceremony', [
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            $response = $request
                ->secureLogin()
                ->toVerify();

            Log::info('AdminWebAuthn verifyOptions success', [
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('AdminWebAuthn verifyOptions failed', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'user_class' => $user ? get_class($user) : null,
                'session_id' => $request->session()->getId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->jsonError(
                'Unable to generate passkey verification options.',
                500,
                $e
            );
        }
    }

   public function verifyStore(AssertedRequest $request): JsonResponse
{
    $currentUser = $this->resolveWebUser($request);

    Log::info('AdminWebAuthn verifyStore hit', [
        'user_id' => $currentUser?->id,
        'email' => $currentUser?->email,
        'user_class' => $currentUser ? get_class($currentUser) : null,
        'session_id_before' => $request->session()->getId(),
        'staff_passkey_verified_before' => $request->session()->get('staff_passkey_verified'),
        'panel_before' => $request->session()->get('panel'),
    ]);

    if (! $this->isAuthorizedStaff($currentUser)) {
        Log::warning('AdminWebAuthn verifyStore unauthorized', [
            'user_id' => $currentUser?->id,
            'email' => $currentUser?->email,
            'session_id' => $request->session()->getId(),
        ]);

        return $this->jsonError('Unauthorized.', 403);
    }

    try {
        $request->setUserResolver(fn ($guard = null) => $currentUser);

        $user = $request->user();

        Log::info('AdminWebAuthn verifyStore resolved user', [
            'current_user_id' => $currentUser?->id,
            'resolved_user_id' => $user?->id,
            'session_id_after_resolve' => $request->session()->getId(),
        ]);

        if (! $user) {
            Log::warning('AdminWebAuthn verifyStore no user resolved', [
                'user_id' => $currentUser?->id,
                'session_id' => $request->session()->getId(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Passkey verification failed.',
            ], 422);
        }

        if ((int) $user->getAuthIdentifier() !== (int) $currentUser->getAuthIdentifier()) {
            Log::warning('AdminWebAuthn verifyStore user mismatch', [
                'current_user_id' => $currentUser?->id,
                'resolved_user_id' => $user?->id,
                'session_id' => $request->session()->getId(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Passkey verification failed.',
            ], 422);
        }

        if (
            ! method_exists($user, 'isStaffPasskeyRole')
            || ! method_exists($user, 'isActiveStaffAccount')
            || ! $user->isStaffPasskeyRole()
            || ! $user->isActiveStaffAccount()
        ) {
            Log::warning('AdminWebAuthn verifyStore resolved user is not valid staff', [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'session_id' => $request->session()->getId(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Passkey verification failed.',
            ], 422);
        }

        $request->session()->put('panel', 'admin');
        $request->session()->put('staff_passkey_verified', true);
        $request->session()->forget('active_role');
        $request->session()->save();

        Log::info('AdminWebAuthn verifyStore success', [
            'user_id' => $currentUser->id,
            'email' => $currentUser->email,
            'session_id_after_save' => $request->session()->getId(),
            'staff_passkey_verified_after' => $request->session()->get('staff_passkey_verified'),
            'panel_after' => $request->session()->get('panel'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Passkey verified successfully.',
            'redirect' => route('admin.dashboard'),
        ]);
    } catch (Throwable $e) {
        Log::error('AdminWebAuthn verifyStore failed', [
            'user_id' => $currentUser?->id,
            'email' => $currentUser?->email,
            'user_class' => $currentUser ? get_class($currentUser) : null,
            'session_id' => $request->session()->getId(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->jsonError(
            'Unable to verify passkey.',
            500,
            $e
        );
    }
}

    protected function resolveWebUser(Request $request)
    {
        return $request->user();
    }

    protected function isAuthorizedStaff($user): bool
    {
        return (bool) (
            $user
            && method_exists($user, 'isStaffPasskeyRole')
            && method_exists($user, 'isActiveStaffAccount')
            && $user->isStaffPasskeyRole()
            && $user->isActiveStaffAccount()
        );
    }

    protected function jsonError(string $message, int $status, ?Throwable $e = null): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'error' => app()->isLocal() && $e ? $e->getMessage() : null,
        ], $status);
    }
}