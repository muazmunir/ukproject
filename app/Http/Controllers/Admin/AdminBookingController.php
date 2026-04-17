<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $baseQuery = $this->reservationBaseQuery();

        $rowsQuery = clone $baseQuery;
        $this->applyFilters($rowsQuery, $filters);

        $rows = $rowsQuery
            ->orderByDesc(DB::raw('COALESCE(reservations.completed_at, reservations.cancelled_at, reservations.updated_at, reservations.created_at)'))
            ->paginate(12)
            ->withQueryString();

        $counts = $this->buildTabCounts($filters);

        return view('admin.bookings.index', [
            'rows'          => $rows,
            'counts'        => $counts,
            'filters'       => $filters,
            'tab'           => $filters['tab'],
            'q'             => $filters['q'],
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function show(Request $request, Reservation $reservation)
    {
        $reservation->load([
            'service.coach',
            'client',
            'package',
            'slots' => fn ($q) => $q->orderBy('start_utc'),
            'payments',
            'payment',
            'refunds',
            'disputes.opener',
            'disputes.resolvedBy',
        ]);

        $currency = (string) ($reservation->currency ?? 'USD');

        $subtotal       = (int) ($reservation->subtotal_minor ?? 0);
        $fees           = (int) ($reservation->fees_minor ?? 0);
        $total          = (int) ($reservation->total_minor ?? 0);
        $refundTotal    = $this->reservationRefundTotalMinor($reservation);
        $platformEarned = (int) ($reservation->platform_earned_minor ?? 0);

        $coachEarned       = (int) ($reservation->coach_earned_minor ?? 0);
        $coachComp         = (int) ($reservation->coach_comp_minor ?? 0);
        $coachPenalty      = (int) ($reservation->coach_penalty_minor ?? 0);
        $coachTotalEarning = $coachEarned + $coachComp - $coachPenalty;
        $clientPenalty     = (int) ($reservation->client_penalty_minor ?? 0);

        $walletUsedMinor  = (int) ($reservation->wallet_platform_credit_used_minor ?? 0);
        $gatewayPaidMinor = (int) ($reservation->payable_minor ?? 0);
        $paymentStatus    = (string) ($reservation->payment_status ?? 'unpaid');

        $clientPaid    = in_array($paymentStatus, ['paid', 'succeeded'], true) ? $total : 0;
        $clientNetPaid = max(0, $clientPaid - $refundTotal);

        $refunds = collect($reservation->refunds ?? []);

        $refundRows = $refunds
            ->sortByDesc(fn ($refund) => $refund->processed_at ?? $refund->requested_at ?? $refund->created_at)
            ->values()
            ->map(function ($refund) {
                return (object) [
                    'id'                         => $refund->id,
                    'payment_id'                 => $refund->payment_id,
                    'status'                     => $refund->status,
                    'method'                     => $refund->method,
                    'provider'                   => $refund->provider,
                    'currency'                   => $refund->currency,

                    'requested_amount_minor'     => (int) ($refund->requested_amount_minor ?? 0),
                    'actual_amount_minor'        => (int) ($refund->actual_amount_minor ?? 0),
                    'wallet_amount_minor'        => (int) ($refund->wallet_amount_minor ?? 0),
                    'external_amount_minor'      => (int) ($refund->external_amount_minor ?? 0),
                    'refunded_to_wallet_minor'   => (int) ($refund->refunded_to_wallet_minor ?? 0),
                    'refunded_to_original_minor' => (int) ($refund->refunded_to_original_minor ?? 0),

                    'wallet_status'              => $refund->wallet_status,
                    'external_status'            => $refund->external_status,

                    'provider_refund_id'         => $refund->provider_refund_id,
                    'provider_order_id'          => $refund->provider_order_id,
                    'provider_capture_id'        => $refund->provider_capture_id,

                    'failure_reason'             => $refund->failure_reason,
                    'requested_at'               => $refund->requested_at,
                    'processed_at'               => $refund->processed_at,
                    'created_at'                 => $refund->created_at,

                    'meta'                       => $refund->meta,
                ];
            });

        $latestRefund = $refundRows->first();

        $refundSummary = [
            'count'                   => $refunds->count(),
            'succeeded_count'         => $refunds->where('status', 'succeeded')->count(),
            'partial_count'           => $refunds->where('status', 'partial')->count(),
            'failed_count'            => $refunds->where('status', 'failed')->count(),
            'processing_count'        => $refunds->whereIn('status', ['processing', 'pending'])->count(),
            'total_refunded_minor'    => (int) $refunds->whereIn('status', ['succeeded', 'partial'])
                ->sum(fn ($r) => (int) ($r->refunded_to_wallet_minor ?? 0) + (int) ($r->refunded_to_original_minor ?? 0)),
            'wallet_refunded_minor'   => (int) $refunds->whereIn('status', ['succeeded', 'partial'])
                ->sum(fn ($r) => (int) ($r->refunded_to_wallet_minor ?? 0)),
            'external_refunded_minor' => (int) $refunds->whereIn('status', ['succeeded', 'partial'])
                ->sum(fn ($r) => (int) ($r->refunded_to_original_minor ?? 0)),
        ];

        $refundState = [
            'latest_status'              => $latestRefund->status ?? 'none',
            'latest_method'              => $latestRefund->method ?? null,
            'latest_requested_at'        => $latestRefund->requested_at ?? null,
            'latest_processed_at'        => $latestRefund->processed_at ?? null,
            'latest_failure_reason'      => $latestRefund->failure_reason ?? null,
            'latest_provider'            => $latestRefund->provider ?? null,
            'latest_provider_refund_id'  => $latestRefund->provider_refund_id ?? null,
            'latest_provider_order_id'   => $latestRefund->provider_order_id ?? null,
            'latest_provider_capture_id' => $latestRefund->provider_capture_id ?? null,
        ];

        $finance = [
            'currency'                     => $currency,
            'status'                       => (string) ($reservation->status ?? ''),
            'payment_status'               => $paymentStatus,
            'settlement_status'            => (string) ($reservation->settlement_status ?? 'pending'),
            'funding_status'               => (string) ($reservation->funding_status ?? 'external_only'),

            'subtotal_minor'               => $subtotal,
            'fees_minor'                   => $fees,
            'total_minor'                  => $total,

            'latest_refund_provider'       => $refundState['latest_provider'] ?? null,
            'latest_provider_refund_id'    => $refundState['latest_provider_refund_id'] ?? null,
            'latest_provider_order_id'     => $refundState['latest_provider_order_id'] ?? null,
            'latest_provider_capture_id'   => $refundState['latest_provider_capture_id'] ?? null,

            'refund_total_minor'           => (int) ($refundSummary['total_refunded_minor'] ?? 0),
            'wallet_refunded_minor'        => (int) ($refundSummary['wallet_refunded_minor'] ?? 0),
            'external_refunded_minor'      => (int) ($refundSummary['external_refunded_minor'] ?? 0),

            'wallet_used_minor'            => $walletUsedMinor,
            'gateway_paid_minor'           => $gatewayPaidMinor,

            'client_paid_minor'            => $clientPaid,
            'client_penalty_minor'         => $clientPenalty,
            'client_net_paid_minor'        => $clientNetPaid,

            'coach_earned_minor'           => $coachEarned,
            'coach_comp_minor'             => $coachComp,
            'coach_penalty_minor'          => $coachPenalty,
            'coach_total_earning_minor'    => $coachTotalEarning,
            'coach_net_minor'              => $coachTotalEarning,

            'platform_earned_minor'        => $platformEarned,

            'coach_gross_minor'            => (int) ($reservation->coach_gross_minor ?? 0),
            'coach_commission_minor'       => (int) ($reservation->coach_commission_minor ?? 0),

            'escrow_release_at'            => $reservation->escrow_release_at,
            'last_slot_end_utc'            => $reservation->last_slot_end_utc,

            'latest_refund_status'         => $refundState['latest_status'],
            'latest_refund_method'         => $refundState['latest_method'],
            'latest_refund_requested_at'   => $refundState['latest_requested_at'],
            'latest_refund_processed_at'   => $refundState['latest_processed_at'],
            'latest_refund_error'          => $refundState['latest_failure_reason'],
        ];

        $disputes = collect($reservation->disputes ?? []);
        $disputeSummary = [
            'all_count'           => $disputes->count(),
            'client_count'        => $disputes->where('opened_by_role', 'client')->count(),
            'coach_count'         => $disputes->where('opened_by_role', 'coach')->count(),
            'open_count'          => $disputes->whereIn('status', ['open', 'opened', 'in_review'])->count(),
            'resolved_count'      => $disputes->whereIn('status', ['resolved', 'rejected', 'closed'])->count(),
            'client_wins_count'   => $disputes->where('opened_by_role', 'client')->whereIn('decision_action', ['refund_full', 'refund_service'])->count(),
            'client_losses_count' => $disputes->where('opened_by_role', 'client')->whereIn('decision_action', ['pay_coach', 'reject'])->count(),
            'coach_wins_count'    => $disputes->where('opened_by_role', 'coach')->whereIn('decision_action', ['pay_coach'])->count(),
            'coach_losses_count'  => $disputes->where('opened_by_role', 'coach')->whereIn('decision_action', ['refund_full', 'refund_service', 'reject'])->count(),
        ];

        $disputeRows = $disputes
            ->sortByDesc(fn ($dispute) => $dispute->resolved_at ?? $dispute->decided_at ?? $dispute->created_at)
            ->values();

        $slotDetails = collect($reservation->slots ?? [])
            ->sortBy('start_utc')
            ->map(function ($slot) {
                $start = $slot->start_utc ? CarbonImmutable::parse($slot->start_utc)->utc() : null;
                $end   = $slot->end_utc ? CarbonImmutable::parse($slot->end_utc)->utc() : null;

                $baseDeadline = $start ? $start->addMinutes(5) : null;
                $extendAt     = $start ? $start->addMinutes(4) : null;

                $effectiveDeadline = null;
                if ($start) {
                    if (!empty($slot->extended_until_utc)) {
                        $effectiveDeadline = CarbonImmutable::parse($slot->extended_until_utc)->utc();
                    } elseif (!empty($slot->wait_deadline_utc)) {
                        $effectiveDeadline = CarbonImmutable::parse($slot->wait_deadline_utc)->utc();
                    } else {
                        $effectiveDeadline = $baseDeadline;
                    }
                }

                $info = is_array($slot->info_json)
                    ? $slot->info_json
                    : (json_decode($slot->info_json ?? '[]', true) ?: []);

                $clientIn = !empty($slot->client_checked_in_at);
                $coachIn  = !empty($slot->coach_checked_in_at);

                $derivedOutcome = null;
                if (($slot->session_status ?? '') === 'no_show_client') {
                    $derivedOutcome = 'Coach attended, client did not.';
                } elseif (($slot->session_status ?? '') === 'no_show_coach') {
                    $derivedOutcome = 'Client attended, coach did not.';
                } elseif (($slot->session_status ?? '') === 'no_show_both') {
                    $derivedOutcome = 'Neither client nor coach attended.';
                } elseif (($slot->session_status ?? '') === 'live') {
                    $derivedOutcome = 'Both parties checked in.';
                } elseif (($slot->session_status ?? '') === 'cancelled') {
                    $derivedOutcome = 'Slot was cancelled/finalized without session.';
                }

                return [
                    'id'                     => $slot->id,
                    'slot_date'              => $slot->slot_date,

                    'start_utc'              => $start,
                    'end_utc'                => $end,
                    'start_ts'               => $start?->timestamp,
                    'end_ts'                 => $end?->timestamp,

                    'session_status'         => $slot->session_status,
                    'derived_outcome'        => $derivedOutcome,

                    'client_checked_in'      => $clientIn,
                    'coach_checked_in'       => $coachIn,
                    'client_checked_in_at'   => $slot->client_checked_in_at,
                    'coach_checked_in_at'    => $slot->coach_checked_in_at,

                    'client_lat'             => $slot->client_lat,
                    'client_lng'             => $slot->client_lng,
                    'coach_lat'              => $slot->coach_lat,
                    'coach_lng'              => $slot->coach_lng,

                    'wait_deadline_utc'      => $slot->wait_deadline_utc,
                    'base_deadline_utc'      => $baseDeadline,
                    'effective_deadline_utc' => $effectiveDeadline,
                    'extend_at_utc'          => $extendAt,
                    'extended_until_utc'     => $slot->extended_until_utc,
                    'extended'               => !empty($slot->extended_until_utc),

                    'nudge1_sent_at'         => $slot->nudge1_sent_at,
                    'nudge2_sent_at'         => $slot->nudge2_sent_at,

                    'finalized_at'           => $slot->finalized_at,
                    'auto_cancelled_at'      => $slot->auto_cancelled_at,

                    'info'                   => $info,
                    'auto_finalized'         => (bool) ($info['auto_finalized'] ?? false),
                    'extended_by'            => $info['extended_by'] ?? null,
                    'extended_at_utc'        => $info['extended_at_utc'] ?? null,
                    'deadline_utc_from_info' => $info['deadline_utc'] ?? null,
                ];
            })
            ->values();

        $sessionSummary = [
            'slots_count'           => $slotDetails->count(),
            'live_count'            => $slotDetails->where('session_status', 'live')->count(),
            'waiting_for_client'    => $slotDetails->where('session_status', 'waiting_for_client')->count(),
            'waiting_for_coach'     => $slotDetails->where('session_status', 'waiting_for_coach')->count(),
            'client_no_show_count'  => $slotDetails->where('session_status', 'no_show_client')->count(),
            'coach_no_show_count'   => $slotDetails->where('session_status', 'no_show_coach')->count(),
            'both_no_show_count'    => $slotDetails->where('session_status', 'no_show_both')->count(),
            'cancelled_count'       => $slotDetails->where('session_status', 'cancelled')->count(),
            'finalized_count'       => $slotDetails->filter(fn ($s) => !empty($s['finalized_at']))->count(),
            'extended_count'        => $slotDetails->filter(fn ($s) => !empty($s['extended']))->count(),
            'client_checked_in_any' => $slotDetails->contains(fn ($s) => !empty($s['client_checked_in'])),
            'coach_checked_in_any'  => $slotDetails->contains(fn ($s) => !empty($s['coach_checked_in'])),
        ];

        $firstSlotStartUtc = optional($slotDetails->first())['start_utc'] ?? null;
        $cancelledAtUtc = $reservation->cancelled_at
            ? CarbonImmutable::parse($reservation->cancelled_at)->utc()
            : null;

        $hoursUntilFirstSlotAtCancellation = null;
        if ($firstSlotStartUtc && $cancelledAtUtc) {
            $hoursUntilFirstSlotAtCancellation = $cancelledAtUtc->diffInRealHours($firstSlotStartUtc, false);
        }

        $isCancelled = in_array(strtolower((string) $reservation->status), ['cancelled', 'canceled'], true);

        $cancellationDetails = [
            'is_cancelled'                        => $isCancelled,
            'cancelled_by'                        => $reservation->cancelled_by,
            'cancelled_at'                        => $reservation->cancelled_at,
            'cancel_reason'                       => $reservation->cancel_reason,
            'hours_until_first_slot'              => $hoursUntilFirstSlotAtCancellation,

            'refund_method'                       => $refundState['latest_method'],
            'refund_status'                       => $refundState['latest_status'],
            'refund_requested_at'                 => $refundState['latest_requested_at'],
            'refund_processed_at'                 => $refundState['latest_processed_at'],
            'refund_error'                        => $refundState['latest_failure_reason'],

            'provider'                            => $refundState['latest_provider'] ?? null,
            'provider_refund_id'                  => $refundState['latest_provider_refund_id'] ?? null,
            'provider_order_id'                   => $refundState['latest_provider_order_id'] ?? null,
            'provider_capture_id'                 => $refundState['latest_provider_capture_id'] ?? null,
            'refund_total_minor'                  => (int) ($refundSummary['total_refunded_minor'] ?? 0),
            'refund_wallet_minor'                 => (int) ($refundSummary['wallet_refunded_minor'] ?? 0),
            'refund_external_minor'               => (int) ($refundSummary['external_refunded_minor'] ?? 0),

            'client_penalty_minor'                => (int) ($reservation->client_penalty_minor ?? 0),
            'coach_penalty_minor'                 => (int) ($reservation->coach_penalty_minor ?? 0),
            'coach_comp_minor'                    => (int) ($reservation->coach_comp_minor ?? 0),

            'platform_earned_minor'               => (int) ($reservation->platform_earned_minor ?? 0),

            'platform_fee_refund_requested_minor' => null,
            'platform_fee_refunded_minor'         => null,

            'rule_bucket'                         => $this->deriveCancellationRuleBucket(
                (string) ($reservation->cancelled_by ?? ''),
                $hoursUntilFirstSlotAtCancellation
            ),
            'human_summary'                       => $this->deriveCancellationHumanSummary(
                $reservation,
                $hoursUntilFirstSlotAtCancellation
            ),
        ];

        $paymentRows = collect($reservation->payments ?? [])
            ->sortByDesc(fn ($payment) => $payment->created_at ?? $payment->id)
            ->values()
            ->map(function ($payment) use ($refundRows) {
                return [
                    'id'                    => $payment->id,
                    'provider'              => $payment->provider,
                    'method'                => $payment->method,
                    'status'                => $payment->status,
                    'currency'              => $payment->currency,
                    'amount_total'          => (int) ($payment->amount_total ?? 0),

                    'provider_payment_id'   => $payment->provider_payment_id,
                    'provider_order_id'     => $payment->provider_order_id,
                    'provider_capture_id'   => $payment->provider_capture_id,
                    'provider_charge_id'    => $payment->provider_charge_id,
                    'provider_refund_id'    => $payment->provider_refund_id,

                    'created_at'            => $payment->created_at,
                    'succeeded_at'          => $payment->succeeded_at,
                    'meta'                  => $payment->meta,

                    'refund_attempts_count' => $refundRows->where('payment_id', $payment->id)->count(),
                ];
            });

        $timeline = collect([
            [
                'type'  => 'booking_created',
                'label' => 'Booking Created',
                'at'    => $reservation->created_at,
                'meta'  => null,
            ],
            [
                'type'  => 'payment',
                'label' => 'Payment',
                'at'    => $reservation->payment?->created_at ?? null,
                'meta'  => $reservation->payment_status,
            ],
            [
                'type'  => 'completed',
                'label' => 'Completed',
                'at'    => $reservation->completed_at ?? null,
                'meta'  => null,
            ],
            [
                'type'  => 'cancelled',
                'label' => 'Cancelled',
                'at'    => $reservation->cancelled_at ?? null,
                'meta'  => $reservation->cancelled_by,
            ],
        ])
            ->merge(
                $refundRows->map(fn ($refund) => [
                    'type'  => 'refund',
                    'label' => 'Refund #' . $refund->id,
                    'at'    => $refund->processed_at ?? $refund->requested_at ?? $refund->created_at,
                    'meta'  => $refund->status,
                ])
            )
            ->merge(
                $disputeRows->map(fn ($dispute) => [
                    'type'  => 'dispute',
                    'label' => 'Dispute #' . $dispute->id,
                    'at'    => $dispute->resolved_at ?? $dispute->decided_at ?? $dispute->created_at,
                    'meta'  => $dispute->status,
                ])
            )
            ->merge(
                $slotDetails->map(function ($slot) {
                    return [
                        'type'  => 'slot',
                        'label' => 'Slot #' . $slot['id'] . ' - ' . ($slot['session_status'] ?: 'unknown'),
                        'at'    => $slot['finalized_at'] ?? $slot['coach_checked_in_at'] ?? $slot['client_checked_in_at'] ?? $slot['start_utc'],
                        'meta'  => $slot['derived_outcome'],
                    ];
                })
            )
            ->filter(fn ($item) => !empty($item['at']))
            ->sortByDesc('at')
            ->values();

        return view('admin.bookings.show', compact(
            'reservation',
            'finance',
            'refundSummary',
            'refundRows',
            'disputeSummary',
            'disputeRows',
            'timeline',
            'slotDetails',
            'sessionSummary',
            'cancellationDetails',
            'paymentRows'
        ));
    }

    private function reservationBaseQuery(): Builder
    {
        return Reservation::query()
            ->with([
                'service.coach',
                'client',
                'package',
                'payments',
                'refunds',
                'disputes',
                'slots',
            ])
            ->withCount([
                'payments',
                'refunds',
                'disputes',
                'slots',
            ])
            ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
            ->leftJoin('users as clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('users as coaches', 'coaches.id', '=', 'reservations.coach_id')
            ->select('reservations.*');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $q = $filters['q'];

            $query->where(function (Builder $w) use ($q) {
                if (ctype_digit($q)) {
                    $id = (int) $q;

                    $w->orWhere('reservations.id', $id)
                        ->orWhere('reservations.client_id', $id)
                        ->orWhere('reservations.coach_id', $id)
                        ->orWhere('reservations.service_id', $id);
                }

                $w->orWhere('reservations.status', 'like', "%{$q}%")
                    ->orWhere('reservations.payment_status', 'like', "%{$q}%")
                    ->orWhere('reservations.settlement_status', 'like', "%{$q}%")
                    ->orWhere('reservations.funding_status', 'like', "%{$q}%")
                    ->orWhere('reservations.service_title_snapshot', 'like', "%{$q}%")
                    ->orWhere('reservations.package_name_snapshot', 'like', "%{$q}%")
                    ->orWhere('services.title', 'like', "%{$q}%")
                    ->orWhere('clients.email', 'like', "%{$q}%")
                    ->orWhere('coaches.email', 'like', "%{$q}%")
                    ->orWhereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) LIKE ?", ["%{$q}%"])
                    ->orWhereHas('refunds', function (Builder $r) use ($q) {
                        $r->where('status', 'like', "%{$q}%")
                            ->orWhere('method', 'like', "%{$q}%")
                            ->orWhere('provider', 'like', "%{$q}%")
                            ->orWhere('failure_reason', 'like', "%{$q}%");
                    })
                    ->orWhereHas('disputes', function (Builder $d) use ($q) {
                        $d->where('status', 'like', "%{$q}%")
                            ->orWhere('opened_by_role', 'like', "%{$q}%")
                            ->orWhere('decision_action', 'like', "%{$q}%")
                            ->orWhere('reason', 'like', "%{$q}%")
                            ->orWhere('subject', 'like', "%{$q}%");
                    });
            });
        }

        if ($filters['service'] !== '') {
            $query->where(function (Builder $w) use ($filters) {
                $w->where('services.title', 'like', '%' . $filters['service'] . '%')
                    ->orWhere('reservations.service_title_snapshot', 'like', '%' . $filters['service'] . '%');
            });
        }

        if ($filters['coach'] !== '') {
            $query->where(function (Builder $w) use ($filters) {
                $w->whereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) LIKE ?", ['%' . $filters['coach'] . '%'])
                    ->orWhere('coaches.email', 'like', '%' . $filters['coach'] . '%');
            });
        }

        if ($filters['client'] !== '') {
            $query->where(function (Builder $w) use ($filters) {
                $w->whereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) LIKE ?", ['%' . $filters['client'] . '%'])
                    ->orWhere('clients.email', 'like', '%' . $filters['client'] . '%');
            });
        }

        if ($filters['status'] !== '') {
            $query->where('reservations.status', $filters['status']);
        }

        if ($filters['payment_status'] !== '') {
            $query->where('reservations.payment_status', $filters['payment_status']);
        }

        if ($filters['settlement_status'] !== '') {
            $query->where('reservations.settlement_status', $filters['settlement_status']);
        }

        if ($filters['refund_status'] !== '') {
            $query->whereHas('refunds', fn (Builder $r) => $r->where('status', $filters['refund_status']));
        }

        if ($filters['funding_status'] !== '') {
            $query->where('reservations.funding_status', $filters['funding_status']);
        }

        if ($filters['provider'] !== '') {
            $query->whereHas('payments', fn (Builder $p) => $p->where('provider', $filters['provider']));
        }

        if ($filters['method'] !== '') {
            $query->whereHas('payments', fn (Builder $p) => $p->where('method', $filters['method']));
        }

        if ($filters['dispute_side'] !== '') {
            $query->whereHas('disputes', fn (Builder $d) => $d->where('opened_by_role', $filters['dispute_side']));
        }

        if ($filters['dispute_result'] !== '') {
            $this->applyDisputeResultFilter($query, $filters['dispute_result']);
        }

        if ($filters['date_from']) {
            $query->whereRaw(
                'COALESCE(reservations.completed_at, reservations.cancelled_at, reservations.refund_processed_at, reservations.created_at) >= ?',
                [$filters['date_from']]
            );
        }

        if ($filters['date_to']) {
            $query->whereRaw(
                'COALESCE(reservations.completed_at, reservations.cancelled_at, reservations.refund_processed_at, reservations.created_at) <= ?',
                [$filters['date_to']]
            );
        }

        if ($filters['amount_from_minor'] !== null) {
            $query->where('reservations.total_minor', '>=', $filters['amount_from_minor']);
        }

        if ($filters['amount_to_minor'] !== null) {
            $query->where('reservations.total_minor', '<=', $filters['amount_to_minor']);
        }

        $this->applyTabFilter($query, $filters['tab']);
    }

    private function applyTabFilter(Builder $query, string $tab): void
    {
        switch ($tab) {
            case 'completed':
                $query->where(function (Builder $w) {
                    $w->where('reservations.status', 'completed')
                        ->orWhere('reservations.settlement_status', 'paid');
                });
                break;

            case 'cancelled':
                $query->whereIn('reservations.status', ['cancelled', 'canceled']);
                break;

            case 'no_show':
                $query->whereIn('reservations.status', ['no_show', 'no_show_client', 'no_show_coach', 'no_show_both']);
                break;

            case 'refunds':
                $query->whereHas('refunds');
                break;

            case 'disputed':
                $query->whereHas('disputes');
                break;

            case 'client_disputes':
                $query->whereHas('disputes', fn (Builder $d) => $d->where('opened_by_role', 'client'));
                break;

            case 'coach_disputes':
                $query->whereHas('disputes', fn (Builder $d) => $d->where('opened_by_role', 'coach'));
                break;
        }
    }

    private function applyDisputeResultFilter(Builder $query, string $result): void
    {
        match ($result) {
            'client_win' => $query->whereHas('disputes', function (Builder $d) {
                $d->where('opened_by_role', 'client')
                    ->whereIn('decision_action', ['refund_full', 'refund_service']);
            }),
            'coach_win' => $query->whereHas('disputes', function (Builder $d) {
                $d->where('opened_by_role', 'coach')
                    ->where('decision_action', 'pay_coach');
            }),
            'client_loss' => $query->whereHas('disputes', function (Builder $d) {
                $d->where('opened_by_role', 'client')
                    ->whereIn('decision_action', ['pay_coach', 'reject']);
            }),
            'coach_loss' => $query->whereHas('disputes', function (Builder $d) {
                $d->where('opened_by_role', 'coach')
                    ->whereIn('decision_action', ['refund_full', 'refund_service', 'reject']);
            }),
            default => null,
        };
    }

    private function buildTabCounts(array $filters): array
    {
        $baseFilters = $filters;
        $tabs = ['all', 'completed', 'cancelled', 'no_show', 'refunds', 'disputed', 'client_disputes', 'coach_disputes'];

        $counts = [];
        foreach ($tabs as $tab) {
            $f = $baseFilters;
            $f['tab'] = $tab;
            $counts[$tab] = $this->filteredReservationCollection($f)->count();
        }

        return $counts;
    }

    private function filteredReservationCollection(array $filters): Collection
    {
        $query = $this->reservationBaseQuery();
        $this->applyFilters($query, $filters);

        return $query->get();
    }

    private function resolveFilters(Request $request): array
    {
        $tab = strtolower((string) $request->query('tab', 'all'));
        $allowedTabs = ['all', 'completed', 'cancelled', 'no_show', 'refunds', 'disputed', 'client_disputes', 'coach_disputes'];

        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'all';
        }

        $tz  = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $range = strtolower((string) $request->query('range', 'lifetime'));
        $from  = $request->query('from');
        $to    = $request->query('to');

        $year  = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $day   = $request->query('day');

        if (in_array($range, ['all', 'all_time', 'lifetime'], true)) {
            $range = 'lifetime';
        }

        if ($range === 'custom' || $from || $to) {
            $range = 'custom';
        }

        $dateFrom = null;
        $dateTo   = null;
        $periodLabel = 'All time';

        switch ($range) {
            case 'daily':
                $baseDay = $day ? Carbon::parse($day, $tz) : $now->copy();
                $dateFrom = $baseDay->copy()->startOfDay();
                $dateTo   = $baseDay->copy()->endOfDay();
                $periodLabel = $baseDay->format('Y-m-d');
                break;

            case 'weekly':
                $dateFrom = $now->copy()->startOfWeek()->startOfDay();
                $dateTo   = $now->copy()->endOfWeek()->endOfDay();
                $periodLabel = $dateFrom->format('Y-m-d') . ' → ' . $dateTo->format('Y-m-d');
                break;

            case 'monthly':
                $baseMonth = $now->copy()->setYear($year)->setMonth($month);
                $dateFrom = $baseMonth->copy()->startOfMonth()->startOfDay();
                $dateTo   = $baseMonth->copy()->endOfMonth()->endOfDay();
                $periodLabel = $baseMonth->format('F Y');
                break;

            case 'yearly':
                $baseYear = $now->copy()->setYear($year);
                $dateFrom = $baseYear->copy()->startOfYear()->startOfDay();
                $dateTo   = $baseYear->copy()->endOfYear()->endOfDay();
                $periodLabel = (string) $year;
                break;

            case 'custom':
                $dateFrom = $from ? Carbon::parse($from, $tz)->startOfDay() : null;
                $dateTo   = $to ? Carbon::parse($to, $tz)->endOfDay() : null;
                $periodLabel = trim(
                    ($dateFrom ? $dateFrom->format('Y-m-d') : '…') . ' → ' .
                    ($dateTo ? $dateTo->format('Y-m-d') : '…')
                );
                break;

            case 'lifetime':
            default:
                $dateFrom = null;
                $dateTo   = null;
                $periodLabel = 'All time';
                break;
        }

        return [
            'tab'               => $tab,
            'q'                 => trim((string) $request->query('q', '')),
            'service'           => trim((string) $request->query('service', '')),
            'coach'             => trim((string) $request->query('coach', '')),
            'client'            => trim((string) $request->query('client', '')),
            'status'            => strtolower((string) $request->query('status', '')),
            'payment_status'    => strtolower((string) $request->query('payment_status', '')),
            'settlement_status' => strtolower((string) $request->query('settlement_status', '')),
            'refund_status'     => strtolower((string) $request->query('refund_status', '')),
            'funding_status'    => strtolower((string) $request->query('funding_status', '')),
            'provider'          => strtolower((string) $request->query('provider', '')),
            'method'            => (string) $request->query('method', ''),
            'dispute_side'      => strtolower((string) $request->query('dispute_side', '')),
            'dispute_result'    => strtolower((string) $request->query('dispute_result', '')),

            'range'             => $range,
            'period_label'      => $periodLabel,
            'year'              => $year,
            'month'             => $month,
            'day'               => $day,
            'date_from'         => $dateFrom,
            'date_to'           => $dateTo,

            'amount_from_minor' => $request->filled('amount_from') ? (int) round(((float) $request->query('amount_from')) * 100) : null,
            'amount_to_minor'   => $request->filled('amount_to') ? (int) round(((float) $request->query('amount_to')) * 100) : null,
        ];
    }

    private function filterOptions(): array
    {
        return [
            'tabs' => [
                'all'             => 'All',
                'completed'       => 'Completed / Paid Out',
                'cancelled'       => 'Cancelled',
                'no_show'         => 'No Show',
                'refunds'         => 'Refunds',
                'disputed'        => 'Disputed',
                'client_disputes' => 'Client Disputes',
                'coach_disputes'  => 'Coach Disputes',
            ],
            'ranges' => [
                'daily'    => 'Daily',
                'weekly'   => 'Weekly',
                'monthly'  => 'Monthly',
                'yearly'   => 'Yearly',
                'lifetime' => 'All Time',
                'custom'   => 'Custom',
            ],
            'statuses' => [
                'pending'        => 'Pending',
                'booked'         => 'Booked',
                'completed'      => 'Completed',
                'cancelled'      => 'Cancelled',
                'canceled'       => 'Canceled',
                'no_show'        => 'No Show',
                'no_show_client' => 'Client No Show',
                'no_show_coach'  => 'Coach No Show',
                'no_show_both'   => 'Both No Show',
            ],
            'payment_statuses' => [
                'requires_payment' => 'Requires Payment',
                'paid'             => 'Paid',
                'failed'           => 'Failed',
            ],
            'settlement_statuses' => [
                'pending'          => 'Pending',
                'paid'             => 'Paid',
                'refund_pending'   => 'Refund Pending',
                'refunded'         => 'Refunded',
                'refunded_partial' => 'Refunded Partial',
                'in_dispute'       => 'In Dispute',
                'cancelled'        => 'Cancelled',
                'canceled'         => 'Canceled',
            ],
            'refund_statuses' => [
                'pending_choice' => 'Pending Choice',
                'processing'     => 'Processing',
                'succeeded'      => 'Succeeded',
                'partial'        => 'Partial',
                'failed'         => 'Failed',
            ],
            'funding_statuses' => [
                'wallet_only'   => 'Wallet Only',
                'mixed'         => 'Mixed',
                'external_only' => 'External Only',
            ],
            'providers' => [
                'stripe' => 'Stripe',
                'paypal' => 'PayPal',
                'wallet' => 'Wallet',
            ],
            'methods' => [
                'VISA'            => 'Visa',
                'MASTERCARD'      => 'Mastercard',
                'AMEX'            => 'American Express',
                'DISCOVER'        => 'Discover',
                'CARD'            => 'Card',
                'KLARNA'          => 'Klarna',
                'PAYPAL'          => 'PayPal',
                'paypal'          => 'PayPal',
                'PLATFORM_CREDIT' => 'Platform Credit',
            ],
            'dispute_sides' => [
                'client' => 'Client Raised',
                'coach'  => 'Coach Raised',
            ],
            'dispute_results' => [
                'client_win'  => 'Client Win',
                'coach_win'   => 'Coach Win',
                'client_loss' => 'Client Loss',
                'coach_loss'  => 'Coach Loss',
            ],
        ];
    }

    private function deriveCancellationRuleBucket(string $cancelledBy, ?float $hoursUntil): ?string
    {
        if ($hoursUntil === null) {
            return null;
        }

        $cancelledBy = strtolower(trim($cancelledBy));

        if (in_array($cancelledBy, ['admin', 'system'], true)) {
            return 'admin_or_system_full_refund';
        }

        if ($hoursUntil >= 48) {
            return '48_plus_hours_full_refund';
        }

        if ($hoursUntil >= 24) {
            return $cancelledBy === 'coach'
                ? '24_to_48_hours_coach_cancel'
                : '24_to_48_hours_client_cancel';
        }

        return $cancelledBy === 'coach'
            ? 'under_24_hours_coach_cancel'
            : 'under_24_hours_client_cancel';
    }

    private function deriveCancellationHumanSummary(Reservation $reservation, ?float $hoursUntil): ?string
    {
        if (!in_array(strtolower((string) $reservation->status), ['cancelled', 'canceled'], true)) {
            return null;
        }

        $by = strtolower((string) ($reservation->cancelled_by ?? ''));

        if (in_array($by, ['admin', 'system'], true)) {
            return 'Cancelled by admin/system. Full refund path applies.';
        }

        if ($hoursUntil === null) {
            return 'Cancelled reservation. Timing window could not be derived.';
        }

        if ($hoursUntil >= 48) {
            return 'Cancelled 48+ hours before first slot. Full refund path applies.';
        }

        if ($hoursUntil >= 24) {
            if ($by === 'coach') {
                return 'Coach cancelled 24–48 hours before first slot. Full refund plus coach penalty path applies.';
            }

            return 'Client cancelled 24–48 hours before first slot. Coach compensation and client penalty path applies.';
        }

        if ($by === 'coach') {
            return 'Coach cancelled less than 24 hours before first slot. Full refund plus higher coach penalty path applies.';
        }

        return 'Client cancelled less than 24 hours before first slot. Higher coach compensation and client penalty path applies.';
    }

    private function succeededRefundRows($refunds): Collection
    {
        return collect($refunds ?? [])->whereIn('status', ['succeeded', 'partial']);
    }

    private function reservationRefundTotalMinor($reservation): int
    {
        return (int) $this->succeededRefundRows($reservation->refunds ?? [])
            ->sum(fn ($refund) => (int) ($refund->refunded_to_wallet_minor ?? 0) + (int) ($refund->refunded_to_original_minor ?? 0));
    }
}