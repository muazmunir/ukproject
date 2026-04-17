<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Admin\CoachController as AdminCoachController;
use Illuminate\Http\Request;
use App\Models\Users;

class SuperCoachController extends AdminCoachController
{
    public function index(Request $r)
    {
        // ✅ run the SAME query logic, but return superadmin view
        $q      = trim((string) $r->input('q'));
        $status = $r->input('status', 'all');
        $per    = (int) $r->input('per', 10);
        if (!in_array($per, [10,20,50,100], true)) $per = 10;

        $base = Users::query()->where('is_coach', 1);

        if ($status === 'deleted') {
            // NOTE: your admin code uses role='coach' here; keep consistent with your system
            $query = Users::onlyTrashed()->where('role', 'coach');
        } else {
            $query = clone $base;
        }

        $coaches = $query
            ->when($q, function ($x) use ($q) {
                $x->where(function ($y) use ($q) {
                    $y->where('first_name','like',"%$q%")
                      ->orWhere('last_name','like',"%$q%")
                      ->orWhere('email','like',"%$q%")
                      ->orWhere('phone','like',"%$q%");
                });
            })
            ->when($status !== 'all' && $status !== 'deleted', function ($x) use ($status) {
                return match ($status) {
                    'active'   => $x->where('coach_verification_status', 'approved'),
                    'pending'  => $x->where('coach_verification_status', 'pending'),
                    'rejected' => $x->where('coach_verification_status', 'rejected'),
                    default    => $x,
                };
            })
            ->orderByDesc('is_approved')
            ->orderBy('first_name')
            ->paginate($per)
            ->appends(['q'=>$q,'status'=>$status,'per'=>$per]);

        $counts = [
            'all'      => (clone $base)->count(),
            'active'   => (clone $base)->where('coach_verification_status', 'approved')->count(),
            'pending'  => (clone $base)->where('coach_verification_status', 'pending')->count(),
            'rejected' => (clone $base)->where('coach_verification_status', 'rejected')->count(),
            'deleted'  => Users::onlyTrashed()->where('is_coach', 1)->count(),
        ];

        $deactCount = 0;

        // ✅ IMPORTANT: superadmin view
        return view('superadmin.coaches.index', compact(
            'coaches','q','status','counts','deactCount','per'
        ));
    }

    public function show($id)
    {
        $user = Users::withTrashed()->findOrFail($id);

        abort_unless(($user->is_coach ?? false) == true, 404, 'Coach profile not found.');

        // ✅ IMPORTANT: superadmin view
        return view('superadmin.coaches.show', compact('user'));
    }
}
