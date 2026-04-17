<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyOtpMail;
use App\Models\Users;
use App\Models\UserVerification;
use App\Support\AnalyticsLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\ClientWelcomeMail;
use App\Mail\CoachUnderReviewMail;

class VerificationController extends Controller
{
    public function show()
    {
        abort_unless(session()->has('verify_user_id'), 404);

        $user = Users::findOrFail(session('verify_user_id'));
        $purpose = session('verify_purpose', 'register');

        return view('auth.verify', compact('user', 'purpose'));
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $userId  = session('verify_user_id');
        $purpose = session('verify_purpose', 'register');

        abort_unless($userId, 404);

        $user = Users::findOrFail($userId);

        $record = UserVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->first();

        if (! $record) {
            AnalyticsLogger::log($request, 'otp_verify_failed', [
                'group'     => 'auth',
                'client_id' => $user->id,
                'meta'      => [
                    'purpose' => $purpose,
                    'reason'  => 'invalid_code',
                ],
            ]);

            return back()->withErrors([
                'code' => __('Invalid code'),
            ]);
        }

        if (now()->greaterThan($record->expires_at)) {
            AnalyticsLogger::log($request, 'otp_verify_failed', [
                'group'     => 'auth',
                'client_id' => $user->id,
                'meta'      => [
                    'purpose' => $purpose,
                    'reason'  => 'expired_code',
                ],
            ]);

            return back()->withErrors([
                'code' => __('Code Expired. Request a New One.'),
            ]);
        }

        $record->update([
            'used_at' => now(),
        ]);

        if ($purpose === 'register' && is_null($user->email_verified_at)) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            if ($user->role === 'client') {
                Mail::to($user->email)->send(new ClientWelcomeMail($user));
            }

            if (($user->is_coach ?? false) || $user->role === 'coach') {
                $coachProfile = $user->coachProfile;

                if ($coachProfile && in_array($coachProfile->application_status, ['submitted', 'under_review'], true)) {
                    Mail::to($user->email)->send(new CoachUnderReviewMail($user));
                }
            }
        }

        AnalyticsLogger::log($request, 'signup_verified', [
            'group'     => 'auth',
            'client_id' => $user->id,
            'meta'      => [
                'purpose'    => $purpose,
                'is_coach'   => (bool) ($user->is_coach ?? false),
                'user_role'  => $user->role,
                'email'      => $user->email,
            ],
        ]);

        if (($user->is_coach ?? false) || $user->role === 'coach') {
            AnalyticsLogger::log($request, 'coach_signup_verified', [
                'group'     => 'auth',
                'client_id' => $user->id,
                'meta'      => [
                    'purpose' => $purpose,
                    'email'   => $user->email,
                ],
            ]);
        } else {
            AnalyticsLogger::log($request, 'client_signup_verified', [
                'group'     => 'auth',
                'client_id' => $user->id,
                'meta'      => [
                    'purpose' => $purpose,
                    'email'   => $user->email,
                ],
            ]);
        }

        Auth::login($user);

        $mode = session('post_verify_mode', 'client');

        session()->forget([
            'verify_user_id',
            'verify_purpose',
            'post_verify_mode',
        ]);

        if ($mode === 'coach' && ($user->is_coach ?? false)) {
            session(['active_role' => 'coach']);

            $coachProfile = $user->coachProfile;

            if (! $coachProfile) {
                return redirect()
                    ->route('coach.application.show')
                    ->with('status', __('Email Verified. Please complete your coach application.'));
            }

            $status = strtolower((string) $coachProfile->application_status);

            if ($status === 'approved') {
                return redirect()
                    ->route('coach.payouts.settings')
                    ->with('status', __('Email Verified. Welcome!'));
            }

            if (in_array($status, ['submitted', 'under_review'], true)) {
                return redirect()
                    ->route('coach.application.review')
                    ->with('status', __('Email Verified. Your coach application is under review.'));
            }

            return redirect()
                ->route('coach.application.show')
                ->with('status', __('Email Verified. Please complete your coach application.'));
        }

        session(['active_role' => 'client']);

        return redirect()
            ->route('client.home')
            ->with('status', __('Email Verified. Welcome!'));
    }

    public function resend(Request $request)
    {
        $userId  = session('verify_user_id');
        $purpose = session('verify_purpose', 'register');

        abort_unless($userId, 404);

        $user = Users::findOrFail($userId);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        UserVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->delete();

        UserVerification::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new VerifyOtpMail($user, $code));

        AnalyticsLogger::log($request, 'otp_resent', [
            'group'     => 'auth',
            'client_id' => $user->id,
            'meta'      => [
                'purpose' => $purpose,
                'email'   => $user->email,
            ],
        ]);

        return back()->with('status', __('Check Your Email For a New Code'));
    }
}