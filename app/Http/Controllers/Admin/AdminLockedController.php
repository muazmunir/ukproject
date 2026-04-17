<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminLockedController extends Controller
{
    public function notice()
    {
        return view('admin.locked');
    }
}
