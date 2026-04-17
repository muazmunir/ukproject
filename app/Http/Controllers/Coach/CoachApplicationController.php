<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachVerificationDocument;
use App\Support\AnalyticsLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoachApplicationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $wasCoachAccount = (bool) ($user->is_coach ?? false);

        // Upgrade a normal client into a coach applicant
        if (! $wasCoachAccount) {
            $user->forceFill([
                'is_coach' => 1,
                'coach_kyc_submitted' => 0,
                'coach_verification_status' => 'draft',
            ])->save();
        }

        $coachProfile = $user->coachProfile;

        if (! $coachProfile) {
            $coachProfile = $user->coachProfile()->create([
                'application_status'   => 'draft',
                'can_accept_bookings'  => false,
                'can_receive_payouts'  => false,
            ]);
        }

        session(['active_role' => 'coach']);

        AnalyticsLogger::log($request, 'coach_application_page_view', [
            'group'   => 'coach_application',
            'user_id' => $user->id,
            'meta'    => [
                'application_status' => (string) ($coachProfile->application_status ?? 'draft'),
                'is_coach'           => (bool) ($user->is_coach ?? false),
                'entry_mode'         => $wasCoachAccount ? 'existing_coach' : 'client_upgrade',
            ],
        ]);

        if ($coachProfile->application_status === 'approved') {
            return redirect()->route('coach.payouts.settings');
        }

        return view('coach.application', compact('coachProfile'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Ensure client can become coach applicant here too
        if (! ($user->is_coach ?? false)) {
            $user->forceFill([
                'is_coach' => 1,
                'coach_kyc_submitted' => 0,
                'coach_verification_status' => 'draft',
            ])->save();
        }

        $coachProfile = $user->coachProfile;

        if (! $coachProfile) {
            $coachProfile = $user->coachProfile()->create([
                'application_status'   => 'draft',
                'can_accept_bookings'  => false,
                'can_receive_payouts'  => false,
            ]);
        }

        if ($coachProfile->application_status === 'approved') {
            AnalyticsLogger::log($request, 'coach_application_blocked', [
                'group'   => 'coach_application',
                'user_id' => $user->id,
                'meta'    => [
                    'reason'             => 'already_approved',
                    'application_status' => (string) $coachProfile->application_status,
                ],
            ]);

            return back()->with('ok', __('Your coach application is already approved.'));
        }

        $rules = [
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'id_type'       => ['required', Rule::in(['passport', 'driving_license'])],
        ];

        if ($request->id_type === 'passport') {
            $rules['passport_image'] = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        }

        if ($request->id_type === 'driving_license') {
            $rules['dl_front'] = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
            $rules['dl_back']  = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $user, $coachProfile, $validated) {
            $baseDir = "kyc/coaches/{$coachProfile->user_id}";

            $coachProfile->documents()
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            $this->storeDocument(
                $coachProfile->id,
                'profile_photo',
                $request->file('profile_photo'),
                $baseDir
            );

            if ($validated['id_type'] === 'passport') {
                $this->storeDocument(
                    $coachProfile->id,
                    'passport',
                    $request->file('passport_image'),
                    $baseDir
                );
            }

            if ($validated['id_type'] === 'driving_license') {
                $this->storeDocument(
                    $coachProfile->id,
                    'driving_license_front',
                    $request->file('dl_front'),
                    $baseDir
                );

                $this->storeDocument(
                    $coachProfile->id,
                    'driving_license_back',
                    $request->file('dl_back'),
                    $baseDir
                );
            }

            $coachProfile->forceFill([
                'application_status'   => 'submitted',
                'applied_at'           => $coachProfile->applied_at ?? now(),
                'review_started_at'    => null,
                'approved_at'          => null,
                'rejected_at'          => null,
                'review_notes'         => null,
                'rejection_reason'     => null,
                'can_accept_bookings'  => false,
                'can_receive_payouts'  => false,
            ])->save();

            $user->forceFill([
                'coach_kyc_submitted'       => 1,
                'coach_verification_status' => 'pending',
            ])->save();

            AnalyticsLogger::log($request, 'coach_application_submitted', [
                'group'   => 'coach_application',
                'user_id' => $user->id,
                'meta'    => [
                    'coach_profile_id'    => (int) $coachProfile->id,
                    'application_status'  => 'submitted',
                    'id_type'             => (string) $validated['id_type'],
                    'documents_uploaded'  => $validated['id_type'] === 'passport'
                        ? ['profile_photo', 'passport']
                        : ['profile_photo', 'driving_license_front', 'driving_license_back'],
                ],
            ]);
        });

        session(['active_role' => 'coach']);

        return redirect()
            ->route('coach.application.review')
            ->with('ok', __('Your Application Was Submitted And Is Under Review.'));
    }

    public function review(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! ($user->is_coach ?? false)) {
            return redirect()
                ->route('coach.application.show')
                ->with('error', __('Please start your coach application first.'));
        }

        $coachProfile = $user->coachProfile;

        if (! $coachProfile) {
            return redirect()->route('coach.application.show');
        }

        session(['active_role' => 'coach']);

        AnalyticsLogger::log($request, 'coach_application_review_view', [
            'group'   => 'coach_application',
            'user_id' => $user->id,
            'meta'    => [
                'application_status' => (string) ($coachProfile->application_status ?? 'draft'),
                'coach_profile_id'   => (int) $coachProfile->id,
            ],
        ]);

        if ($coachProfile->application_status === 'approved') {
            return redirect()->route('coach.payouts.settings');
        }

        return view('coach.application-review', compact('coachProfile'));
    }

    private function storeDocument(int $coachProfileId, string $type, $file, string $baseDir): void
    {
        $path = $file->store($baseDir, 'public');

        CoachVerificationDocument::create([
            'coach_profile_id' => $coachProfileId,
            'document_type'    => $type,
            'storage_disk'     => 'public',
            'storage_path'     => $path,
            'mime_type'        => $file->getClientMimeType(),
            'size_bytes'       => $file->getSize(),
            'status'           => 'active',
        ]);
    }
}