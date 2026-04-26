<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise student_payment_terms.status from 'unpaid' → 'pending'.
 *
 * AssessmentService::buildPaymentTerms() created all terms with status='unpaid'
 * but the PaymentStatus enum and all query code uses 'pending' as the initial
 * unpaid status. This caused every balance query to return ₱0 for fresh
 * assessments because 'unpaid' was not in PaymentStatus::unpaidValues().
 *
 * This is a one-way data fix. The rollback leaves them as 'unpaid' for safety
 * so we can re-run forward if needed, but going back to 'unpaid' is not
 * recommended.
 */
return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('student_payment_terms')
            ->where('status', 'unpaid')
            ->update(['status' => 'pending']);

        \Illuminate\Support\Facades\Log::info(
            "Migration: normalised {$affected} student_payment_terms rows from 'unpaid' to 'pending'."
        );
    }

    public function down(): void
    {
        // Intentionally left as no-op. Reverting to 'unpaid' would re-break
        // all balance queries. If you need to rollback, do it manually.
    }
};