<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use App\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Services\WalletService;

class CoachDashboardController extends Controller
{
    public function messages()       { return view('coach.messages'); }
    public function calendar()       { return view('coach.calendar'); }
    public function services()       { return view('coach.services'); }
    public function bookings()       { return view('coach.bookings'); }
    public function disputes()       { return view('coach.disputes'); }
    public function qualifications() { return view('coach.qualifications'); }

    public function index(Request $request)
    {
       $user    = $request->user()->loadMissing('coachProfile');
        $coachId = (int) $user->id;

        // Prefer app timezone (keep as-is)
        $tz  = config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($tz);

        // Inputs
        $range = strtolower((string) $request->query('range', 'lifetime')); // default lifetime
        $from  = $request->query('from');
        $to    = $request->query('to');

        $year  = (int) $request->query('year',  $now->year);
        $month = (int) $request->query('month', $now->month);
        $day   = $request->query('day'); // YYYY-MM-DD

        $periodLabel = 'All time';
        $dateFrom = null;
        $dateTo   = null;

        if (in_array($range, ['all', 'lifetime'], true)) {
            $range = 'lifetime';
        }

        if ($range === 'custom' || $from || $to) {
            $range = 'custom';
        }

        switch ($range) {
            case 'daily': {
                $baseDay = $day ? CarbonImmutable::parse($day, $tz) : $now;
                $dateFrom = $baseDay->startOfDay();
                $dateTo   = $baseDay->endOfDay();
                $periodLabel = $baseDay->format('Y-m-d');
                break;
            }
            case 'weekly': {
                $dateFrom = $now->startOfWeek()->startOfDay();
                $dateTo   = $now->endOfWeek()->endOfDay();
                $periodLabel = $dateFrom->format('Y-m-d') . ' → ' . $dateTo->format('Y-m-d');
                break;
            }
            case 'monthly': {
                $baseMonth = $now->setYear($year)->setMonth($month);
                $dateFrom = $baseMonth->startOfMonth()->startOfDay();
                $dateTo   = $baseMonth->endOfMonth()->endOfDay();
                $periodLabel = $baseMonth->format('F Y');
                break;
            }
            case 'yearly': {
                $baseYear = $now->setYear($year);
                $dateFrom = $baseYear->startOfYear()->startOfDay();
                $dateTo   = $baseYear->endOfYear()->endOfDay();
                $periodLabel = (string) $year;
                break;
            }
            case 'custom': {
                $dateFrom = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : null;
                $dateTo   = $to   ? CarbonImmutable::parse($to,   $tz)->endOfDay()   : null;
                $periodLabel = trim(
                    ($dateFrom ? $dateFrom->format('Y-m-d') : '…') . ' → ' . ($dateTo ? $dateTo->format('Y-m-d') : '…')
                );
                break;
            }
            case 'lifetime':
            default: {
                $dateFrom = null;
                $dateTo   = null;
                $periodLabel = 'All time';
                break;
            }
        }

        /**
         * =========================================================
         * BASE QUERIES (fixes cancelled + no-show counting)
         * =========================================================
         *
         * - baseAll: everything (pending included) => funnel / diagnostics
         * - basePaidAny: paid reservations (ANY status) => real commitments/outcomes
         * - baseBookedPaid: paid + currently booked (active) => optional views
         */
        $baseAll = Reservation::query()->where('coach_id', $coachId);

        $basePaidAny = (clone $baseAll)
            ->where('payment_status', 'paid');

        $baseBookedPaid = (clone $basePaidAny)
            ->where('status', 'booked');

        // -------------------- Analytics events (views / visits / enquiries)
        $eventsBase = AnalyticsEvent::query()
            ->where('coach_id', $coachId)
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('created_at', '<=', $dateTo));

        $profileViews      = (int) (clone $eventsBase)->where('type', 'profile_view')->count();
        $bookingPageVisits = (int) (clone $eventsBase)->where('type', 'booking_page_visit')->count();
        $enquiries         = (int) (clone $eventsBase)->where('type', 'enquiry_message')->count();

        // -------------------- Currency (simple)
        $currency = (string) (
            (clone $basePaidAny)->whereNotNull('currency')->value('currency')
            ?? (clone $baseAll)->whereNotNull('currency')->value('currency')
            ?? 'USD'
        );

        // -------------------- Formatter (minor -> money)
        $fmt = function (int $minor) use ($currency) {
            $amount = number_format($minor / 100, 2);
            return '$' . $amount;
        };

