<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Moved here from Forge's scheduled jobs. Tokens are refreshed on the hour, before the syncs that use them.
Schedule::command('withings:authenticate')->hourly();
Schedule::command('peloton:reauthenticate')->hourly();
Schedule::command('withings:weight')->hourlyAt(5);
Schedule::command('peloton:weight')->hourlyAt(10);

// The dashboard also logs on load, so this only matters when nobody opens it for a while.
Schedule::command('wealth:log-liabilities')->dailyAt('06:00');
