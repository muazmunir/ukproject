<?php
// app/Http/Controllers/SuperAdmin/AdminController.php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Users;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Users::where('role', 'admin')->paginate(10);
        return view('superadmin.admins.index', compact('admins'));
    }

    public function lock(Users $admin)
    {
        $admin->update(['is_locked' => true]);

        return back()->with('success', 'Admin account locked.');
    }

    public function unlock(Users $admin)
    {
        $admin->update(['is_locked' => false]);

        return back()->with('success', 'Admin account unlocked.');
    }
}
