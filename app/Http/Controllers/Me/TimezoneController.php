<?php
// app/Http/Controllers/Me/TimezoneController.php
namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TimezoneController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required','string', function($attr,$val,$fail){
                if (!in_array($val, \DateTimeZone::listIdentifiers(), true)) {
                    $fail('Invalid timezone');
                }
            }],
        ]);

        $user = $request->user(); // coach OR client

        // Avoid noisy writes
        $cacheKey = "tz-last-{$user->id}";
        $last = Cache::get($cacheKey);

        if ($last === $data['timezone'] && $user->timezone === $data['timezone']) {
            return response()->json(['updated' => false, 'reason' => 'unchanged']);
        }

        $old = $user->timezone;
        $user->forceFill(['timezone' => $data['timezone']])->save();
        Cache::put($cacheKey, $data['timezone'], now()->addHours(6));

        // Optional: kick any rebuild job if you cache slot renders per user
        // dispatch(new RebuildAvailabilityForUser($user->id));

        return response()->json(['updated' => true, 'old' => $old, 'new' => $data['timezone']]);
    }


    public function updateAdmin(Request $request)
{
    $data = $request->validate([
        'timezone' => ['required','string', function($attr,$val,$fail){
            if (!in_array($val, \DateTimeZone::listIdentifiers(), true)) $fail('Invalid timezone');
        }],
    ]);

    // ✅ however you currently identify the admin user
    // Example: session('admin_id') or auth()->user() if you did login properly
    $admin = auth()->user(); // if this is null, replace with your admin session resolver

    abort_unless($admin, 401);

    $admin->forceFill(['timezone' => $data['timezone']])->save();

    return response()->json(['updated' => true, 'new' => $data['timezone']]);
}


    public function storeCookie(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required','string', function($attr,$val,$fail){
                if (!in_array($val, \DateTimeZone::listIdentifiers(), true)) {
                    $fail('Invalid timezone');
                }
            }],
        ]);

        return response()->json(['stored' => true, 'tz' => $data['timezone']])
            ->cookie('client_tz', $data['timezone'], 60 * 24 * 180, '/', null, false, false);
    }
}
