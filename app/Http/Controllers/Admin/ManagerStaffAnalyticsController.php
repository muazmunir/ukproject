<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\StaffTeam;
use App\Models\StaffTeamMember;
use App\Models\SupportConversation;
use App\Models\SupportConversationRating;
use App\Models\Users;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class ManagerStaffAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);

        $staffBaseQuery = $this->buildStaffQuery($filters);
        $staffIds = (clone $staffBaseQuery)->pluck('users.id')->all();

        $staffPerformance = $this->buildStaffPerformancePaginator($staffBaseQuery, $filters);

        $teamMap = $this->buildTeamMap(
            $staffPerformance->getCollection()->pluck('id')->all()
        );

        $staffPerformance->setCollection(
            $staffPerformance->getCollection()->map(function ($row) use ($teamMap, $filters) {
                return $this->transformStaffRow($row, $teamMap, $filters);
            })
        );

        $summary = $this->buildSummary($staffIds, $filters);
        $ratingDistribution = $this->buildRatingDistribution($staffIds, $filters);
        $ratings = $this->buildRatingsPaginator($staffIds, $filters);

        $topRatedStaff = $this->buildTopRatedStaff($staffIds, $filters, $filters['leaderboard_limit']);
        $lowestRatedStaff = $this->buildLowestRatedStaff($staffIds, $filters, $filters['leaderboard_limit']);
        $handlingLeaders = $this->buildHandlingLeaders($staffIds, $filters, $filters['leaderboard_limit']);
        $requeueLeaders = $this->buildRequeueLeaders($staffIds, $filters, $filters['leaderboard_limit']);

        $disputeDecisionLeaders = $this->buildDisputeDecisionLeaders($staffIds, $filters, $filters['leaderboard_limit']);
        $disputeResolutionLeaders = $this->buildDisputeResolutionLeaders($staffIds, $filters, $filters['leaderboard_limit']);
        $openDisputeLeaders = $this->buildOpenedDisputeLeaders($staffIds, $filters, $filters['leaderboard_limit']);
        $sentToReviewLeaders = $this->buildSentToReviewLeaders($staffIds, $filters, $filters['leaderboard_limit']);

        $selectedStaff = $this->buildSelectedStaffBlock($filters);

        $chartData = [
            'ratings_distribution' => $this->buildRatingsDistributionChart($ratingDistribution),
            'role_distribution' => $this->buildRoleDistributionChart($staffIds),

            'top_rated_chart' => $this->buildStaffBarChart($topRatedStaff, 'avg_rating', $filters['leaderboard_limit']),
            'lowest_rated_chart' => $this->buildStaffBarChart($lowestRatedStaff, 'avg_rating', $filters['leaderboard_limit']),
            'handling_time_chart' => $this->buildStaffBarChart($handlingLeaders, 'avg_handling_minutes', $filters['leaderboard_limit']),
            'requeue_chart' => $this->buildStaffBarChart($requeueLeaders, 'requeued_chats', $filters['leaderboard_limit']),

            'dispute_decision_chart' => $this->buildStaffBarChart($disputeDecisionLeaders, 'dispute_decisions', $filters['leaderboard_limit']),
            'dispute_resolution_chart' => $this->buildStaffBarChart($disputeResolutionLeaders, 'resolved_disputes', $filters['leaderboard_limit']),
            'dispute_open_load_chart' => $this->buildStaffBarChart($openDisputeLeaders, 'opened_disputes', $filters['leaderboard_limit']),
            'sent_to_review_chart' => $this->buildStaffBarChart($sentToReviewLeaders, 'sent_to_review', $filters['leaderboard_limit']),

            'overview_comparison' => [
                'labels' => [
                    'Completed Chats',
                    'Requeued Chats',
                    'Escalations Requested',
                    'Closed Without Resolve',
                    'Disputes Created',
                ],
                'values' => [
                    (int) ($summary->completed_chats ?? 0),
                    (int) ($summary->requeued_chats ?? 0),
                    (int) ($summary->escalated_chats ?? 0),
                    (int) ($summary->closed_without_resolve ?? 0),
                    (int) ($summary->disputes_created ?? 0),
                ],
            ],

            'dispute_actions_chart' => [
                'labels' => ['Pay Coach', 'Refund Full', 'Refund Service', 'Rejected'],
                'values' => [
                    (int) ($summary->dispute_pay_coach ?? 0),
                    (int) ($summary->dispute_refund_full ?? 0),
                    (int) ($summary->dispute_refund_service ?? 0),
                    (int) ($summary->dispute_rejected ?? 0),
                ],
            ],

            'dispute_status_chart' => [
                'labels' => ['Queue', 'Opened', 'In Review', 'Resolved'],
                'values' => [
                    (int) ($summary->queue_disputes ?? 0),
                    (int) ($summary->opened_disputes ?? 0),
                    (int) ($summary->in_review_disputes ?? 0),
                    (int) ($summary->resolved_disputes ?? 0),
                ],
            ],
        ];

       $staffOptions = Users::query()
    ->from('users')
    ->whereRaw('LOWER(users.role) = ?', ['admin'])
    ->leftJoin('staff_team_members as stm', function ($join) {
        $join->on('stm.agent_id', '=', 'users.id')
            ->whereNull('stm.end_at');
    })
    ->leftJoin('staff_teams as st', function ($join) {
        $join->on('st.id', '=', 'stm.team_id')
            ->where('st.is_active', true)
            ->whereNull('st.deleted_at');
    })
    ->orderBy('users.username')
    ->orderBy('users.first_name')
    ->orderBy('users.last_name')
    ->get([
        'users.id',
        'users.username',
        'users.first_name',
        'users.last_name',
        'users.email',
        'users.role',
        'st.id as team_id',
    ])
    ->map(function ($u) {
        return (object) [
            'id' => $u->id,
            'label' => $this->resolveUserDisplayName($u) . ' - ' . ($u->email ?: '—'),
            'role' => $this->displayRole($u->role),
            'team_id' => $u->team_id,
        ];
    });

        $teamOptions = StaffTeam::query()
            ->where('manager_id', auth()->id())
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'manager_id']);

        return view('admin.staff_analytics.index', [
            'filters' => $filters,
            'summary' => $summary,
            'chartData' => $chartData,
            'ratingDistribution' => $ratingDistribution,
            'staffPerformance' => $staffPerformance,
            'ratings' => $ratings,
            'selectedStaff' => $selectedStaff,

            'topRatedStaff' => $topRatedStaff,
            'lowestRatedStaff' => $lowestRatedStaff,
            'handlingLeaders' => $handlingLeaders,
            'requeueLeaders' => $requeueLeaders,

            'disputeDecisionLeaders' => $disputeDecisionLeaders,
            'disputeResolutionLeaders' => $disputeResolutionLeaders,
            'openDisputeLeaders' => $openDisputeLeaders,
            'sentToReviewLeaders' => $sentToReviewLeaders,

            'staffOptions' => $staffOptions,
            'teamOptions' => $teamOptions,
            'sortOptions' => $this->sortOptions(),
            'tabOptions' => $this->tabOptions(),
            'periodOptions' => $this->periodOptions(),
        ]);
    }

    protected function resolveFilters(Request $request): array
    {
        $sort = strtolower((string) $request->get('sort', 'completed_desc'));
        if (!array_key_exists($sort, $this->sortOptions())) {
            $sort = 'completed_desc';
        }

        $tab = strtolower((string) $request->get('tab', 'overview'));
        if (!array_key_exists($tab, $this->tabOptions())) {
            $tab = 'overview';
        }

        $period = strtolower((string) $request->get('period', 'all'));
        if (!array_key_exists($period, $this->periodOptions())) {
            $period = 'all';
        }

        $day = $request->filled('day')
            ? Carbon::parse((string) $request->get('day'))->format('Y-m-d')
            : now()->format('Y-m-d');

        $week = $request->filled('week')
            ? (string) $request->get('week')
            : now()->format('o-\WW');

        $month = $request->filled('month')
            ? (string) $request->get('month')
            : now()->format('Y-m');

        $year = $request->filled('year')
            ? (int) $request->get('year')
            : (int) now()->year;

        $customFrom = $request->filled('from')
            ? Carbon::parse((string) $request->get('from'))->startOfDay()
            : null;

        $customTo = $request->filled('to')
            ? Carbon::parse((string) $request->get('to'))->endOfDay()
            : null;

        [$from, $to] = $this->resolveDateRange(
            $period,
            $day,
            $week,
            $month,
            $year,
            $customFrom,
            $customTo
        );

        $stars = $request->filled('stars') ? (int) $request->get('stars') : null;
        if ($stars !== null && !in_array($stars, [1, 2, 3, 4, 5], true)) {
            $stars = null;
        }

        return [
            'role' => 'admin',
            'tab' => $tab,
            'period' => $period,

            'day' => $day,
            'week' => $week,
            'month' => $month,
            'year' => $year,

            'from' => $from,
            'to' => $to,
            'custom_from' => $customFrom,
            'custom_to' => $customTo,

            'q' => trim((string) $request->get('q', '')),
            'staff_id' => $request->filled('staff_id') ? (int) $request->get('staff_id') : null,
            'team_id' => $request->filled('team_id') ? (int) $request->get('team_id') : null,
            'sort' => $sort,
            'stars' => $stars,
            'staff_per' => max(5, min(100, (int) $request->get('staff_per', 15))),
            'ratings_per' => max(5, min(100, (int) $request->get('ratings_per', 12))),
            'leaderboard_limit' => max(3, min(20, (int) $request->get('leaderboard_limit', 7))),
        ];
    }

    protected function resolveDateRange(
        string $period,
        string $day,
        string $week,
        string $month,
        int $year,
        ?Carbon $customFrom,
        ?Carbon $customTo
    ): array {
        return match ($period) {
            'daily' => [
                Carbon::parse($day)->startOfDay(),
                Carbon::parse($day)->endOfDay(),
            ],

            'weekly' => $this->parseWeekRange($week),

            'monthly' => [
                Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay(),
                Carbon::createFromFormat('Y-m', $month)->endOfMonth()->endOfDay(),
            ],

            'yearly' => [
                Carbon::create($year, 1, 1)->startOfYear()->startOfDay(),
                Carbon::create($year, 1, 1)->endOfYear()->endOfDay(),
            ],

            'custom' => [
                ($customFrom && $customTo) ? $customFrom : null,
                ($customFrom && $customTo) ? $customTo : null,
            ],

            default => [null, null],
        };
    }

    protected function parseWeekRange(string $week): array
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
            $year = (int) $m[1];
            $weekNo = (int) $m[2];

            $start = now()->setISODate($year, $weekNo)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = (clone $start)->endOfWeek(Carbon::SUNDAY)->endOfDay();

            return [$start, $end];
        }

        $start = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = now()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return [$start, $end];
    }

   protected function buildStaffQuery(array $filters): Builder
{
    $q = Users::query()
        ->from('users')
        ->whereRaw('LOWER(users.role) = ?', ['admin']);

    if (!empty($filters['q'])) {
        $term = $filters['q'];

        $q->where(function ($sub) use ($term) {
            $sub->where('users.username', 'like', "%{$term}%")
                ->orWhere('users.email', 'like', "%{$term}%")
                ->orWhere('users.first_name', 'like', "%{$term}%")
                ->orWhere('users.last_name', 'like', "%{$term}%");
        });
    }

    if (!empty($filters['staff_id'])) {
        $q->where('users.id', $filters['staff_id']);
    }

    if (!empty($filters['team_id'])) {
        $teamId = (int) $filters['team_id'];

        $q->whereExists(function ($sq) use ($teamId) {
            $sq->selectRaw('1')
                ->from('staff_team_members as stm')
                ->join('staff_teams as st', 'st.id', '=', 'stm.team_id')
                ->whereColumn('stm.agent_id', 'users.id')
                ->whereNull('stm.end_at')
                ->where('st.id', $teamId)
                ->where('st.is_active', true)
                ->whereNull('st.deleted_at');
        });
    }

    return $q;
}

    protected function buildStaffMetricsQuery(Builder $staffQuery, array $filters): Builder
    {
        $from = $filters['from'];
        $to = $filters['to'];

        return (clone $staffQuery)
            ->select([
                'users.id',
                'users.username',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.role',
                DB::raw('COALESCE(users.started_at, users.created_at) as service_start_at'),
            ])
            ->selectSub(
                $this->conversationMetricSubquery('closed_by', 'resolved', $from, $to),
                'completed_chats'
            )
            ->selectSub(
                $this->conversationMetricSubquery('closed_by', 'closed', $from, $to),
                'closed_chats'
            )
            ->selectSub(
                $this->requeueMetricSubquery($from, $to),
                'requeued_chats'
            )
            ->selectSub(
                $this->escalationRequestedSubquery($from, $to),
                'escalations_requested'
            )
            ->selectSub(
                $this->escalationHandledSubquery($from, $to),
                'escalations_handled'
            )
            ->selectSub(
                $this->averageHandlingSubquery($from, $to),
                'avg_handling_minutes'
            )
            ->selectSub(
                $this->ratingCountSubquery($from, $to),
                'rating_count'
            )
            ->selectSub(
                $this->ratingAverageSubquery($from, $to),
                'avg_rating'
            )
            ->selectSub(
                $this->disputeDecisionCountSubquery($from, $to),
                'dispute_decisions'
            )
            ->selectSub(
                $this->disputeResolutionCountSubquery($from, $to),
                'resolved_disputes'
            )
            ->selectSub(
                $this->disputeDecisionActionCountSubquery('pay_coach', $from, $to),
                'dispute_pay_coach'
            )
            ->selectSub(
                $this->disputeDecisionActionCountSubquery('refund_full', $from, $to),
                'dispute_refund_full'
            )
            ->selectSub(
                $this->disputeDecisionActionCountSubquery('refund_service', $from, $to),
                'dispute_refund_service'
            )
            ->selectSub(
                $this->disputeDecisionActionCountSubquery('reject', $from, $to),
                'dispute_rejected'
            )
            ->selectSub(
                $this->disputeCurrentAssignedSubquery(),
                'opened_disputes'
            )
            ->selectSub(
                $this->disputeSentToReviewSubquery($from, $to),
                'sent_to_review'
            );
    }

    protected function buildStaffPerformancePaginator(Builder $staffQuery, array $filters): LengthAwarePaginator
    {
        $rows = $this->buildStaffMetricsQuery(clone $staffQuery, $filters);
        $this->applySort($rows, $filters['sort']);

        return $rows
            ->paginate($filters['staff_per'], ['*'], 'staff_page')
            ->appends(request()->query());
    }

    protected function transformStaffRow($row, array $teamMap, array $filters = [])
    {
        $row->display_name = $this->resolveUserDisplayName($row);
        $row->display_role = $this->displayRole($row->role);
        $row->team_name = $teamMap[$row->id]['team_name'] ?? '—';
        $row->team_manager = $teamMap[$row->id]['manager_name'] ?? '—';

        $serviceStart = $row->service_start_at ? Carbon::parse($row->service_start_at) : null;
        $row->service_start_at = $serviceStart;
        $row->service_days = $serviceStart ? $serviceStart->diffInDays(now()) : 0;

        $row->completed_chats = (int) ($row->completed_chats ?? 0);
        $row->closed_chats = (int) ($row->closed_chats ?? 0);
        $row->requeued_chats = (int) ($row->requeued_chats ?? 0);
        $row->escalations_requested = (int) ($row->escalations_requested ?? 0);
        $row->escalations_handled = (int) ($row->escalations_handled ?? 0);

        $row->rating_count = (int) ($row->rating_count ?? 0);
        $row->avg_rating = $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : null;
        $row->avg_handling_minutes = $row->avg_handling_minutes !== null ? round((float) $row->avg_handling_minutes, 1) : null;

        $row->dispute_decisions = (int) ($row->dispute_decisions ?? 0);
        $row->resolved_disputes = (int) ($row->resolved_disputes ?? 0);
        $row->dispute_pay_coach = (int) ($row->dispute_pay_coach ?? 0);
        $row->dispute_refund_full = (int) ($row->dispute_refund_full ?? 0);
        $row->dispute_refund_service = (int) ($row->dispute_refund_service ?? 0);
        $row->dispute_rejected = (int) ($row->dispute_rejected ?? 0);
        $row->opened_disputes = (int) ($row->opened_disputes ?? 0);
        $row->sent_to_review = (int) ($row->sent_to_review ?? 0);

        $row->details_url = route('admin.staff_analytics.index', array_merge(request()->query(), [
            'staff_id' => $row->id,
            'tab' => 'staff',
        ]));

        return $row;
    }

    protected function buildSummary(array $staffIds, array $filters): object
    {
        if (empty($staffIds)) {
            return (object) [
                'completed_chats' => 0,
                'avg_handling_minutes' => null,
                'requeued_chats' => 0,
                'escalated_chats' => 0,
                'avg_rating' => null,
                'rating_count' => 0,
                'with_feedback_count' => 0,
                'closed_without_resolve' => 0,
                'total_staff' => 0,
                'total_agents' => 0,
                'total_managers' => 0,
                'rated_staff_count' => 0,

                'disputes_created' => 0,
                'resolved_disputes' => 0,
                'queue_disputes' => 0,
                'opened_disputes' => 0,
                'in_review_disputes' => 0,
                'avg_dispute_handling_minutes' => null,
                'avg_dispute_resolution_minutes' => null,
                'resolution_rate' => 0.0,

                'dispute_pay_coach' => 0,
                'dispute_refund_full' => 0,
                'dispute_refund_service' => 0,
                'dispute_rejected' => 0,
            ];
        }

        $from = $filters['from'];
        $to = $filters['to'];

        $completedChats = SupportConversation::query()
            ->whereIn('closed_by', $staffIds)
            ->where('status', 'resolved')
            ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('closed_at', '<=', $to))
            ->count();

        $closedWithoutResolve = SupportConversation::query()
            ->whereIn('closed_by', $staffIds)
            ->where('status', 'closed')
            ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('closed_at', '<=', $to))
            ->count();

        $avgHandlingMinutes = SupportConversation::query()
            ->whereIn('closed_by', $staffIds)
            ->whereIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_started_at')
            ->whereNotNull('closed_at')
            ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('closed_at', '<=', $to))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, sla_started_at, closed_at)) as avg_minutes')
            ->value('avg_minutes');

        $requeuedChats = DB::table('support_messages')
            ->where('support_messages.type', 'system')
            ->where('support_messages.sender_type', 'system')
            ->whereIn('support_messages.sender_id', $staffIds)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(support_messages.meta, '$.event')) = 'conversation_requeued'")
            ->when($from, fn ($q) => $q->where('support_messages.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('support_messages.created_at', '<=', $to))
            ->count();

        $escalatedChats = SupportConversation::query()
            ->whereIn('manager_requested_by', $staffIds)
            ->whereNotNull('manager_requested_at')
            ->when($from, fn ($q) => $q->where('manager_requested_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('manager_requested_at', '<=', $to))
            ->count();

        $ratingsAgg = SupportConversationRating::query()
            ->whereIn('admin_id', $staffIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->selectRaw('COUNT(*) as rating_count, AVG(stars) as avg_rating, COUNT(DISTINCT admin_id) as rated_staff_count')
            ->first();

        $withFeedbackCount = SupportConversationRating::query()
            ->whereIn('admin_id', $staffIds)
            ->whereNotNull('feedback')
            ->whereRaw('TRIM(feedback) <> ""')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        $totalAgents = Users::query()
            ->whereIn('id', $staffIds)
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->count();

        $totalManagers = 0;

        $disputesCreatedBase = Dispute::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $resolvedDisputesBase = Dispute::query()
            ->whereNotNull('resolved_at')
            ->when($from, fn ($q) => $q->where('resolved_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to));

        $disputeDecisionsBase = Dispute::query()
            ->whereNotNull('decided_at')
            ->when($from, fn ($q) => $q->where('decided_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('decided_at', '<=', $to));

        $disputesCreated = (clone $disputesCreatedBase)->count();
        $resolvedDisputes = (clone $resolvedDisputesBase)->count();

        $disputeHandlingSeconds = Dispute::query()
            ->whereNotNull('decided_at')
            ->whereNotNull('sla_total_seconds')
            ->when($from, fn ($q) => $q->where('decided_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('decided_at', '<=', $to))
            ->avg('sla_total_seconds');

        $disputeResolutionMinutes = Dispute::query()
            ->whereNotNull('created_at')
            ->whereNotNull('resolved_at')
            ->when($from, fn ($q) => $q->where('resolved_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_minutes')
            ->value('avg_minutes');

        $queueDisputes = Dispute::query()
            ->whereNull('resolved_at')
            ->whereIn('status', ['open'])
            ->whereNull('assigned_staff_id')
            ->count();

        $openedDisputes = Dispute::query()
            ->whereNull('resolved_at')
            ->whereIn('status', ['opened'])
            ->count();

        $inReviewDisputes = Dispute::query()
            ->whereNull('resolved_at')
            ->whereIn('status', ['in_review'])
            ->count();

        $resolutionRate = $disputesCreated > 0
            ? round(($resolvedDisputes / $disputesCreated) * 100, 1)
            : 0.0;

        return (object) [
            'completed_chats' => $completedChats,
            'avg_handling_minutes' => $avgHandlingMinutes !== null ? round((float) $avgHandlingMinutes, 1) : null,
            'requeued_chats' => $requeuedChats,
            'escalated_chats' => $escalatedChats,
            'avg_rating' => $ratingsAgg && $ratingsAgg->avg_rating !== null ? round((float) $ratingsAgg->avg_rating, 2) : null,
            'rating_count' => (int) ($ratingsAgg->rating_count ?? 0),
            'with_feedback_count' => $withFeedbackCount,
            'closed_without_resolve' => $closedWithoutResolve,
            'total_staff' => count($staffIds),
            'total_agents' => $totalAgents,
            'total_managers' => $totalManagers,
            'rated_staff_count' => (int) ($ratingsAgg->rated_staff_count ?? 0),

            'disputes_created' => $disputesCreated,
            'resolved_disputes' => $resolvedDisputes,
            'queue_disputes' => $queueDisputes,
            'opened_disputes' => $openedDisputes,
            'in_review_disputes' => $inReviewDisputes,
            'avg_dispute_handling_minutes' => $disputeHandlingSeconds !== null ? round(((float) $disputeHandlingSeconds) / 60, 1) : null,
            'avg_dispute_resolution_minutes' => $disputeResolutionMinutes !== null ? round((float) $disputeResolutionMinutes, 1) : null,
            'resolution_rate' => $resolutionRate,

            'dispute_pay_coach' => (clone $disputeDecisionsBase)->where('decision_action', 'pay_coach')->count(),
            'dispute_refund_full' => (clone $disputeDecisionsBase)->where('decision_action', 'refund_full')->count(),
            'dispute_refund_service' => (clone $disputeDecisionsBase)->where('decision_action', 'refund_service')->count(),
            'dispute_rejected' => (clone $disputeDecisionsBase)->where('decision_action', 'reject')->count(),
        ];
    }

    protected function buildRatingDistribution(array $staffIds, array $filters): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        return SupportConversationRating::query()
            ->whereIn('admin_id', $staffIds)
            ->when($filters['from'], fn ($q) => $q->where('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->where('created_at', '<=', $filters['to']))
            ->selectRaw('stars, COUNT(*) as total')
            ->groupBy('stars')
            ->orderByDesc('stars')
            ->get()
            ->keyBy('stars');
    }

    protected function buildRatingsPaginator(array $staffIds, array $filters): LengthAwarePaginator
    {
        if (empty($staffIds)) {
            return SupportConversationRating::query()
                ->whereRaw('1 = 0')
                ->paginate($filters['ratings_per'], ['*'], 'ratings_page')
                ->appends(request()->query());
        }

        $q = SupportConversationRating::query()
            ->with([
                'customer:id,username,first_name,last_name,email',
                'ratedAdmin:id,username,first_name,last_name,email,role',
                'conversation:id,thread_id,status,closed_at,closed_by,manager_requested_at',
            ])
            ->whereIn('admin_id', $staffIds)
            ->when($filters['from'], fn ($x) => $x->where('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($x) => $x->where('created_at', '<=', $filters['to']));

        if (!empty($filters['stars'])) {
            $q->where('stars', $filters['stars']);
        }

        $q->orderByDesc('created_at');

        return $q->paginate($filters['ratings_per'], ['*'], 'ratings_page')
            ->through(function ($row) {
                $rated = $row->ratedAdmin;
                $customer = $row->customer;

                return (object) [
                    'id' => $row->id,
                    'stars' => (int) $row->stars,
                    'feedback' => $row->feedback,
                    'created_at' => $row->created_at,
                    'conversation_id' => $row->support_conversation_id,
                    'conversation_status' => $row->conversation->status ?? null,
                    'conversation_url' => $this->buildSupportConversationUrl($row->conversation),
                    'customer_name' => $customer?->username
                        ?: trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''))
                        ?: ($customer?->email ?? '—'),
                    'staff_id' => $rated?->id,
                    'staff_name' => $rated ? $this->resolveUserDisplayName($rated) : '—',
                    'staff_role' => $this->displayRole($rated?->role),
                    'details_url' => $rated
                        ? route('admin.staff_analytics.index', array_merge(request()->query(), [
                            'staff_id' => $rated->id,
                            'tab' => 'staff',
                        ]))
                        : null,
                ];
            })
            ->appends(request()->query());
    }

    protected function buildTopRatedStaff(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->having('rating_count', '>', 0)
            ->orderByDesc('avg_rating')
            ->orderByDesc('rating_count')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildLowestRatedStaff(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->having('rating_count', '>', 0)
            ->orderByRaw('CASE WHEN avg_rating IS NULL THEN 1 ELSE 0 END')
            ->orderBy('avg_rating')
            ->orderByDesc('rating_count')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildHandlingLeaders(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->orderByDesc('avg_handling_minutes')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildRequeueLeaders(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->orderByDesc('requeued_chats')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildDisputeDecisionLeaders(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->orderByDesc('dispute_decisions')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildDisputeResolutionLeaders(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->orderByDesc('resolved_disputes')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildOpenedDisputeLeaders(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->orderByDesc('opened_disputes')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildSentToReviewLeaders(array $staffIds, array $filters, int $limit): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        $base = Users::query()
            ->from('users')
            ->whereIn('users.id', $staffIds);

        $rows = $this->buildStaffMetricsQuery($base, $filters)
            ->orderByDesc('sent_to_review')
            ->limit($limit)
            ->get();

        $teamMap = $this->buildTeamMap($rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->transformStaffRow($row, $teamMap, $filters));
    }

    protected function buildSelectedStaffBlock(array $filters): ?object
    {
        if (empty($filters['staff_id'])) {
            return null;
        }

       $staff = Users::query()
    ->whereRaw('LOWER(role) = ?', ['admin'])
    ->where('id', $filters['staff_id'])
    ->first();

        if (!$staff) {
            return null;
        }

        $teamMap = $this->buildTeamMap([$staff->id]);
        $team = $teamMap[$staff->id] ?? ['team_name' => '—', 'manager_name' => '—'];

        $from = $filters['from'];
        $to = $filters['to'];

        $metrics = (object) [
            'completed_chats' => SupportConversation::query()
                ->where('closed_by', $staff->id)
                ->where('status', 'resolved')
                ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('closed_at', '<=', $to))
                ->count(),

            'closed_chats' => SupportConversation::query()
                ->where('closed_by', $staff->id)
                ->where('status', 'closed')
                ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('closed_at', '<=', $to))
                ->count(),

            'avg_handling_minutes' => SupportConversation::query()
                ->where('closed_by', $staff->id)
                ->whereIn('status', ['resolved', 'closed'])
                ->whereNotNull('sla_started_at')
                ->whereNotNull('closed_at')
                ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('closed_at', '<=', $to))
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, sla_started_at, closed_at)) as avg_minutes')
                ->value('avg_minutes'),

            'requeued_chats' => DB::table('support_messages')
                ->where('type', 'system')
                ->where('sender_type', 'system')
                ->where('sender_id', $staff->id)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.event')) = 'conversation_requeued'")
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->count(),

            'escalations_requested' => SupportConversation::query()
                ->where('manager_requested_by', $staff->id)
                ->whereNotNull('manager_requested_at')
                ->when($from, fn ($q) => $q->where('manager_requested_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('manager_requested_at', '<=', $to))
                ->count(),

            'escalations_handled' => SupportConversation::query()
                ->where('manager_id', $staff->id)
                ->whereNotNull('manager_requested_at')
                ->when($from, fn ($q) => $q->where('manager_joined_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('manager_joined_at', '<=', $to))
                ->count(),
        ];

        $ratingsAgg = SupportConversationRating::query()
            ->where('admin_id', $staff->id)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->selectRaw('COUNT(*) as rating_count, AVG(stars) as avg_rating')
            ->first();

        $ratingDistribution = SupportConversationRating::query()
            ->where('admin_id', $staff->id)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->selectRaw('stars, COUNT(*) as total')
            ->groupBy('stars')
            ->orderByDesc('stars')
            ->get()
            ->keyBy('stars');

        $recentRatings = SupportConversationRating::query()
            ->with([
                'customer:id,username,first_name,last_name,email',
                'conversation:id,thread_id,status,closed_at,closed_by,manager_requested_at',
            ])
            ->where('admin_id', $staff->id)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $customer = $row->customer;

                return (object) [
                    'stars' => (int) $row->stars,
                    'feedback' => $row->feedback,
                    'created_at' => $row->created_at,
                    'conversation_id' => $row->support_conversation_id,
                    'conversation_status' => $row->conversation->status ?? null,
                    'conversation_url' => $this->buildSupportConversationUrl($row->conversation),
                    'customer_name' => $customer?->username
                        ?: trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''))
                        ?: ($customer?->email ?? '—'),
                ];
            });

        $recentConversations = SupportConversation::query()
            ->with('user:id,username,first_name,last_name,email')
            ->where(function ($q) use ($staff) {
                $q->where('closed_by', $staff->id)
                    ->orWhere('assigned_staff_id', $staff->id)
                    ->orWhere('manager_requested_by', $staff->id)
                    ->orWhere('manager_id', $staff->id);
            })
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(function ($conv) {
                $customer = $conv->user;

                return (object) [
                    'id' => $conv->id,
                    'thread_id' => $conv->thread_id,
                    'status' => $conv->status,
                    'customer_name' => $customer?->username
                        ?: trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''))
                        ?: ($customer?->email ?? '—'),
                    'scope_role' => $conv->scope_role,
                    'created_at' => $conv->created_at,
                    'closed_at' => $conv->closed_at,
                    'manager_requested_at' => $conv->manager_requested_at,
                    'details_url' => $this->buildSupportConversationUrl($conv),
                ];
            });

        $disputeAgg = Dispute::query()
            ->where('decided_by_staff_id', $staff->id)
            ->when($from, fn ($q) => $q->where('decided_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('decided_at', '<=', $to))
            ->selectRaw("
                COUNT(*) as dispute_decisions,
                SUM(CASE WHEN decision_action = 'pay_coach' THEN 1 ELSE 0 END) as dispute_pay_coach,
                SUM(CASE WHEN decision_action = 'refund_full' THEN 1 ELSE 0 END) as dispute_refund_full,
                SUM(CASE WHEN decision_action = 'refund_service' THEN 1 ELSE 0 END) as dispute_refund_service,
                SUM(CASE WHEN decision_action = 'reject' THEN 1 ELSE 0 END) as dispute_rejected
            ")
            ->first();

        $resolvedDisputes = Dispute::query()
            ->where('resolved_by_staff_id', $staff->id)
            ->whereNotNull('resolved_at')
            ->when($from, fn ($q) => $q->where('resolved_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to))
            ->count();

        $openedDisputes = Dispute::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereNull('resolved_at')
            ->whereIn('status', ['opened'])
            ->count();

        $sentToReview = DB::table('dispute_summaries')
            ->where('staff_id', $staff->id)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        $recentDisputes = Dispute::query()
            ->with(['reservation.service', 'reservation.client', 'reservation.service.coach'])
            ->where(function ($q) use ($staff) {
                $q->where('decided_by_staff_id', $staff->id)
                    ->orWhere('resolved_by_staff_id', $staff->id)
                    ->orWhere('assigned_staff_id', $staff->id);
            })
            ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('updated_at', '<=', $to))
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(function ($d) {
                return (object) [
                    'id' => $d->id,
                    'status' => $d->status,
                    'title_label' => $d->title_label,
                    'reservation_id' => $d->reservation_id,
                    'decision_action' => $d->decision_action,
                    'decided_at' => $d->decided_at,
                    'resolved_at' => $d->resolved_at,
                    'updated_at' => $d->updated_at,
                    'sla_total_seconds' => (int) ($d->sla_total_seconds ?? 0),
                    'client_name' => $d->reservation?->client
                        ? $this->resolveUserDisplayName($d->reservation->client)
                        : '—',
                    'coach_name' => $d->reservation?->service?->coach
                        ? $this->resolveUserDisplayName($d->reservation->service->coach)
                        : '—',
                    'details_url' => $this->buildDisputeUrl($d),
                ];
            });

        return (object) [
            'id' => $staff->id,
            'name' => $this->resolveUserDisplayName($staff),
            'email' => $staff->email,
            'role' => $this->displayRole($staff->role),
            'team_name' => $team['team_name'],
            'team_manager' => $team['manager_name'],
            'service_start_at' => $staff->started_at ?: $staff->created_at,
            'service_days' => ($staff->started_at ?: $staff->created_at)
                ? Carbon::parse($staff->started_at ?: $staff->created_at)->diffInDays(now())
                : 0,
            'metrics' => (object) [
                'completed_chats' => (int) $metrics->completed_chats,
                'closed_chats' => (int) $metrics->closed_chats,
                'avg_handling_minutes' => $metrics->avg_handling_minutes !== null ? round((float) $metrics->avg_handling_minutes, 1) : null,
                'requeued_chats' => (int) $metrics->requeued_chats,
                'escalations_requested' => (int) $metrics->escalations_requested,
                'escalations_handled' => (int) $metrics->escalations_handled,
                'rating_count' => (int) ($ratingsAgg->rating_count ?? 0),
                'avg_rating' => $ratingsAgg && $ratingsAgg->avg_rating !== null ? round((float) $ratingsAgg->avg_rating, 2) : null,

                'dispute_decisions' => (int) ($disputeAgg->dispute_decisions ?? 0),
                'resolved_disputes' => (int) $resolvedDisputes,
                'dispute_pay_coach' => (int) ($disputeAgg->dispute_pay_coach ?? 0),
                'dispute_refund_full' => (int) ($disputeAgg->dispute_refund_full ?? 0),
                'dispute_refund_service' => (int) ($disputeAgg->dispute_refund_service ?? 0),
                'dispute_rejected' => (int) ($disputeAgg->dispute_rejected ?? 0),
                'opened_disputes' => (int) $openedDisputes,
                'sent_to_review' => (int) $sentToReview,
            ],
            'rating_distribution' => $ratingDistribution,
            'rating_distribution_chart' => $this->buildRatingsDistributionChart($ratingDistribution),
            'recent_ratings' => $recentRatings,
            'recent_conversations' => $recentConversations,
            'recent_disputes' => $recentDisputes,
        ];
    }

    protected function buildRoleDistributionChart(array $staffIds): array
    {
        if (empty($staffIds)) {
            return [
                'labels' => ['Agents'],
                'values' => [0],
            ];
        }

        $agents = Users::query()
            ->whereIn('id', $staffIds)
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->count();

        return [
            'labels' => ['Agents'],
            'values' => [$agents],
        ];
    }

    protected function buildRatingsDistributionChart(Collection $distribution): array
    {
        $raw = [
            5 => (int) ($distribution[5]->total ?? 0),
            4 => (int) ($distribution[4]->total ?? 0),
            3 => (int) ($distribution[3]->total ?? 0),
            2 => (int) ($distribution[2]->total ?? 0),
            1 => (int) ($distribution[1]->total ?? 0),
        ];

        $total = array_sum($raw);

        return [
            'labels' => ['5 Star', '4 Star', '3 Star', '2 Star', '1 Star'],
            'values' => array_values($raw),
            'percentages' => [
                $this->percent($raw[5], $total),
                $this->percent($raw[4], $total),
                $this->percent($raw[3], $total),
                $this->percent($raw[2], $total),
                $this->percent($raw[1], $total),
            ],
            'total' => $total,
            'click_urls' => [
                $this->buildStarFilterUrl(5),
                $this->buildStarFilterUrl(4),
                $this->buildStarFilterUrl(3),
                $this->buildStarFilterUrl(2),
                $this->buildStarFilterUrl(1),
            ],
        ];
    }

    protected function buildStaffBarChart(Collection $rows, string $metric, int $limit = 7): array
    {
        $rows = $rows->take($limit)->values();

        return [
            'labels' => $rows->map(fn ($r) => $r->display_name ?? $this->resolveUserDisplayName($r))->values()->all(),
            'values' => $rows->map(fn ($r) => round((float) ($r->{$metric} ?? 0), 2))->values()->all(),
        ];
    }

    protected function buildTeamMap(array $staffIds): array
    {
        if (empty($staffIds)) {
            return [];
        }

        $map = [];

        $agentTeams = StaffTeamMember::query()
            ->whereIn('agent_id', $staffIds)
            ->whereNull('end_at')
            ->whereHas('team', function ($q) {
                $q->where('manager_id', auth()->id())
                    ->where('is_active', true)
                    ->whereNull('deleted_at');
            })
            ->with(['team.manager'])
            ->get();

        foreach ($agentTeams as $m) {
            if (!$m->team) {
                continue;
            }

            $map[$m->agent_id] = [
                'team_name' => $m->team->name ?? '—',
                'manager_name' => $m->team->manager
                    ? $this->resolveUserDisplayName($m->team->manager)
                    : '—',
            ];
        }

        return $map;
    }

    protected function conversationMetricSubquery(string $staffColumn, string $status, ?Carbon $from, ?Carbon $to)
    {
        return SupportConversation::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn($staffColumn, 'users.id')
            ->where('status', $status)
            ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('closed_at', '<=', $to));
    }

    protected function requeueMetricSubquery(?Carbon $from, ?Carbon $to)
    {
        return DB::table('support_messages')
            ->selectRaw('COUNT(*)')
            ->whereColumn('support_messages.sender_id', 'users.id')
            ->where('support_messages.type', 'system')
            ->where('support_messages.sender_type', 'system')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(support_messages.meta, '$.event')) = 'conversation_requeued'")
            ->when($from, fn ($q) => $q->where('support_messages.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('support_messages.created_at', '<=', $to));
    }

    protected function escalationRequestedSubquery(?Carbon $from, ?Carbon $to)
    {
        return SupportConversation::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('manager_requested_by', 'users.id')
            ->whereNotNull('manager_requested_at')
            ->when($from, fn ($q) => $q->where('manager_requested_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('manager_requested_at', '<=', $to));
    }

    protected function escalationHandledSubquery(?Carbon $from, ?Carbon $to)
    {
        return SupportConversation::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('manager_id', 'users.id')
            ->whereNotNull('manager_requested_at')
            ->when($from, fn ($q) => $q->where('manager_joined_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('manager_joined_at', '<=', $to));
    }

    protected function averageHandlingSubquery(?Carbon $from, ?Carbon $to)
    {
        return SupportConversation::query()
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, sla_started_at, closed_at))')
            ->whereColumn('closed_by', 'users.id')
            ->whereIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_started_at')
            ->whereNotNull('closed_at')
            ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('closed_at', '<=', $to));
    }

    protected function ratingCountSubquery(?Carbon $from, ?Carbon $to)
    {
        return SupportConversationRating::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('admin_id', 'users.id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }

    protected function ratingAverageSubquery(?Carbon $from, ?Carbon $to)
    {
        return SupportConversationRating::query()
            ->selectRaw('AVG(stars)')
            ->whereColumn('admin_id', 'users.id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }

    protected function disputeDecisionCountSubquery(?Carbon $from, ?Carbon $to)
    {
        return Dispute::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('decided_by_staff_id', 'users.id')
            ->whereNotNull('decided_at')
            ->when($from, fn ($q) => $q->where('decided_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('decided_at', '<=', $to));
    }

    protected function disputeResolutionCountSubquery(?Carbon $from, ?Carbon $to)
    {
        return Dispute::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('resolved_by_staff_id', 'users.id')
            ->whereNotNull('resolved_at')
            ->when($from, fn ($q) => $q->where('resolved_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to));
    }

    protected function disputeDecisionActionCountSubquery(string $action, ?Carbon $from, ?Carbon $to)
    {
        return Dispute::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('decided_by_staff_id', 'users.id')
            ->where('decision_action', $action)
            ->whereNotNull('decided_at')
            ->when($from, fn ($q) => $q->where('decided_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('decided_at', '<=', $to));
    }

    protected function disputeCurrentAssignedSubquery()
    {
        return Dispute::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('assigned_staff_id', 'users.id')
            ->whereNull('resolved_at')
            ->whereIn('status', ['opened']);
    }

    protected function disputeSentToReviewSubquery(?Carbon $from, ?Carbon $to)
    {
        return DB::table('dispute_summaries')
            ->selectRaw('COUNT(*)')
            ->whereColumn('dispute_summaries.staff_id', 'users.id')
            ->when($from, fn ($q) => $q->where('dispute_summaries.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('dispute_summaries.created_at', '<=', $to));
    }

    protected function applySort(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'rating_desc':
                $query->orderByDesc('avg_rating')
                    ->orderByDesc('rating_count');
                break;

            case 'rating_asc':
                $query->orderByRaw('CASE WHEN avg_rating IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('avg_rating')
                    ->orderByDesc('rating_count');
                break;

            case 'aht_desc':
                $query->orderByDesc('avg_handling_minutes');
                break;

            case 'aht_asc':
                $query->orderByRaw('CASE WHEN avg_handling_minutes IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('avg_handling_minutes');
                break;

            case 'requeued_desc':
                $query->orderByDesc('requeued_chats');
                break;

            case 'escalated_desc':
                $query->orderByDesc('escalations_requested');
                break;

            case 'dispute_decisions_desc':
                $query->orderByDesc('dispute_decisions');
                break;

            case 'dispute_resolved_desc':
                $query->orderByDesc('resolved_disputes');
                break;

            case 'open_disputes_desc':
                $query->orderByDesc('opened_disputes');
                break;

            case 'sent_to_review_desc':
                $query->orderByDesc('sent_to_review');
                break;

            case 'name_asc':
                $query->orderBy('users.username')
                    ->orderBy('users.first_name')
                    ->orderBy('users.last_name');
                break;

            case 'completed_desc':
            default:
                $query->orderByDesc('completed_chats')
                    ->orderByDesc('avg_rating')
                    ->orderBy('users.username');
                break;
        }
    }

    protected function displayRole(?string $role): string
    {
        return match (strtolower((string) $role)) {
            'admin' => 'Agent',
            'manager' => 'Manager',
            default => ucfirst((string) $role),
        };
    }

    protected function tabOptions(): array
    {
        return [
            'overview' => 'Overview',
            'performance' => 'Performance',
            'ratings' => 'Ratings',
            'staff' => 'Staff Details',
            'disputes' => 'Disputes',
        ];
    }

    protected function periodOptions(): array
    {
        return [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'all' => 'All Time',
            'custom' => 'Custom',
        ];
    }

    protected function sortOptions(): array
    {
        return [
            'completed_desc' => 'Most Completed Chats',
            'rating_desc' => 'Highest Rating',
            'rating_asc' => 'Lowest Rating',
            'aht_desc' => 'Longest Handling Time',
            'aht_asc' => 'Shortest Handling Time',
            'requeued_desc' => 'Most Requeued',
            'escalated_desc' => 'Most Escalations Requested',
            'dispute_decisions_desc' => 'Most Dispute Decisions',
            'dispute_resolved_desc' => 'Most Resolved Disputes',
            'open_disputes_desc' => 'Most Active Assigned Disputes',
            'sent_to_review_desc' => 'Most Sent To Review',
            'name_asc' => 'Name A-Z',
        ];
    }

    protected function resolveUserDisplayName($user): string
    {
        return $user->username
            ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($user->email ?? '—');
    }

    protected function percent(int|float $value, int|float $total): float
    {
        if ((float) $total <= 0) {
            return 0.0;
        }

        return round((((float) $value) / ((float) $total)) * 100, 1);
    }

    protected function buildStarFilterUrl(int $stars): string
    {
        return route('admin.staff_analytics.index', array_merge(request()->query(), [
            'tab' => 'ratings',
            'stars' => $stars,
        ]));
    }

    protected function buildSupportConversationUrl($conversation): ?string
    {
        if (!$conversation) {
            return null;
        }

        if (Route::has('admin.support.conversations.show')) {
            return route('admin.support.conversations.show', $conversation->id);
        }

        if (Route::has('superadmin.support.conversations.show')) {
            return route('superadmin.support.conversations.show', $conversation->id);
        }

        if (Route::has('admin.support.show')) {
            return route('admin.support.show', $conversation->id);
        }

        if (Route::has('superadmin.support.show')) {
            return route('superadmin.support.show', $conversation->id);
        }

        return null;
    }

    protected function buildDisputeUrl($dispute): ?string
    {
        if (!$dispute) {
            return null;
        }

        if (Route::has('admin.disputes.show')) {
            return route('admin.disputes.show', $dispute->id);
        }

        if (Route::has('superadmin.disputes.show')) {
            return route('superadmin.disputes.show', $dispute->id);
        }

        return null;
    }
}