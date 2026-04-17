<?php

namespace App\Http\Controllers;

use App\Models\ReservationSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;

class SlotCallController extends Controller
{
    public function join(Request $request, ReservationSlot $slot)
    {
        $user = $request->user();

        // Make sure slot has reservation relation
        $reservation = $slot->reservation;
        abort_unless($reservation, 404);

        // Only coach or client can join
        $isCoach  = (int) $reservation->coach_id === (int) $user->id;
        $isClient = (int) $reservation->client_id === (int) $user->id;
        abort_unless($isCoach || $isClient, 403);

        // Only for ONLINE environment (adjust if your value differs)
        abort_unless(($reservation->environment ?? null) === 'online', 403);

        // Unlock only after BOTH checked in
        abort_unless($slot->coach_checked_in_at && $slot->client_checked_in_at, 403);

        // Ensure room exists
        if (! $slot->call_room) {
            $slot->call_provider = 'jaas';
            $slot->call_room = 'zv-slot-' . $slot->id . '-' . strtoupper(Str::random(8));
            $slot->save();
        }

        // Read jaas config
        $appId = config('services.jaas.app_id');
        $kid   = config('services.jaas.kid');
        $pk    = config('services.jaas.private_key');

        abort_unless($appId && $kid && $pk, 500);

        // Convert "\n" in env back to real new lines
        $pk = str_replace("\\n", "\n", $pk);

        // Token lifetime: allow 6 hours (your calls can be 3–4 hours)
        $now = time();
        $payload = [
            'aud' => 'jitsi',
            'iss' => 'chat',
            'sub' => $appId,
            'room' => '*',
            'nbf' => $now - 10,
            'exp' => $now + (60 * 60 * 6),

            'context' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->full_name ?? $user->name ?? 'User',
                    'email' => $user->email ?? null,
                    'moderator' => $isCoach ? "true" : "false",
                ],
                'features' => [
                    'recording' => false,
                    'livestreaming' => false,
                    'transcription' => false,
                    'outbound-call' => false,
                ],
            ],
        ];

        // kid goes in JWT header (4th arg)
        $jwt = JWT::encode($payload, $pk, 'RS256', $kid);

        // JaaS requires roomName = "<APP_ID>/<ROOM>"
        $roomName = $appId . '/' . $slot->call_room;

        return view('slots.call', [
            'appId'    => $appId,
            'roomName' => $roomName,
            'jwt'      => $jwt,
        ]);
    }
}
