<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes rounding discrepancies in student_payment_terms where
     * the sum of term amounts doesn't equal the total_assessment due to float
     * division rounding. The last term absorbs any remainder so the sum always
     * equals total_assessment exactly.
     */
    public function up(): void
    {
        // Iterate through all student assessments
        $assessments = DB::table('student_assessments')->get();
        
        foreach ($assessments as $assessment) {
            // Get all payment terms for this assessment, ordered by term_order
            $terms = DB::table('student_payment_terms')
                ->where('student_assessment_id', $assessment->id)
                ->orderBy('term_order', 'asc')
                ->get();

            if ($terms->isEmpty()) {
                continue;
            }

            // Calculate the sum of all term amounts
            $currentSum = $terms->sum('amount');
            $expectedTotal = $assessment->total_assessment;

            // If there's a discrepancy, fix the last term
            if (abs($currentSum - $expectedTotal) >= 0.01) {
                $lastTerm = $terms->last();
                // Calculate the correct amount for the last term to absorb rounding remainder
                $sumOfOtherTerms = $currentSum - $lastTerm->amount;
                $correctedLastAmount = $expectedTotal - $sumOfOtherTerms;

                // Update both amount and balance (if no payment made yet, balance = amount)
                // If payment has been made, balance should be recalculated based on actual paid vs amount
                DB::table('student_payment_terms')
                    ->where('id', $lastTerm->id)
                    ->update([
                        'amount' => round($correctedLastAmount, 2),
                        'balance' => $lastTerm->paid_date === null 
                            ? round($correctedLastAmount, 2)
                            : round($correctedLastAmount - ($lastTerm->amount - $lastTerm->balance), 2),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data cannot be reliably reversed - this is a data correction migration
        // If rollback is needed, restore from backup
    }
};
