<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\CancellationService;
use Illuminate\Http\Request;

class ReservationCancelController extends Controller
{
    public function clientCancel(
        Request $request,
        Reservation $reservation,
        CancellationService $svc
    ) {
        // 🔐 Ownership check
        abort_unless((int) $reservation->client_id === (int) auth()->id(), 403);

        $validated = $request->validate([
            'reason'        => ['nullable', 'string', 'max:1000'],
            'refund_method' => ['nullable', 'in:wallet_credit,original_payment'],
        ]);

        // ✅ Persist refund destination BEFORE cancel service runs
        // (service reads $reservation->refund_method to decide wallet vs provider refund)
      $chosen = $validated['refund_method'] ?? null;

// ONLY save refund_method if user actually selected it
// If not selected, leave it NULL/empty so service can set pending_choice
$reservation->refund_method = $chosen ?: null;
$reservation->save();


        $ok = $svc->cancel(
            $reservation,
            'client',
            $validated['reason'] ?? null
        );

        if (! $ok) {
            return back()->with(
                'error',
                __('Cancellation not allowed. The session may have started or the cancellation window has passed.')
            );
        }

        // Reload to read computed fields (refund, penalties, etc.)
        $reservation->refresh();

        // Build user-friendly message (NO business logic here)
        $refund   = (int) ($reservation->refund_total_minor ?? 0);
        $penalty  = (int) ($reservation->client_penalty_minor ?? 0);
        $method   = (string) ($reservation->refund_method ?? 'wallet_credit');

        if ($refund > 0 && $penalty === 0) {
            $msg = __('Booking cancelled. A full refund has been issued.');
        } elseif ($refund > 0 && $penalty > 0) {
            $msg = __('Booking cancelled. A partial refund has been issued after cancellation penalties.');
        } else {
            $msg = __('Booking cancelled.');
        }

        // Optional: clarify destination
        if ($refund > 0) {
            if ($method === 'original_payment') {
                $msg .= ' ' . __('Refund will be sent to your original payment method (processing time depends on provider).');
            } else {
                $msg .= ' ' . __('Refund was added to your wallet credit (instant, non-withdrawable).');
            }
        }

        return back()->with('success', $msg);
    }

    public function clientCancelQuote(Request $request, Reservation $reservation)
    {
        abort_unless((int) $reservation->client_id === (int) $request->user()->id, 403);

        $svc = app(CancellationService::class);

        $q = $svc->quote($reservation, 'client');

        if (! $q) {
            return response()->json([
                'ok' => false,
                'message' => 'Cancellation is not available for this booking.',
            ], 422);
        }

        return response()->json(['ok' => true] + $q);
    }
}
