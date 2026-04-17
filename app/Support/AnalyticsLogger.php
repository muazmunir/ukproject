<?php

namespace App\Support;

use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AnalyticsLogger
{
    public static function log(Request $request, string $type, array $data = []): void
    {
        try {
            $user = $request->user();

            $visitorToken = $request->cookie('visitor_token');

            if (! $visitorToken) {
                $visitorToken = (string) Str::uuid();

                Cookie::queue(cookie(
                    'visitor_token',
                    $visitorToken,
                    60 * 24 * 365,
                    null,
                    null,
                    $request->isSecure(),
                    false,
                    false,
                    'Lax'
                ));
            }

            $payload = [
                'type'           => $type,
                'event_group'    => $data['group'] ?? null,

                'user_id'        => $user?->id,
                'coach_id'       => $data['coach_id'] ?? 0,
                'client_id'      => $data['client_id'] ?? null,
                'service_id'     => $data['service_id'] ?? null,
                'reservation_id' => $data['reservation_id'] ?? null,
                'payment_id'     => $data['payment_id'] ?? null,

                'session_id'     => (string) $request->session()->getId(),
                'visitor_token'  => $visitorToken,

                'page'           => $request->path(),
                'url'            => $request->fullUrl(),
                'method'         => $request->method(),
                'ip'             => $request->ip(),
                'user_agent'     => $request->userAgent(),

                'device_type'    => $data['device_type'] ?? null,
                'platform'       => $data['platform'] ?? null,
                'country'        => $data['country'] ?? null,
                'city'           => $data['city'] ?? null,

                'meta'           => $data['meta'] ?? null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            AnalyticsEvent::create($payload);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}