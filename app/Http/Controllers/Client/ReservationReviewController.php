<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationReview;
use App\Services\ReservationUiService;
use App\Services\UserRoleRatingService;
use App\Support\AnalyticsLogger;
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
        abort_unless((int) $reservation->client_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'stars'       => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $reservation->loadMissing([
            'slots',
            'service.coach',
            'clientReview',
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
            (bool) ($flags['allSessionsClientCompleted'] ?? false)
            || $status === 'completed'
            || $settlement === 'paid';

        if (! $serviceFinishedForRating || $reviewBlockedByRefundOrDispute) {
            AnalyticsLogger::log($request, 'coach_review_blocked', [
                'group'          => 'review',
                'client_id'      => (int) $request->user()->id,
                'reservation_id' => (int) $reservation->id,
                'coach_id'       => (int) ($reservation->service?->coach_id ?? 0),
                'service_id'     => (int) ($reservation->service_id ?? 0),
                'meta'           => [
                    'reason'        => 'booking_not_eligible',
                    'settlement'    => $settlement,
                    'status'        => $status,
                    'flags'         => $flags,
                ],
            ]);

            abort(422, 'This booking is not eligible for rating yet.');
        }

        if ($reservation->clientReview) {
            AnalyticsLogger::log($request, 'coach_review_blocked', [
                'group'          => 'review',
                'client_id'      => (int) $request->user()->id,
                'reservation_id' => (int) $reservation->id,
                'coach_id'       => (int) ($reservation->service?->coach_id ?? 0),
                'service_id'     => (int) ($reservation->service_id ?? 0),
                'meta'           => [
                    'reason' => 'duplicate_review',
                ],
            ]);

            abort(422, 'You already rated this booking.');
        }

        $coachId = (int) ($reservation->service?->coach_id ?? 0);

        if ($coachId <= 0) {
            AnalyticsLogger::log($request, 'coach_review_blocked', [
                'group'          => 'review',
                'client_id'      => (int) $request->user()->id,
                'reservation_id' => (int) $reservation->id,
                'service_id'     => (int) ($reservation->service_id ?? 0),
                'meta'           => [
                    'reason' => 'coach_not_found',
                ],
            ]);

            abort(422, 'Coach account was not found for this booking.');
        }

        DB::transaction(function () use ($request, $reservation, $data, $coachId, $ratingService) {
            $review = ReservationReview::create([
                'reservation_id' => $reservation->id,
                'reviewer_id'    => $request->user()->id,
                'reviewee_id'    => $coachId,
                'reviewer_role'  => 'client',
                'reviewee_role'  => 'coach',
                'stars'          => (int) $data['stars'],
                'description'    => trim($data['description']),
            ]);

            $ratingService->refreshUserRoleRating($coachId, 'coach');

            $freshCoach = \App\Models\Users::query()->find($coachId);

            AnalyticsLogger::log($request, 'coach_review_submitted', [
                'group'          => 'review',
                'client_id'      => (int) $request->user()->id,
                'reservation_id' => (int) $reservation->id,
                'coach_id'       => $coachId,
                'service_id'     => (int) ($reservation->service_id ?? 0),
                'meta'           => [
                    'review_id'            => (int) $review->id,
                    'stars'                => (int) $data['stars'],
                    'description_length'   => mb_strlen(trim((string) $data['description'])),
                    'coach_rating_avg'     => $freshCoach?->coach_rating_avg,
                    'coach_rating_count'   => $freshCoach?->coach_rating_count,
                ],
            ]);
        });

        return redirect()
            ->route('client.home', ['tab' => request('tab', 'my')])
            ->with('success', 'Your rating has been submitted.');
    }
}