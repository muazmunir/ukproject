<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoachApplicationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if (!($user->is_coach ?? false)) {
            return redirect()->route('coach.apply');
        }

        $coachProfile = $user->coachProfile;

        if (!$coachProfile) {
            $coachProfile = $user->coachProfile()->create([
                'application_status'  => 'draft',
                'can_accept_bookings' => false,
                'can_receive_payouts' => false,
            ]);
        }

        if ($coachProfile->application_status === 'submitted') {
            return redirect()->route('coach.application.review');
        }

        if ($coachProfile->application_status === 'approved') {
            return redirect()->route('coach.home');
        }

        return view('coach.application', compact('coachProfile'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!($user->is_coach ?? false)) {
            return redirect()->route('coach.apply');
        }

        $coachProfile = $user->coachProfile;

        if (!$coachProfile) {
            $coachProfile = $user->coachProfile()->create([
                'application_status'  => 'draft',
                'can_accept_bookings' => false,
                'can_receive_payouts' => false,
            ]);
        }

        if ($coachProfile->application_status === 'approved') {
            return back()->with('ok', __('You are already approved as a coach.'));
        }

        $rules = [
            'profile_photo' => ['required','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'id_type'       => ['required', Rule::in(['passport','driving_license'])],
        ];

        if ($request->id_type === 'passport') {
            $rules['passport_image'] = ['required','image','mimes:jpg,jpeg,png,webp','max:4096'];
        }

        if ($request->id_type === 'driving_license') {
            $rules['dl_front'] = ['required','image','mimes:jpg,jpeg,png,webp','max:4096'];
            $rules['dl_back']  = ['required','image','mimes:jpg,jpeg,png,webp','max:4096'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $user, $coachProfile, $validated) {
            $baseDir = "kyc/coaches/{$user->id}";

            // mark old active docs as replaced
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
                'application_status'  => 'submitted',
                'applied_at'          => $coachProfile->applied_at ?? now(),
                'review_started_at'   => null,
                'approved_at'         => null,
                'rejected_at'         => null,
                'review_notes'        => null,
                'rejection_reason'    => null,
                'can_accept_bookings' => false,
            ])->save();
        });

        session(['active_role' => 'coach']);

        return redirect()->route('coach.application.review')
            ->with('ok', __('Your documents were submitted and are under review.'));
    }

    public function review(Request $request)
    {
        $user = $request->user();
        $coachProfile = $user->coachProfile;

        if (!$coachProfile) {
            return redirect()->route('coach.application.show');
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