<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceFavorite;
use App\Models\Users;   // your users model
use Illuminate\Http\Request;
use DateTimeZone;

class ServicesController extends Controller
{
 public function index(Request $request)
{
    // ===== read filters from request =====
    $q        = trim((string) $request->input('q'));
    $city     = $request->string('city')->toString();
    $levels   = array_filter((array) $request->input('levels', []));  // ['beginner', 'intermediate', ...]
    $coachId  = $request->integer('coach_id');
    $country  = $request->string('country')->toString();
    $minPrice = $request->input('min_price');
    $maxPrice = $request->input('max_price');

    // ----- NEW: category via slug (and fallback to old category_id) -----
    $categorySlug    = $request->string('category')->toString();
    $categoryId      = null;
    $selectedCategory = null;

    if ($categorySlug !== '') {
        // main path: ?category=slug
        $selectedCategory = ServiceCategory::active()
            ->where('slug', $categorySlug)
            ->first();

        if ($selectedCategory) {
            $categoryId = $selectedCategory->id;
        }
    } else {
        // backward-compatible: ?category_id=123
        $categoryId = $request->integer('category_id');
        if ($categoryId) {
            $selectedCategory = ServiceCategory::active()->find($categoryId);
        }
    }

    // ===== base query =====
    $servicesQuery = Service::query()
        ->active()
        ->with([
          'coach' => function ($q) {
    $q->select(
        'id',
        'first_name',
        'last_name',
        'avatar_path',
        'city',
        'country',
        'timezone',
        'coach_rating_avg',
        'coach_rating_count'
    );
},
            'category:id,name',   // add slug if you ever need it in cards
            'packages',
        ])
        ->withMin('packages', 'total_price')
        ->withMin('packages', 'hourly_rate')

        // keyword search
        ->when($q, fn ($qq) =>
            $qq->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            })
        )

        // category filter (now using computed $categoryId)
        ->when($categoryId, fn ($qq) => $qq->where('category_id', $categoryId))

        // multiple service levels
        ->when($levels, fn ($qq) => $qq->whereIn('service_level', $levels))

        // city filter – via coach relation
        ->when($city, function ($qq) use ($city) {
            $qq->whereHas('coach', function ($c) use ($city) {
                $c->where('city', 'like', "%{$city}%");
            });
        })

        // coach
        ->when($coachId, fn ($qq) => $qq->where('coach_id', $coachId))

        // country filter – via coach relation
        ->when($country, function ($qq) use ($country) {
            $qq->whereHas('coach', function ($c) use ($country) {
                $c->where('country', $country);
            });
        })

        // price range
        ->when(is_numeric($minPrice) || is_numeric($maxPrice), function ($qq) use ($minPrice, $maxPrice) {
            $qq->whereHas('packages', function ($p) use ($minPrice, $maxPrice) {
                if (is_numeric($minPrice)) {
                    $p->where('total_price', '>=', $minPrice);
                }
                if (is_numeric($maxPrice)) {
                    $p->where('total_price', '<=', $maxPrice);
                }
            });
        })

        // default ordering (newest first)
        ->orderBy('services.created_at', 'desc');

    $services = $servicesQuery
        ->paginate(24)
        ->withQueryString();

    // ===== data for filter dropdowns =====
    $categories = ServiceCategory::active()
        ->get([
            'id',
            'name',
            'slug',
            'description',
            'cover_image',
            'icon_path',
            'sort_order',
        ]);

    // list of coaches for "Select Coach"
    $coaches = Users::where('role', 'coach')
        ->where('is_approved', true)
        ->orderBy('first_name')
        ->get(['id', 'first_name', 'last_name', 'avatar_path', 'city', 'country']);

    // DISTINCT countries & cities – from users (coaches)
    $countries = Users::where('role', 'coach')
        ->where('is_approved', true)
        ->whereNotNull('country')
        ->distinct()
        ->orderBy('country')
        ->pluck('country');

    $cities = collect();
    if ($country) {
        $cities = Users::where('role', 'coach')
            ->where('is_approved', true)
            ->where('country', $country)
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    return view('services.index', [
        'services'         => $services,
        'categories'       => $categories,
        'coaches'          => $coaches,
        'countries'        => $countries,
        'cities'           => $cities,

        // current filter values
        'q'                => $q,
        'categoryId'       => $categoryId,      // still useful if you need it
        'selectedCategory' => $selectedCategory, // <-- for hero & selects
        'city'             => $city,
        'coachId'          => $coachId,
        'country'          => $country,
        'minPrice'         => $minPrice,
        'maxPrice'         => $maxPrice,
        'levels'           => $levels,
    ]);
}


    public function show(Service $service)
    {
        
        $service->load('coach');

        $rawTz = optional($service->coach)->timezone;
        try {
            $coachTz = $rawTz ? (new DateTimeZone(trim($rawTz)))->getName() : config('app.timezone', 'UTC');
        } catch (\Throwable $e) {
            $coachTz = config('app.timezone', 'UTC');
        }

        $availabilityUrl = route('services.availability', $service->id);

        return view('services.show', [
            'service'         => $service,
            'availabilityUrl' => $availabilityUrl,
            'coachTz'         => $coachTz,
            'defaultTz'       => config('app.timezone','UTC'),
        ]);
    }
}
