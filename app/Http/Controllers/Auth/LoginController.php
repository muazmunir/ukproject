<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserVerification;
use App\Mail\VerifyOtpMail;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Users; // or App\Models\User depending on your app
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    

public function authenticate(Request $request)
{
    $credentials = $request->validate([
        'email'    => ['required','email'],
        'password' => ['required'],
    ]);

    $email = $credentials['email'];
    $pass  = $credentials['password'];

    // include soft-deleted users
    $existing = Users::withTrashed()->where('email', $email)->first();

    // 1) soft-deleted => block
    if ($existing && $existing->trashed()) {
        return back()
            ->withErrors([
                'email' => __('Your Account Has Been Deactivated. Please Contact Support If You Believe This Is a Mistake.'),
            ])
            ->onlyInput('email');
    }

    // 2) google-linked => block password login
    

    // 3) invalid credentials / user not found
    if (!$existing || !Hash::check($pass, $existing->password)) {
        return back()->withErrors([
            'email' => __('The Provided Credentials Do Not Match Our Records.'),
        ])->onlyInput('email');
    }

    // OPTIONAL: if you require email verification first (register verification)
    if (is_null($existing->email_verified_at)) {
        // send them to REGISTER verification flow
        session([
            'verify_user_id' => $existing->id,
            'verify_purpose' => 'register',
        ]);

        return redirect()->route('auth.verify.show')
            ->with('status', __('Please Verify Your Email First.'));
    }

    // ✅ SEND OTP FOR LOGIN (every time)
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // IMPORTANT: delete only LOGIN codes (not register)
    UserVerification::where('user_id', $existing->id)
        ->where('purpose', 'login')
        ->delete();

    UserVerification::create([
        'user_id'    => $existing->id,
        'code'       => $code,
        'purpose'    => 'login',
        'expires_at' => now()->addMinutes(10),
        'used_at'    => null,
    ]);

    Mail::to($existing->email)->send(new VerifyOtpMail($existing, $code));

    session([
        'verify_user_id' => $existing->id,
        'verify_purpose' => 'login',
    ]);

    // do NOT Auth::login here
    return redirect()->route('auth.verify.show');
        
}


    // add checkbox later if you want

   

    // ===== HERE: handle soft-deleted + google cases =====

    // include soft-deleted users in this lookup
   


    public function showAdmin()
    {
        return view('admin.auth.login'); // separate blade for admin look
    }

    /** ADMIN: handle login */
public function loginAdmin(Request $request)
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $email = strtolower(trim($credentials['email']));

    $user = Users::withTrashed()
        ->where('email', $email)
        ->first();

    if (! $user) {
        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ])->onlyInput('email');
    }

    if ($user->trashed()) {
        return back()->withErrors([
            'email' => 'This account is deactivated.',
        ])->onlyInput('email');
    }

    if (! in_array($user->role, ['admin', 'manager'], true)) {
        return back()->withErrors([
            'email' => 'You are not allowed to access the admin panel.',
        ])->onlyInput('email');
    }

    if (! (bool) ($user->is_active ?? true)) {
        return back()->withErrors([
            'email' => 'Your account is disabled.',
        ])->onlyInput('email');
    }

    if (! Hash::check($credentials['password'], $user->password)) {
        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ])->onlyInput('email');
    }

    Auth::login($user, $request->boolean('remember'));

    $request->session()->regenerate();

    $request->session()->put('panel', 'admin');
    $request->session()->put('staff_passkey_verified', false);
    $request->session()->forget('active_role');

    $hasPasskeys = false;

    try {
        if (method_exists($user, 'webauthnCredentials')) {
            $hasPasskeys = $user->webauthnCredentials()->exists();
        } elseif (method_exists($user, 'webAuthnCredentials')) {
            $hasPasskeys = $user->webAuthnCredentials()->exists();
        } elseif (method_exists($user, 'passkeys')) {
            $hasPasskeys = $user->passkeys()->exists();
        }
    } catch (\Throwable $e) {
        \Log::error('Admin login passkey existence check failed', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'message' => $e->getMessage(),
        ]);

        $hasPasskeys = false;
    }

    return redirect()->route(
        $hasPasskeys
            ? 'admin.webauthn.verify'
            : 'admin.webauthn.register'
    );
}
    /** ADMIN: logout */
   /** ADMIN: logout (same web guard) */
public function logoutAdmin(Request $r)
{
    Auth::logout();

    // remove admin panel marker
    $r->session()->forget('panel');

    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return redirect()->route('admin.login');
}

    


        // ---------------------------
    // GOOGLE LOGIN: redirect
    // ---------------------------
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    // ---------------------------
    // GOOGLE LOGIN: callback
    // ---------------------------
   public function handleGoogleCallback()
{
    try {
        $googleUser = Socialite::driver('google')->stateless()->user();
    } catch (\Exception $e) {
        return redirect()->route('login')
            ->withErrors(['email' => 'Unable To Login With Google. Please Try Again.']);
    }

    $googleId    = $googleUser->getId();
    $email       = $googleUser->getEmail();
    $name        = $googleUser->getName();
    $avatar      = $googleUser->getAvatar();
    $accessToken = $googleUser->token;

    // IMPORTANT: include soft-deleted users
    $user = Users::withTrashed()->where('google_id', $googleId)->first();

    if (!$user && $email) {
        $user = Users::withTrashed()->where('email', $email)->first();
    }

    // 🚫 If user exists but is soft-deleted -> block login, do NOT recreate
    if ($user && $user->trashed()) {
        return redirect()->route('login')
            ->withErrors([
                'email' => __(
                    'Your Account Has Been Deactivated. Please Contact Support If You Want To Restore Access.'
                ),
            ]);
    }

    // 3) If still not found -> create NEW user (onboarding incomplete)
    if (!$user) {
        $firstName = $name;
        $lastName  = null;

        if ($name && strpos($name, ' ') !== false) {
            [$firstName, $lastName] = explode(' ', $name, 2);
        }

        $user = Users::create([
            'first_name'           => $firstName,
            'last_name'            => $lastName,
            'email'                => $email,
            'role'                 => 'client',
            'password'             => bcrypt(Str::random(16)),
            'google_id'            => $googleId,
            // 'google_token'         => $accessToken,
            'google_avatar'        => $avatar,
            'email_verified_at'    => now(),
            'onboarding_completed' => false,
        ]);
    } else {
        // 4) Existing user -> keep role etc.
        $user->update([
            'google_id'     => $googleId,
            // 'google_token'  => $accessToken,
            'google_avatar' => $avatar,
        ]);

        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
        }
    }

    // 5) Log them in
   Auth::login($user, true);

// Set acting role based on coach status
$acting = ($user->role === 'coach' || ($user->is_coach ?? false)) ? 'coach' : 'client';
session(['active_role' => $acting]);

if (!$user->onboarding_completed) {
    return redirect()->route('onboarding.show');
}

if ($acting === 'coach') {
    return redirect()->intended(route('coach.home'));
}

return redirect()->intended(route('client.home'));

}



}
