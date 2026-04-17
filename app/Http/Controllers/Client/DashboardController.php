<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Services\ReservationUiService;

class DashboardController extends Controller
{
    public function home(Request $request, ReservationUiService $ui)
    {
        $user = $request->user();

        $tab = $request->query('tab', 'my');

        $q = Reservation::query()
            ->with([
                'service.coach',
                'package',
                'slots',
                'dispute',
                'clientDispute',
                'payments',
                'walletPayment',
                'externalPayment',
                'refunds',
                'latestRefund',

                // ✅ add these relations
                'clientReview',
                'coachReview',
            ])
            ->where('client_id', $user->id);

        $q->addSelect('reservations.*');

        $q->selectSub(function ($sub) {
            $sub->from('reservation_slots')
                ->selectRaw('MIN(start_utc)')
                ->whereColumn('reservation_slots.reservation_id', 'reservations.id')
                ->whereRaw("LOWER(TRIM(session_status)) NOT IN ('completed','no_show_client')");
        }, 'next_start_utc');

        $q->selectSub(function ($sub) {
            $sub->from('reservation_slots')
                ->selectRaw('MAX(end_utc)')
                ->whereColumn('reservation_slots.reservation_id', 'reservations.id');
        }, 'last_end_utc');

        if ($tab === 'refunded') {
            $q->whereIn('settlement_status', ['refunded', 'refunded_partial'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        } elseif ($tab === 'dispute') {
            $q->where(function ($w) {
                $w->whereNotNull('disputed_by_client_at')
                  ->orWhereNotNull('disputed_by_coach_at');
            })
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        } elseif ($tab === 'cancelled') {
            $q->where(function ($w) {
                $w->whereIn('status', ['cancelled', 'canceled'])
                  ->orWhereNotNull('cancelled_at');
            })
                ->whereNotIn('settlement_status', ['refunded', 'refunded_partial'])
                ->orderByDesc('cancelled_at')
                ->orderByDesc('updated_at');
        } elseif ($tab === 'completed') {
            $q->where('payment_status', 'paid')
                ->whereNotIn('settlement_status', ['refunded', 'refunded_partial'])
                ->whereNull('disputed_by_client_at')
                ->whereNull('disputed_by_coach_at')
                ->where(function ($w) {
                    $w->whereNotIn('status', ['cancelled', 'canceled'])
                      ->whereNull('cancelled_at');
                })
                ->whereHas('slots')
                ->whereDoesntHave('slots', function ($s) {
                    $s->whereRaw("LOWER(TRIM(session_status)) NOT IN ('completed','no_show_client')");
                })
                ->orderByDesc('last_end_utc')
                ->orderByDesc('id');
        } elseif ($tab === 'in_progress') {
            $q->where('payment_status', 'paid')
                ->whereNotIn('settlement_status', ['refunded', 'refunded_partial'])
                ->whereNull('disputed_by_client_at')
                ->whereNull('disputed_by_coach_at')
                ->where(function ($w) {
                    $w->whereNotIn('status', ['cancelled', 'canceled'])
                      ->whereNull('cancelled_at');
                })
                ->whereHas('slots')
                ->whereHas('slots', function ($s) {
                    $s->whereRaw("LOWER(TRIM(session_status)) NOT IN ('completed','no_show_client')");
                })
                ->whereDoesntHave('slots', function ($s) {
                    $s->whereRaw("LOWER(TRIM(session_status)) IN ('no_show_coach','no_show_both')");
                })
                ->orderByRaw('next_start_utc IS NULL')
                ->orderBy('next_start_utc', 'asc')
                ->orderByDesc('id');
        } else {
            $q->whereIn('payment_status', ['paid', 'refunded', 'partial_refund', 'refunded_partial', 'failed', 'pending'])
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        $bookings = $q->paginate(8)->appends(['tab' => $tab]);

        $tz = $user->timezone ?? config('app.timezone', 'UTC');

        $bookings->getCollection()->transform(function ($res) use ($tz, $ui) {
            $flags = $ui->postSessionFlags($res, $tz);

            $res->ui_can_complete = (bool)($flags['canCompleteClient'] ?? false);

            $hasDispute = (bool) $res->clientDispute;
            $res->ui_can_dispute  = (bool)($flags['canDisputeClient'] ?? false) && ! $hasDispute;

            $res->ui_within_dispute_window = (bool)($flags['withinDisputeWindow'] ?? false);
            $res->ui_dispute_window_ends   = $flags['disputeWindowEnds'] ?? null;
            $res->ui_last_slot_end         = $flags['lastSlotMoment'] ?? null;

            $res->ui_debug_now     = $flags['debug_now'] ?? null;
            $res->ui_debug_last    = $flags['debug_last'] ?? null;
            $res->ui_debug_end     = $flags['debug_end'] ?? null;
            $res->ui_debug_now_ts  = $flags['debug_now_ts'] ?? null;
            $res->ui_debug_last_ts = $flags['debug_last_ts'] ?? null;
            $res->ui_debug_end_ts  = $flags['debug_end_ts'] ?? null;

            $res->ui_total_slots     = (int)($flags['totalSlots'] ?? 0);
            $res->ui_completed_slots = (int)($flags['clientCompletedSlots'] ?? 0);
            $res->ui_all_finished    = (bool)($flags['allSessionsFinished'] ?? false);

            // ✅ rating overlay logic for client
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
                (bool)($flags['allSessionsClientCompleted'] ?? false)
                || $status === 'completed'
                || $settlement === 'paid';

            $alreadyRatedByClient = !empty($res->clientReview);

            $res->ui_must_rate_coach =
                $serviceFinishedForRating
                && ! $reviewBlockedByRefundOrDispute
                && ! $alreadyRatedByClient;

            return $res;
        });

        // ✅ first pending reservation that must be rated
        $pendingClientRatingReservation = $bookings->getCollection()
            ->first(fn ($res) => (bool) ($res->ui_must_rate_coach ?? false));

        $cancelledBookings = ($tab === 'cancelled')
            ? $bookings
            : Reservation::with([
                'service.coach',
                'package',
                'slots',
                'dispute',
                'clientDispute',
                'payments' => function ($p) {
                    $p->where('status', 'succeeded')
                      ->whereIn('provider', ['stripe', 'paypal', 'wallet']);
                },
                'walletPayment',
                'externalPayment',
                'refunds',
                'latestRefund',
                'clientReview',
                'coachReview',
            ])
                ->where('client_id', $user->id)
                ->where(function ($w) {
                    $w->whereIn('status', ['cancelled', 'canceled'])
                      ->orWhereNotNull('cancelled_at');
                })
                ->orderByDesc('cancelled_at')
                ->orderByDesc('updated_at')
                ->paginate(5, ['*'], 'cancelled_page');

        return view('client.home', [
            'tab'                            => $tab,
            'bookings'                       => $bookings,
            'cancelledBookings'              => $cancelledBookings,
            'tz'                             => $tz,

            // ✅ send to blade
            'pendingClientRatingReservation' => $pendingClientRatingReservation,
        ]);
    }

    public function messages(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::with(['service', 'coach', 'client'])
            ->where('client_id', $user->id)
            ->whereHas('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get();

        if ($conversations->isEmpty()) {
            return view('client.messages', [
                'conversations' => $conversations,
                'conversation'  => null,
            ]);
        }

        $activeId = $request->integer('conversation');

        if ($activeId) {
            $conversation = $conversations->firstWhere('id', $activeId) ?? $conversations->first();
        } else {
            $conversation = $conversations->first();
        }

        $conversation->load([
            'service',
            'coach',
            'client',
            'messages.sender',
            'messages.service',
        ]);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);

        return view('client.messages', [
            'conversations' => $conversations,
            'conversation'  => $conversation,
        ]);
    }

    public function disputeCreate()
    {
        return view('client.disputes-create');
    }

    public function disputeIndex()
    {
        return view('client.disputes-index');
    }

    public function profileEdit()
    {
        return view('client.profile-edit');
    }

    public function cancellations()
    {
        return view('client.cancellations');
    }
}