<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add UNIQUE(student_assessment_id, term_order) to student_payment_terms.
 *
 * WHY THIS MATTERS
 * ----------------
 * Without this constraint, a failed-then-retried assessment creation, a race
 * condition between two concurrent requests, or a bug in createPaymentTerms()
 * can silently insert duplicate term rows for the same assessment.
 * The result is a student being charged twice for the same term — a billing
 * error that is invisible at the application level until a manual DB audit.
 *
 * BEFORE ADDING THE CONSTRAINT
 * ----------------------------
 * The migration detects and reports any existing duplicate rows so the
 * administrator can resolve them before the constraint is enforced.
 * If duplicates exist, this migration will throw an informative exception
 * rather than letting MySQL produce a cryptic 1062 error.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Safety check: abort loudly if duplicates already exist ────────────
        $duplicates = DB::table('student_payment_terms')
            ->select('student_assessment_id', 'term_order', DB::raw('COUNT(*) as cnt'))
            ->groupBy('student_assessment_id', 'term_order')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates
                ->map(fn ($row) => "assessment_id={$row->student_assessment_id} term_order={$row->term_order} ({$row->cnt} rows)")
                ->implode(', ');

            throw new \RuntimeException(
                "Cannot add UNIQUE(student_assessment_id, term_order): duplicate rows exist. " .
                "Resolve these before re-running the migration: {$details}"
            );
        }

        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->unique(
                ['student_assessment_id', 'term_order'],
                'uq_payment_term_assessment_order'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->dropUnique('uq_payment_term_assessment_order');
        });
    }
};