        /**
         * =========================================================
         * UBER / FIVERR STYLE KPI MODEL
         * - DEMAND (Funnel)    => created_at on baseAll (attempts)
         * - OUTCOMES (Ops)     => completed_at / cancelled_at / no_show on basePaidAny
         * - CASH (Accounting)  => wallet ledger (source of truth)
         * =========================================================
         */

        // -------------------- Demand / Funnel: bookings created in window (includes pending)
        $bookedQ = (clone $baseAll)
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('created_at', '<=', $dateTo));

        $bookingsCreatedCount = (int) (clone $bookedQ)->count();
        $bookedGmvMinor       = (int) (clone $bookedQ)->sum(DB::raw('COALESCE(subtotal_minor, 0)'));

        // Keep these names for your existing conversion UI
        $bookingsCount = $bookingsCreatedCount;

        // Conversion rates (views/enquiries -> bookings created)
        $convViewsToBookings = $profileViews > 0
            ? round(($bookingsCreatedCount / $profileViews) * 100, 2)
            : 0.0;

        $convEnquiryToBooking = $enquiries > 0
            ? round(($bookingsCreatedCount / $enquiries) * 100, 2)
            : 0.0;

        // -------------------- Paid cohort (denominator for rates + ledger scoping)
        // "Paid bookings created in window" (any later outcome counts against this cohort)
        $paidBookedQ = (clone $basePaidAny)
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('created_at', '<=', $dateTo));

        $paidBookingsCount     = (int) (clone $paidBookedQ)->count();
        $cohortReservationIds  = (clone $paidBookedQ)->pluck('id')->all();

        // -------------------- Outcomes (Ops): completed / cancelled in window (paid only)
        $completedQ = (clone $basePaidAny)
            ->whereNotNull('completed_at')
            ->when($dateFrom, fn($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('completed_at', '<=', $dateTo));

        $completedCount    = (int) (clone $completedQ)->count();
        $completedGmvMinor = (int) (clone $completedQ)->sum(DB::raw('COALESCE(subtotal_minor, 0)'));

        $cancelledQ = (clone $basePaidAny)
            ->whereNotNull('cancelled_at')
            ->when($dateFrom, fn($q) => $q->where('cancelled_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('cancelled_at', '<=', $dateTo));

        $cancelledCount    = (int) (clone $cancelledQ)->count();
        $cancelledGmvMinor = (int) (clone $cancelledQ)->sum(DB::raw('COALESCE(subtotal_minor, 0)'));

        // No-show outcomes (paid only)
        $noShowCount = (int) (clone $basePaidAny)
            ->whereIn('status', ['no_show', 'no_show_coach', 'no_show_client', 'no_show_both'])
            ->when($dateFrom, fn($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('updated_at', '<=', $dateTo))
            ->count();

        // Performance rates: outcomes ÷ paid cohort
        $cancellationRate = $paidBookingsCount > 0
            ? round(($cancelledCount / $paidBookingsCount) * 100, 2)
            : 0.0;

        $completionRate = $paidBookingsCount > 0
            ? round(($completedCount / $paidBookingsCount) * 100, 2)
            : 0.0;

        // -------------------- CASH / SETTLEMENT (Accounting truth): ledger-first
        $coachWalletQ = WalletTransaction::query()
            ->where('user_id', $coachId)
            ->where('balance_type', WalletService::BAL_WITHDRAW)
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('created_at', '<=', $dateTo))
            // Keep cash scoped to paid cohort if we have a date window
            ->when(!empty($cohortReservationIds), fn($q) => $q->whereIn('reservation_id', $cohortReservationIds));

        // Coach paid earnings (net) = credits for earnings release
        $coachPaidNetMinor = (int) (clone $coachWalletQ)
            ->where('type', 'credit')
            ->where('reason', 'coach_earnings_release')
            ->sum(DB::raw('COALESCE(amount_minor,0)'));

        // Coach penalties (ledger) = debits for penalty reasons
        $coachPenaltiesMinor = (int) (clone $coachWalletQ)
            ->where('type', 'debit')
            ->whereIn('reason', [
                'cancel_penalty',
                'penalty_coach_no_show_10pct',
                'penalty_both_no_show_10pct',
            ])
            ->sum(DB::raw('COALESCE(amount_minor,0)'));

        // Coach compensation (ledger) = credits for comp reasons
        $coachCompMinor = (int) (clone $coachWalletQ)
            ->where('type', 'credit')
            ->whereIn('reason', [
                'cancel_compensation',
            ])
            ->sum(DB::raw('COALESCE(amount_minor,0)'));

        // Final impact (cash view)
        $coachFinalImpactMinor = (int) ($coachPaidNetMinor + $coachCompMinor - $coachPenaltiesMinor);

        /**
         * Reporting snapshots (optional):
         * These fields live on reservations. Keep them paid-only + completed.
         */
        $paidQ = (clone $basePaidAny)
            ->where('settlement_status', 'paid')
            ->whereNotNull('completed_at')
            ->when($dateFrom, fn($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('completed_at', '<=', $dateTo));

        $coachGrossMinor      = (int) (clone $paidQ)->sum(DB::raw('COALESCE(coach_gross_minor, 0)'));
        $coachCommissionMinor = (int) (clone $paidQ)->sum(DB::raw('COALESCE(coach_commission_minor, 0)'));
        $coachNetMinor        = (int) (clone $paidQ)->sum(DB::raw('COALESCE(coach_earned_minor, 0)'));

        $completedRefundedQ = (clone $basePaidAny)
            ->whereNotNull('completed_at')
            ->whereIn('settlement_status', ['refund_pending','refunded','refunded_partial'])
            ->when($dateFrom, fn($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('completed_at', '<=', $dateTo));

        $completedRefundedCount    = (int) (clone $completedRefundedQ)->count();
        $completedRefundedGmvMinor = (int) (clone $completedRefundedQ)->sum(DB::raw('COALESCE(subtotal_minor,0)'));

        // Client fees (charged to client) - informational on coach dashboard (activity window)
        // Use paid-any windowed so cancelled/refunds can be reflected properly.
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

        $clientFeesMinor = (int) (clone $windowed)->sum(DB::raw('COALESCE(fees_minor, 0)'));

        // -------------------- Recent per-booking table (activity feed)
        $rows = (clone $windowed)
            ->orderByDesc(DB::raw('COALESCE(completed_at, cancelled_at, updated_at, created_at)'))
            ->select([
                'id',
                'status',
                'settlement_status',
                'currency',
                'created_at',
                'completed_at',
                'cancelled_at',

                // booked money (this is what should show even for cancelled)
                'subtotal_minor',
                'fees_minor',
                'total_minor',

                // settlement snapshots (paid only, often 0 for cancelled/refund states)
                'coach_gross_minor',
                'coach_commission_minor',
                'coach_earned_minor',
                'coach_net_minor',

                // penalty/comp
                'coach_penalty_minor',
                'coach_comp_minor',
                'platform_earned_minor',

                // refunds
                'refund_status',
                'refund_total_minor',
            ])
            ->paginate(10);

        // -------------------- Charts
        $bucket = match ($range) {
            'daily'   => 'hour',
            'weekly', 'monthly', 'custom' => 'day',
            'yearly', 'lifetime' => 'month',
            default   => 'month',
        };

        $chartPaid = (clone $basePaidAny)
            ->where('settlement_status', 'paid')
            ->whereNotNull('completed_at')
            ->when($dateFrom, fn($q) => $q->where('completed_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('completed_at', '<=', $dateTo));

        $keyExpr = match ($bucket) {
            'hour'  => "DATE_FORMAT(completed_at, '%Y-%m-%d %H:00')",
            'day'   => "DATE_FORMAT(completed_at, '%Y-%m-%d')",
            default => "DATE_FORMAT(completed_at, '%Y-%m')",
        };

        $earningsSeries = $chartPaid
            ->selectRaw("$keyExpr as k")
            ->selectRaw("SUM(COALESCE(coach_earned_minor,0)) as net_minor")
            ->selectRaw("SUM(COALESCE(coach_gross_minor,0)) as gross_minor")
            ->selectRaw("COUNT(*) as bookings_count")
            ->groupBy('k')
            ->orderBy('k')
            ->get();

        $labels      = $earningsSeries->pluck('k')->values()->all();
        $lineNet     = $earningsSeries->pluck('net_minor')->map(fn($v) => (int)$v)->values()->all();
        $barBookings = $earningsSeries->pluck('bookings_count')->map(fn($v) => (int)$v)->values()->all();
        $barGross    = $earningsSeries->pluck('gross_minor')->map(fn($v) => (int)$v)->values()->all();

        $pie = [
            ['label' => 'Paid Net',      'value_minor' => (int) $coachPaidNetMinor],
            ['label' => 'Client Compensation ',  'value_minor' => (int) $coachCompMinor],
            ['label' => 'Penalties ',     'value_minor' => (int) $coachPenaltiesMinor],
            ['label' => 'Paid Commission',        'value_minor' => (int) $coachCommissionMinor],
        ];

        // -------------------- Payload (AJAX)
        $payload = [
            'currency'    => $currency,
            'periodLabel' => $periodLabel,

            // Demand + outcomes
            'bookings_created_count' => $bookingsCreatedCount,
            'booked_gmv'             => $fmt($bookedGmvMinor),

            'completed_count'        => $completedCount,
            'completed_gmv'          => $fmt($completedGmvMinor),

            'cancelled_count'        => $cancelledCount,
            'cancelled_gmv'          => $fmt($cancelledGmvMinor),

            'completion_rate'        => $completionRate,
            'cancellation_rate'      => $cancellationRate,
            'no_show_count'          => $noShowCount,

            // Cash
            'coach_paid_net'         => $fmt($coachPaidNetMinor),
            'coach_penalties'        => $fmt($coachPenaltiesMinor),
            'coach_comp'             => $fmt($coachCompMinor),
            'client_fees'            => $fmt($clientFeesMinor),
            'coach_final_impact'     => $fmt($coachFinalImpactMinor),

            // Reporting snapshots (paid-only)
            'coach_gross'            => $fmt($coachGrossMinor),
            'coach_commission'       => $fmt($coachCommissionMinor),
            'coach_net'              => $fmt($coachNetMinor),

            // Funnel
            'profileViews'           => $profileViews,
            'bookingPageVisits'      => $bookingPageVisits,
            'enquiries'              => $enquiries,
            'bookingsCount'          => $bookingsCount,
            'convViewsToBookings'    => $convViewsToBookings,
            'convEnquiryToBooking'   => $convEnquiryToBooking,

            'completed_refunded_count' => $completedRefundedCount,
            'completed_refunded_gmv'   => $fmt($completedRefundedGmvMinor),

            // Filters/debug
            'range'       => $range,
            'dateFrom'    => $dateFrom?->toIso8601String(),
            'dateTo'      => $dateTo?->toIso8601String(),
            'bucket'      => $bucket,

            // Charts
            'charts' => [
                'labels' => $labels,
                'line' => [
                    'net_minor' => $lineNet,
                ],
                'bar' => [
                    'bookings_count' => $barBookings,
                    'gross_minor'    => $barGross,
                ],
                'pie' => $pie,
            ],

            // Partials
            'tiles_html' => view('coach.partials.dashboard_tiles', compact(
                'bookingsCreatedCount',
                'bookedGmvMinor',
                'completedCount',
                'completedGmvMinor',
                'cancelledCount',
                'cancelledGmvMinor',
                'completionRate',
                'cancellationRate',
                'noShowCount',

                'coachPaidNetMinor',
                'coachPenaltiesMinor',
                'coachCompMinor',
                'coachFinalImpactMinor',
                'clientFeesMinor',

                'coachGrossMinor',
                'coachCommissionMinor',
                'coachNetMinor',

                'profileViews',
                'bookingPageVisits',
                'enquiries',
                'bookingsCount',
                'convViewsToBookings',
                'convEnquiryToBooking',
                'completedRefundedCount',
                'completedRefundedGmvMinor',

                'fmt'
            ))->render(),

            'table_html' => view('coach.partials.dashboard_table', compact('rows', 'fmt'))->render(),
            'meta_html'  => view('coach.partials.dashboard_meta', compact('currency', 'periodLabel'))->render(),
        ];

        if ($request->ajax()) {
            return response()->json($payload);
        }

       $coachProfile = $user->coachProfile;
$coachKycState = $coachProfile?->application_status;

        return view('coach.home', compact(
            'coachKycState',
            'rows',
            'currency',
            'periodLabel',
            'range',
            'dateFrom',
            'dateTo',
            'bucket',
            'fmt',

            // Demand/Outcome
            'bookingsCreatedCount',
            'bookedGmvMinor',
            'completedCount',
            'completedGmvMinor',
            'cancelledCount',
            'cancelledGmvMinor',
            'completionRate',
            'cancellationRate',
            'noShowCount',

            // Cash (ledger truth)
            'coachPaidNetMinor',
            'coachPenaltiesMinor',
            'coachCompMinor',
            'clientFeesMinor',
            'coachFinalImpactMinor',

            // Paid snapshots
            'coachGrossMinor',
            'coachCommissionMinor',
            'coachNetMinor',

            // Funnel
            'profileViews',
            'bookingPageVisits',
            'enquiries',
            'bookingsCount',
            'convViewsToBookings',
            'convEnquiryToBooking',
            'completedRefundedCount',
            'completedRefundedGmvMinor',

            // charts
            'labels',
            'lineNet',
            'barBookings',
            'barGross',
            'pie'
        ));
    }
}