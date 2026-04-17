<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportConversationRating;
use App\Models\Users;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SupportReviewAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (strtolower((string)($user->role ?? '')) !== 'manager') {
            abort(403);
        }

        $tz  = config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($tz);

        $range = strtolower((string) $request->query('range', 'lifetime'));
        $from  = $request->query('from');
        $to    = $request->query('to');

        $year  = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $day   = $request->query('day');

        $selectedAdminId = $request->filled('admin_id') ? (int) $request->query('admin_id') : null;

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
                $dateTo   = $to   ? CarbonImmutable::parse($to, $tz)->endOfDay() : null;
                $periodLabel = trim(
                    ($dateFrom ? $dateFrom->format('Y-m-d') : '…') . ' → ' .
                    ($dateTo ? $dateTo->format('Y-m-d') : '…')
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

        $admins = Users::query()
            ->where('role', 'admin')
            ->orderBy('username')
            ->get([
                'id',
                'username',
                'email',
                'first_name',
                'last_name',
            ]);

        $selectedAdmin = $selectedAdminId
            ? $admins->firstWhere('id', $selectedAdminId)
            : null;

        /*
        |--------------------------------------------------------------------------
        | Base ratings query
        | Only reviews for ADMIN-handled conversations
        |--------------------------------------------------------------------------
        */
        $ratingsBase = SupportConversationRating::query()
            ->from('support_conversation_ratings as r')
            ->join('support_conversations as c', 'c.id', '=', 'r.support_conversation_id')
            ->leftJoin('users as admins', 'admins.id', '=', 'r.admin_id')
            ->whereNotNull('r.admin_id')
            ->where(function ($q) {
                $q->where('c.closed_by_role', 'admin')
                  ->orWhere('c.assigned_staff_role', 'admin');
            })
            ->when($selectedAdminId, fn ($q) => $q->where('r.admin_id', $selectedAdminId))
            ->when($dateFrom, fn ($q) => $q->where('r.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('r.created_at', '<=', $dateTo));

        $summary = (clone $ratingsBase)
            ->selectRaw('COUNT(*) as total_reviews')
            ->selectRaw('ROUND(AVG(r.stars), 2) as avg_rating')
            ->selectRaw('SUM(CASE WHEN r.stars = 5 THEN 1 ELSE 0 END) as five_star_count')
            ->selectRaw('SUM(CASE WHEN r.stars = 4 THEN 1 ELSE 0 END) as four_star_count')
            ->selectRaw('SUM(CASE WHEN r.stars = 3 THEN 1 ELSE 0 END) as three_star_count')
            ->selectRaw('SUM(CASE WHEN r.stars = 2 THEN 1 ELSE 0 END) as two_star_count')
            ->selectRaw('SUM(CASE WHEN r.stars = 1 THEN 1 ELSE 0 END) as one_star_count')
            ->first();

        $totalReviews = (int)($summary->total_reviews ?? 0);
        $avgRating    = (float)($summary->avg_rating ?? 0);

        $distribution = [
            5 => (int)($summary->five_star_count ?? 0),
            4 => (int)($summary->four_star_count ?? 0),
            3 => (int)($summary->three_star_count ?? 0),
            2 => (int)($summary->two_star_count ?? 0),
            1 => (int)($summary->one_star_count ?? 0),
        ];

        $resolvedCount = SupportConversation::query()
            ->where('status', 'resolved')
            ->where(function ($q) {
                $q->where('closed_by_role', 'admin')
                  ->orWhere('assigned_staff_role', 'admin');
            })
            ->when($selectedAdminId, function ($q) use ($selectedAdminId) {
                $q->where(function ($sub) use ($selectedAdminId) {
                    $sub->where('closed_by', $selectedAdminId)
                        ->orWhere('assigned_staff_id', $selectedAdminId);
                });
            })
            ->when($dateFrom, fn ($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('updated_at', '<=', $dateTo))
            ->count();

        $leaderboard = SupportConversationRating::query()
            ->from('support_conversation_ratings as r')
            ->join('support_conversations as c', 'c.id', '=', 'r.support_conversation_id')
            ->join('users as admins', 'admins.id', '=', 'r.admin_id')
            ->where(function ($q) {
                $q->where('c.closed_by_role', 'admin')
                  ->orWhere('c.assigned_staff_role', 'admin');
            })
            ->when($selectedAdminId, fn ($q) => $q->where('r.admin_id', $selectedAdminId))
            ->when($dateFrom, fn ($q) => $q->where('r.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('r.created_at', '<=', $dateTo))
            ->groupBy('r.admin_id', 'admins.username', 'admins.first_name', 'admins.last_name', 'admins.email')
            ->selectRaw('
                r.admin_id,
                admins.username,
                admins.first_name,
                admins.last_name,
                admins.email,
                COUNT(*) as total_reviews,
                ROUND(AVG(r.stars), 2) as avg_rating,
                SUM(CASE WHEN r.stars = 5 THEN 1 ELSE 0 END) as five_star_count,
                SUM(CASE WHEN r.stars <= 2 THEN 1 ELSE 0 END) as low_star_count
            ')
            ->orderByDesc('avg_rating')
            ->orderByDesc('total_reviews')
            ->paginate(10)
            ->withQueryString();

       $recentFeedback = (clone $ratingsBase)
    ->leftJoin('users as raters', 'raters.id', '=', 'r.user_id')
    ->whereNotNull('r.feedback')
    ->where('r.feedback', '!=', '')
    ->select([
        'r.id',
        'r.admin_id',
        'r.user_id',
        'r.stars',
        'r.feedback',
        'r.created_at',

        'admins.username as admin_username',
        'admins.first_name as admin_first_name',
        'admins.last_name as admin_last_name',
        'admins.email as admin_email',

        'raters.username as rater_username',
        'raters.first_name as rater_first_name',
        'raters.last_name as rater_last_name',
        'raters.email as rater_email',

        'c.id as conversation_id',
        'c.thread_id',
        'c.scope_role',
    ])
    ->orderByDesc('r.created_at')
    ->limit(12)
    ->get();

        return view('admin.support.reviews.index', [
            'admins'          => $admins,
            'selectedAdminId' => $selectedAdminId,
            'selectedAdmin'   => $selectedAdmin,

            'range'       => $range,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'periodLabel' => $periodLabel,

            'summary' => [
                'total_reviews'  => $totalReviews,
                'avg_rating'     => $avgRating,
                'resolved_count' => $resolvedCount,
                'five_star_rate' => $totalReviews > 0 ? round(($distribution[5] / $totalReviews) * 100, 1) : 0,
            ],

            'distribution'   => $distribution,
            'leaderboard'    => $leaderboard,
            'recentFeedback' => $recentFeedback,
        ]);
    }
}