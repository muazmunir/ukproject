<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\CancellationService;
use Illuminate\Http\Request;

class ReservationCancelController extends Controller
{
    public function cancelQuote(Reservation $reservation, CancellationService $svc)
    {
        // ✅ ensure coach owns this reservation
        abort_unless((int)$reservation->coach_id === (int)auth()->id(), 403);

        $quote = $svc->quote($reservation, 'coach');

        if (!$quote) {
            return response()->json([
                'ok' => false,
                'message' => 'Cancellation is not allowed for this booking.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'quote' => $quote,
        ]);
    }

    public function coachCancel(Request $request, Reservation $reservation, CancellationService $svc)
    {
        abort_unless((int)$reservation->coach_id === (int)auth()->id(), 403);

        $data = $request->validate([
            'reason' => ['nullable','string','max:500'],
        ]);

        $ok = $svc->cancel($reservation, 'coach', $data['reason'] ?? null);

        if (!$ok) {
            return back()->with('error', __('Cancellation Is Not Allowed For This Booking.'));
        }

        return back()->with('ok', __('Booking Cancelled Successfully.'));
    }
}
