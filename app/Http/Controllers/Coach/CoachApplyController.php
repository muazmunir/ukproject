<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoachApplyController extends Controller
{
 public function show(Request $request)
{
    $user = $request->user();

    $submitted = (bool) ($user->coach_kyc_submitted ?? false);
    $status    = (string) ($user->coach_verification_status ?? 'draft');

    // If already submitted and not rejected -> block apply completely
    if (($user->is_coach ?? false) && $submitted && $status !== 'rejected') {
        session(['active_role' => 'coach']);
        return redirect()->route('coach.home');
    }

    // If coach already started but not submitted OR rejected -> go KYC
    if (($user->is_coach ?? false)) {
        session(['active_role' => 'coach']);
        return redirect()->route('coach.kyc.show');
    }

    // Not a coach yet -> show apply/start page
    return view('coach.apply'); // ideally this should be a "Start coach" page
}


    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->is_coach) {
            $user->forceFill([
                'is_coach' => 1,
                'coach_kyc_submitted' => 0,
                'coach_verification_status' => 'draft',
            ])->save();
        }

        session(['active_role' => 'coach']);
        return redirect()->route('coach.kyc.show');
    }
}

