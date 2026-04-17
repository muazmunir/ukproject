<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Login;

use App\Listeners\MarkStaffOfflineOnLogout;
use App\Listeners\MarkStaffOnlineOnLogin;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Logout::class => [
            MarkStaffOfflineOnLogout::class,
        ],

        Login::class => [
            MarkStaffOnlineOnLogin::class,
        ],
    ];
}