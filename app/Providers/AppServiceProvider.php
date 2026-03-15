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
     *
     * NOTE: Do NOT share Inertia data here.
     * All shared props are managed exclusively by HandleInertiaRequests middleware
     * to prevent overwrite conflicts and ensure full user objects are passed.
     */
    public function boot(): void
    {
        //
    }
}