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
// Till payments whose notice never arrived (server or internet down): hourly for the last 3 hours, and a full-day sweep at night.
\Illuminate\Support\Facades\Schedule::command('pos:kk-poll --hours=3')->hourlyAt(17)->timezone('Africa/Nairobi')->between('7:00', '23:00')->withoutOverlapping()->runInBackground();
\Illuminate\Support\Facades\Schedule::command('pos:kk-poll --hours=26')->dailyAt('23:35')->timezone('Africa/Nairobi')->withoutOverlapping()->runInBackground();

// Clock-in photos are kept 60 days, then deleted (the clock time stays).
\Illuminate\Support\Facades\Schedule::call(function () {
    foreach (\App\Models\StaffClock::where('at', '<', now()->subDays(60))->whereNotNull('photo')->get() as $c) {
        \Illuminate\Support\Facades\Storage::disk('local')->delete($c->photo);
        $c->update(['photo' => null]);
    }
})->name('purge-clock-photos')->dailyAt('03:10')->timezone('Africa/Nairobi')->withoutOverlapping();

// Activity log is kept 90 days.
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Models\ActivityLog::where('created_at', '<', now()->subDays(90))->delete())->name('purge-activity-log')->dailyAt('03:20')->timezone('Africa/Nairobi')->withoutOverlapping();
