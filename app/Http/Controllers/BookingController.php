<?php
// app/Http/Controllers/BookingController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Models\ServiceFee;
// use App\Services\EscrowService;
use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Services\WalletService;
use App\Services\ReservationUiService;

class BookingController extends Controller
{
    /**
     * Keep a single source of truth for “final / finished slot” states.
     * (Must match ReservationUiService)
     */
    private array $finalStatuses = [
        'completed',
        'no_show_coach',
        'no_show_client',
        'no_show_both',
        'cancelled',
        'canceled',
    ];

    /**
     * CLIENT: list bookings
     */
    public function clientIndex(Request $r, ReservationUiService $ui)
    {
        $userId = (int) auth()->id();
        $tz     = $r->query('tz') ?: (auth()->user()->timezone ?? config('app.timezone','UTC'));
        $tab    = $r->query('tab', 'my');

   $q = Reservation::with([
    'service.coach',
    'package',
    'slots',
    'walletPayment',
    'externalPayment',
    'clientDispute',
    'clientReview',
    'coachReview',
])
            ->where('client_id', $userId)
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
                // ✅ Completed should NOT leak by “has end_utc”
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
                $q->whereNotIn('status', ['cancelled','canceled'])
                  ->whereNotIn('settlement_status', ['refunded','refunded_partial','cancelled'])
                  ->whereNull('disputed_by_client_at')
                  ->whereNull('disputed_by_coach_at');
                break;
        }

        $reservations = $q->orderByDesc('id')->paginate(8)->withQueryString();

        // ✅ attach computed flags for CARD UI using the SERVICE
        $reservations->getCollection()->transform(function ($res) use ($tz, $ui) {
            $flags = $ui->postSessionFlags($res, $tz);

            $res->ui_can_complete = (bool)($flags['canCompleteClient'] ?? false);
          $res->ui_can_dispute  = (bool)(($flags['canDisputeClient'] ?? false) && empty($res->clientDispute));

$res->ui_dispute_window_ends = $flags['disputeWindowEnds'] ?? null;
$res->ui_last_slot_end       = $flags['lastSlotMoment'] ?? null;
            $res->ui_total_slots         = (int)($flags['totalSlots'] ?? 0);

            $res->ui_completed_slots     = (int)($flags['clientCompletedSlots'] ?? 0);
            $res->ui_all_finished        = (bool)($flags['allSessionsFinished'] ?? false);

            return $res;
        });

        $bookings = $reservations;

