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
     * Send a queued email + database notification when a due date is assigned.
     *
     * ── Minor fix: diffInDays() direction ────────────────────────────────────
     * The previous guard was:
     *
     *   $event->term->due_date->diffInDays(now()) <= 7
     *
     * Carbon's diffInDays() always returns an absolute (positive) value when
     * called with a single argument, regardless of direction. This means a due
     * date that is already 30 days PAST would still pass the <= 7 check if
     * the absolute difference happened to be ≤ 7, which is only saved by the
     * subsequent `isFuture()` guard — but relying on that order is fragile.
     *
     * The correct idiom for "due date is within N days from now (in the future)"
     * is `now()->diffInDays($dueDate, false)` which returns a negative number
     * for past dates, letting the >= 0 check do the right thing in one step.
     *
     * We also send the email for ANY future due date assignment (not just within
     * 7 days) since the admin has explicitly chosen to notify the student. The
     * 7-day gate made sense for a scheduled job but is overly restrictive for
     * an admin-triggered action where the due date may be months away.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function handle(DueAssigned $event): void
    {
        $term = $event->term;

        // Only send if there is a due date and it is still in the future
        if (! $term->due_date || ! $term->due_date->isFuture()) {
            return;
        }

        // `diffInDays(false)` returns negative for past dates; >= 0 means future.
        // We notify for ALL future due dates set by an admin action.
        $daysUntilDue = now()->diffInDays($term->due_date, false);

        if ($daysUntilDue >= 0) {
            $event->user->notify(new PaymentDueNotification(
                $term->term_name,
                (float) $term->balance,
                $term->due_date,
            ));
        }
    }
}