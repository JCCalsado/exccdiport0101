<?php

namespace App\Services;

use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use Illuminate\Support\Facades\DB;

class PaymentCarryoverService
{
    /**
     * Apply carryover logic to all payment terms for an assessment.
     *
     * Each term's balance = its own original amount PLUS any unpaid balance
     * carried forward from the previous term.
     *
     * For a freshly created assessment (no payments yet) every term's balance
     * will equal its amount and carryover will be zero between terms.
     *
     * Wrapped in a DB transaction — all terms update atomically.
     *
     * FIX (Bug #3): Previously `$carryoverBalance` was read from `$term->balance`
     * immediately after `$term->update([...])`. Eloquent's update() persists to
     * the DB but does NOT refresh the in-memory model, so `$term->balance` still
     * held the OLD value. Every subsequent term's balance was computed from a
     * stale base, causing cascading inflation. The fix is to use the locally
     * computed `$totalBalance` variable instead of re-reading the model.
     */
    public function applyCarryoverToAssessment(StudentAssessment $assessment): void
    {
        DB::transaction(function () use ($assessment) {
            $terms = $assessment->paymentTerms()
                ->orderBy('term_order')
                ->get();

            if ($terms->count() === 0) {
                return;
            }

            $carryoverBalance = 0.0;

            foreach ($terms as $term) {
                $previousTermUnpaid  = $carryoverBalance;
                $currentTermAmount   = (float) $term->amount;

                // Total balance for this term = own amount + any unpaid from previous term
                $totalBalance = round($previousTermUnpaid + $currentTermAmount, 2);

                $remarks = null;
                if ($previousTermUnpaid > 0) {
                    $remarks = 'Balance of ₱' . number_format($previousTermUnpaid, 2) . ' carried from previous term(s)';
                }

                $term->update([
                    'balance' => $totalBalance,
                    'remarks' => $remarks,
                    'status'  => $totalBalance > 0 ? StudentPaymentTerm::STATUS_PENDING : StudentPaymentTerm::STATUS_PAID,
                ]);

                // FIX: use the locally computed $totalBalance — NOT $term->balance.
                // $term->balance is stale (Eloquent does not refresh after update()).
                $carryoverBalance = $totalBalance;
            }

            // Annotate the last term so it is clear no further carryover occurs
            $lastTerm = $terms->last();
            if ($lastTerm && (float) $lastTerm->balance > 0) {
                $existingRemarks = $lastTerm->remarks ?? '';
                $lastTerm->update([
                    'remarks' => ($existingRemarks ? $existingRemarks . '. ' : '') . 'Final term — no carryover beyond this',
                ]);
            }
        });
    }

    /**
     * Apply a payment across terms using carryover priority.
     *
     * Payments are distributed to the earliest unpaid terms first (term_order ASC).
     * Wrapped in a DB transaction — all term updates are atomic.
     */
    public function applyPayment(StudentAssessment $assessment, float $paymentAmount): array
    {
        return DB::transaction(function () use ($assessment, $paymentAmount) {
            $appliedPayments = [];
            $remainingAmount = $paymentAmount;

            $terms = $assessment->paymentTerms()
                ->where('balance', '>', 0)
                ->orderBy('term_order')
                ->get();

            foreach ($terms as $term) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $termBalance    = (float) $term->balance;
                $amountToApply  = min($remainingAmount, $termBalance);
                $newBalance     = round($termBalance - $amountToApply, 2);
                $newStatus      = $newBalance <= 0 ? StudentPaymentTerm::STATUS_PAID : StudentPaymentTerm::STATUS_PARTIAL;

                $term->update([
                    'balance'   => max(0.0, $newBalance),
                    'status'    => $newStatus,
                    'paid_date' => $newStatus === StudentPaymentTerm::STATUS_PAID ? now() : $term->paid_date,
                ]);

                $appliedPayments[] = [
                    'term'              => $term->term_name,
                    'applied'           => $amountToApply,
                    'remaining_balance' => max(0.0, $newBalance),
                ];

                $remainingAmount -= $amountToApply;
            }

            return [
                'applied_payments' => $appliedPayments,
                'remaining_amount' => $remainingAmount,
                'total_applied'    => $paymentAmount - $remainingAmount,
            ];
        });
    }

    /**
     * Get total remaining balance across all terms for an assessment.
     */
    public function getTotalRemainingBalance(StudentAssessment $assessment): float
    {
        return (float) $assessment->paymentTerms()->sum('balance');
    }

    /**
     * Check if assessment is fully paid.
     */
    public function isFullyPaid(StudentAssessment $assessment): bool
    {
        return $this->getTotalRemainingBalance($assessment) <= 0;
    }

    /**
     * Get the next unpaid/partially-paid/overdue term for an assessment.
     */
    public function getNextPendingTerm(StudentAssessment $assessment): ?StudentPaymentTerm
    {
        return $assessment->paymentTerms()
            ->whereIn('status', \App\Enums\PaymentStatus::unpaidValues())
            ->orderBy('term_order')
            ->first();
    }

    /**
     * Get a full payment breakdown suitable for display or API response.
     */
    public function getPaymentBreakdown(StudentAssessment $assessment): array
    {
        $terms = $assessment->paymentTerms()
            ->orderBy('term_order')
            ->get()
            ->map(fn ($term) => [
                'id'              => $term->id,
                'term_name'       => $term->term_name,
                'term_order'      => $term->term_order,
                'percentage'      => $term->percentage,
                'original_amount' => (float) $term->amount,
                'balance'         => (float) $term->balance,
                'status'          => $term->status,
                'due_date'        => $term->due_date?->format('Y-m-d'),
                'remarks'         => $term->remarks,
                'has_carryover'   => $term->hasCarryover(),
            ])
            ->toArray();

        return [
            'total_assessment' => (float) $assessment->total_assessment,
            'total_paid'       => (float) $assessment->total_assessment - $this->getTotalRemainingBalance($assessment),
            'total_remaining'  => $this->getTotalRemainingBalance($assessment),
            'is_fully_paid'    => $this->isFullyPaid($assessment),
            'terms'            => $terms,
        ];
    }
}