<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CoachApprovedMail;
use App\Mail\CoachRejectedMail;
use App\Services\AdminSecurity\AdminRiskService;

class CoachController extends Controller
{
    public function index(Request $r)
    {
        $q      = trim((string) $r->input('q'));
        $status = $r->input('status', 'all'); // all|active|pending|rejected|deleted
        $per    = (int) $r->input('per', 10);

        if (!in_array($per, [10,20,50,100], true)) {
            $per = 10;
        }

        $base = Users::query()
            ->where('is_coach', 1)
            ->with('coachProfile');

        if ($status === 'deleted') {
            $query = Users::onlyTrashed()
                ->where('is_coach', 1)
                ->with('coachProfile');
        } else {
            $query = clone $base;
        }

        $coaches = $query
            ->when($q, function ($x) use ($q) {
                $x->where(function ($y) use ($q) {
                    $y->where('first_name', 'like', "%$q%")
                      ->orWhere('last_name', 'like', "%$q%")
                      ->orWhere('email', 'like', "%$q%")
                      ->orWhere('phone', 'like', "%$q%");
                });
            })
            ->when($status !== 'all' && $status !== 'deleted', function ($x) use ($status) {
                return match ($status) {
                    'active'   => $x->whereHas('coachProfile', fn ($q) => $q->where('application_status', 'approved')),
                    'pending'  => $x->whereHas('coachProfile', fn ($q) => $q->where('application_status', 'submitted')),
                    'rejected' => $x->whereHas('coachProfile', fn ($q) => $q->where('application_status', 'rejected')),
                    default    => $x,
                };
            })
            ->orderBy('first_name')
            ->paginate($per)
            ->appends([
                'q'      => $q,
                'status' => $status,
                'per'    => $per,
            ]);

        $counts = [
            'all'      => (clone $base)->count(),
            'active'   => Users::where('is_coach', 1)->whereHas('coachProfile', fn ($q) => $q->where('application_status', 'approved'))->count(),
            'pending'  => Users::where('is_coach', 1)->whereHas('coachProfile', fn ($q) => $q->where('application_status', 'submitted'))->count(),
            'rejected' => Users::where('is_coach', 1)->whereHas('coachProfile', fn ($q) => $q->where('application_status', 'rejected'))->count(),
            'deleted'  => Users::onlyTrashed()->where('is_coach', 1)->count(),
        ];

        $deactCount = 0;

        return view('admin.coaches.index', compact(
            'coaches',
            'q',
            'status',
            'counts',
            'deactCount',
            'per'
        ));
    }

    public function approve(Users $user)
    {
        abort_unless(($user->is_coach ?? false) == true, 404);

        $coachProfile = $user->coachProfile;
        abort_unless($coachProfile, 404, 'Coach application not found.');

        $wasApproved = ((string) $coachProfile->application_status === 'approved');

        DB::transaction(function () use ($user, $coachProfile) {
            $user->forceFill([
                'is_coach' => 1,
                'role'     => 'client',
            ])->save();

            $coachProfile->forceFill([
                'application_status'   => 'approved',
                'review_started_at'    => $coachProfile->review_started_at ?? now(),
                'approved_at'          => now(),
                'rejected_at'          => null,
                'review_notes'         => null,
                'rejection_reason'     => null,
                'can_accept_bookings'  => true,
                'can_receive_payouts'  => false, // becomes true after Stripe onboarding
            ])->save();
        });

        if (!$wasApproved) {
            Mail::to($user->email)->send(new CoachApprovedMail($user));
        }

        return back()->with('ok', 'Coach approved.');
    }

    public function reject(Request $request, Users $user)
    {
        abort_unless(($user->is_coach ?? false) == true, 404);

        $coachProfile = $user->coachProfile;
        abort_unless($coachProfile, 404, 'Coach application not found.');

        $wasRejected = ((string) $coachProfile->application_status === 'rejected');

        DB::transaction(function () use ($request, $user, $coachProfile) {
            $user->forceFill([
                'role' => 'client',
            ])->save();

            $coachProfile->forceFill([
                'application_status'   => 'rejected',
                'review_started_at'    => $coachProfile->review_started_at ?? now(),
                'approved_at'          => null,
                'rejected_at'          => now(),
                'review_notes'         => null,
                'rejection_reason'     => $request->input('rejection_reason'),
                'can_accept_bookings'  => false,
                'can_receive_payouts'  => false,
            ])->save();
        });

        if (!$wasRejected) {
            Mail::to($user->email)->send(new CoachRejectedMail($user));
        }

        return back()->with('ok', 'Coach rejected.');
    }

    public function destroy(Users $user, AdminRiskService $risk)
    {
        abort_unless(($user->is_coach ?? false) == true, 404);

        DB::transaction(function () use ($user, $risk) {
            $user->delete();

            $adminId = (int) auth()->id();
            $risk->recordUserDeletion($adminId, (int) $user->id, 'coach');
        });

        return back()->with('ok', 'Coach removed.');
    }

    public function restore($id, AdminRiskService $risk)
    {
        $user = Users::onlyTrashed()->with('coachProfile')->findOrFail($id);

        abort_unless(($user->is_coach ?? false) == true, 404);

        DB::transaction(function () use ($user, $risk) {
            $user->restore();
            $risk->recordUserRestore(auth()->id(), $user);
        });

        return back()->with('ok', 'Coach restored successfully.');
    }

    public function show($id)
    {
        $user = Users::withTrashed()
            ->with(['coachProfile.documents'])
            ->findOrFail($id);

        abort_unless(($user->is_coach ?? false) == true, 404, 'Coach profile not found.');

        return view('admin.coaches.show', compact('user'));
    }
}