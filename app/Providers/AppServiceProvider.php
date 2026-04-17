<?php

namespace App\Providers;

use App\Services\PayoutProviders\Stripe\StripeConnectAccountService;
use App\Services\PayoutProviders\Stripe\StripeTransferClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret'));
        });

        $this->app->singleton(StripeConnectAccountService::class, function ($app) {
            return new StripeConnectAccountService(
                $app->make(StripeClient::class)
            );
        });

        $this->app->singleton(StripeTransferClient::class, function ($app) {
            return new StripeTransferClient(
                $app->make(StripeClient::class)
            );
        });
    }

    public function boot(): void
    {
        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }

        Paginator::defaultView('vendor.pagination.simple-bootstrap-5');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });
    }
}