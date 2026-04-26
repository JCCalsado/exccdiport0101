<?php

namespace App\Listeners;

use App\Events\DueAssigned;
use App\Notifications\PaymentDueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentDueNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Send an email + database notification when an admin assigns a due date.
     *
     * Bug 8 fix — diffInDays() direction:
     *   The old guard was: $term->due_date->diffInDays(now()) <= 7
     *
     *   Carbon's diffInDays() with a single argument returns the ABSOLUTE
     *   difference regardless of direction.  A due date 30 days in the PAST
     *   has an absolute diff of 30 — it fails the <= 7 check — fine here by
     *   accident.  But a due date 3 days in the PAST has an absolute diff of
     *   3 — it PASSES the <= 7 check — and the only thing preventing an email
     *   going out for a past-due date was the adjacent isFuture() guard.
     *   Relying on guard ordering is fragile and semantically misleading.
     *
     *   Fix: use now()->diffInDays($dueDate, false) which returns NEGATIVE for
     *   past dates.  A >= 0 check cleanly means "due date is today or future".
     *
     *   We email for ANY future due date set by an admin action (not just
     *   within 7 days).  The 7-day window made sense for a scheduled cron job
     *   that runs daily, but here the admin is explicitly telling the student
     *   about a deadline — we should always send that notification.
     */
    public function handle(DueAssigned $event): void
    {
        $term = $event->term;

        if (! $term->due_date) {
            return;
        }

        // Bug 8 fix: signed diff — negative means past, positive means future
        $daysUntilDue = now()->diffInDays($term->due_date, false);

        // Only notify when the due date is today or in the future
        if ($daysUntilDue < 0) {
            return;
        }

        // NOTIFICATION: LARAVEL DATABASE CHANNEL
        // Payment due is a transactional reminder tied to specific user.
        // Uses: $user->notify() → sends mail + writes to `notifications` table
        // Why: Event-driven, immediate delivery, user-specific
        // See: docs/NOTIFICATION_ARCHITECTURE.md for system overview
        $event->user->notify(new PaymentDueNotification(
            $term->term_name,
            (float) $term->balance,
            $term->due_date,
        ));
    }
}