<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CoachPayout;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperWithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $period = strtolower((string) $request->query('period', 'all'));
        $status = strtolower((string) $request->query('status', 'all'));
        $provider = strtolower((string) $request->query('provider', 'all'));
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($request, $period);

        $baseQuery = CoachPayout::query()
            ->with([
                'coachProfile.user',
                'payoutAccount',
                'payoutRun',
            ]);

        $this->applyFilters(
            query: $baseQuery,
            startDate: $startDate,
            endDate: $endDate,
            status: $status,
            provider: $provider,
            search: $search
        );

        $withdrawals = (clone $baseQuery)
            ->latest('created_at')
            ->paginate(15)
            ->appends($request->query());

        $summaryBase = clone $baseQuery;

        $summary = [
            'total_records' => (clone $summaryBase)->count(),
            'total_amount_minor' => (int) (clone $summaryBase)->sum('amount_minor'),

            'paid_count' => (clone $summaryBase)
                ->where('status', 'paid')
                ->count(),

            'processing_count' => (clone $summaryBase)
                ->whereIn('status', ['pending', 'processing', 'transfer_created', 'payout_pending'])
                ->count(),

            'failed_count' => (clone $summaryBase)
                ->whereIn('status', ['failed', 'reversed'])
                ->count(),

            'paid_amount_minor' => (int) (clone $summaryBase)
                ->where('status', 'paid')
                ->sum('amount_minor'),

            'processing_amount_minor' => (int) (clone $summaryBase)
                ->whereIn('status', ['pending', 'processing', 'transfer_created', 'payout_pending'])
                ->sum('amount_minor'),

            'failed_amount_minor' => (int) (clone $summaryBase)
                ->whereIn('status', ['failed', 'reversed'])
                ->sum('amount_minor'),
        ];

        $currency = strtoupper((string) ((clone $baseQuery)->value('currency') ?? 'USD'));

        $barChart = $this->buildBarChart(
            query: clone $baseQuery,
            period: $period,
            startDate: $startDate,
            endDate: $endDate
        );

        $lineChart = $this->buildLineChart(
            query: clone $baseQuery,
            period: $period,
            startDate: $startDate,
            endDate: $endDate
        );

        $pieChart = $this->buildPieChart(
            query: clone $baseQuery
        );

        $availableStatuses = [
            'all',
            'pending',
            'processing',
            'transfer_created',
            'payout_pending',
            'paid',
            'failed',
            'reversed',
        ];

        $availableProviders = [
            'all',
            'stripe',
        ];

        return view('superadmin.withdrawals.index', [
            'withdrawals' => $withdrawals,
            'summary' => $summary,
            'currency' => $currency,

            'period' => $period,
            'status' => $status,
            'provider' => $provider,
            'search' => $search,

            'startDate' => $startDate?->toDateString(),
            'endDate' => $endDate?->toDateString(),

            'barChart' => $barChart,
            'lineChart' => $lineChart,
            'pieChart' => $pieChart,

            'availableStatuses' => $availableStatuses,
            'availableProviders' => $availableProviders,

            'selectedDay' => $request->query('day'),
            'selectedWeek' => $request->query('week'),
            'selectedMonth' => $request->query('month'),
            'selectedYear' => $request->query('year'),
            'customFrom' => $request->query('from'),
            'customTo' => $request->query('to'),
        ]);
    }

    private function applyFilters(
        $query,
        ?Carbon $startDate,
        ?Carbon $endDate,
        string $status,
        string $provider,
        string $search
    ): void {
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ]);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($provider !== 'all' && $provider !== '') {
            $query->where('provider', $provider);
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('id', 'like', '%' . $search . '%')
                    ->orWhere('provider_transfer_id', 'like', '%' . $search . '%')
                    ->orWhere('provider_payout_id', 'like', '%' . $search . '%')
                    ->orWhere('provider_balance_txn_id', 'like', '%' . $search . '%')
                    ->orWhere('failure_reason', 'like', '%' . $search . '%')
                    ->orWhereHas('coachProfile.user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }
    }

    private function resolveDateRange(Request $request, string $period): array
    {
        return match ($period) {
            'daily' => $this->resolveDailyRange($request),
            'weekly' => $this->resolveWeeklyRange($request),
            'monthly' => $this->resolveMonthlyRange($request),
            'yearly' => $this->resolveYearlyRange($request),
            'custom' => $this->resolveCustomRange($request),
            'all' => [null, null],
            default => [null, null],
        };
    }

    private function resolveDailyRange(Request $request): array
    {
        $day = (string) $request->query('day', '');

        if ($day === '') {
            $date = now();
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        $date = Carbon::parse($day);

        return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
    }

    private function resolveWeeklyRange(Request $request): array
    {
        $week = (string) $request->query('week', '');

        if (! preg_match('/^\d{4}-W\d{2}$/', $week)) {
            return [now()->startOfWeek(), now()->endOfWeek()];
        }

        [$year, $weekNumber] = explode('-W', $week);

        $start = Carbon::now()->setISODate((int) $year, (int) $weekNumber)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        return [$start, $end];
    }

    private function resolveMonthlyRange(Request $request): array
    {
        $month = (string) $request->query('month', '');

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    private function resolveYearlyRange(Request $request): array
    {
        $year = (int) $request->query('year', now()->year);

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        return [$start, $end];
    }

    private function resolveCustomRange(Request $request): array
    {
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        if ($from === '' || $to === '') {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function buildBarChart($query, string $period, ?Carbon $startDate, ?Carbon $endDate): array
    {
        [$labels, $values] = $this->buildTimeSeries(
            query: $query,
            period: $period,
            startDate: $startDate,
            endDate: $endDate,
            mode: 'amount'
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Withdrawal Amount',
                    'data' => $values,
                ],
            ],
        ];
    }

    private function buildLineChart($query, string $period, ?Carbon $startDate, ?Carbon $endDate): array
    {
        [$labels, $values] = $this->buildTimeSeries(
            query: $query,
            period: $period,
            startDate: $startDate,
            endDate: $endDate,
            mode: 'count'
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Withdrawal Count',
                    'data' => $values,
                ],
            ],
        ];
    }

    private function buildPieChart($query): array
    {
        $rows = $query
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return [
            'labels' => $rows->pluck('status')
                ->map(fn ($status) => ucfirst(str_replace('_', ' ', (string) $status)))
                ->values()
                ->all(),
            'datasets' => [
                [
                    'label' => 'Status Distribution',
                    'data' => $rows->pluck('total')
                        ->map(fn ($count) => (int) $count)
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    private function buildTimeSeries($query, string $period, ?Carbon $startDate, ?Carbon $endDate, string $mode): array
    {
        $start = $startDate?->copy() ?? now()->subMonths(11)->startOfMonth();
        $end = $endDate?->copy() ?? now()->endOfMonth();

        $bucket = $this->resolveChartBucket($period, $start, $end);

        $rows = $query
            ->selectRaw($this->bucketSelectSql($bucket) . ' as bucket')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(amount_minor), 0) as total_amount_minor')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $labels = [];
        $values = [];

        foreach ($this->generateBuckets($bucket, $start, $end) as $bucketItem) {
            $key = $bucketItem['key'];
            $labels[] = $bucketItem['label'];

            $row = $rows->get($key);

            $values[] = $mode === 'count'
                ? (int) ($row->total_count ?? 0)
                : round(((int) ($row->total_amount_minor ?? 0)) / 100, 2);
        }

        return [$labels, $values];
    }

    private function resolveChartBucket(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'daily' => 'hour',
            'weekly' => 'day',
            'monthly' => 'day',
            'yearly' => 'month',
            'custom' => $start->diffInDays($end) <= 31
                ? 'day'
                : ($start->diffInMonths($end) <= 12 ? 'month' : 'year'),
            'all' => 'month',
            default => 'day',
        };
    }

    private function bucketSelectSql(string $bucket): string
    {
        return match ($bucket) {
            'hour' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
            'day' => "DATE(created_at)",
            'month' => "DATE_FORMAT(created_at, '%Y-%m-01')",
            'year' => "DATE_FORMAT(created_at, '%Y-01-01')",
            default => "DATE(created_at)",
        };
    }

    private function generateBuckets(string $bucket, Carbon $start, Carbon $end): array
    {
        $items = [];

        if ($bucket === 'hour') {
            $cursor = $start->copy()->startOfDay();
            $last = $end->copy()->endOfDay();

            while ($cursor->lte($last)) {
                $items[] = [
                    'key' => $cursor->format('Y-m-d H:00:00'),
                    'label' => $cursor->format('H:i'),
                ];
                $cursor->addHour();
            }

            return $items;
        }

        if ($bucket === 'day') {
            foreach (CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay()) as $date) {
                $items[] = [
                    'key' => $date->format('Y-m-d'),
                    'label' => $date->format('d M'),
                ];
            }

            return $items;
        }

        if ($bucket === 'month') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();

            while ($cursor->lte($last)) {
                $items[] = [
                    'key' => $cursor->format('Y-m-01'),
                    'label' => $cursor->format('M Y'),
                ];
                $cursor->addMonth();
            }

            return $items;
        }

        $cursor = $start->copy()->startOfYear();
        $last = $end->copy()->startOfYear();

        while ($cursor->lte($last)) {
            $items[] = [
                'key' => $cursor->format('Y-01-01'),
                'label' => $cursor->format('Y'),
            ];
            $cursor->addYear();
        }

        return $items;
    }
}