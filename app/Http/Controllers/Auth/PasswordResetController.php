<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    // Show email input
    public function request()
    {
        return view('auth.forgot-password');
    }

    // Send reset email
    public function email(Request $request)
    {
        $request->validate([
            'email' => ['required','email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('ok', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    // Show reset form
    public function reset(string $token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    // Update password
    public function update(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email','password','password_confirmation','token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('login')->with('ok','Password set successfully.');
    }
}
