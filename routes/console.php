<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('slots:process')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('support:leave-tick')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('support:sync-offline --mins=3')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('settlements:release-escrow')
    ->everyMinute()
    ->withoutOverlapping();

/**
 * Daily coach payout runs
 * Stagger providers to avoid collision and make logs easier to debug.
 */
Schedule::command('payouts:run-coach stripe')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('payouts:run-coach payoneer')
    ->dailyAt('02:10')
    ->withoutOverlapping()
    ->onOneServer();
    
    Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
    
   

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');