<?php

namespace App\Listeners;

use App\Events\DueAssigned;
use App\Models\PaymentReminder;
use Illuminate\Support\Facades\Auth;

class GenerateDueAssignedReminder
{
    /**
     * Handle the DueAssigned event.
     *
     * Creates or updates a PaymentReminder record for the student's feed.
     *
     * ── FIX Bug 4 ────────────────────────────────────────────────────────────
     * The previous updateOrCreate key was (user_id + student_payment_term_id + type).
     * Because `type` changes depending on urgency at the moment the admin saves
     * (payment_due → approaching_due → overdue), each urgency level created an
     * independent row instead of updating the existing one. A student could end up
     * with three separate reminder cards for the same term.
     *
     * Fix: key only on (user_id + student_payment_term_id). Type and message are
     * now part of the VALUES being updated so they always reflect the latest state.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function handle(DueAssigned $event): void
    {
        $user = $event->user;
        $term = $event->term;

        // Calculate days until due (negative = already overdue)
        $daysUntilDue = now()->diffInDays($term->due_date, false);

        // Determine reminder type and message based on urgency
        if ($daysUntilDue < 0) {
            $type    = PaymentReminder::TYPE_OVERDUE;
            $message = "{$term->term_name} is overdue by " . abs((int) $daysUntilDue) . " day(s). Amount due: ₱" . number_format($term->balance, 2);
        } elseif ($daysUntilDue <= 3) {
            $type    = PaymentReminder::TYPE_APPROACHING_DUE;
            $message = "{$term->term_name} is due in {$daysUntilDue} day(s). Amount due: ₱" . number_format($term->balance, 2);
        } else {
            $type    = PaymentReminder::TYPE_PAYMENT_DUE;
            $message = "{$term->term_name} payment due on " . $term->due_date->format('M d, Y') . ". Amount: ₱" . number_format($term->balance, 2);
        }

        PaymentReminder::updateOrCreate(
            [
                // ── FIXED: key on (user_id + term_id) only ──
                // `type` is intentionally excluded from the key so that a single
                // reminder row is refreshed when urgency changes, rather than
                // creating a new duplicate row per urgency tier.
                'user_id'                 => $user->id,
                'student_payment_term_id' => $term->id,
            ],
            [
                'type'                  => $type,    // ← now a VALUE, not a key
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