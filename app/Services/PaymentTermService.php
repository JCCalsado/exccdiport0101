<?php

namespace App\Services;

use App\Models\StudentPaymentTerm;
use Carbon\Carbon;

class PaymentTermService
{
    /**
     * Record a payment against a payment term and update its balance/status
     */
    public static function recordPayment(StudentPaymentTerm $term, float $amount, Carbon $paymentDate): array
    {
        // Calculate new balance
        $previousBalance = (float) $term->balance;
        $newBalance = max(0, $previousBalance - $amount);
        $amountApplied = $previousBalance - $newBalance;

        // Determine new status
        $newStatus = $term->status;
        if ($newBalance == 0) {
            $newStatus = StudentPaymentTerm::STATUS_PAID;
        } elseif ($previousBalance > 0 && $newBalance > 0) {
            $newStatus = StudentPaymentTerm::STATUS_PARTIAL;
        }

        // Update payment term
        $term->update([
            'balance' => $newBalance,
            'status' => $newStatus,
            'paid_date' => $newStatus === StudentPaymentTerm::STATUS_PAID ? $paymentDate : $term->paid_date,
        ]);

        return [
            'term_id' => $term->id,
            'term_name' => $term->term_name,
            'previous_balance' => $previousBalance,
            'amount_applied' => $amountApplied,
            'new_balance' => $newBalance,
            'new_status' => $newStatus,
            'fully_paid' => $newBalance == 0,
        ];
    }

    /**
     * Get total outstanding balance for a student
     * Query through StudentAssessment for consistent access patterns
     */
    public static function getTotalOutstandingBalance(int $userId): float
    {
        return StudentPaymentTerm::whereHas('assessment', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('status', '!=', StudentPaymentTerm::STATUS_PAID)
            ->sum('balance');
    }

    /**
     * Get unpaid payment terms for an assessment
     */
    public static function getUnpaidTerms($assessmentId)
    {
        return StudentPaymentTerm::where('student_assessment_id', $assessmentId)
            ->where('status', '!=', StudentPaymentTerm::STATUS_PAID)
            ->orderBy('term_order')
            ->get();
    }

    /**
     * Get payment term status summary for a student
     * Query through StudentAssessment for consistent access patterns
     */
    public static function getPaymentTermsSummary(int $userId): array
    {
        $terms = StudentPaymentTerm::whereHas('assessment', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->get();

        return [
            'total_terms' => $terms->count(),
            'paid_terms' => $terms->where('status', StudentPaymentTerm::STATUS_PAID)->count(),
            'partial_terms' => $terms->where('status', StudentPaymentTerm::STATUS_PARTIAL)->count(),
            'pending_terms' => $terms->where('status', StudentPaymentTerm::STATUS_PENDING)->count(),
            'overdue_terms' => $terms->filter(fn($t) => $t->isOverdue())->count(),
            'total_outstanding' => self::getTotalOutstandingBalance($userId),
        ];
    }
}
