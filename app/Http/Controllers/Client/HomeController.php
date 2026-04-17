<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Services\ReservationUiService;

class HomeController extends Controller
{
    public function index(Request $request, ReservationUiService $ui)
    {
        $user  = $request->user();
        $tab   = $request->query('tab', 'my');
        $tz    = $user->timezone ?? config('app.timezone', 'UTC');
        $debug = (bool) $request->boolean('debug', false); // add ?debug=1

        $q = Reservation::with([
                'service.coach',
                'package',
                'slots',
                'walletPayment',
                'externalPayment',
                'clientDispute',
            ])
            ->where('client_id', $user->id)
            ->where('payment_status', 'paid');

        switch ($tab) {

            case 'cancelled':
                $q->where(function ($w) {
                    $w->whereIn('status', ['cancelled','canceled'])
                      ->orWhere('settlement_status', 'cancelled');
                });
                break;

            case 'refunded':
                $q->where(function ($w) {
                    $w->whereIn('settlement_status', ['refunded','refunded_partial'])
                      ->orWhere('refund_total_minor', '>', 0);
                });
                break;

            case 'dispute':
                $q->where(function ($w) {
                    $w->whereNotNull('disputed_by_client_at')
                      ->orWhereNotNull('disputed_by_coach_at')
                      ->orWhere('settlement_status', 'in_dispute');
                });
                break;

            case 'completed':
                /**
                 * ✅ Completed should NOT leak any booking just because it has end_utc.
                 * We keep it strict:
                 * - status completed OR
                 * - settlement settled/paid OR
                 * - all slots are in final statuses (finished)
                 */
                $q->where(function ($w) {
                    $w->where('status', 'completed')
                      ->orWhereIn('settlement_status', ['settled','paid'])
                      ->orWhereDoesntHave('slots', function ($s) {
                          $s->whereRaw("LOWER(TRIM(COALESCE(session_status,''))) NOT IN (
                              'completed','no_show_coach','no_show_client','no_show_both','cancelled','canceled'
                          )");
                      });
                });
                break;

            case 'in_progress':
                // ✅ in_progress = has ANY slot NOT in final state
                $q->whereNotIn('status', ['cancelled','canceled'])
                  ->whereNotIn('settlement_status', ['refunded','refunded_partial','cancelled'])
                  ->whereHas('slots', function ($s) {
                      $s->whereRaw("LOWER(TRIM(COALESCE(session_status,''))) NOT IN (
                          'completed','no_show_coach','no_show_client','no_show_both','cancelled','canceled'
                      )");
                  });
                break;

            case 'my':
            default:
                // ✅ my = active (not cancelled/refunded/dispute)
                $q->whereNotIn('status', ['cancelled','canceled'])
                  ->whereNotIn('settlement_status', ['refunded','refunded_partial','cancelled'])
                  ->whereNull('disputed_by_client_at')
                  ->whereNull('disputed_by_coach_at');
                break;
        }

        $bookings = $q->latest('id')->paginate(10);

        /**
         * ✅ Apply ReservationUiService per booking so Blade can trust:
         * $reservation->ui_can_complete
         * $reservation->ui_can_dispute
         *
         * IMPORTANT:
         * postSessionFlags() may finalize ended slots. That’s OK here, but it means this
         * page can update slot status from "in_progress" -> "completed" after end time.
         */
        foreach ($bookings as $reservation) {
            $flags = $ui->postSessionFlags($reservation, $tz);

            // Client side flags (what your blade expects)
            $reservation->ui_can_complete = (bool)($flags['canCompleteClient'] ?? false);

            // Your service currently returns canDisputeClientBase; map it:
           $reservation->ui_can_dispute  = (bool)($flags['canDisputeClient'] ?? false);

            // Optional (handy for UI/debug)
            $reservation->ui_within_dispute_window = (bool)($flags['withinDisputeWindow'] ?? false);
            $reservation->ui_dispute_window_ends   = $flags['disputeWindowEnds'] ?? null;
            $reservation->ui_total_slots           = (int)($flags['totalSlots'] ?? 0);
            $reservation->ui_client_completed      = (bool)($flags['allSessionsClientCompleted'] ?? false);

            $reservation->debug_now_ts  = $flags['debug_now_ts'] ?? null;
$reservation->debug_last_ts = $flags['debug_last_ts'] ?? null;
$reservation->debug_end_ts  = $flags['debug_end_ts'] ?? null;

$reservation->debug_now  = $flags['debug_now'] ?? null;
$reservation->debug_last = $flags['debug_last'] ?? null;
$reservation->debug_end  = $flags['debug_end'] ?? null;
        }

        // Optional debug dump (?debug=1)
        if ($debug) {
            // return response()->json($bookings);
        }

        return view('client.home', compact('tab', 'bookings', 'tz'));
    }
}