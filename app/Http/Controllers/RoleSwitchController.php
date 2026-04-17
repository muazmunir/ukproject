<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoleSwitchController extends Controller
{
    /**
     * POST /role/switch
     * body: role=client|coach
     */
    public function switch(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return redirect()->route('login');
    }

    $user->loadMissing('coachProfile');

    $role = strtolower(trim((string) $request->input('role', 'client')));

    if (in_array(strtolower((string)($user->role ?? '')), ['admin','manager','super_admin'], true)) {
        session(['panel' => 'admin']);
        session()->forget('active_role');

        return redirect()->route('admin.dashboard');
    }

    if (!in_array($role, ['client', 'coach'], true)) {
        return back()->with('error', 'Invalid role.');
    }

    if ($role === 'client') {
        session(['active_role' => 'client']);
        session()->forget('panel');
        $request->session()->migrate(true);

        return redirect()->route('client.home')
            ->with('success', 'Switched to Client mode.');
    }

    if ($role === 'coach') {
        if (!($user->is_coach ?? false)) {
            session(['active_role' => 'client']);
            $request->session()->migrate(true);

            return redirect()->route('coach.apply');
        }

        $coachProfile = $user->coachProfile;

        if (!$coachProfile) {
            session(['active_role' => 'client']);
            $request->session()->migrate(true);

            return redirect()->route('coach.application.show')
                ->with('success', 'Continue coach onboarding.');
        }

        $status = (string) ($coachProfile->application_status ?? 'draft');

        if ($status === 'approved') {
            session(['active_role' => 'coach']);
            $request->session()->migrate(true);

            return redirect()->route('coach.home')
                ->with('success', 'Switched to Coach mode.');
        }

        session(['active_role' => 'client']);
        $request->session()->migrate(true);

        if ($status === 'submitted') {
            return redirect()->route('coach.application.review')
                ->with('success', 'Your coach application is under review.');
        }

        if ($status === 'rejected') {
            return redirect()->route('coach.application.show')
                ->with('error', 'Your previous application was rejected. Please resubmit.');
        }

        return redirect()->route('coach.application.show')
            ->with('success', 'Continue coach onboarding.');
    }

    return redirect()->route('client.home');
}
}
