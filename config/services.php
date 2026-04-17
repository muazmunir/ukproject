<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'key'           => env('STRIPE_KEY'),
        'secret'        => env('STRIPE_SECRET'),
        'webhook_secret'=> env('STRIPE_WEBHOOK_SECRET'),
        'connect_country'  => env('STRIPE_CONNECT_COUNTRY', 'US'),
    'connect_business_type' => env('STRIPE_CONNECT_BUSINESS_TYPE', 'individual'),
    ],

   'paypal' => [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'mode' => env('PAYPAL_MODE', 'sandbox'),
    'webhook_id'     => env('PAYPAL_WEBHOOK_ID'),
],
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('GOOGLE_REDIRECT_URI'),
],
'jaas' => [
  'app_id'      => env('JAAS_APP_ID'),
  'kid'         => env('JAAS_KID'),
  'private_key' => env('JAAS_PRIVATE_KEY'),
],







    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
