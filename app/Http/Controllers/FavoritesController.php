<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\Users;

class FavoritesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $favoriteServices = Service::whereHas('favorites', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
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
            ->paginate(9, ['*'], 'services_page');

        $favoriteCoaches = $user->favoriteCoaches()
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.avatar_path',
                'users.city',
                'users.country',
                'users.coach_rating_avg',
                'users.coach_rating_count'
            )
            ->paginate(9, ['*'], 'coaches_page');

        foreach ($favoriteServices as $svc) {
            $svc->append(['price_value', 'price_unit', 'level_label']);
        }

        return view('favorites.index', compact('favoriteServices', 'favoriteCoaches'));
    }
}