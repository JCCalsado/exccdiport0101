<?php

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Notifications\PaymentConfirmed;
use App\Services\PhilSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentConfirmationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PaymentRecorded $event): void
    {
        $user = $event->user;

        // Email + database notification
        $user->notify(new PaymentConfirmed(
            $event->transactionId,
            $event->amount,
            $event->reference,
        ));

        // SMS via PhilSMS
        $phone = $user->phone ?? null;
        if (! $phone) return;

        $amount = number_format($event->amount, 2);
        $ref    = $event->reference;
        $name   = $user->first_name ?? 'Student';

        $appUrl  = rtrim(config('app.url'), '/');
        $receiptUrl = $appUrl . '/transactions/' . $event->transactionId . '/receipt';

        $message = "Hi {$name}! Payment of P{$amount} confirmed. Ref: {$ref}. "
                 . "View receipt: {$receiptUrl} -CCDI";

        app(PhilSmsService::class)->send($phone, $message);
    }
}
