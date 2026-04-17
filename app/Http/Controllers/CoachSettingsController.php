<?php
// app/Http/Controllers/Coach/CoachSettingsController.php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoachSettingsController extends Controller
{
    public function __construct()
    {
        // Coach settings must be authenticated
        $this->middleware('auth');
    }

    /**
     * Show the coach settings page (timezone section).
     */
    public function edit(Request $request)
    {
        $user = $request->user();

        // We only show the currently saved coach timezone here.
        // The browser/device timezone can be detected on the client and sent via JS if you like.
        return view('coach.settings.edit', [
            'coachTz' => $this->safeTz($user->timezone ?? null),
        ]);
    }

    /**
     * Persist the coach's IANA timezone to the DB (canonical “owner” timezone).
     * Accepts normal form posts and AJAX (JSON) posts.
     */
    public function setTimezone(Request $request)
    {
        // Accept tz from either form-encoded or JSON
        $request->validate([
            'tz' => 'required|string|max:64',
        ]);

        $tz = $this->safeTz($request->input('tz'));

        $request->user()->forceFill(['timezone' => $tz])->save();

        // If request expects JSON (AJAX), return JSON
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['ok' => true, 'timezone' => $tz]);
        }

        // Otherwise redirect back with a flash message
        return back()->with('status', __('Timezone updated to :tz', ['tz' => $tz]));
    }

    /**
     * Validate and normalize an IANA timezone safely.
     * Falls back to app timezone (or UTC) on invalid input.
     */
    private function safeTz(?string $tz, ?string $fallback = null): string
    {
        $fallback = $fallback ?: config('app.timezone', 'UTC');
        $tz = is_string($tz) ? trim($tz) : '';
        try {
            return $tz !== '' ? (new \DateTimeZone($tz))->getName() : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
