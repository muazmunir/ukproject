<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Support\AnalyticsLogger;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        AnalyticsLogger::log($request, 'site_visit', [
            'group' => 'funnel',
            'meta' => [
                'screen' => 'home',
            ],
        ]);

        $services = Service::active()
            ->where('is_approved', 1)
            ->with([
                'coach' => function ($q) {
                    $q->select(
                        'id',
                        'first_name',
                        'last_name',
                        'avatar_path',
                        'city',
                        'country',
                        'coach_rating_avg',
                        'coach_rating_count'
                    );
                },
                'favorites',
                'packages:id,service_id,total_price,hourly_rate',
            ])
            ->latest('services.created_at')
            ->take(12)
            ->get();

        $categories = ServiceCategory::query()
            ->where('is_active', 1)
            ->where('show_in_scrollbar', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('home.index', compact('services', 'categories'));
    }
}