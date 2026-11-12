<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Manager-edited settings (Manage > Settings) override .env defaults in config('pos.*').
        try {
            \App\Models\Setting::applyToConfig();
        } catch (\Throwable $e) {
            // settings table not migrated yet - keep .env defaults
        }
        //
    }
}
