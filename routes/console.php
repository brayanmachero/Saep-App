<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-refresh Kizeo cache every 4 hours
Schedule::command('kizeo:cache-warm')->everyFourHours()->withoutOverlapping();
