<?php

namespace App\Http\Controllers;

use App\Models\Users;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\AnalyticsLogger;

class PublicCoachController extends Controller
{
    /**
     * Show public profile + services for a coach
     */
    public function show(Users $coach, Request $request)
    {
        if (! $coach->is_coach || ! $coach->is_approved) {
            abort(404);
        }

        AnalyticsLogger::log($request, 'profile_view', [
            'group'    => 'discovery',
            'coach_id' => $coach->id,
            'meta'     => [
                'coach_user_id' => $coach->id,
                'page_type'     => 'public_coach_profile',
            ],
        ]);

        $coach->loadAvg('reviewsReceivedAsCoach as coach_rating_avg', 'stars');
        $coach->loadCount('reviewsReceivedAsCoach as coach_reviews_count');

        $services = Service::query()
            ->active()
            ->where('coach_id', $coach->id)
            ->where('is_approved', 1)
            ->with([
                'category:id,name',
                'packages',
                'favorites',
                'coach' => function ($q) {
                    $q->select('id', 'first_name', 'last_name', 'avatar_path', 'city', 'country')
                        ->withAvg('reviewsReceivedAsCoach as coach_rating_avg', 'stars')
                        ->withCount('reviewsReceivedAsCoach as coach_reviews_count');
                },
            ])
            ->withMin('packages', 'total_price')
            ->withMin('packages', 'hourly_rate')
            ->orderByDesc('created_at')
            ->paginate(9);

        $showSocialProfiles = (bool) DB::table('site_settings')
            ->where('key', 'trainer_show_social_profiles')
            ->value('value');

        return view('coaches.show', [
            'coach'              => $coach,
            'services'           => $services,
            'showSocialProfiles' => $showSocialProfiles,
        ]);
    }
}