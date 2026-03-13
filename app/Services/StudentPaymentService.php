<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentPaymentService
{
    /**
     * Process a payment for a user against a specific payment term.
     *
     * ⚠️ IMPORTANT: When $requiresApproval=true, the CALLER is responsible for
     * starting the approval workflow. This service does NOT automatically start
     * workflows. If a workflow is not created, accounting will never see the
     * pending payment for approval. See TransactionController::payNow() for an
     * example of proper workflow initialization.
     *
     * @param  User   $user             The user making the payment
     * @param  float  $amount           Amount being paid
     * @param  array  $options {
     *     payment_method:   string,
     *     paid_at:          string (date),
     *     description:      string|null,
     *     selected_term_id: int,
     *     term_name:        string|null,
     *     year:             int|null,
     *     semester:         string|null,
     * }
     * @param  bool   $requiresApproval Whether the payment needs admin approval
     * @return array {
     *     transaction_id:        int,
     *     transaction_reference: string,
     *     message:               string,
     * }
     *
     * @throws \Exception on validation or processing failure
     */
    public function processPayment(User $user, float $amount, array $options, bool $requiresApproval = true): array
    {
        $termId = (int) ($options['selected_term_id'] ?? 0);

        if ($termId === 0) {
            throw new \Exception('A payment term must be selected.');
        }

        $term = StudentPaymentTerm::findOrFail($termId);

        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $options, $term, $requiresApproval) {

            $reference = 'PAY-' . Str::upper(Str::random(8));

            // Determine transaction status based on approval requirement
            $status = $requiresApproval ? 'awaiting_approval' : 'paid';

            // Normalise description — never null to satisfy DB NOT NULL constraint
            $description = $options['description'] ?? null;
            if (empty($description)) {
                $description = 'Payment — ' . ($options['term_name'] ?? $term->term_name);
            }

            // Build meta for audit trail. Store selected_term_id so finalizeApprovedPayment()
            // can look up the EXACT term without relying on term_name string-matching, which
            // breaks when a student has multiple assessments with identically-named terms.
            $meta = [
                'payment_method'    => $options['payment_method'] ?? null,
                'description'       => $description,
                'term_name'         => $options['term_name'] ?? $term->term_name,
                'selected_term_id'  => $term->id,
                'requires_approval' => $requiresApproval,
            ];

            // Create the transaction record
            $transaction = Transaction::create([
                'user_id'         => $user->id,
                'reference'       => $reference,
                'kind'            => 'payment',
                'type'            => $options['term_name'] ?? $term->term_name,
                'amount'          => $amount,
                'status'          => $status,
                'payment_channel' => $options['payment_method'] ?? null,
                'paid_at'         => $options['paid_at'] ?? now(),
                'year'            => $options['year'] ?? now()->year,
                'semester'        => $options['semester'] ?? null,
                'meta'            => $meta,
            ]);

            // Update payment term balance and status only when immediately approved (staff-side payment)
            if (!$requiresApproval) {
                $newBalance = max(0, (float) $term->balance - $amount);
                $newStatus  = $newBalance <= 0
                    ? StudentPaymentTerm::STATUS_PAID
                    : StudentPaymentTerm::STATUS_PARTIAL;

                $term->update([
                    'balance'   => $newBalance,
                    'status'    => $newStatus,
                    'paid_date' => $newStatus === StudentPaymentTerm::STATUS_PAID ? now() : $term->paid_date,
                ]);

                // Create a Payment record so the history table shows the entry
                if ($user->student) {
                    Payment::create([
                        'student_id'       => $user->student->id,
                        'amount'           => $amount,
                        'payment_method'   => $options['payment_method'] ?? null,
                        'reference_number' => $reference,
                        'description'      => $description,
                        'status'           => Payment::STATUS_COMPLETED,
                        'paid_at'          => $options['paid_at'] ?? now(),
                    ]);
                }

                // Recalculate account balance
                AccountService::recalculate($user);

                // ── Check if all terms of this assessment are now fully paid ──
                // If yes, notify admin to create the next semester's assessment.
                $this->checkAndNotifyProgressionReady($user, $term->student_assessment_id);

                $message = 'Payment of ₱' . number_format($amount, 2) . ' recorded successfully.';
            } else {
                $message = 'Payment of ₱' . number_format($amount, 2) . ' submitted and is awaiting accounting approval.';
            }

            return [
                'transaction_id'        => $transaction->id,
                'transaction_reference' => $reference,
                'message'               => $message,
            ];
        });
    }

    /**
     * Finalize an approved payment by updating the transaction and payment term.
     * Called when a payment approval workflow is completed.
     *
     * Uses the stored `selected_term_id` in transaction meta for reliable term
     * lookup — avoids mismatches when a student has multiple assessments that
     * contain identically-named payment terms (e.g. "Upon Registration" for both
     * 1st Sem and 2nd Sem assessments).
     *
     * @param  Transaction $transaction The approved payment transaction
     * @return void
     * @throws \Exception on processing failure
     */
    public function finalizeApprovedPayment(Transaction $transaction): void
    {
        if ($transaction->kind !== 'payment') {
            throw new \Exception('Transaction is not a payment.');
        }

        if ($transaction->status === 'paid') {
            // Already finalized — idempotent, skip
            return;
        }

        DB::transaction(function () use ($transaction) {
            $user   = $transaction->user;
            $amount = (float) $transaction->amount;

            // ── Priority 1: use the term ID stored in meta ──
            $termId = isset($transaction->meta['selected_term_id'])
                ? (int) $transaction->meta['selected_term_id']
                : null;

            $term = null;

            if ($termId) {
                $term = StudentPaymentTerm::find($termId);
            }

            // ── Fallback: match by term name scoped to user (last resort) ──
            if (!$term) {
                $termName = $transaction->meta['term_name'] ?? $transaction->type;

                Log::warning('finalizeApprovedPayment: term_id not in meta, falling back to name match', [
                    'transaction_id' => $transaction->id,
                    'term_name'      => $termName,
                    'user_id'        => $user->id,
                ]);

                // Query through StudentAssessment instead of direct user_id lookup
                $term = $user->assessments()
                    ->first()
                    ?->paymentTerms()
                    ->where('term_name', $termName)
                    ->whereIn('status', ['pending', 'partial'])
                    ->orderBy('due_date', 'desc')
                    ->first();
            }

            if (!$term) {
                throw new \Exception(
                    "Could not find StudentPaymentTerm for transaction #{$transaction->id} (user {$user->id}). " .
                    "Payment cannot be finalized without a term reference."
                );
            }

            // ── Update the payment term ──
            $newBalance = max(0, (float) $term->balance - $amount);
            $newStatus  = $newBalance <= 0
                ? StudentPaymentTerm::STATUS_PAID
                : StudentPaymentTerm::STATUS_PARTIAL;

            $term->update([
                'balance'   => $newBalance,
                'status'    => $newStatus,
                'paid_date' => $newStatus === StudentPaymentTerm::STATUS_PAID
                    ? now()
                    : $term->paid_date,
            ]);

            // ── Normalise description ──
            $description = $transaction->meta['description'] ?? null;
            if (empty($description)) {
                $description = 'Payment — ' . $term->term_name;
            }

            // ── Create Payment record for history ──
            if ($user->student) {
                Payment::create([
                    'student_id'       => $user->student->id,
                    'amount'           => $amount,
                    'payment_method'   => $transaction->payment_channel,
                    'reference_number' => $transaction->reference,
                    'description'      => $description,
                    'status'           => Payment::STATUS_COMPLETED,
                    'paid_at'          => $transaction->paid_at ?? now(),
                ]);
            }

            // ── Mark transaction as paid ──
            $transaction->update(['status' => 'paid']);

            // ── Recalculate account balance ──
            AccountService::recalculate($user);

            // ── Check if all terms of this assessment are now fully paid ──
            // If yes, notify admin to create the next semester's assessment.
            $this->checkAndNotifyProgressionReady($user, $term->student_assessment_id);

            Log::info('Payment finalized successfully', [
                'transaction_id' => $transaction->id,
                'term_id'        => $term->id,
                'term_name'      => $term->term_name,
                'amount'         => $amount,
                'new_balance'    => $newBalance,
            ]);
        });
    }

    /**
     * Cancel a rejected payment by updating the transaction status.
     * Called when a payment approval workflow is rejected.
     *
     * @param  Transaction $transaction The rejected payment transaction
     * @return void
     */
    public function cancelRejectedPayment(Transaction $transaction): void
    {
        if ($transaction->kind !== 'payment') {
            throw new \Exception('Transaction is not a payment.');
        }

        DB::transaction(function () use ($transaction) {
            // Mark as cancelled — term balance was never deducted (payment was pending)
            $transaction->update(['status' => 'cancelled']);

            Log::info('Payment cancelled due to workflow rejection', [
                'transaction_id' => $transaction->id,
                'amount'         => $transaction->amount,
                'reference'      => $transaction->reference,
            ]);
        });
    }

    /**
     * Get the total outstanding balance for a user, derived from their payment terms.
     * Queries through StudentAssessment to ensure consistent access pattern.
     *
     * @param  User $user
     * @return float
     */
    public function getTotalOutstandingBalance(User $user): float
    {
        return (float) StudentPaymentTerm::whereHas('assessment', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->whereIn('status', ['pending', 'partial'])
            ->sum('balance');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Semester Completion Detection + Admin Notification
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * After every payment finalization (approved or direct), check whether ALL
     * payment terms of the given assessment are now fully paid.
     *
     * If yes:
     *   1. Notify the Admin  — "Student X finished paying [Year] [Sem]. Please
     *                           create their next semester's assessment."
     *   2. Notify the Student — "Your [Year] [Sem] is fully settled. The admin
     *                            is preparing your next semester's payables."
     *
     * The check is idempotent:  if a "progression_ready" notification for this
     * exact assessment already exists, it is not duplicated.
     *
     * The Admin then navigates to StudentFees/Create and creates the assessment
     * manually — exactly the same flow as any other new assessment creation.
     *
     * @param  User $user           The student whose term was just paid.
     * @param  int  $assessmentId   The assessment that was just partially or
     *                              fully paid.
     */
    private function checkAndNotifyProgressionReady(User $user, int $assessmentId): void
    {
        try {
            $assessment = StudentAssessment::with('paymentTerms')->find($assessmentId);

            if (!$assessment) {
                return;
            }

            // Are ALL 5 terms now fully paid?
            $allPaid = $assessment->paymentTerms->isNotEmpty()
                && $assessment->paymentTerms->every(
                    fn ($t) => $t->status === StudentPaymentTerm::STATUS_PAID
                );

            if (!$allPaid) {
                return; // Still outstanding balances — do nothing
            }

            // Guard: don't create duplicate notifications for the same assessment
            $alreadyNotified = Notification::where('type', 'progression_ready')
                ->whereJsonContains('term_ids', $assessmentId)
                ->exists();

            if ($alreadyNotified) {
                Log::info('StudentPaymentService: progression notification already exists, skipping', [
                    'user_id'       => $user->id,
                    'assessment_id' => $assessmentId,
                ]);
                return;
            }

            $yearLevel  = $assessment->year_level;
            $semester   = $assessment->semester;
            $schoolYear = $assessment->school_year;
            $studentName = trim($user->first_name . ' ' . $user->last_name);

            // Determine what comes next for the label in the notification message
            $nextLabel = $this->resolveNextSemesterLabel($yearLevel, $semester);

            // ── 1. Admin notification ─────────────────────────────────────────
            Notification::create([
                'title'       => "📋 Assessment Required: {$studentName}",
                'message'     => "{$studentName} (ID: {$user->account_id}) has fully paid their "
                               . "{$yearLevel} {$semester} ({$schoolYear}) assessment. "
                               . "Please create their {$nextLabel} assessment via Student Fees → Create Assessment.",
                'type'        => 'progression_ready',
                'target_role' => 'admin',
                'user_id'     => null,        // Broadcast to all admins, not one specific admin
                'is_active'   => true,
                'is_complete' => false,
                'start_date'  => now()->toDateString(),
                'end_date'    => now()->addDays(30)->toDateString(),
                // Reuse term_ids to store the assessment ID for duplicate-guard above
                'term_ids'    => [$assessmentId],
            ]);

            // ── 2. Student notification ───────────────────────────────────────
            Notification::create([
                'title'       => "✅ {$yearLevel} {$semester} Fully Paid!",
                'message'     => "Congratulations! You have fully settled all payment terms for "
                               . "{$yearLevel} {$semester} ({$schoolYear}). "
                               . "The admin is now preparing your {$nextLabel} assessment. "
                               . "You will be notified once it is ready.",
                'type'        => 'payment_due',
                'target_role' => 'student',
                'user_id'     => $user->id,
                'is_active'   => true,
                'is_complete' => false,
                'start_date'  => now()->toDateString(),
                'end_date'    => now()->addDays(14)->toDateString(),
            ]);

            Log::info('StudentPaymentService: progression_ready notifications sent', [
                'user_id'         => $user->id,
                'assessment_id'   => $assessmentId,
                'year_level'      => $yearLevel,
                'semester'        => $semester,
                'next_label'      => $nextLabel,
            ]);

        } catch (\Exception $e) {
            // Never let notification failure break payment finalization
            Log::error('StudentPaymentService: failed to send progression_ready notification', [
                'user_id'       => $user->id,
                'assessment_id' => $assessmentId,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a human-readable label for the next semester/year.
     *
     * Examples:
     *   1st Year, 1st Sem  →  "1st Year 2nd Sem"
     *   1st Year, 2nd Sem  →  "2nd Year 1st Sem"
     *   4th Year, 2nd Sem  →  "graduation" (end of program)
     */
    private function resolveNextSemesterLabel(string $yearLevel, string $semester): string
    {
        $progression = [
            '1st Year|1st Sem' => '1st Year 2nd Sem',
            '1st Year|2nd Sem' => '2nd Year 1st Sem',
            '2nd Year|1st Sem' => '2nd Year 2nd Sem',
            '2nd Year|2nd Sem' => '3rd Year 1st Sem',
            '3rd Year|1st Sem' => '3rd Year 2nd Sem',
            '3rd Year|2nd Sem' => '4th Year 1st Sem',
            '4th Year|1st Sem' => '4th Year 2nd Sem',
            '4th Year|2nd Sem' => 'graduation (program completed)',
        ];

        return $progression["{$yearLevel}|{$semester}"] ?? 'next semester';
    }
}