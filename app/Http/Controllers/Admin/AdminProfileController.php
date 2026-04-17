<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use App\Models\StaffTeamMember;

class AdminProfileController extends Controller
{
   public function edit()
{
    $user = auth()->user();
    abort_unless(in_array($user->role, ['admin','manager'], true), 403);

    $activeTeamMember = StaffTeamMember::query()
        ->where('agent_id', $user->id)
        ->whereNull('end_at')
        ->with(['team.manager'])
        ->first();

    $myManager = $activeTeamMember?->team?->manager; // Users model
    $myTeam    = $activeTeamMember?->team;

    return view('admin.profile.edit', compact('user','myManager','myTeam'));
}

    public function update(Request $r)
    {
        $user = auth()->user();
        abort_unless(in_array($user->role, ['admin','manager'], true), 403);

        // ✅ hard lock gate (since you said: is_locked only)
        if ((int)($user->is_locked ?? 0) === 1) {
            return back()->with('error', 'Your account is locked. Please contact Super Admin.');
        }

        // We allow ONLY: avatar + password
        $data = $r->validate([
            'profile_pic' => ['nullable','file','mimes:jpg,jpeg,png,webp','max:5120'],
            'remove_avatar' => ['nullable','in:0,1'],

            'current_password' => ['nullable','string'],
            'new_password' => ['nullable','string', Password::min(8)->mixedCase()->numbers()],
            'new_password_confirmation' => ['nullable','same:new_password'],
        ]);

        // Determine what the user is trying to do
        $wantsPasswordChange = filled($r->new_password) || filled($r->current_password);
        $wantsAvatarChange   = $r->hasFile('profile_pic') || $r->input('remove_avatar') === '1';

        if (!$wantsPasswordChange && !$wantsAvatarChange) {
            return back()->with('error', 'No changes to save.');
        }

        // ✅ Password change flow (requires current password)
        if ($wantsPasswordChange) {
            if (!filled($r->current_password)) {
                return back()->withErrors(['current_password' => 'Current password is required.'])->withInput();
            }

            if (!Hash::check($r->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
            }

            if (!filled($r->new_password)) {
                return back()->withErrors(['new_password' => 'New password is required.'])->withInput();
            }

            $user->password = Hash::make($r->new_password);
        }

        // ✅ Avatar remove/replace
        if ($r->input('remove_avatar') === '1' && !$r->hasFile('profile_pic')) {
            if ($user->avatar_path) Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($r->hasFile('profile_pic')) {
            if ($user->avatar_path) Storage::disk('public')->delete($user->avatar_path);
            $path = $r->file('profile_pic')->store('staff/avatars', 'public');
            $user->avatar_path = $path;
        }

        $user->save();

        return back()->with('ok', 'Profile Updated.');
    }
}
