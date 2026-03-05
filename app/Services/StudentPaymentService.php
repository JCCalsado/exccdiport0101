<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentPaymentService
{
    /**
     * Process a payment for a user against a specific payment term.
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

        $term = StudentPaymentTerm::findOrFail($termId);

        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $options, $term, $requiresApproval) {

            $reference = 'PAY-' . Str::upper(Str::random(8));

            // Determine transaction status based on approval requirement
            $status = $requiresApproval ? 'pending' : 'paid';

            // Build meta for audit trail
            $meta = [
                'payment_method'    => $options['payment_method'] ?? null,
                'description'       => $options['description'] ?? null,
                'term_name'         => $options['term_name'] ?? $term->term_name,
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

            // Update payment term balance and status only when immediately approved
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
                        'description'      => $options['description'] ?? ($options['term_name'] ?? $term->term_name),
                        'status'           => Payment::STATUS_COMPLETED,
                        'paid_at'          => $options['paid_at'] ?? now(),
                    ]);
                }

                // Recalculate account balance
                AccountService::recalculate($user);

                $message = 'Payment of ₱' . number_format($amount, 2) . ' recorded successfully.';
            } else {
                $message = 'Payment of ₱' . number_format($amount, 2) . ' submitted and is awaiting approval.';
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
            // Already finalized, skip
            return;
        }

        DB::transaction(function () use ($transaction) {
            $user = $transaction->user;
            $amount = $transaction->amount;
            
            // Get the term name from transaction meta or type
            $termName = $transaction->meta['term_name'] ?? $transaction->type;

            // Find the associated StudentPaymentTerm by user_id and term_name
            // StudentPaymentTerm is directly linked to User, not through Student
            $term = StudentPaymentTerm::where('user_id', $user->id)
                ->where('term_name', $termName)
                ->first();

            if (!$term) {
                // Fallback: find any unpaid or partial term with this name for this user
                $term = StudentPaymentTerm::where('user_id', $user->id)
                    ->where('term_name', $termName)
                    ->whereIn('status', ['pending', 'partial'])
                    ->orderBy('due_date', 'desc')
                    ->first();
            }

            if (!$term) {
                throw new \Exception(
                    "Could not find StudentPaymentTerm for '{$termName}' for user {$user->id}. " .
                    "Payment cannot be finalized without term reference."
                );
            }

            // Update the payment term balance and status
            $newBalance = max(0, (float) $term->balance - $amount);
            $newStatus = $newBalance <= 0
                ? StudentPaymentTerm::STATUS_PAID
                : StudentPaymentTerm::STATUS_PARTIAL;

            $term->update([
                'balance'   => $newBalance,
                'status'    => $newStatus,
                'paid_date' => $newStatus === StudentPaymentTerm::STATUS_PAID ? now() : $term->paid_date,
            ]);

            // Create a Payment record for history
            if ($user->student) {
                Payment::create([
                    'student_id'       => $user->student->id,
                    'amount'           => $amount,
                    'payment_method'   => $transaction->payment_channel,
                    'reference_number' => $transaction->reference,
                    'description'      => $transaction->meta['description'] ?? $termName,
                    'status'           => Payment::STATUS_COMPLETED,
                    'paid_at'          => $transaction->paid_at,
                ]);
            }

            // Update the transaction status to 'paid'
            $transaction->update([
                'status' => 'paid',
            ]);

            // Recalculate the account balance
            AccountService::recalculate($user);
        });
    }

    /**
     * Cancel a rejected payment by updating the transaction status.
     * Called when a payment approval workflow is rejected.
     *
     * @param  Transaction $transaction The rejected payment transaction
     * @return void
     * @throws \Exception on processing failure
     */
    public function cancelRejectedPayment(Transaction $transaction): void
    {
        if ($transaction->kind !== 'payment') {
            throw new \Exception('Transaction is not a payment.');
        }

        DB::transaction(function () use ($transaction) {
            // Update the transaction status to 'cancelled'
            $transaction->update([
                'status' => 'cancelled',
            ]);

            // No need to update term balance since it was never deducted
            // (payment was pending, not yet applied)

            Log::info('Payment cancelled due to workflow rejection', [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);
        });
    }
}