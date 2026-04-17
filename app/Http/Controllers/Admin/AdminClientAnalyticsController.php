<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Users;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminClientAnalyticsController extends Controller
{
    public function show(Request $request, Users $client)
    {
        abort_unless(
            auth()->check() && in_array((string) auth()->user()->role, ['admin', 'manager', 'agent'], true),
            403
        );

        $clientId = (int) $client->id;
        $tz       = config('app.timezone', 'UTC');
        $now      = CarbonImmutable::now($tz);

        // ---------------------------------
        // Selected global range
        // ---------------------------------
        $range = strtolower((string) $request->query('range', 'lifetime'));
        $from  = $request->query('from');
        $to    = $request->query('to');
        $year  = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $day   = $request->query('day');

        if (in_array($range, ['all', 'lifetime'], true)) {
            $range = 'lifetime';
        }

        if ($range === 'custom' || $from || $to) {
            $range = 'custom';
        }

        $periodLabel = 'All time';
        $dateFrom = null;
        $dateTo   = null;

        switch ($range) {
            case 'daily':
                $baseDay = $day ? CarbonImmutable::parse($day, $tz) : $now;
                $dateFrom = $baseDay->startOfDay();
                $dateTo   = $baseDay->endOfDay();
                $periodLabel = 'Today: ' . $baseDay->format('Y-m-d');
                break;

            case 'weekly':
                $dateFrom = $now->startOfWeek()->startOfDay();
                $dateTo   = $now->endOfWeek()->endOfDay();
                $periodLabel = 'This Week: ' . $dateFrom->format('Y-m-d') . ' → ' . $dateTo->format('Y-m-d');
                break;

            case 'monthly':
                $baseMonth = $now->setYear($year)->setMonth($month);
                $dateFrom = $baseMonth->startOfMonth()->startOfDay();
                $dateTo   = $baseMonth->endOfMonth()->endOfDay();
                $periodLabel = 'This Month: ' . $baseMonth->format('F Y');
                break;

            case 'yearly':
                $baseYear = $now->setYear($year);
                $dateFrom = $baseYear->startOfYear()->startOfDay();
                $dateTo   = $baseYear->endOfYear()->endOfDay();
                $periodLabel = 'This Year: ' . $year;
                break;

            case 'custom':
                $dateFrom = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : null;
                $dateTo   = $to ? CarbonImmutable::parse($to, $tz)->endOfDay() : null;
                $periodLabel = 'Custom: ' . trim(
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

        $fmt = fn (int $minor) => '$' . number_format($minor / 100, 2);

        $currency = (string) (
            Reservation::where('client_id', $clientId)->whereNotNull('currency')->value('currency')
            ?? Refund::whereHas('reservation', fn ($q) => $q->where('client_id', $clientId))->value('currency')
            ?? 'USD'
        );

        // ---------------------------------
        // Reservation queries (global filter applies)
        // ---------------------------------
        $reservationsBase = Reservation::query()
            ->where('client_id', $clientId);

        $windowedReservations = (clone $reservationsBase)
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where(function ($qq) use ($dateFrom) {
                    $qq->where('created_at', '>=', $dateFrom)
                        ->orWhere('completed_at', '>=', $dateFrom)
                        ->orWhere('cancelled_at', '>=', $dateFrom)
                        ->orWhere('refund_requested_at', '>=', $dateFrom)
                        ->orWhere('refund_processed_at', '>=', $dateFrom)
                        ->orWhere('updated_at', '>=', $dateFrom);
                });
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where(function ($qq) use ($dateTo) {
                    $qq->where('created_at', '<=', $dateTo)
                        ->orWhere('completed_at', '<=', $dateTo)
                        ->orWhere('cancelled_at', '<=', $dateTo)
                        ->orWhere('refund_requested_at', '<=', $dateTo)
                        ->orWhere('refund_processed_at', '<=', $dateTo)
                        ->orWhere('updated_at', '<=', $dateTo);
                });
            });

        $paidReservationsQ = Reservation::query()
            ->where('client_id', $clientId)
            ->where('payment_status', 'paid')
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo));

        $totalSpendMinor = (int) (clone $paidReservationsQ)->sum(DB::raw('COALESCE(total_minor,0)'));
        $totalPlatformPaidMinor = (int) (clone $paidReservationsQ)->sum(DB::raw('COALESCE(fees_minor,0)'));
        $totalServiceSpendMinor = (int) (clone $paidReservationsQ)->sum(DB::raw('COALESCE(subtotal_minor,0)'));

        $totalPlatformFeeRefundedMinor = (int) (clone $windowedReservations)
            ->sum(DB::raw('COALESCE(platform_fee_refunded_minor,0)'));

        $totalNetPlatformPaidMinor = max(0, $totalPlatformPaidMinor - $totalPlatformFeeRefundedMinor);

        $paidBookingsCount = (int) (clone $paidReservationsQ)->count();

        $completedBookingsCount = (int) Reservation::query()
            ->where('client_id', $clientId)
            ->whereNotNull('completed_at')
            ->where('payment_status', 'paid')
            ->when($dateFrom, fn ($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('completed_at', '<=', $dateTo))
            ->count();

        $cancelledBookingsCount = (int) Reservation::query()
            ->where('client_id', $clientId)
            ->whereNotNull('cancelled_at')
            ->where('payment_status', 'paid')
            ->when($dateFrom, fn ($q) => $q->where('cancelled_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('cancelled_at', '<=', $dateTo))
            ->count();

        $avgBookingValueMinor = $paidBookingsCount > 0
            ? (int) round($totalSpendMinor / $paidBookingsCount)
            : 0;

        $clientLostMinor = (int) (clone $windowedReservations)
            ->sum(DB::raw('COALESCE(client_penalty_minor,0)'));

        // ---------------------------------
        // Refund analytics
        // ---------------------------------
        $refundsBase = Refund::query()
            ->whereHas('reservation', fn ($q) => $q->where('client_id', $clientId))
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where(function ($qq) use ($dateFrom) {
                    $qq->where('requested_at', '>=', $dateFrom)
                        ->orWhere('processed_at', '>=', $dateFrom)
                        ->orWhere('created_at', '>=', $dateFrom);
                });
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where(function ($qq) use ($dateTo) {
                    $qq->where('requested_at', '<=', $dateTo)
                        ->orWhere('processed_at', '<=', $dateTo)
                        ->orWhere('created_at', '<=', $dateTo);
                });
            });

        $refundsCount = (int) (clone $refundsBase)->count();
        $refundSucceededCount = (int) (clone $refundsBase)->where('status', 'succeeded')->count();
        $refundPartialCount = (int) (clone $refundsBase)->where('status', 'partial')->count();
        $refundFailedCount = (int) (clone $refundsBase)->where('status', 'failed')->count();
        $refundProcessingCount = (int) (clone $refundsBase)->whereIn('status', ['processing'])->count();

        $totalRefundedMinor = (int) (clone $refundsBase)
            ->whereIn('status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(actual_amount_minor,0)'));

        $walletRefundedMinor = (int) (clone $refundsBase)
            ->whereIn('status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunded_to_wallet_minor,0)'));

        $externalRefundedMinor = (int) (clone $refundsBase)
            ->whereIn('status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunded_to_original_minor,0)'));

        // ---------------------------------
        // Disputes - all disputes related to this client's reservations
        // ---------------------------------
        $allDisputesBase = Dispute::query()
            ->whereHas('reservation', fn ($q) => $q->where('client_id', $clientId))
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo));

        $clientDisputesBase = (clone $allDisputesBase)
            ->where('opened_by_role', 'client');

        $coachDisputesBase = (clone $allDisputesBase)
            ->where('opened_by_role', 'coach');

        $clientDisputesCount = (int) (clone $clientDisputesBase)->count();
        $coachDisputesCount  = (int) (clone $coachDisputesBase)->count();

        $openDisputesCount = (int) (clone $allDisputesBase)
            ->whereIn('status', ['open', 'opened', 'in_review'])
            ->count();

        $resolvedDisputesCount = (int) (clone $allDisputesBase)
            ->whereIn('status', ['resolved', 'rejected'])
            ->count();

        // client win = refund_full, refund_service
        $clientDisputeWinsCount = (int) (clone $clientDisputesBase)
            ->whereIn('decision_action', ['refund_full', 'refund_service'])
            ->count();

        // client loss = pay_coach, reject
        $clientDisputeLossesCount = (int) (clone $clientDisputesBase)
            ->whereIn('decision_action', ['pay_coach', 'reject'])
            ->count();

        // coach win = pay_coach
        $coachDisputeWinsCount = (int) (clone $coachDisputesBase)
            ->whereIn('decision_action', ['pay_coach'])
            ->count();

        // coach loss = refund_full, refund_service, reject
        $coachDisputeLossesCount = (int) (clone $coachDisputesBase)
            ->whereIn('decision_action', ['refund_full', 'refund_service', 'reject'])
            ->count();

        $clientDisputeRefundedMinor = (int) Refund::query()
            ->whereHas('reservation.disputes', function ($q) use ($clientId, $dateFrom, $dateTo) {
                $q->whereHas('reservation', fn ($qq) => $qq->where('client_id', $clientId))
                    ->where('opened_by_role', 'client')
                    ->whereIn('decision_action', ['refund_full', 'refund_service']);

                if ($dateFrom) {
                    $q->where('created_at', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $q->where('created_at', '<=', $dateTo);
                }
            })
            ->whereIn('status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(actual_amount_minor,0)'));

        // ---------------------------------
        // Tables
        // ---------------------------------
        $reservationRows = (clone $windowedReservations)
            ->with(['service', 'coach'])
            ->orderByDesc(DB::raw('COALESCE(completed_at, cancelled_at, updated_at, created_at)'))
            ->paginate(10, ['*'], 'reservations_page')
            ->withQueryString();

        $refundRows = (clone $refundsBase)
            ->with(['reservation.service', 'reservation.coach'])
            ->orderByDesc(DB::raw('COALESCE(processed_at, requested_at, created_at)'))
            ->paginate(10, ['*'], 'refunds_page')
            ->withQueryString();

       $disputeRows = (clone $allDisputesBase)
    ->with([
        'reservation.service',
        'reservation.coach',
        'opener',
        'resolvedBy',
    ])
    ->orderByDesc(DB::raw('COALESCE(resolved_at, decided_at, created_at)'))
    ->paginate(10, ['*'], 'disputes_page')
    ->withQueryString();

        return view('admin.clients.stats', compact(
            'client',
            'currency',
            'periodLabel',
            'range',
            'dateFrom',
            'dateTo',
            'fmt',

            'totalSpendMinor',
            'paidBookingsCount',
            'completedBookingsCount',
            'cancelledBookingsCount',
            'avgBookingValueMinor',

            'refundsCount',
            'refundSucceededCount',
            'refundPartialCount',
            'refundFailedCount',
            'refundProcessingCount',
            'totalRefundedMinor',
            'walletRefundedMinor',
            'externalRefundedMinor',

            'clientLostMinor',

            'clientDisputesCount',
            'coachDisputesCount',
            'openDisputesCount',
            'resolvedDisputesCount',
            'clientDisputeWinsCount',
            'clientDisputeLossesCount',
            'coachDisputeWinsCount',
            'coachDisputeLossesCount',
            'clientDisputeRefundedMinor',

            'reservationRows',
            'refundRows',
            'disputeRows',

            'totalPlatformPaidMinor',
            'totalPlatformFeeRefundedMinor',
            'totalNetPlatformPaidMinor',
            'totalServiceSpendMinor',
        ));
    }
}