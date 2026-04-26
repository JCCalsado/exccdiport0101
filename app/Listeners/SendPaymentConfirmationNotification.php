<?php

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Notifications\PaymentConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentConfirmationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PaymentRecorded $event): void
    {
        // NOTIFICATION: LARAVEL DATABASE CHANNEL
        // Payment confirmation is a transactional receipt tied to specific user.
        // Uses: $user->notify() → sends mail + writes to `notifications` table
        // Why: Event-driven, immediate delivery, user-specific
        // See: docs/NOTIFICATION_ARCHITECTURE.md for system overview
        $event->user->notify(new PaymentConfirmed(
            $event->transactionId,
            $event->amount,
            $event->reference,
        ));
    }
}
