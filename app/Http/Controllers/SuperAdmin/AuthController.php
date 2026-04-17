<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('superadmin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Only allow login for superadmins
        $credentials = $request->only('email', 'password');
        $credentials['role'] = 'superadmin';

        if (Auth::attempt($credentials, $request->remember)) {
            return redirect()->route('superadmin.dashboard')
                ->with('success', 'Welcome Super Admin!');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials or unauthorized account.',
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('superadmin.login')->with('success', 'Logged out');
    }
}
