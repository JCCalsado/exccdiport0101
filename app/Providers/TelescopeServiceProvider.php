<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
    }

    public function boot(): void
    {
        if (!class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        $this->hideSensitiveRequestDetails();

        \Laravel\Telescope\Telescope::filter(function (\Laravel\Telescope\IncomingEntry $entry) {
            return $this->app->environment('local') ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        \Laravel\Telescope\Telescope::hideRequestParameters(['_token']);
        \Laravel\Telescope\Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            return in_array($user->email, []);
        });
    }
}