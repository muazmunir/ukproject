<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationReview;
use App\Services\ReservationUiService;
use App\Services\UserRoleRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationReviewController extends Controller
{
    public function store(
        Request $request,
        Reservation $reservation,
        ReservationUiService $ui,
        UserRoleRatingService $ratingService
    ) {
        $coachId = (int) optional($reservation->service)->coach_id;
        abort_unless($coachId === (int) $request->user()->id, 403);

        $data = $request->validate([
            'stars'       => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $reservation->loadMissing([
            'service',
            'client',
            'slots',
            'coachReview',
            'dispute',
        ]);

        $flags = $ui->postSessionFlags($reservation, $request->user()->timezone);

        $settlement = strtolower((string) ($reservation->settlement_status ?? ''));
        $status     = strtolower((string) ($reservation->status ?? ''));

        $reviewBlockedByRefundOrDispute = in_array($settlement, [
            'refund_pending',
            'refunded',
            'refunded_partial',
            'in_dispute',
            'cancelled',
        ], true);

        $serviceFinishedForRating =
            (bool) ($flags['allSessionsFinished'] ?? false)
            || $status === 'completed'
            || $settlement === 'paid';

        abort_if(! $serviceFinishedForRating || $reviewBlockedByRefundOrDispute, 422, 'This booking is not eligible for rating yet.');
        abort_if($reservation->coachReview, 422, 'You already rated this booking.');

        DB::transaction(function () use ($request, $reservation, $data, $ratingService) {
            ReservationReview::create([
                'reservation_id' => $reservation->id,
                'reviewer_id'    => $request->user()->id,
                'reviewee_id'    => $reservation->client_id,
                'reviewer_role'  => 'coach',
                'reviewee_role'  => 'client',
                'stars'          => (int) $data['stars'],
                'description'    => trim($data['description']),
            ]);

            // update rating for user's CLIENT role
            $ratingService->refreshUserRoleRating((int) $reservation->client_id, 'client');
        });

        return redirect()
            ->route('coach.bookings', ['tab' => request('tab', 'my')])
            ->with('success', 'Your rating has been submitted.');
    }
}