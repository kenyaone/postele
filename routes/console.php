<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly jobs (needs the cPanel cron: * * * * * php artisan schedule:run)
\Illuminate\Support\Facades\Schedule::command('pos:daily-summary')->dailyAt('21:30')->timezone('Africa/Nairobi')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('pos:backup')->dailyAt('21:40')->timezone('Africa/Nairobi')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('pos:mpesa-reconcile')->everyMinute()->withoutOverlapping();
