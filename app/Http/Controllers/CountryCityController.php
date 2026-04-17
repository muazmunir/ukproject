<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use App\Models\City; // keep if you’ll use for cities()
use App\Models\ServiceCategory;

class CountryCityController extends Controller
{
    /**
     * GET /cc/countries
     * JS expects:
     *  { success: true, data: [ { name, code }, ... ] }
     * where "code" is ISO2 (used later for phone + mapping).
     */
    public function countries(Request $req): JsonResponse
    {
        try {
            // columns that actually exist in your screenshot: name, iso2, phonecode
            $rows = Country::query()
                ->orderBy('name')
                ->get(['id', 'name', 'iso2', 'phonecode']);

            $data = $rows->map(function ($c) {
                $iso2     = strtoupper($c->iso2 ?? '');
                $phoneRaw = $c->phonecode ?? null;

                return [
                    'name'       => $c->name,
                    // JS uses "code" as ISO2 for mapping
                    'code'       => $iso2 ?: (string) $c->id,
                    'iso2'       => $iso2,
                    // not strictly needed by JS here, but handy:
                    'phone_code' => $phoneRaw
                        ? ('+' . ltrim($phoneRaw, '+'))
                        : null,
                ];
            })->values()->all();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('Countries DB error', ['e' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Server error: countries',
            ], 500);
        }
    }

    /**
     * GET /cc/codes
     * JS expects:
     *  { success: true, data: [ { name, iso2, code }, ... ] }
     * where "code" is the dial code, e.g. +92.
     */
    public function codes(Request $req): JsonResponse
    {
        try {
            $rows = Country::query()
                ->orderBy('name')
                ->get(['name', 'iso2', 'phonecode']);

            $data = $rows->map(function ($c) {
                $iso2     = strtoupper($c->iso2 ?? '');
                $phoneRaw = $c->phonecode ?? null;

                if (!$iso2 || !$phoneRaw) {
                    return null; // skip incomplete rows
                }

                $dial = '+' . ltrim($phoneRaw, '+');

                return [
                    'name' => $c->name,
                    'iso2' => $iso2,
                    'code' => $dial,
                ];
            })
            ->filter()  // remove nulls
            ->values()
            ->all();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('Codes DB error', ['e' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Server error: codes',
            ], 500);
        }
    }

    // keep your cities() here — we’ll adjust it once you show cities table structure



    /**
     * GET /cc/cities?country=Pakistan
     *
     * JS sends the *country name* (opt.text) as "country".
     * We find that country in DB and return its city list.
     *
     * JS expects:
     *  success: true,
     *  data: ["Karachi","Lahore",...]
     */
    public function cities(Request $req): JsonResponse
    {
        $countryParam = trim((string) $req->query('country', ''));
    
        if ($countryParam === '') {
            return response()->json([
                'success' => false,
                'message' => 'country is required',
            ], 422);
        }
    
        try {
            // JS sends the *country name* (opt.text) here, e.g. "Pakistan"
            $country = Country::query()
                ->where('name', $countryParam)
                ->orWhere('iso2', strtoupper($countryParam))
                ->first();
    
            if (!$country) {
                // no such country in DB → just return empty list
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ]);
            }
    
            $iso2 = strtoupper($country->iso2 ?? '');
    
            // Prefer matching by ISO2 against cities.country_code
            $query = City::query();
    
            if ($iso2 !== '') {
                $query->where('country_code', $iso2);
            } else {
                // fallback: use numeric id link if your IDs line up
                $query->where('country_id', $country->id);
            }
    
            $cities = $query
                ->orderBy('name')
                ->pluck('name')
                ->filter(fn ($c) => is_string($c) && trim($c) !== '')
                ->unique()
                ->values()
                ->all();
    
            return response()->json([
                'success' => true,
                'data'    => $cities,
            ]);
        } catch (\Throwable $e) {
            Log::error('Cities DB error', [
                'e'       => $e->getMessage(),
                'country' => $countryParam,
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Server error: cities',
            ], 500);
        }
    }

    /**
 * GET /cc/locations/search?q=rawa
 * Returns:
 *  { success:true, data:[ {city:"Rawalpindi", country:"Pakistan"}, {city:"", country:"Austria"} ] }
 */
public function locationSearch(Request $req): JsonResponse
{
    $q = trim((string) $req->query('q', ''));

    if (mb_strlen($q) < 2) {
        return response()->json(['success' => true, 'data' => []]);
    }

    try {
        $qLike = "%{$q}%";

        // 1) Cities (best match)
        // Assumes cities table has: name, country_code (ISO2) OR country_id
        $cityRows = City::query()
            ->where('name', 'like', $qLike)
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'country_code', 'country_id']);

        // Build ISO2->CountryName map once for fast lookup
        $iso2List = $cityRows->pluck('country_code')->filter()->map(fn($x)=>strtoupper($x))->unique()->values();
        $countryByIso = Country::query()
            ->whereIn('iso2', $iso2List)
            ->get(['name','iso2'])
            ->mapWithKeys(fn($c) => [strtoupper($c->iso2) => $c->name]);

        // If your cities use country_id instead of country_code, build ID->name map too
        $idList = $cityRows->pluck('country_id')->filter()->unique()->values();
        $countryById = Country::query()
            ->whereIn('id', $idList)
            ->get(['id','name'])
            ->mapWithKeys(fn($c) => [(int)$c->id => $c->name]);

        $cityResults = $cityRows->map(function($c) use ($countryByIso, $countryById) {
            $iso2 = strtoupper((string)($c->country_code ?? ''));
            $countryName = $iso2 && isset($countryByIso[$iso2])
                ? $countryByIso[$iso2]
                : ($c->country_id && isset($countryById[(int)$c->country_id]) ? $countryById[(int)$c->country_id] : null);

            return $countryName ? [
                'city' => (string)$c->name,
                'country' => (string)$countryName
            ] : null;
        })->filter()->values();

        // 2) Countries (fallback)
        $countryResults = Country::query()
            ->where('name', 'like', $qLike)
            ->orderBy('name')
            ->limit(5)
            ->get(['name'])
            ->map(fn($c) => ['city' => '', 'country' => (string)$c->name])
            ->values();

        // Merge + unique
        $data = $cityResults
            ->concat($countryResults)
            ->unique(fn($x) => strtolower(($x['city'] ?? '').'|'.($x['country'] ?? '')))
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $data]);
    } catch (\Throwable $e) {
        Log::error('LocationSearch DB error', ['e' => $e->getMessage(), 'q' => $q]);
        return response()->json(['success' => false, 'message' => 'Server error: locationSearch'], 500);
    }
}


public function categoriesSearch(Request $req): JsonResponse
{
    $q = trim((string) $req->query('q', ''));

    if (mb_strlen($q) < 2) {
        return response()->json(['success' => true, 'data' => []]);
    }

    $rows = ServiceCategory::query()
        ->select(['id','name','slug','icon_path'])
        ->where('name', 'like', "%{$q}%")
        ->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END, name ASC", ["{$q}%"])
        ->limit(8)
        ->get();

    return response()->json([
        'success' => true,
        'data' => $rows->map(fn($r) => [
            'id'   => $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
            'icon' => $r->icon_path ? asset('storage/'.$r->icon_path) : null,
        ]),
    ]);
}
    
}
