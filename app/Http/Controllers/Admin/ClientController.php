<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AdminSecurity\AdminRiskService;


class ClientController extends Controller
{
    public function index(Request $r)
{
    $q      = trim((string) $r->input('q'));
    $status = $r->input('status', 'all'); // all|deleted
    $per    = (int) $r->input('per', 10);
    if (!in_array($per, [10,20,50,100], true)) $per = 10;

    $base = Users::query()->where('role', 'client'); // non-deleted only

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

    return view('admin.clients.index', compact('customers','q','status','counts','per'));
}


    public function show($id)
{
    $user = Users::withTrashed()->findOrFail($id);
    abort_unless($user->role === 'client', 404);

    return view('admin.clients.show', [
        'customer' => $user,
    ]);
}


    public function destroy(Users $user, AdminRiskService $risk)
{
    abort_unless($user->role === 'client', 404);

    DB::transaction(function () use ($user, $risk) {
        $user->delete();

        // ✅ log + threshold check (3 in 3 min / 10 in 24h)
        $adminId = (int) auth()->id();
        $risk->recordUserDeletion($adminId, (int) $user->id, 'client');
    });

    return back()->with('ok', 'Customer Removed.');
}


   public function restore($id, AdminRiskService $risk)
{
    $user = Users::onlyTrashed()->findOrFail($id);

    // ✅ Safety: only clients can be restored here
    abort_unless($user->role === 'client', 404);

    DB::transaction(function () use ($user, $risk) {
        // restore user
        $user->restore();

        // ✅ LOG RESTORE ACTION
        $risk->recordUserRestore(auth()->id(), $user);
    });

    return back()->with('ok', 'Customer restored successfully.');
}

}
