<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;
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
}