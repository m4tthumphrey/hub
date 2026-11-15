<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The dashboard also logs on load, so this only matters when nobody opens it for a while.
Schedule::command('wealth:log-liabilities')->dailyAt('06:00');
