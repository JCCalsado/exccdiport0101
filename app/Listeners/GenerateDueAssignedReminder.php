<?php

namespace App\Listeners;

use App\Events\DueAssigned;
use App\Models\PaymentReminder;
use Illuminate\Support\Facades\Auth;

class GenerateDueAssignedReminder
{
    /**
     * Create or update a PaymentReminder feed entry when an admin sets a due date.
     *
     * Bug 7 fix — duplicate rows caused by `type` being part of the upsert key:
     *   The previous key was (user_id, student_payment_term_id, type).
     *   Because `type` is derived from urgency at the moment the admin saves
     *   (payment_due → approaching_due → overdue), each urgency tier created an
     *   independent row.  A student who had their due date changed three times
     *   across the urgency thresholds could end up with three reminder cards for
     *   the same term.
     *
     *   Fix: key on (user_id, student_payment_term_id) only.  `type` and
     *   `message` are now UPDATE values, so a single row is kept up-to-date
     *   regardless of how many times the admin adjusts the same term's due date.
     */
    public function handle(DueAssigned $event): void
    {
        $user = $event->user;
        $term = $event->term;

        // Days until due; negative means already overdue
        $daysUntilDue = now()->diffInDays($term->due_date, false);

        if ($daysUntilDue < 0) {
            $type    = PaymentReminder::TYPE_OVERDUE;
            $message = "{$term->term_name} is overdue by " . abs((int) $daysUntilDue) . " day(s). "
                     . "Amount due: ₱" . number_format($term->balance, 2);
        } elseif ($daysUntilDue <= 3) {
            $type    = PaymentReminder::TYPE_APPROACHING_DUE;
            $message = "{$term->term_name} is due in {$daysUntilDue} day(s). "
                     . "Amount due: ₱" . number_format($term->balance, 2);
        } else {
            $type    = PaymentReminder::TYPE_PAYMENT_DUE;
            $message = "{$term->term_name} payment due on " . $term->due_date->format('M d, Y') . ". "
                     . "Amount: ₱" . number_format($term->balance, 2);
        }

        PaymentReminder::updateOrCreate(
            [
                // Bug 7 fix: key on term only — type is now an updated value, not a discriminator
                'user_id'                 => $user->id,
                'student_payment_term_id' => $term->id,
            ],
            [
                'type'                  => $type,
                'student_assessment_id' => $term->student_assessment_id,
                'message'               => $message,
                'outstanding_balance'   => $term->balance,
                'status'                => PaymentReminder::STATUS_SENT,
                'in_app_sent'           => true,
                'sent_at'               => now(),
                'trigger_reason'        => PaymentReminder::TRIGGER_DUE_DATE_CHANGE,
                'triggered_by'          => Auth::id(),
                'metadata'              => [
                    'term_order'  => $term->term_order,
                    'due_date'    => $term->due_date?->toDateString(),
                    'percentage'  => $term->percentage,
                ],
            ]
        );
    }
}