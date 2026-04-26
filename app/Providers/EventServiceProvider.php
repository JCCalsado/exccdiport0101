<?php

namespace App\Providers;

use App\Events\DueAssigned;
use App\Events\PaymentRecorded;
use App\Listeners\GenerateDueAssignedReminder;
use App\Listeners\GeneratePaymentReceivedReminder;
use App\Listeners\MarkNotificationCompleteOnPayment;
use App\Listeners\SendPaymentConfirmationNotification;
use App\Listeners\SendPaymentDueNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DueAssigned::class => [
            GenerateDueAssignedReminder::class,
            SendPaymentDueNotification::class,
        ],
        PaymentRecorded::class => [
            GeneratePaymentReceivedReminder::class,
            SendPaymentConfirmationNotification::class,
            MarkNotificationCompleteOnPayment::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    /**
     * Disable auto-discovery to prevent double-registration.
     *
     * Laravel 12 scans app/Listeners/ AND registers $listen array — both.
     * Result without this override: every listener fires TWICE per event,
     * causing duplicate emails and duplicate PaymentReminder rows.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}