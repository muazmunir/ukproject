<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->onboarding_completed) {
            return redirect()->route('dashboard');
        }

        $role = $user->role;
        return view('auth.onboarding', compact('user', 'role'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'username'     => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'dob'          => ['required', 'date'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'country'      => ['required', 'string', 'max:255'],
            'city'         => ['required', 'string', 'max:255'],
            'phone_code'   => ['nullable', 'string', 'max:10'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'role'         => ['required', 'in:client,coach'],
        ]);

        $user->fill($data);

        // ✅ keep is_coach synced with role
        $user->is_coach = ($data['role'] === 'coach') ? 1 : 0;

        $user->onboarding_completed = true;
        $user->save();

        // ✅ default to client mode after onboarding
        session(['active_role' => 'client']);

        return redirect()->route('client.home')->with('ok', 'Welcome! Your Profile Is Ready.');
        // or: return redirect()->route('dashboard')->with('ok', 'Welcome! Your Profile Is Ready.');
    }
}
