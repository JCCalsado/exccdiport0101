<?php

namespace App\Http\Controllers;

use App\Models\PaymentReminder;
use Illuminate\Http\Request;

class PaymentReminderController extends Controller
{
    /**
     * Mark a reminder as read. Only the owning student may do this.
     */
    public function markRead(Request $request, PaymentReminder $reminder)
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $reminder->markAsRead();

        return back()->with('success', 'Reminder marked as read.');
    }

    /**
     * Dismiss a reminder. Only the owning student may do this.
     */
    public function dismiss(Request $request, PaymentReminder $reminder)
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $reminder->markAsDismissed();

        return back()->with('success', 'Reminder dismissed.');
    }
}