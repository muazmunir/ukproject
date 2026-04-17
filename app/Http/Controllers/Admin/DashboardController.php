<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Users;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Visit;
use App\Models\ServiceFee;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Generic helper for per-widget ranges
     */
    protected function resolveRange(Request $request, string $prefix): array
    {
        $now    = Carbon::now();
        $preset = $request->query("{$prefix}_preset", 'all');

        $from  = null;
        $to    = null;
        $label = 'All time';

        switch ($preset) {
            case 'day':
                $from  = $now->copy()->startOfDay();
                $to    = $now->copy()->endOfDay();
                $label = 'Today';
                break;

            case 'week':
                $from  = $now->copy()->startOfWeek();
                $to    = $now->copy()->endOfWeek();
                $label = 'This week';
                break;

            case 'month':
                $from  = $now->copy()->startOfMonth();
                $to    = $now->copy()->endOfMonth();
                $label = 'This month';
                break;

            case 'year':
                $from  = $now->copy()->startOfYear();
                $to    = $now->copy()->endOfYear();
                $label = 'This year';
                break;

            case 'month_year':
                $month = (int) $request->query("{$prefix}_month", $now->month);
                $year  = (int) $request->query("{$prefix}_year", $now->year);

                $from  = Carbon::create($year, $month, 1)->startOfDay();
                $to    = $from->copy()->endOfMonth();
                $label = $from->format('F Y');
                break;

            case 'custom':
                $fromInput = $request->query("{$prefix}_from");
                $toInput   = $request->query("{$prefix}_to");

                if ($fromInput && $toInput) {
                    $from  = Carbon::parse($fromInput)->startOfDay();
                    $to    = Carbon::parse($toInput)->endOfDay();
                    $label = $from->format('d M Y') . ' → ' . $to->format('d M Y');
                }
                break;

            case 'all':
            default:
                $preset = 'all';
                $label  = 'All time';
                break;
        }

        return [
            'preset' => $preset,
            'from'   => $from,
            'to'     => $to,
            'label'  => $label,
        ];
    }

    public function index(Request $request)
    {
        // one place to define what we consider "paid" payments
        $paidStatuses = ['succeeded', 'SUCCEEDED', 'completed', 'COMPLETED', 'paid', 'PAID'];

        // Commission rate for coaches (e.g. 10%)
        $coachCommissionPercent = ServiceFee::where('party', 'coach')
            ->where('is_active', true)
            ->value('amount');   // 10.00
        $coachCommissionRate = $coachCommissionPercent
            ? ((float) $coachCommissionPercent / 100.0)
            : 0.0;

        // Per-box ranges
        $kpiRange   = $this->resolveRange($request, 'kpi');
        $usersRange = $this->resolveRange($request, 'users');
        $salesRange = $this->resolveRange($request, 'sales');   // used for all "money" charts
        $newRange   = $this->resolveRange($request, 'new');
        $txRange    = $this->resolveRange($request, 'tx');

        /* ===================== KPI CARD ===================== */

        $kpiReservations = Reservation::query();
        $kpiPayments     = Payment::whereIn('status', $paidStatuses);
        $kpiUsers        = Users::query();

        // active visitors in last 20 seconds
        $activeSince   = now()->subSeconds(20);
        $visitorsCount = Visit::where('last_seen_at', '>=', $activeSince)
            ->distinct('visitor_id')
            ->count('visitor_id');

        if ($kpiRange['from'] && $kpiRange['to']) {
            $kpiReservations->whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']]);
            $kpiPayments->whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']]);
            $kpiUsers->whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']]);
        }

        $bookingsCount = $kpiReservations->count();

        // total client revenue (what client paid)
        $totalSalesRaw = $kpiPayments->sum('amount_total');    // adjust if you store in cents
        $totalSales    = (float) $totalSalesRaw;

        // Filtered or global counters
        if ($kpiRange['from'] && $kpiRange['to']) {
            $vendorsCount = Users::where('role', 'coach')
                ->whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']])
                ->count();

            $clientsCount = Users::where('role', 'client')
                ->whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']])
                ->count();

            $servicesCount = Service::whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']])
                ->count();

            $categoriesCount = ServiceCategory::whereBetween('created_at', [$kpiRange['from'], $kpiRange['to']])
                ->count();
        } else {
            $vendorsCount    = Users::where('role', 'coach')->count();
            $clientsCount    = Users::where('role', 'client')->count();
            $servicesCount   = Service::count();
            $categoriesCount = ServiceCategory::count();
        }

        /* ===================== USER STATS (usersRange) ===================== */

        $usersQuery = Users::query();
        if ($usersRange['from'] && $usersRange['to']) {
            $usersQuery->whereBetween('created_at', [$usersRange['from'], $usersRange['to']]);
        }

        $userStats = $usersQuery
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /* ===================== SALES & BOOKINGS (salesRange) ===================== */

        // Daily revenue (client side, amount_total)
        $salesQuery = Payment::whereIn('status', $paidStatuses);
        if ($salesRange['from'] && $salesRange['to']) {
            $salesQuery->whereBetween('created_at', [$salesRange['from'], $salesRange['to']]);
        }

        $salesStats = $salesQuery
            ->selectRaw('DATE(created_at) as date, SUM(amount_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Bookings per day
        $bookingsQuery = Reservation::query();
        if ($salesRange['from'] && $salesRange['to']) {
            $bookingsQuery->whereBetween('created_at', [$salesRange['from'], $salesRange['to']]);
        }

        $bookingStats = $bookingsQuery
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /* ===================== NEW USERS TIMESERIES (by role) ===================== */

        $newUsersBase = Users::query();
        if ($usersRange['from'] && $usersRange['to']) {
            $newUsersBase->whereBetween('created_at', [$usersRange['from'], $usersRange['to']]);
        }

        $newUsersStats = $newUsersBase
            ->selectRaw('DATE(created_at) as date, role, COUNT(*) as total')
            ->groupBy('date', 'role')
            ->orderBy('date')
            ->get();

        $newUserDates = $newUsersStats->pluck('date')->unique()->sort()->values();

        $clientsPerDate = $newUsersStats
            ->where('role', 'client')
            ->pluck('total', 'date');

        $coachesPerDate = $newUsersStats
            ->where('role', 'coach')
            ->pluck('total', 'date');

        $newUsersLabels       = $newUserDates->map(fn ($d) => Carbon::parse($d)->format('M d'))->values();
        $newClientsSeries     = [];
        $newCoachesSeries     = [];
        $newUsersTotalSeries  = [];

        foreach ($newUserDates as $d) {
            $c  = (int) ($clientsPerDate[$d] ?? 0);
            $co = (int) ($coachesPerDate[$d] ?? 0);

            $newClientsSeries[]    = $c;
            $newCoachesSeries[]    = $co;
            $newUsersTotalSeries[] = $c + $co;
        }

        /* ===================== CANCELLATIONS (salesRange) ===================== */

        $cancellationsQuery = Reservation::where('status', 'cancelled');
        if ($salesRange['from'] && $salesRange['to']) {
            $cancellationsQuery->whereBetween('created_at', [$salesRange['from'], $salesRange['to']]);
        }

        $cancellationStats = $cancellationsQuery
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /* ===================== REVENUE BY PAYMENT METHOD ===================== */

        $paymentMethodQuery = Payment::whereIn('status', $paidStatuses);
        if ($salesRange['from'] && $salesRange['to']) {
            $paymentMethodQuery->whereBetween('created_at', [$salesRange['from'], $salesRange['to']]);
        }

        $paymentMethods = $paymentMethodQuery
            ->selectRaw('method, COUNT(*) as count, SUM(amount_total) as total')
            ->groupBy('method')
            ->get();

        $paymentMethodLabels = $paymentMethods->pluck('method')
            ->map(fn ($m) => $m ? ucfirst($m) : 'Unknown')
            ->values();

        $paymentMethodTotals = $paymentMethods->pluck('total')->map(fn ($v) => (float) $v)->values();
        $paymentMethodCounts = $paymentMethods->pluck('count')->values();

        /* ===================== BOOKINGS BY HOUR OF DAY ===================== */

        $bookingsHourQuery = Reservation::query();
        if ($salesRange['from'] && $salesRange['to']) {
            $bookingsHourQuery->whereBetween('created_at', [$salesRange['from'], $salesRange['to']]);
        }

        $bookingsByHourRaw = $bookingsHourQuery
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hours             = collect(range(0, 23));
        $bookingsByHourMap = $bookingsByHourRaw->pluck('total', 'hour');

        $bookingsByHourLabels = $hours->map(fn ($h) => sprintf('%02d:00', $h))->values();
        $bookingsByHourValues = $hours->map(fn ($h) => (int) ($bookingsByHourMap[$h] ?? 0))->values();

        /* ===================== USERS BY ROLE & BOOKINGS BY STATUS ===================== */

        $roleClients = Users::where('role', 'client');
        $roleCoaches = Users::where('role', 'coach');

        if ($usersRange['from'] && $usersRange['to']) {
            $roleClients->whereBetween('created_at', [$usersRange['from'], $usersRange['to']]);
            $roleCoaches->whereBetween('created_at', [$usersRange['from'], $usersRange['to']]);
        }

        $clientsTotal = $roleClients->count();
        $coachesTotal = $roleCoaches->count();

        $bookingStatusQuery = Reservation::query();
        if ($txRange['from'] && $txRange['to']) {
            $bookingStatusQuery->whereBetween('created_at', [$txRange['from'], $txRange['to']]);
        }

        $bookingStatus = $bookingStatusQuery
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        /* ===================== TRANSACTIONS TABLE (txRange) ===================== */

        $txQuery = Payment::whereIn('status', $paidStatuses);
        if ($txRange['from'] && $txRange['to']) {
            $txQuery->whereBetween('created_at', [$txRange['from'], $txRange['to']]);
        }

        $payments = $txQuery->latest()->take(10)->get();

        /* ===================== COACH REVENUE & PLATFORM FEES (salesRange) ===================== */

        $coachRevQuery = Payment::whereIn('status', $paidStatuses);
        if ($salesRange['from'] && $salesRange['to']) {
            $coachRevQuery->whereBetween('created_at', [$salesRange['from'], $salesRange['to']]);
        }

        $coachRevenueRaw = $coachRevQuery
            ->selectRaw('DATE(created_at) as date, SUM(coach_earnings) as coach_total, SUM(platform_fee) as platform_total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Build lightweight collections with {date, total} so we can reuse fillDateBuckets
        $coachNetStats = $coachRevenueRaw->map(function ($row) {
            return (object) [
                'date'  => $row->date,
                'total' => (float) $row->coach_total,
            ];
        });

        $platformFeeStats = $coachRevenueRaw->map(function ($row) {
            return (object) [
                'date'  => $row->date,
                'total' => (float) $row->platform_total,
            ];
        });

        /* ===================== ALIGN & DERIVE SERIES ===================== */

        // Base aligned series
        $usersSeries        = $this->fillDateBuckets($userStats,        $usersRange['from'], $usersRange['to']);
        $salesSeries        = $this->fillDateBuckets($salesStats,       $salesRange['from'], $salesRange['to']);
        $bookingsSeries     = $this->fillDateBuckets($bookingStats,     $salesRange['from'], $salesRange['to']);
        $cancellationsSeries= $this->fillDateBuckets($cancellationStats,$salesRange['from'], $salesRange['to']);
        $coachGrossSeries   = $this->fillDateBuckets($coachNetStats,    $salesRange['from'], $salesRange['to']);
        $platformFeeSeries  = $this->fillDateBuckets($platformFeeStats, $salesRange['from'], $salesRange['to']);

        // Average booking value per day (client side)
        $avgBookingValues = [];
        foreach ($bookingsSeries['values'] as $i => $count) {
            $salesVal = $salesSeries['values'][$i] ?? 0;
            $avgBookingValues[] = $count > 0
                ? round($salesVal / $count, 2)
                : 0;
        }

        // Coach revenue: without vs with platform fees (commission)
        // Here coach_earning = "without platform charges".
        $coachWithoutPlatform = array_map(
            fn ($v) => round((float) $v, 2),
            $coachGrossSeries['values']->all()
        );

        $coachWithPlatform = [];
        foreach ($coachWithoutPlatform as $v) {
            $coachWithPlatform[] = round($v * (1 - $coachCommissionRate), 2);
        }

        /* ===================== Chart data payload ===================== */

        $chartData = [
            // Base trends
            'users'    => $usersSeries,
            'sales'    => $salesSeries,
            'bookings' => $bookingsSeries,

            // Bookings & revenue over time
            'bookings_revenue' => [
                'labels'   => $bookingsSeries['labels'],
                'bookings' => $bookingsSeries['values'],
                'revenue'  => $salesSeries['values'],
            ],

            // New Users Over Time
            'new_users' => [
                'labels'  => $newUsersLabels,
                'clients' => $newClientsSeries,
                'coaches' => $newCoachesSeries,
                'total'   => $newUsersTotalSeries,
            ],

            // Cancellations Over Time
            'cancellations' => $cancellationsSeries,

            // Avg booking value
            'avg_booking_value' => [
                'labels' => $bookingsSeries['labels'],
                'values' => $avgBookingValues,
            ],

            // Payment methods
            'payment_methods' => [
                'labels' => $paymentMethodLabels,
                'totals' => $paymentMethodTotals,
                'counts' => $paymentMethodCounts,
            ],

            // Bookings by hour
            'bookings_by_hour' => [
                'labels' => $bookingsByHourLabels,
                'values' => $bookingsByHourValues,
            ],

            // Composition pies
            'composition' => [
                'roles' => [
                    'labels' => ['Clients', 'Coaches'],
                    'values' => [$clientsTotal, $coachesTotal],
                ],
                'booking_status' => [
                    'labels' => $bookingStatus->pluck('status')
                        ->map(fn ($s) => ucfirst($s ?? 'unknown'))
                        ->values(),
                    'values' => $bookingStatus->pluck('total')->values(),
                ],
            ],

            // Coach revenue with/without platform fees
            'coach_revenue' => [
                'labels'           => $coachGrossSeries['labels'],
                'without_platform' => $coachWithoutPlatform,    // coach_earning as is
                'with_platform'    => $coachWithPlatform,       // after commission deduction
            ],

            // Platform fees over time
            'platform_fees' => $platformFeeSeries,
        ];

        return view('admin.dashboard.index', [
            // KPI box
            'kpiRange'        => $kpiRange,
            'visitorsCount'   => $visitorsCount,
            'totalSales'      => $totalSales,
            'bookingsCount'   => $bookingsCount,
            'vendorsCount'    => $vendorsCount,
            'servicesCount'   => $servicesCount,
            'clientsCount'    => $clientsCount,
            'categoriesCount' => $categoriesCount,

            // charts ranges
            'usersRange'      => $usersRange,
            'salesRange'      => $salesRange,

            // chart data
            'chartData'       => $chartData,

            // new users list
            'newRange'        => $newRange,
            'latestClients'   => Users::where('role', 'client')
                                    ->latest()
                                    ->paginate(5, ['*'], 'clients_page'),
            'latestCoaches'   => Users::where('role', 'coach')
                                    ->latest()
                                    ->paginate(5, ['*'], 'coaches_page'),

            // transactions
            'txRange'         => $txRange,
            'payments'        => $payments,
        ]);
    }

    /**
     * Fill missing dates between $from and $to with zeros.
     * $stats must be a collection of {date, total}.
     */
    protected function fillDateBuckets($stats, Carbon $from = null, Carbon $to = null)
    {
        if (!$from || !$to) {
            return [
                'labels' => $stats->pluck('date')
                    ->map(fn ($d) => Carbon::parse($d)->format('M d'))
                    ->values(),
                'values' => $stats->pluck('total')->values(),
            ];
        }

        // Clamp "to" to today (never include future)
        $today = Carbon::today();
        if ($to->greaterThan($today)) {
            $to = $today;
        }

        $period = new \Carbon\CarbonPeriod($from->copy(), $to->copy());
        $buckets = [];

        foreach ($period as $date) {
            $key    = $date->format('Y-m-d');
            $record = $stats->firstWhere('date', $key);
            $buckets[$key] = $record ? (float) $record->total : 0;
        }

        return [
            'labels' => collect(array_keys($buckets))
                ->map(fn ($d) => Carbon::parse($d)->format('M d'))
                ->values(),
            'values' => array_values($buckets),
        ];
    }

    // JSON endpoint for live "active visitors" widget
    public function activeVisitors()
    {
        $activeSince = now()->subSeconds(20);

        $count = Visit::where('last_seen_at', '>=', $activeSince)
            ->distinct('visitor_id')
            ->count('visitor_id');

        return response()->json(['count' => $count]);
    }
}
