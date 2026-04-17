<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachPayout;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminWithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $baseQuery = CoachPayout::query()
            ->with([
                'coachProfile.user',
                'payoutAccount',
                'payoutRun',
            ]);

        $this->applyFilters(
            query: $baseQuery,
            startDate: $filters['startDate'],
            endDate: $filters['endDate'],
            status: $filters['status'],
            provider: $filters['provider'],
            search: $filters['search']
        );

        $withdrawals = (clone $baseQuery)
            ->latest('created_at')
            ->paginate(15)
            ->appends($request->query());

        $filterOptions = [
            'periods' => [
                'daily'   => 'Daily',
                'weekly'  => 'Weekly',
                'monthly' => 'Monthly',
                'yearly'  => 'Yearly',
                'custom'  => 'Custom',
                'all'     => 'All Time',
            ],
            'statuses' => [
                'all'              => 'All Statuses',
                'pending'          => 'Pending',
                'processing'       => 'Processing',
                'transfer_created' => 'Transfer Created',
                'payout_pending'   => 'Payout Pending',
                'paid'             => 'Paid',
                'failed'           => 'Failed',
                'reversed'         => 'Reversed',
            ],
            'providers' => [
                'all'    => 'All Providers',
                'stripe' => 'Stripe',
            ],
        ];

        return view('admin.withdrawals.index', [
            'withdrawals'       => $withdrawals,
            'filters'           => $filters,
            'filterOptions'     => $filterOptions,

            'period'            => $filters['period'],
            'status'            => $filters['status'],
            'provider'          => $filters['provider'],
            'search'            => $filters['search'],

            'startDate'         => optional($filters['startDate'])->toDateString(),
            'endDate'           => optional($filters['endDate'])->toDateString(),

            'selectedDay'       => $filters['selectedDay'],
            'selectedWeek'      => $filters['selectedWeek'],
            'selectedMonth'     => $filters['selectedMonth'],
            'selectedYear'      => $filters['selectedYear'],
            'customFrom'        => $filters['customFrom'],
            'customTo'          => $filters['customTo'],
        ]);
    }

    public function show(CoachPayout $withdrawal)
    {
        $withdrawal->load([
            'coachProfile.user',
            'payoutAccount',
            'payoutRun',
        ]);

        return view('admin.withdrawals.show', [
            'withdrawal' => $withdrawal,
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

    private function resolveFilters(Request $request): array
    {
        $period = strtolower((string) $request->query('period', 'all'));
        $status = strtolower((string) $request->query('status', 'all'));
        $provider = strtolower((string) $request->query('provider', 'all'));
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($request, $period);

        return [
            'period'        => $period,
            'status'        => $status,
            'provider'      => $provider,
            'search'        => $search,

            'startDate'     => $startDate,
            'endDate'       => $endDate,

            'selectedDay'   => $request->query('day'),
            'selectedWeek'  => $request->query('week'),
            'selectedMonth' => $request->query('month'),
            'selectedYear'  => $request->query('year'),
            'customFrom'    => $request->query('from'),
            'customTo'      => $request->query('to'),
        ];
    }

    private function resolveDateRange(Request $request, string $period): array
    {
        return match ($period) {
            'daily'   => $this->resolveDailyRange($request),
            'weekly'  => $this->resolveWeeklyRange($request),
            'monthly' => $this->resolveMonthlyRange($request),
            'yearly'  => $this->resolveYearlyRange($request),
            'custom'  => $this->resolveCustomRange($request),
            'all'     => [null, null],
            default   => [null, null],
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

        if (!preg_match('/^\d{4}-W\d{2}$/', $week)) {
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

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
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
}