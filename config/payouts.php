<?php

return [
    'protection_window_hours' => env('PAYOUT_PROTECTION_WINDOW_HOURS', 48),
    'daily_payout_hour_utc' => env('PAYOUT_DAILY_HOUR_UTC', 2),

    'providers' => [
        'stripe' => [
            'enabled' => env('STRIPE_PAYOUTS_ENABLED', true),
        ],

        'payoneer' => [
            'enabled' => env('PAYONEER_ENABLED', false),
            'env' => env('PAYONEER_ENV', 'sandbox'),
            'base_url' => env('PAYONEER_BASE_URL'),
            'client_id' => env('PAYONEER_CLIENT_ID'),
            'client_secret' => env('PAYONEER_CLIENT_SECRET'),
            'partner_id' => env('PAYONEER_PARTNER_ID'),
            'program_id' => env('PAYONEER_PROGRAM_ID'),
            'webhook_secret' => env('PAYONEER_WEBHOOK_SECRET'),
        ],
    ],
];