        return view('client.home', compact('bookings','tz','tab'));
    }

    /**
     * CLIENT: show booking detail
     */
    public function clientShow(Reservation $reservation, ReservationUiService $ui)
{
    abort_unless($reservation->client_id === auth()->id(), 403);

  $reservation->load([
    'service.coach',
    'package',
    'slots',
    'walletPayment',
    'externalPayment',
    'clientDispute',
    'latestRefund',
    'refunds',
    'clientReview',
'coachReview',
]);

    

  $reservation->refresh()->load([
    'service.coach',
    'package',
    'slots',
    'walletPayment',
    'externalPayment',
    'clientDispute',
    'latestRefund',
    'refunds',
]);

    $tz = auth()->user()->timezone
        ?? $reservation->client_tz
        ?? config('app.timezone', 'UTC');

    $flags = $ui->postSessionFlags($reservation, $tz);

    $canComplete = (bool) ($flags['canCompleteClient'] ?? false);
$canDisputeBase = (bool) ($flags['canDisputeClient'] ?? false);
$canDispute = $canDisputeBase && empty($reservation->clientDispute);

$lastSlotEnd = $flags['lastSlotMoment'] ?? null;
    $disputeWindowEnds = $flags['disputeWindowEnds'] ?? null;

    return view('client.bookings.show', compact(
        'reservation',
        'tz',
        'canComplete',
        'canDispute',
        'lastSlotEnd',
        'disputeWindowEnds'
    ));
}
    /**
     * COACH: list bookings
     */
    public function coachIndex(Request $request, ReservationUiService $ui)
    {
        $coach     = $request->user();
        $activeTab = $request->query('tab', 'my');

       $fallbackCoachFeePercent = (float) (ServiceFee::where('slug', 'coach_commission')
    ->where('is_active', 1)
    ->value('amount') ?? 0);

        $query = Reservation::query()
    ->with([
        'service.coach',
        'package',
        'client',
        'slots',

        // 🔥 REQUIRED for correct fundingLabel(), externalPaidMinor(), refund splits
        'payments' => function ($p) {
            $p->where('status', 'succeeded')
              ->whereIn('provider', ['wallet','stripe','paypal']);
        },

        'walletPayment',
        'externalPayment',

        // (Optional but recommended if coach blade shows dispute state anywhere)
        'dispute',
        'coachDispute',
        'clientDispute',
         'clientReview',
        'coachReview',
    ])
            ->whereHas('service', function ($q) use ($coach) {
                $q->where('coach_id', $coach->id);
            })
            ->withExists([
                'disputes as has_coach_dispute' => function ($q) use ($coach) {
                    $q->where('opened_by_role', 'coach')
                      ->where('opened_by_user_id', $coach->id);
                },
                'disputes as has_client_dispute' => function ($q) {
                    $q->where('opened_by_role', 'client');
                },
            ]);

        // ✅ computed columns for ordering
        $query->addSelect('reservations.*');

        /**
         * ✅ next_start_utc should be the next slot that is NOT in FINAL state
         * (not just <> completed)
         */
        $finalsSql = "'completed','no_show_coach','no_show_client','no_show_both','cancelled','canceled'";

        $query->selectSub(function ($sub) use ($finalsSql) {
            $sub->from('reservation_slots')
                ->selectRaw('MIN(start_utc)')
                ->whereColumn('reservation_slots.reservation_id', 'reservations.id')
                ->whereRaw("LOWER(TRIM(COALESCE(session_status,''))) NOT IN ($finalsSql)");
        }, 'next_start_utc');

        // last slot end (for ordering)
        $query->selectSub(function ($sub) {
            $sub->from('reservation_slots')
                ->selectRaw('MAX(end_utc)')
                ->whereColumn('reservation_slots.reservation_id', 'reservations.id');
        }, 'last_end_utc');

        // coach dispute id
        $query->selectSub(function ($sub) use ($coach) {
            $sub->from('disputes')
                ->select('id')
                ->whereColumn('disputes.reservation_id', 'reservations.id')
                ->where('opened_by_role', 'coach')
                ->where('opened_by_user_id', $coach->id)
                ->orderByDesc('id')
                ->limit(1);
        }, 'coach_dispute_id');

        // client dispute id
        $query->selectSub(function ($sub) {
            $sub->from('disputes')
                ->select('id')
                ->whereColumn('disputes.reservation_id', 'reservations.id')
                ->where('opened_by_role', 'client')
                ->orderByDesc('id')
                ->limit(1);
        }, 'client_dispute_id');

        // -----------------------------
        // TAB FILTERS (Coach)
        // -----------------------------
        switch ($activeTab) {

            case 'refunded':
                $query->whereIn('settlement_status', ['refunded','refunded_partial'])
                      ->orderByDesc('updated_at')
                      ->orderByDesc('id');
                break;

            case 'dispute':
                $query->where(function($x){
                        $x->whereNotNull('disputed_by_client_at')
                          ->orWhereNotNull('disputed_by_coach_at')
                          ->orWhere('settlement_status', 'in_dispute');
                    })
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id');
                break;

            case 'cancelled':
                $query->where(function ($w) {
                        $w->whereIn('status', ['cancelled','canceled'])
                          ->orWhereNotNull('cancelled_at')
                          ->orWhere('settlement_status', 'cancelled');
                    })
                    ->whereNotIn('settlement_status', ['refunded','refunded_partial'])
                    ->orderByDesc('cancelled_at')
                    ->orderByDesc('updated_at');
                break;

            case 'completed':
                /**
                 * ✅ Completed (coach tab) should be strict:
                 * - reservation status completed OR
                 * - settlement paid/settled OR
                 * - all slots final
                 */
                $query->where(function ($w) use ($finalsSql) {
                        $w->where('status', 'completed')
                          ->orWhereIn('settlement_status', ['paid','settled'])
                          ->orWhereDoesntHave('slots', function ($s) use ($finalsSql) {
                              $s->whereRaw("LOWER(TRIM(COALESCE(session_status,''))) NOT IN ($finalsSql)");
                          });
                    })
                    ->orderByRaw('last_end_utc IS NULL')
                    ->orderByDesc('last_end_utc')
                    ->orderByDesc('id');
                break;

            case 'in_progress':
                /**
                 * ✅ In progress = at least one slot NOT final.
                 * Also exclude refunded/cancelled track.
                 */
                $query->where('payment_status', 'paid')
                    ->whereNotIn('settlement_status', ['refunded','refunded_partial','cancelled'])
                    ->whereNotIn('status', ['cancelled','canceled'])
                    ->whereNull('cancelled_at')
                    ->whereHas('slots', function($s) use ($finalsSql){
                        $s->whereRaw("LOWER(TRIM(COALESCE(session_status,''))) NOT IN ($finalsSql)");
                    })
                    ->orderByRaw('next_start_utc IS NULL')
                    ->orderBy('next_start_utc', 'asc')
                    ->orderByDesc('id');
                break;

            case 'my':
            default:
                // “My” = everything
                $query->orderByDesc('booked_at')
                      ->orderByDesc('created_at');
                break;
        }

        $bookings = $query->paginate(10)->withQueryString();

        $tz = $coach->timezone ?? config('app.timezone','UTC');

        // ✅ attach UI flags via ReservationUiService
      $bookings->getCollection()->transform(function ($res) use ($tz, $coach, $ui) {
    $flags = $ui->postSessionFlags($res, $tz);

    $res->ui_can_complete = (bool)($flags['canCompleteCoach'] ?? false);

    $res->ui_can_dispute = (bool)($flags['canDisputeCoach'] ?? false)
        && !((bool)($res->has_coach_dispute ?? false));

    // ✅ mandatory coach rating logic
    $settlement = strtolower((string) ($res->settlement_status ?? ''));
    $status     = strtolower((string) ($res->status ?? ''));

    $reviewBlockedByRefundOrDispute = in_array($settlement, [
        'refund_pending',
        'refunded',
        'refunded_partial',
        'in_dispute',
        'cancelled',
    ], true);

    $serviceFinishedForRating =
        (bool)($flags['allSessionsFinished'] ?? false)
        || $status === 'completed'
        || $settlement === 'paid';

    $alreadyRatedByCoach = !empty($res->coachReview);

    $res->ui_must_rate_client =
        $serviceFinishedForRating
        && ! $reviewBlockedByRefundOrDispute
        && ! $alreadyRatedByCoach;

    try {
        $slotStatuses = $res->slots
            ? $res->slots->map(fn($s) => [
                'slot_id' => $s->id,
                'start_utc' => (string) $s->start_utc,
                'end_utc' => (string) $s->end_utc,
                'finalized_at' => (string) $s->finalized_at,
                'session_status' => (string) $s->session_status,
            ])->values()->all()
            : [];

        Log::info('COACH_UI_FLAGS', [
            'coach_id' => $coach->id ?? null,
            'reservation_id' => $res->id,
            'reservation_status' => $res->status,
            'payment_status' => $res->payment_status,
            'settlement_status' => $res->settlement_status,
            'has_coach_dispute' => (bool)($res->has_coach_dispute ?? false),
            'coach_dispute_id' => $res->coach_dispute_id ?? null,
            'ui_can_complete' => $res->ui_can_complete,
            'ui_can_dispute' => $res->ui_can_dispute,
            'ui_must_rate_client' => $res->ui_must_rate_client,
            'slots' => $slotStatuses,
        ]);
    } catch (\Throwable $e) {
        Log::error('COACH_UI_FLAGS_LOG_FAIL', [
            'reservation_id' => $res->id ?? null,
            'err' => $e->getMessage(),
        ]);
    }

    return $res;
});
$pendingCoachRatingReservation = $bookings->getCollection()
    ->first(fn ($res) => (bool) ($res->ui_must_rate_client ?? false));

        return view('coach.bookings.index', [
            'bookings'        => $bookings,
            'activeTab'       => $activeTab,
            'tz'              => $tz,
            'fallbackCoachFeePercent' => $fallbackCoachFeePercent,
            'pendingCoachRatingReservation' => $pendingCoachRatingReservation,
        ]);
    }

    /**
     * COACH: show booking detail
     */
    public function coachShow(Reservation $reservation, ReservationUiService $ui)
    {
        $coach = auth()->user();

        abort_unless(optional($reservation->service)->coach_id === $coach->id, 403);

        $reservation->load([
    'service.coach',
    'package',
    'slots',

    'payments' => function ($p) {
        $p->where('status', 'succeeded')
          ->whereIn('provider', ['wallet','stripe','paypal']);
    },

    'walletPayment',
    'externalPayment',
    'dispute',
    'coachDispute',
    'clientDispute',
    'clientReview',
'coachReview',
]);
        
        $reservation->refresh();

        $tz = $coach->timezone
            ?? $reservation->client_tz
            ?? config('app.timezone','UTC');

        $coachFeePercent = ServiceFee::where('slug', 'coach_commission')
            ->where('is_active', 1)
            ->value('amount') ?? 0;

        // Optional: pass flags to detail blade if you show buttons there too
        $flags = $ui->postSessionFlags($reservation, $tz);
        $reservation->ui_can_complete = (bool)($flags['canCompleteCoach'] ?? false);
        $reservation->ui_can_dispute  = (bool)($flags['canDisputeCoach'] ?? false);

        return view('coach.bookings.show', compact('reservation','tz','coachFeePercent'));
    }

    /**
     * CLIENT: mark complete
     */
    public function clientComplete(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->client_id === auth()->id(), 403);

        $reservation->forceFill(['completed_by_client_at' => now()])->save();
        app(\App\Services\ReservationSettlementService::class)->recompute($reservation->id);

        return back()->with('ok', __('Marked complete. Settlement will occur when both complete or after 48 hours if no dispute.'));
    }

    public function coachComplete(Request $request, Reservation $reservation)
    {
        $coach = $request->user();
        abort_unless(optional($reservation->service)->coach_id === $coach->id, 403);

        $reservation->forceFill(['completed_by_coach_at' => now()])->save();
        app(\App\Services\ReservationSettlementService::class)->recompute($reservation->id);

        return back()->with('ok', __('Marked complete. Settlement will occur when both complete or after 48 hours if no dispute.'));
    }

    /**
     * Cancel (old legacy)
     */
    public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel-reservation', $reservation);

        if ($reservation->payment_status === 'paid') {
            return back()->with('error', __('Paid Reservations Require Support To Cancel.'));
        }

        $reservation->update(['status' => 'cancelled']);

        return redirect()->route('bookings.index')->with('success', __('Reservation Cancelled.'));
    }

    /**
     * Paid cancellation policy (unchanged from your code)
     */
    public function cancelPaidBooking(Request $request, Reservation $reservation, WalletService $wallet)
    {
        $user = $request->user();

        $isClient = ((int)$reservation->client_id === (int)$user->id);
        $coachId  = (int) optional($reservation->service)->coach_id;
        $isCoach  = $coachId && $coachId === (int)$user->id;

        if (! $isClient && ! $isCoach) abort(403);

        if (in_array($reservation->status, ['cancelled','canceled'], true)) {
            return back()->with('error', 'This reservation is already cancelled.');
        }

        if ($reservation->payment_status !== 'paid') {
            return back()->with('error', 'Only paid reservations use this cancellation policy.');
        }

        $request->validate([
            'reason' => ['nullable','string','max:2000'],
        ]);

        return DB::transaction(function () use ($reservation, $wallet, $isClient, $isCoach, $request, $coachId) {

            $reservation->loadMissing(['slots','walletPayment','externalPayment','service']);

            $firstStart = $reservation->first_slot_start_utc
                ? CarbonImmutable::parse($reservation->first_slot_start_utc)->utc()
                : ($reservation->slots->min('start_utc')
                    ? CarbonImmutable::parse($reservation->slots->min('start_utc'))->utc()
                    : null);

            if (! $firstStart) {
                return back()->with('error', 'Cannot cancel: first slot time is missing.');
            }

            $now = CarbonImmutable::now('UTC');

            if ($now->gte($firstStart)) {
                return back()->with('error', 'Cancellation is not allowed after the first slot has started.');
            }

            $anyCheckin = $reservation->slots->contains(fn($s) => $s->client_checked_in_at || $s->coach_checked_in_at);
            if ($anyCheckin) {
                return back()->with('error', 'Cancellation is not allowed after any session check-in.');
            }

            $subtotal = (int)$reservation->subtotal_minor;
            $fees     = (int)$reservation->fees_minor;
            $total    = (int)$reservation->total_minor;

            $hoursUntil = $now->diffInRealHours($firstStart, false);
            $band = $hoursUntil >= 48 ? '48_plus' : ($hoursUntil >= 24 ? '24_48' : '0_24');

            $actor = $isClient ? 'client' : 'coach';

            $refundToClient = 0;
            $clientPenalty  = 0;
            $coachPenalty   = 0;
            $platformEarned = 0;

            if ($band === '48_plus') {
                $refundToClient = $total;
                $platformEarned = 0;
            }

            if ($band === '24_48') {
                if ($actor === 'coach') {
                    $refundToClient = $total;
                    $coachPenalty   = (int) round($subtotal * 0.10);
                    $platformEarned = $coachPenalty;
                } else {
                    $clientPenalty  = $fees + (int) round($subtotal * 0.10);
                    $refundToClient = max(0, $total - $clientPenalty);
                    $platformEarned = $clientPenalty;
                }
            }

            if ($band === '0_24') {
                if ($actor === 'coach') {
                    $refundToClient = $total;
                    $coachPenalty   = (int) round($subtotal * 0.20);
                    $platformEarned = $coachPenalty;
                } else {
                    $clientPenalty  = $fees + (int) round($subtotal * 0.20);
                    $refundToClient = max(0, $total - $clientPenalty);
                    $platformEarned = $clientPenalty;
                }
            }

            $paymentId = $reservation->externalPayment?->id ?? $reservation->walletPayment?->id;

            if ($refundToClient > 0) {
                $wallet->credit(
                    (int)$reservation->client_id,
                    $refundToClient,
                    'cancel_refund',
                    $reservation->id,
                    $paymentId,
                    ['band' => $band, 'actor' => $actor]
                );
            }

            if ($coachPenalty > 0 && $coachId) {
                $wallet->debit(
                    $coachId,
                    $coachPenalty,
                    'cancel_penalty_coach',
                    $reservation->id,
                    $paymentId,
                    ['band' => $band, 'actor' => $actor]
                );
            }

            $reservation->forceFill([
                'status'               => 'cancelled',
                'cancelled_at'         => now(),
                'cancelled_by'         => $actor,
                'cancel_reason'        => $request->input('reason'),

                'settlement_status'    => 'cancelled',
                'refund_total_minor'   => $refundToClient,
                'client_penalty_minor' => $clientPenalty,
                'coach_penalty_minor'  => $coachPenalty,
                'platform_earned_minor'=> $platformEarned,
                'coach_earned_minor'   => 0,

                'first_slot_start_utc' => $reservation->first_slot_start_utc ?? $firstStart,
            ])->save();

            foreach ($reservation->slots as $slot) {
                $slot->session_status = 'cancelled';
                $slot->finalized_at   = $slot->finalized_at ?? now();
                $slot->save();
            }

            return redirect()->back()->with('success', 'Booking cancelled according to policy. Refund/penalties applied to wallet.');
        });
    }
}