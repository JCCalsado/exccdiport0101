<?php

namespace App\Observers;

use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use Illuminate\Validation\ValidationException;

/**
 * Enforce the invariant:
 *   SUM(student_payment_terms.amount) per assessment == total_assessment
 *
 * This observer is the application-layer counterpart to the MySQL trigger
 * defined in migration 2026_03_17_000002_add_payment_term_sum_trigger.
 *
 * WHY BOTH:
 *   - MySQL trigger  → catches ALL writes (raw SQL, seeders, tinker, external tools)
 *   - This observer  → catches Eloquent writes in ALL environments (SQLite in tests,
 *                       PostgreSQL if the stack ever changes, and provides a readable
 *                       Laravel ValidationException instead of a raw DB SIGNAL error)
 *
 * TOLERANCE:
 *   ±0.02 rounding tolerance avoids false positives from floating-point division
 *   when 5 terms are computed as percentages of an arbitrary total assessment.
 *
 * REGISTRATION:
 *   Register in AppServiceProvider::boot() — see that file.
 */
class StudentPaymentTermObserver
{
    /**
     * Called before a new term is inserted.
     */
    public function creating(StudentPaymentTerm $term): void
    {
        $this->assertTermSumValid($term, isNew: true);
    }

    /**
     * Called before an existing term is updated.
     * Only re-checks when the `amount` column is actually changing.
     */
    public function updating(StudentPaymentTerm $term): void
    {
        if ($term->isDirty('amount')) {
            $this->assertTermSumValid($term, isNew: false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate that the sum of all term amounts for the assessment (including
     * the row being written) does not exceed total_assessment.
     *
     * @param  StudentPaymentTerm  $term   The term model being saved.
     * @param  bool                $isNew  True for creates, false for updates.
     *
     * @throws ValidationException when the sum would exceed total_assessment.
     */
    private function assertTermSumValid(StudentPaymentTerm $term, bool $isNew): void
    {
        $assessment = StudentAssessment::find($term->student_assessment_id);

        if (! $assessment) {
            // Assessment not found — let the FK constraint handle it.
            return;
        }

        $totalAssessment = (float) $assessment->total_assessment;

        // Sum all existing terms, excluding the current row on updates.
        $existingSum = StudentPaymentTerm::where('student_assessment_id', $term->student_assessment_id)
            ->when(! $isNew, fn ($q) => $q->where('id', '!=', $term->id))
            ->sum('amount');

        $projectedSum = round((float) $existingSum + (float) $term->amount, 2);
        $tolerance    = 0.02;

        if ($projectedSum > $totalAssessment + $tolerance) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Term amount of ₱%s would bring the total to ₱%s, exceeding the assessment total of ₱%s. ' .
                    'Reduce this term or adjust the assessment first.',
                    number_format($term->amount, 2),
                    number_format($projectedSum, 2),
                    number_format($totalAssessment, 2)
                ),
            ]);
        }
    }
}