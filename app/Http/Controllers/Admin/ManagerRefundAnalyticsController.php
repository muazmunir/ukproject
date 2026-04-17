<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Refund;
use App\Models\Users;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManagerRefundAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            auth()->check() && strtolower((string) auth()->user()->role) === 'manager',
            403
        );

        $tz  = config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($tz);

        $range = strtolower((string) $request->query('range', 'lifetime'));
        $from  = $request->query('from');
        $to    = $request->query('to');
        $year  = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $day   = $request->query('day');

        $staffId     = (int) $request->query('staff_id', 0);
        $status      = strtolower((string) $request->query('status', ''));
        $destination = strtolower((string) $request->query('destination', ''));
        $tab         = strtolower((string) $request->query('tab', 'overview'));
        $q           = trim((string) $request->query('q', ''));

        if (in_array($range, ['all', 'lifetime'], true)) {
            $range = 'lifetime';
        }

        if ($range === 'custom' || $from || $to) {
            $range = 'custom';
        }

        [$dateFrom, $dateTo, $periodLabel] = $this->resolvePeriod(
            $range,
            $day,
            $month,
            $year,
            $from,
            $to,
            $tz,
            $now
        );

        $fmt = fn (int $minor) => '$' . number_format($minor / 100, 2);

        /*
        |--------------------------------------------------------------------------
        | Agent = admin / manager
        |--------------------------------------------------------------------------
        */
        $staffOptions = Users::query()
            ->whereIn(DB::raw('LOWER(role)'), ['admin', 'manager'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'username', 'email', 'role']);

        $staffMap = $staffOptions->keyBy('id');

        /*
        |--------------------------------------------------------------------------
        | BASE REFUND QUERY
        |--------------------------------------------------------------------------
        */
        $refundBase = Refund::query()
            ->with([
                'reservation.service',
                'reservation.client',
                'reservation.coach',
                'reservation.disputes',
            ]);

        $this->applyRefundDateRange($refundBase, $dateFrom, $dateTo);
        $this->applyRefundCommonFilters($refundBase, $status, $destination, $q);

        /*
        |--------------------------------------------------------------------------
        | Cancellation Refunds
        | Cancellation refund = reservation cancelled + no dispute refund decision
        |--------------------------------------------------------------------------
        */
        $cancellationRefundBase = (clone $refundBase)
            ->whereHas('reservation', function ($rq) {
                $rq->whereNotNull('cancelled_at');
            })
            ->whereDoesntHave('reservation.disputes', function ($dq) {
                $dq->whereIn('decision_action', ['refund_full', 'refund_service']);
            });

        if ($staffId > 0) {
            $cancellationRefundBase->where('refunds.requested_by_user_id', $staffId);
        }

        /*
        |--------------------------------------------------------------------------
        | Dispute Refunds
        | Dispute refund = refund tied to dispute decision refund_full/refund_service
        |--------------------------------------------------------------------------
        */
        $disputeRefundBase = (clone $refundBase)
            ->whereHas('reservation.disputes', function ($dq) {
                $dq->whereIn('decision_action', ['refund_full', 'refund_service']);
            });

        if ($staffId > 0) {
            $disputeRefundBase->whereHas('reservation.disputes', function ($dq) use ($staffId) {
                $dq->whereIn('decision_action', ['refund_full', 'refund_service'])
                    ->where(function ($qq) use ($staffId) {
                        $qq->where('resolved_by_staff_id', $staffId)
                            ->orWhere('decided_by_staff_id', $staffId);
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Coach Payout Decisions
        |--------------------------------------------------------------------------
        */
        $coachPayoutBase = Dispute::query()
            ->with([
                'reservation.service',
                'reservation.client',
                'reservation.coach',
                'decidedBy',
            ])
            ->where('disputes.decision_action', 'pay_coach');

        $this->applyDisputeDateRange($coachPayoutBase, $dateFrom, $dateTo);

        if ($staffId > 0) {
            $coachPayoutBase->where(function ($q) use ($staffId) {
                $q->where('disputes.decided_by_staff_id', $staffId)
                    ->orWhere('disputes.resolved_by_staff_id', $staffId);
            });
        }

        if ($q !== '') {
            $coachPayoutBase->where(function ($qq) use ($q) {
                if (ctype_digit($q)) {
                    $qq->orWhere('disputes.id', (int) $q)
                        ->orWhere('disputes.reservation_id', (int) $q)
                        ->orWhere('disputes.decided_by_staff_id', (int) $q)
                        ->orWhere('disputes.resolved_by_staff_id', (int) $q);
                }

                $qq->orWhere('disputes.decision_action', 'like', "%{$q}%")
                    ->orWhereHas('reservation.service', fn ($s) => $s->where('title', 'like', "%{$q}%"))
                    ->orWhereHas('reservation.client', fn ($c) => $c->where('username', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('reservation.coach', fn ($c) => $c->where('username', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Paginated Tables
        |--------------------------------------------------------------------------
        */
        $cancellationRefundRows = (clone $cancellationRefundBase)
            ->orderByDesc(DB::raw('COALESCE(refunds.processed_at, refunds.requested_at, refunds.created_at)'))
            ->paginate(15, ['*'], 'cancellation_page')
            ->withQueryString();

        $disputeRefundRows = (clone $disputeRefundBase)
            ->orderByDesc(DB::raw('COALESCE(refunds.processed_at, refunds.requested_at, refunds.created_at)'))
            ->paginate(15, ['*'], 'dispute_page')
            ->withQueryString();

        $coachPayoutRows = (clone $coachPayoutBase)
            ->orderByDesc(DB::raw('COALESCE(disputes.decided_at, disputes.resolved_at, disputes.created_at)'))
            ->paginate(15, ['*'], 'payout_page')
            ->withQueryString();

        $cancellationRefundRows->getCollection()->transform(
            fn ($refund) => $this->decorateRefundRow($refund, $staffMap, 'cancellation')
        );

        $disputeRefundRows->getCollection()->transform(
            fn ($refund) => $this->decorateRefundRow($refund, $staffMap, 'dispute')
        );

        $coachPayoutRows->getCollection()->transform(function ($row) use ($staffMap) {
            $handlerId = (int) ($row->resolved_by_staff_id ?? $row->decided_by_staff_id ?? 0);
            $handler   = $staffMap->get($handlerId);

            $row->decided_by_name = $handler
                ? $this->personName($handler)
                : 'System';

            $row->decision_label = 'Coach payout';
            $row->funds_destination_label = 'Coach';
            $row->event_at = $row->resolved_at ?? $row->decided_at ?? $row->created_at;

            return $row;
        });

        /*
        |--------------------------------------------------------------------------
        | KPI - Overview
        |--------------------------------------------------------------------------
        */
        $allRefundCount = (int) (clone $refundBase)->count();

        $allRefundMinor = (int) (clone $refundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.actual_amount_minor,0)'));

        $walletRefundMinor = (int) (clone $refundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0)'));

        $originalRefundMinor = (int) (clone $refundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_original_minor,0)'));

        $processingRefundCount = (int) (clone $refundBase)
            ->where('refunds.status', 'processing')
            ->count();

        $failedRefundCount = (int) (clone $refundBase)
            ->where('refunds.status', 'failed')
            ->count();

        $avgRefundMinor = $allRefundCount > 0
            ? (int) round($allRefundMinor / max(1, $allRefundCount))
            : 0;

        /*
        |--------------------------------------------------------------------------
        | KPI - Cancellation Refunds
        |--------------------------------------------------------------------------
        */
        $cancellationRefundCount = (int) (clone $cancellationRefundBase)->count();

        $cancellationRefundMinor = (int) (clone $cancellationRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.actual_amount_minor,0)'));

        $cancellationWalletMinor = (int) (clone $cancellationRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0)'));

        $cancellationOriginalMinor = (int) (clone $cancellationRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_original_minor,0)'));

        /*
        |--------------------------------------------------------------------------
        | KPI - Dispute Refunds
        |--------------------------------------------------------------------------
        */
        $disputeRefundCount = (int) (clone $disputeRefundBase)->count();

        $disputeRefundMinor = (int) (clone $disputeRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.actual_amount_minor,0)'));

        $disputeWalletMinor = (int) (clone $disputeRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0)'));

        $disputeOriginalMinor = (int) (clone $disputeRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_original_minor,0)'));

        /*
        |--------------------------------------------------------------------------
        | KPI - Coach Payouts
        |--------------------------------------------------------------------------
        */
        $coachPayoutCount = (int) (clone $coachPayoutBase)->count();

        $coachPayoutMinor = (int) (clone $coachPayoutBase)
            ->join('reservations', 'reservations.id', '=', 'disputes.reservation_id')
            ->sum(DB::raw('COALESCE(reservations.coach_earned_minor,0)'));

        /*
        |--------------------------------------------------------------------------
        | Agent Performance
        |--------------------------------------------------------------------------
        | Totals are intentionally built from:
        | - cancellation refunds handled by refund.requested_by_user_id
        | - dispute refunds handled by dispute resolved/decided staff
        |--------------------------------------------------------------------------
        */
        $agentCancellationRefunds = (clone $cancellationRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->get();

        $agentDisputeRefunds = (clone $disputeRefundBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->get();

        $agentPayoutSummaryRaw = (clone $coachPayoutBase)
            ->join('reservations', 'reservations.id', '=', 'disputes.reservation_id')
            ->selectRaw('
                COALESCE(disputes.resolved_by_staff_id, disputes.decided_by_staff_id) as staff_id,
                COUNT(*) as payout_count,
                SUM(COALESCE(reservations.coach_earned_minor,0)) as payout_minor
            ')
            ->groupBy(DB::raw('COALESCE(disputes.resolved_by_staff_id, disputes.decided_by_staff_id)'))
            ->get();

        $agentPerformance = $this->buildAgentPerformanceSummary(
            $staffOptions,
            $staffMap,
            $agentCancellationRefunds,
            $agentDisputeRefunds,
            $agentPayoutSummaryRaw
        );

        $topRefundAgent = $agentPerformance->sortByDesc('refunds_count')->first();
        $topRefundAmountAgent = $agentPerformance->sortByDesc('refunds_minor')->first();

        return view('admin.refunds.analytics', compact(
            'periodLabel',
            'range',
            'dateFrom',
            'dateTo',
            'staffId',
            'status',
            'destination',
            'tab',
            'q',
            'year',
            'month',

            'fmt',
            'staffOptions',

            'allRefundCount',
            'allRefundMinor',
            'walletRefundMinor',
            'originalRefundMinor',
            'processingRefundCount',
            'failedRefundCount',
            'avgRefundMinor',

            'cancellationRefundCount',
            'cancellationRefundMinor',
            'cancellationWalletMinor',
            'cancellationOriginalMinor',

            'disputeRefundCount',
            'disputeRefundMinor',
            'disputeWalletMinor',
            'disputeOriginalMinor',

            'coachPayoutCount',
            'coachPayoutMinor',

            'cancellationRefundRows',
            'disputeRefundRows',
            'coachPayoutRows',

            'agentPerformance',
            'topRefundAgent',
            'topRefundAmountAgent'
        ));
    }

    private function resolvePeriod(
        string $range,
        $day,
        int $month,
        int $year,
        $from,
        $to,
        string $tz,
        CarbonImmutable $now
    ): array {
        $periodLabel = 'All time';
        $dateFrom = null;
        $dateTo = null;

        switch ($range) {
            case 'daily':
                $baseDay = $day ? CarbonImmutable::parse($day, $tz) : $now;
                $dateFrom = $baseDay->startOfDay();
                $dateTo = $baseDay->endOfDay();
                $periodLabel = $baseDay->format('Y-m-d');
                break;

            case 'weekly':
                $dateFrom = $now->startOfWeek()->startOfDay();
                $dateTo = $now->endOfWeek()->endOfDay();
                $periodLabel = $dateFrom->format('Y-m-d') . ' → ' . $dateTo->format('Y-m-d');
                break;

            case 'monthly':
                $baseMonth = $now->setYear($year)->setMonth($month);
                $dateFrom = $baseMonth->startOfMonth()->startOfDay();
                $dateTo = $baseMonth->endOfMonth()->endOfDay();
                $periodLabel = $baseMonth->format('F Y');
                break;

            case 'yearly':
                $baseYear = $now->setYear($year);
                $dateFrom = $baseYear->startOfYear()->startOfDay();
                $dateTo = $baseYear->endOfYear()->endOfDay();
                $periodLabel = (string) $year;
                break;

            case 'custom':
                $dateFrom = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : null;
                $dateTo   = $to ? CarbonImmutable::parse($to, $tz)->endOfDay() : null;
                $periodLabel = trim(
                    ($dateFrom ? $dateFrom->format('Y-m-d') : '…') . ' → ' .
                    ($dateTo ? $dateTo->format('Y-m-d') : '…')
                );
                break;

            case 'lifetime':
            default:
                $dateFrom = null;
                $dateTo = null;
                $periodLabel = 'All time';
                break;
        }

        return [$dateFrom, $dateTo, $periodLabel];
    }

    private function applyRefundDateRange($query, $dateFrom, $dateTo): void
    {
        $query
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where(function ($qq) use ($dateFrom) {
                    $qq->where('refunds.requested_at', '>=', $dateFrom)
                        ->orWhere('refunds.processed_at', '>=', $dateFrom)
                        ->orWhere('refunds.created_at', '>=', $dateFrom);
                });
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where(function ($qq) use ($dateTo) {
                    $qq->where('refunds.requested_at', '<=', $dateTo)
                        ->orWhere('refunds.processed_at', '<=', $dateTo)
                        ->orWhere('refunds.created_at', '<=', $dateTo);
                });
            });
    }

    private function applyDisputeDateRange($query, $dateFrom, $dateTo): void
    {
        $query
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where(function ($qq) use ($dateFrom) {
                    $qq->where('disputes.decided_at', '>=', $dateFrom)
                        ->orWhere('disputes.resolved_at', '>=', $dateFrom)
                        ->orWhere('disputes.created_at', '>=', $dateFrom);
                });
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where(function ($qq) use ($dateTo) {
                    $qq->where('disputes.decided_at', '<=', $dateTo)
                        ->orWhere('disputes.resolved_at', '<=', $dateTo)
                        ->orWhere('disputes.created_at', '<=', $dateTo);
                });
            });
    }

    private function applyRefundCommonFilters($query, string $status, string $destination, string $q): void
    {
        if ($status !== '') {
            $query->where('refunds.status', $status);
        }

        if ($destination !== '') {
            if ($destination === 'wallet') {
                $query->where('refunds.refunded_to_wallet_minor', '>', 0);
            } elseif ($destination === 'original') {
                $query->where('refunds.refunded_to_original_minor', '>', 0);
            } elseif ($destination === 'mixed') {
                $query->where('refunds.refunded_to_wallet_minor', '>', 0)
                    ->where('refunds.refunded_to_original_minor', '>', 0);
            }
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                if (ctype_digit($q)) {
                    $qq->orWhere('refunds.id', (int) $q)
                        ->orWhere('refunds.reservation_id', (int) $q)
                        ->orWhere('refunds.requested_by_user_id', (int) $q);
                }

                $qq->orWhere('refunds.provider', 'like', "%{$q}%")
                    ->orWhere('refunds.method', 'like', "%{$q}%")
                    ->orWhere('refunds.status', 'like', "%{$q}%")
                    ->orWhereHas('reservation.service', fn ($s) => $s->where('title', 'like', "%{$q}%"))
                    ->orWhereHas('reservation.client', fn ($c) => $c->where('username', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('reservation.coach', fn ($c) => $c->where('username', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            });
        }
    }

    private function decorateRefundRow($refund, Collection $staffMap, string $refundType)
    {
        $reservation = $refund->reservation;
        $refund->refund_type = $refundType;

        if ($refundType === 'dispute') {
            $relevantDispute = $this->getRelevantRefundDispute($reservation);

            $resolverId = (int) ($relevantDispute->resolved_by_staff_id ?? $relevantDispute->decided_by_staff_id ?? 0);

            if ($resolverId > 0 && $staffMap->has($resolverId)) {
                $refund->issued_by_name = $this->personName($staffMap->get($resolverId));
                $refund->issued_by_type = 'agent';
            } else {
                $refund->issued_by_name = 'System';
                $refund->issued_by_type = 'system';
            }

            $refund->decision_label = $relevantDispute?->decision_action
                ? match ((string) $relevantDispute->decision_action) {
                    'refund_full'    => 'Dispute refund - full',
                    'refund_service' => 'Dispute refund - service only',
                    default          => 'Dispute refund',
                }
                : 'Dispute refund';

            $refund->event_at = $relevantDispute->resolved_at
                ?? $relevantDispute->decided_at
                ?? $refund->processed_at
                ?? $refund->requested_at
                ?? $refund->created_at;
        } else {
            [$issuedByLabel, $issuedByType] = $this->resolveRefundIssuer($refund, $reservation, $staffMap);

            $refund->issued_by_name = $issuedByLabel;
            $refund->issued_by_type = $issuedByType;
            $refund->decision_label = 'Cancellation refund';
            $refund->event_at = $refund->processed_at ?? $refund->requested_at ?? $refund->created_at;
        }

        $wallet   = (int) ($refund->refunded_to_wallet_minor ?? 0);
        $original = (int) ($refund->refunded_to_original_minor ?? 0);

        if ($wallet > 0 && $original > 0) {
            $refund->funds_destination_label = 'Client wallet + original payment';
        } elseif ($wallet > 0) {
            $refund->funds_destination_label = 'Client wallet';
        } elseif ($original > 0) {
            $refund->funds_destination_label = 'Client original payment';
        } else {
            $refund->funds_destination_label = 'Client';
        }

        return $refund;
    }

    private function resolveRefundIssuer($refund, $reservation, Collection $staffMap): array
    {
        $issuerId = (int) ($refund->requested_by_user_id ?? 0);

        if ($issuerId > 0 && $staffMap->has($issuerId)) {
            return [$this->personName($staffMap->get($issuerId)), 'agent'];
        }

        $clientId = (int) ($reservation->client_id ?? 0);
        $coachId  = (int) ($reservation->coach_id ?? 0);

        if ($issuerId > 0 && $issuerId === $clientId) {
            return ['Client', 'client'];
        }

        if ($issuerId > 0 && $issuerId === $coachId) {
            return ['Coach', 'coach'];
        }

        if ($issuerId > 0) {
            return ['User #' . $issuerId, 'user'];
        }

        return ['System', 'system'];
    }

    private function getRelevantRefundDispute($reservation): ?Dispute
    {
        $disputes = $reservation?->disputes;

        if (!$disputes || $disputes->isEmpty()) {
            return null;
        }

        return $disputes
            ->filter(fn ($d) => in_array((string) $d->decision_action, ['refund_full', 'refund_service'], true))
            ->sortByDesc(function ($d) {
                return optional($d->resolved_at)->timestamp
                    ?? optional($d->decided_at)->timestamp
                    ?? optional($d->created_at)->timestamp
                    ?? $d->id;
            })
            ->first();
    }

    private function resolveCancellationAgentId($refund, Collection $staffMap): int
    {
        $issuerId = (int) ($refund->requested_by_user_id ?? 0);
        return $staffMap->has($issuerId) ? $issuerId : 0;
    }

    private function resolveDisputeAgentId($refund): int
    {
        $relevantDispute = $this->getRelevantRefundDispute($refund->reservation);
        return (int) ($relevantDispute->resolved_by_staff_id ?? $relevantDispute->decided_by_staff_id ?? 0);
    }

    private function personName($user): string
    {
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($user->username ?: $user->email ?: ('#' . $user->id));
    }

    private function buildAgentPerformanceSummary(
        Collection $staffOptions,
        Collection $staffMap,
        Collection $agentCancellationRefunds,
        Collection $agentDisputeRefunds,
        Collection $agentPayoutSummaryRaw
    ): Collection {
        $rows = collect();

        foreach ($staffOptions as $staff) {
            $rows->put((int) $staff->id, [
                'staff_id'             => (int) $staff->id,
                'name'                 => $this->personName($staff),
                'role'                 => strtolower((string) $staff->role),
                 'role_label'           => $staff->role_label,
                'refunds_count'        => 0,
                'refunds_minor'        => 0,
                'cancellation_count'   => 0,
                'cancellation_minor'   => 0,
                'dispute_refund_count' => 0,
                'dispute_refund_minor' => 0,
                'payout_count'         => 0,
                'payout_minor'         => 0,
            ]);
        }

        foreach ($agentCancellationRefunds as $refund) {
            $sid = $this->resolveCancellationAgentId($refund, $staffMap);
            if ($sid <= 0 || !$rows->has($sid)) {
                continue;
            }

            $current = $rows->get($sid);
            $current['cancellation_count']++;
            $current['cancellation_minor'] += (int) ($refund->actual_amount_minor ?? 0);
            $rows->put($sid, $current);
        }

        foreach ($agentDisputeRefunds as $refund) {
            $sid = $this->resolveDisputeAgentId($refund);
            if ($sid <= 0 || !$rows->has($sid)) {
                continue;
            }

            $current = $rows->get($sid);
            $current['dispute_refund_count']++;
            $current['dispute_refund_minor'] += (int) ($refund->actual_amount_minor ?? 0);
            $rows->put($sid, $current);
        }

        foreach ($agentPayoutSummaryRaw as $row) {
            $sid = (int) ($row->staff_id ?? 0);
            if ($sid <= 0 || !$rows->has($sid)) {
                continue;
            }

            $current = $rows->get($sid);
            $current['payout_count'] = (int) ($row->payout_count ?? 0);
            $current['payout_minor'] = (int) ($row->payout_minor ?? 0);
            $rows->put($sid, $current);
        }

        $rows = $rows->map(function ($row) {
            $row['refunds_count'] = (int) $row['cancellation_count'] + (int) $row['dispute_refund_count'];
            $row['refunds_minor'] = (int) $row['cancellation_minor'] + (int) $row['dispute_refund_minor'];
            return $row;
        });

        return $rows->sortByDesc('refunds_minor')->values();
    }
}