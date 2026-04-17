<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffInvite;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StaffInviteController extends Controller
{
    public function show(string $token)
    {
        $invite = StaffInvite::with('user')->where('token', $token)->firstOrFail();

        // token invalid states
        if ($invite->isUsed()) {
            return view('auth.staff-invite-message', [
                'title' => 'Invite already used',
                'message' => 'This invite link has already been used. Please login.',
            ]);
        }

        if ($invite->isExpired()) {
            return view('auth.staff-invite-message', [
                'title' => 'Invite expired',
                'message' => 'This invite link has expired. Please contact the Super Admin to resend an invite.',
            ]);
        }

        $user = $invite->user;

        // safety: only admin/manager allowed
        if (!in_array($user->role, ['admin','manager'], true)) {
            abort(403);
        }

        // optional: disabled staff can't accept invite
        if ((int)($user->is_active ?? 1) !== 1) {
            return view('auth.staff-invite-message', [
                'title' => 'Account disabled',
                'message' => 'Your staff account is currently disabled.',
            ]);
        }

        return view('auth.staff-invite-set-password', compact('invite','user'));
    }

    public function store(Request $request, string $token)
    {
        $invite = StaffInvite::with('user')->where('token', $token)->firstOrFail();

        if ($invite->isUsed()) {
            return redirect()->route('login')->with('ok', 'Invite already used. Please login.');
        }

        if ($invite->isExpired()) {
            return redirect()->route('login')->withErrors(['email' => 'Invite expired. Contact Super Admin.']);
        }

        $user = $invite->user;

        if (!in_array($user->role, ['admin','manager'], true)) abort(403);

        $request->validate([
            'password' => ['required','min:8','confirmed'],
        ]);

        // Set password, mark invite used, login
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        $invite->forceFill([
            'used_at' => now(),
        ])->save();

        Auth::login($user);

        return redirect('/admin')->with('ok', 'Password set successfully. Welcome!');
    }
}
