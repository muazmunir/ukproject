<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Admin\ClientController as AdminClientController;
use Illuminate\Http\Request;
use App\Models\Users;

class SuperClientController extends AdminClientController
{
    public function index(Request $r)
    {
        $q      = trim((string) $r->input('q'));
        $status = $r->input('status', 'all'); // all|deleted
        $per    = (int) $r->input('per', 10);
        if (!in_array($per, [10,20,50,100], true)) $per = 10;

        $base = Users::query()->where('role', 'client');

        $query = ($status === 'deleted')
            ? Users::onlyTrashed()->where('role', 'client')
            : clone $base;

        $customers = $query
            ->when($q, function ($x) use ($q) {
                $x->where(function ($y) use ($q) {
                    $y->where('first_name','like',"%$q%")
                      ->orWhere('last_name','like',"%$q%")
                      ->orWhere('email','like',"%$q%")
                      ->orWhere('phone','like',"%$q%");
                });
            })
            ->orderBy('first_name')
            ->paginate($per)
            ->appends(['q'=>$q,'status'=>$status,'per'=>$per]);

        $counts = [
            'all'     => (clone $base)->count(),
            'deleted' => Users::onlyTrashed()->where('role','client')->count(),
        ];

        // ✅ superadmin view
        return view('superadmin.clients.index', compact('customers','q','status','counts','per'));
    }

    public function show($id)
    {
        $user = Users::withTrashed()->findOrFail($id);
        abort_unless($user->role === 'client', 404);

        // ✅ superadmin view
        return view('superadmin.clients.show', [
            'customer' => $user,
        ]);
    }
}
