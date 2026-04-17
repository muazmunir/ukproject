<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminSecurityEvent;
use App\Models\Users;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class StaffPasswordResetController extends Controller
{
    public function request()
    {
        return view('admin.auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim((string) $request->email));

        $user = Users::query()
            ->where('email', $email)
            ->whereIn('role', ['admin', 'manager'])
            ->whereNull('deleted_at')
            ->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'We could not find an active staff account for that email.',
            ]);
        }

        // Create token manually and send custom admin reset notification
        $token = Password::broker()->createToken($user);
        $user->sendAdminPasswordResetNotification($token);

        AdminSecurityEvent::create([
            'admin_user_id' => $user->id,
            'type' => 'staff_password_reset_requested',
            'status' => 'open',
            'message' => 'Staff password reset requested.',
            'meta' => [
                'email' => $user->email,
                'role' => $user->role,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ],
        ]);

        return back()->with('status', __('passwords.sent'));
    }

    public function reset(Request $request, string $token)
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                if (! $user instanceof Users) {
                    return;
                }

                if (! in_array($user->role, ['admin', 'manager'], true)) {
                    abort(403);
                }

                DB::transaction(function () use ($user, $password, $request) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    AdminSecurityEvent::create([
                        'admin_user_id' => $user->id,
                        'type' => 'staff_password_reset_completed',
                        'status' => 'open',
                        'message' => 'Staff password reset completed.',
                        'meta' => [
                            'email' => $user->email,
                            'role' => $user->role,
                            'ip' => $request->ip(),
                            'user_agent' => substr((string) $request->userAgent(), 0, 500),
                        ],
                    ]);
                });

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => [__($status)],
            ])->withInput($request->only('email'));
        }

       return redirect()
    ->route('admin.login')
    ->with('success', 'Password reset successful. Please sign in again and verify your passkey.');
    }
}