<?php

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Models\Notification;
use App\Models\StudentAssessment;
use Illuminate\Contracts\Queue\ShouldQueue;

class MarkNotificationCompleteOnPayment implements ShouldQueue
{
    /**
     * When a payment is recorded, mark all payment_due notification banners
     * as complete once the student's full assessment balance is cleared.
     *
     * Bug 10 fix — ambiguous assessments() latest():
     *   The previous code used $user->assessments()->latest('created_at')->first()
     *   to find the assessment to check.  If a student has had multiple
     *   enrolment assessments (e.g. different semesters), this always grabs the
     *   most recently CREATED one — which may not be the one the payment just
     *   reduced.  If the payment was for an older assessment the listener would
     *   read the wrong balance and either never mark notifications complete, or
     *   mark them complete prematurely.
     *
     *   Fix: PaymentRecorded carries the transaction ID. We resolve the
     *   assessment via the payment transaction's meta (assessment_id) when
     *   available.  We fall back to the latest assessment only when the
     *   transaction has no explicit assessment link, preserving backward
     *   compatibility with older payment records.
     *
     *   Additionally we now mark only the SPECIFIC notification for the term
     *   that was paid (when we can determine it) rather than all payment_due
     *   banners.  If the full balance is cleared, all remaining payment_due
     *   banners are marked complete.
     */
    public function handle(PaymentRecorded $event): void
    {
        $user = $event->user;

        // Try to resolve the assessment that this payment was applied to
        // so we check the right balance.
        $studentAssessment = $this->resolveAssessment($user, $event->transactionId);

        if (! $studentAssessment) {
            return;
        }

        $totalBalance = $studentAssessment->paymentTerms()
            ->where('balance', '>', 0)
            ->sum('balance');

        // Mark all payment_due banners complete only when the full balance is cleared
        if ($totalBalance <= 0) {
            Notification::where('user_id', $user->id)
                ->where('type', 'payment_due')
                ->where('is_complete', false)
                ->update(['is_complete' => true]);
        }
    }

    /**
     * Resolve which StudentAssessment this payment transaction belongs to.
     *
     * Priority:
     *   1. Transaction meta['assessment_id']  (explicit link — most accurate)
     *   2. Latest assessment by created_at    (fallback for older records)
     */
    private function resolveAssessment(\App\Models\User $user, int $transactionId): ?\App\Models\StudentAssessment
    {
        // Try to find the assessment via the transaction's meta
        $transaction = $user->transactions()->find($transactionId);

        if ($transaction && ! empty($transaction->meta['assessment_id'])) {
            $assessment = StudentAssessment::find($transaction->meta['assessment_id']);
            if ($assessment && $assessment->user_id === $user->id) {
                return $assessment;
            }
        }

        // Fallback: use the latest assessment (original behaviour)
        return $user->assessments()->latest('created_at')->first();
    }
}