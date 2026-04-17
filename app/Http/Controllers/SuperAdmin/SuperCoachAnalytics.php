<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Dispute;
use App\Models\Reservation;
use App\Models\Users;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperCoachAnalytics extends Controller
{
    public function show(Request $request, Users $coach)
    {
        abort_unless(in_array((string) auth()->user()?->role, ['admin', 'manager','superadmin'], true), 403);

        $coachId = (int) $coach->id;
        $tz      = config('app.timezone', 'UTC');
        $now     = CarbonImmutable::now($tz);

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
                $baseDay   = $day ? CarbonImmutable::parse($day, $tz) : $now;
                $dateFrom  = $baseDay->startOfDay();
                $dateTo    = $baseDay->endOfDay();
                $periodLabel = $baseDay->format('Y-m-d');
                break;

            case 'weekly':
                $dateFrom  = $now->startOfWeek()->startOfDay();
                $dateTo    = $now->endOfWeek()->endOfDay();
                $periodLabel = $dateFrom->format('Y-m-d') . ' → ' . $dateTo->format('Y-m-d');
                break;

            case 'monthly':
                $baseMonth = $now->setYear($year)->setMonth($month);
                $dateFrom  = $baseMonth->startOfMonth()->startOfDay();
                $dateTo    = $baseMonth->endOfMonth()->endOfDay();
                $periodLabel = $baseMonth->format('F Y');
                break;

            case 'yearly':
                $baseYear  = $now->setYear($year);
                $dateFrom  = $baseYear->startOfYear()->startOfDay();
                $dateTo    = $baseYear->endOfYear()->endOfDay();
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
                $dateTo   = null;
                $periodLabel = 'All time';
                break;
        }

        $baseAll = Reservation::query()->where('coach_id', $coachId);

        $basePaidAny = (clone $baseAll)
            ->where('payment_status', 'paid');

        $currency = (string) (
            (clone $basePaidAny)->whereNotNull('currency')->value('currency')
            ?? (clone $baseAll)->whereNotNull('currency')->value('currency')
            ?? 'USD'
        );

        $fmt = function (int $minor) {
            return '$' . number_format($minor / 100, 2);
        };

        // Funnel events
        $eventsBase = AnalyticsEvent::query()
            ->where('coach_id', $coachId)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo));

        $profileViews      = (int) (clone $eventsBase)->where('type', 'profile_view')->count();
        $bookingPageVisits = (int) (clone $eventsBase)->where('type', 'booking_page_visit')->count();
        $enquiries         = (int) (clone $eventsBase)->where('type', 'enquiry_message')->count();

        // Bookings created
        $bookedQ = (clone $baseAll)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo));

        $bookingsCreatedCount = (int) (clone $bookedQ)->count();
        $bookedGmvMinor       = (int) (clone $bookedQ)->sum(DB::raw('COALESCE(subtotal_minor,0)'));

        // Completed
        $completedQ = (clone $basePaidAny)
            ->whereNotNull('completed_at')
            ->when($dateFrom, fn ($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('completed_at', '<=', $dateTo));

        $completedCount    = (int) (clone $completedQ)->count();
        $completedGmvMinor = (int) (clone $completedQ)->sum(DB::raw('COALESCE(subtotal_minor,0)'));

        // Cancelled
        $cancelledQ = (clone $basePaidAny)
            ->whereNotNull('cancelled_at')
            ->when($dateFrom, fn ($q) => $q->where('cancelled_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('cancelled_at', '<=', $dateTo));

        $cancelledCount    = (int) (clone $cancelledQ)->count();
        $cancelledGmvMinor = (int) (clone $cancelledQ)->sum(DB::raw('COALESCE(subtotal_minor,0)'));

        // Who cancelled
        $coachCancelledCount = (int) (clone $cancelledQ)->where('cancelled_by', 'coach')->count();
        $clientCancelledCount = (int) (clone $cancelledQ)->where('cancelled_by', 'client')->count();
        $adminCancelledCount = (int) (clone $cancelledQ)->where('cancelled_by', 'admin')->count();
        $systemCancelledCount = (int) (clone $cancelledQ)->where('cancelled_by', 'system')->count();

        // No-shows
        $coachNoShowCount = (int) (clone $basePaidAny)
            ->where(function ($q) {
                $q->where('status', 'no_show_coach')
                  ->orWhere(function ($qq) {
                      $qq->where('status', 'no_show')
                         ->where('settlement_status', 'refund_pending')
                         ->where('coach_penalty_minor', '>', 0)
                         ->where('refund_total_minor', '>', 0);
                  });
            })
            ->when($dateFrom, fn ($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('updated_at', '<=', $dateTo))
            ->count();

        $clientNoShowCount = (int) (clone $basePaidAny)
            ->where('status', 'no_show_client')
            ->when($dateFrom, fn ($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('updated_at', '<=', $dateTo))
            ->count();

        $bothNoShowCount = (int) (clone $basePaidAny)
            ->where(function ($q) {
                $q->where('status', 'no_show_both')
                  ->orWhere(function ($qq) {
                      $qq->where('status', 'no_show')
                         ->where('settlement_status', 'refund_pending')
                         ->where('coach_penalty_minor', '>', 0)
                         ->where('platform_fee_minor', '>', 0);
                  });
            })
            ->when($dateFrom, fn ($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('updated_at', '<=', $dateTo))
            ->count();

        $totalNoShowCount = $coachNoShowCount + $clientNoShowCount + $bothNoShowCount;

        // Refunds
        $completedRefundedQ = (clone $basePaidAny)
            ->whereNotNull('completed_at')
            ->whereIn('settlement_status', ['refund_pending', 'refunded', 'refunded_partial'])
            ->when($dateFrom, fn ($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('completed_at', '<=', $dateTo));

        $completedRefundedCount    = (int) (clone $completedRefundedQ)->count();
        $completedRefundedGmvMinor = (int) (clone $completedRefundedQ)->sum(DB::raw('COALESCE(subtotal_minor,0)'));

        // Paid cohort for rates
        $paidBookedQ = (clone $basePaidAny)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo));

        $paidBookingsCount = (int) (clone $paidBookedQ)->count();

        $completionRate = $paidBookingsCount > 0
            ? round(($completedCount / $paidBookingsCount) * 100, 2)
            : 0.0;

        $cancellationRate = $paidBookingsCount > 0
            ? round(($cancelledCount / $paidBookingsCount) * 100, 2)
            : 0.0;

        $convViewsToBookings = $profileViews > 0
            ? round(($bookingsCreatedCount / $profileViews) * 100, 2)
            : 0.0;

        $convEnquiryToBooking = $enquiries > 0
            ? round(($bookingsCreatedCount / $enquiries) * 100, 2)
            : 0.0;

        // Cash / wallet
        $cohortReservationIds = (clone $paidBookedQ)->pluck('id')->all();

        $coachWalletQ = WalletTransaction::query()
            ->where('user_id', $coachId)
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->when(!empty($cohortReservationIds), fn ($q) => $q->whereIn('reservation_id', $cohortReservationIds));

        $coachPaidNetMinor = (int) (clone $coachWalletQ)
            ->where('type', 'credit')
            ->where('reason', 'coach_earnings_release')
            ->sum(DB::raw('COALESCE(amount_minor,0)'));

        $coachPenaltiesMinor = (int) (clone $coachWalletQ)
            ->where('type', 'debit')
            ->whereIn('reason', [
                'cancel_penalty',
                'penalty_coach_no_show_10pct',
                'penalty_both_no_show_10pct',
            ])
            ->sum(DB::raw('COALESCE(amount_minor,0)'));

        $coachCompMinor = (int) (clone $coachWalletQ)
            ->where('type', 'credit')
            ->whereIn('reason', [
                'cancel_compensation',
            ])
            ->sum(DB::raw('COALESCE(amount_minor,0)'));

        $coachFinalImpactMinor = $coachPaidNetMinor + $coachCompMinor - $coachPenaltiesMinor;

        $paidQ = (clone $basePaidAny)
            ->where('settlement_status', 'paid')
            ->whereNotNull('completed_at')
            ->when($dateFrom, fn ($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('completed_at', '<=', $dateTo));

        $coachGrossMinor      = (int) (clone $paidQ)->sum(DB::raw('COALESCE(coach_gross_minor,0)'));
        $coachCommissionMinor = (int) (clone $paidQ)->sum(DB::raw('COALESCE(coach_commission_minor,0)'));
        $coachNetMinor        = (int) (clone $paidQ)->sum(DB::raw('COALESCE(coach_earned_minor,0)'));

        $windowed = (clone $basePaidAny)
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where(function ($qq) use ($dateFrom) {
                    $qq->where('created_at', '>=', $dateFrom)
                        ->orWhere('completed_at', '>=', $dateFrom)
                        ->orWhere('cancelled_at', '>=', $dateFrom)
                        ->orWhere('updated_at', '>=', $dateFrom);
                });
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where(function ($qq) use ($dateTo) {
                    $qq->where('created_at', '<=', $dateTo)
                        ->orWhere('completed_at', '<=', $dateTo)
                        ->orWhere('cancelled_at', '<=', $dateTo)
                        ->orWhere('updated_at', '<=', $dateTo);
                });
            });

        $clientFeesMinor = (int) (clone $windowed)->sum(DB::raw('COALESCE(fees_minor,0)'));

        // Client metrics
        $uniqueClientsCount = (int) (clone $baseAll)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->distinct('client_id')
            ->count('client_id');

        $repeatClientsCount = (int) DB::table('reservations')
            ->select('client_id')
            ->where('coach_id', $coachId)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->groupBy('client_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        // Disputes
        $disputesBase = Dispute::query()
            ->whereHas('reservation', fn ($q) => $q->where('coach_id', $coachId))
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo));

        $coachDisputesRaisedCount = (int) (clone $disputesBase)
            ->where('opened_by_role', 'coach')
            ->count();

        $clientDisputesAgainstCoachCount = (int) (clone $disputesBase)
            ->where('opened_by_role', 'client')
            ->count();

        $openDisputesCount = (int) (clone $disputesBase)
            ->whereIn('status', ['open', 'opened', 'in_review'])
            ->count();

        $resolvedDisputesCount = (int) (clone $disputesBase)
            ->where('status', 'resolved')
            ->count();

        $rejectedDisputesCount = (int) (clone $disputesBase)
            ->where('status', 'rejected')
            ->count();

        // Chart data
        $bucket = match ($range) {
            'daily'   => 'hour',
            'weekly', 'monthly', 'custom' => 'day',
            'yearly', 'lifetime' => 'month',
            default   => 'month',
        };

        $chartPaid = (clone $basePaidAny)
            ->where('settlement_status', 'paid')
            ->whereNotNull('completed_at')
            ->when($dateFrom, fn ($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('completed_at', '<=', $dateTo));

        $keyExpr = match ($bucket) {
            'hour'  => "DATE_FORMAT(completed_at, '%Y-%m-%d %H:00')",
            'day'   => "DATE_FORMAT(completed_at, '%Y-%m-%d')",
            default => "DATE_FORMAT(completed_at, '%Y-%m')",
        };

        $earningsSeries = $chartPaid
            ->selectRaw("$keyExpr as k")
            ->selectRaw("SUM(COALESCE(coach_earned_minor,0)) as net_minor")
            ->selectRaw("COUNT(*) as bookings_count")
            ->groupBy('k')
            ->orderBy('k')
            ->get();

        $labels      = $earningsSeries->pluck('k')->values()->all();
        $lineNet     = $earningsSeries->pluck('net_minor')->map(fn ($v) => (int) $v)->values()->all();
        $barBookings = $earningsSeries->pluck('bookings_count')->map(fn ($v) => (int) $v)->values()->all();

        $rows = (clone $windowed)
            ->with(['client', 'service'])
            ->orderByDesc(DB::raw('COALESCE(completed_at, cancelled_at, updated_at, created_at)'))
            ->select([
                'id',
                'client_id',
                'service_id',
                'status',
                'settlement_status',
                'refund_status',
                'cancelled_by',
                'currency',
                'created_at',
                'completed_at',
                'cancelled_at',
                'subtotal_minor',
                'fees_minor',
                'total_minor',
                'coach_earned_minor',
                'coach_penalty_minor',
                'coach_comp_minor',
                'refund_total_minor',
            ])
            ->paginate(12)
            ->withQueryString();

        return view('superadmin.coaches.stats', compact(
            'coach',
            'currency',
            'periodLabel',
            'range',
            'dateFrom',
            'dateTo',
            'bucket',
            'fmt',

            'profileViews',
            'bookingPageVisits',
            'enquiries',
            'bookingsCreatedCount',
            'bookedGmvMinor',
            'completedCount',
            'completedGmvMinor',
            'cancelledCount',
            'cancelledGmvMinor',
            'completionRate',
            'cancellationRate',

            'coachCancelledCount',
            'clientCancelledCount',
            'adminCancelledCount',
            'systemCancelledCount',

            'coachNoShowCount',
            'clientNoShowCount',
            'bothNoShowCount',
            'totalNoShowCount',

            'coachPaidNetMinor',
            'coachPenaltiesMinor',
            'coachCompMinor',
            'coachFinalImpactMinor',
            'clientFeesMinor',
            'coachGrossMinor',
            'coachCommissionMinor',
            'coachNetMinor',

            'convViewsToBookings',
            'convEnquiryToBooking',
            'completedRefundedCount',
            'completedRefundedGmvMinor',

            'uniqueClientsCount',
            'repeatClientsCount',

            'coachDisputesRaisedCount',
            'clientDisputesAgainstCoachCount',
            'openDisputesCount',
            'resolvedDisputesCount',
            'rejectedDisputesCount',

            'labels',
            'lineNet',
            'barBookings',
            'rows'
        ));
    }
}