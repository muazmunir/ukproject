<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayPalWebhookController;


Route::get('/health', fn () => ['ok' => true]);
// add your API endpoints here…


Route::post('stripe/webhook', [PaymentController::class, 'webhook']);
Route::post('paypal/webhook', [PayPalWebhookController::class, 'handle']);
Route::post('/visitor/ping', function () {
    $visitorId = request()->cookie('za_visitor');
    if (!$visitorId) return;

    \App\Models\Visit::where('visitor_id', $visitorId)
        ->update(['last_seen_at' => now()]);

    return response()->json(['ok' => true]);
});
