<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyOtpMail;
use App\Models\Users;
use App\Models\UserVerification;
use App\Support\AnalyticsLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['client', 'coach'])],

            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'username'   => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'dob'        => ['nullable', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'country'    => ['nullable', 'string', 'max:120'],
            'city'       => ['nullable', 'string', 'max:120'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'timezone'   => ['nullable', 'string', 'max:120'],
            'password'   => ['required', 'confirmed', 'min:8'],
            'accept'     => ['accepted'],
        ]);

        $wantsCoach = ($data['role'] === 'coach');

        $user = DB::transaction(function () use ($data, $wantsCoach) {
            $user = Users::create([
                'role'       => 'client',
                'is_coach'   => $wantsCoach ? 1 : 0,

                // legacy compatibility
                'coach_kyc_submitted'       => 0,
                'coach_verification_status' => $wantsCoach ? 'draft' : null,

                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'username'   => $data['username'] ?? null,
                'dob'        => $data['dob'] ?? null,
                'email'      => $data['email'],
                'country'    => $data['country'] ?? null,
                'city'       => $data['city'] ?? null,
                'phone_code' => $data['phone_code'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'timezone'   => $data['timezone'] ?? null,
                'password'   => Hash::make($data['password']),
                'onboarding_completed' => true,
            ]);

            if ($wantsCoach) {
                $user->coachProfile()->create([
                    'application_status'  => 'draft',
                    'can_accept_bookings' => false,
                    'can_receive_payouts' => false,
                ]);
            }

            UserVerification::where('user_id', $user->id)
                ->where('purpose', 'register')
                ->delete();

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            UserVerification::create([
                'user_id'    => $user->id,
                'code'       => $code,
                'purpose'    => 'register',
                'expires_at' => now()->addMinutes(10),
            ]);

            Mail::to($user->email)->send(new VerifyOtpMail($user, $code));

            return $user;
        });

        AnalyticsLogger::log($request, 'signup_created', [
            'group'     => 'auth',
            'client_id' => $user->id,
            'meta'      => [
                'signup_role' => $wantsCoach ? 'coach' : 'client',
                'email'       => $user->email,
                'country'     => $user->country,
                'city'        => $user->city,
                'is_coach'    => (bool) $user->is_coach,
            ],
        ]);

        if ($wantsCoach) {
            AnalyticsLogger::log($request, 'coach_signup_created', [
                'group'     => 'auth',
                'client_id' => $user->id,
                'meta'      => [
                    'signup_role' => 'coach',
                    'email'       => $user->email,
                ],
            ]);
        } else {
            AnalyticsLogger::log($request, 'client_signup_created', [
                'group'     => 'auth',
                'client_id' => $user->id,
                'meta'      => [
                    'signup_role' => 'client',
                    'email'       => $user->email,
                ],
            ]);
        }

        session([
            'verify_user_id'   => $user->id,
            'verify_purpose'   => 'register',
            'post_verify_mode' => $wantsCoach ? 'coach' : 'client',
        ]);

        return redirect()->route('auth.verify.show');
    }
}