<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a composite index on student_enrollments optimised for the year-wide lookup.
 *
 * BACKGROUND
 * ----------
 * Migration 2026_03_22_072020_add_index_to_student_enrollments_for_lookup.php added:
 *
 *   INDEX se_user_year_sem_status_idx (user_id, school_year, semester, status)
 *
 * That index was designed for the original semester-scoped query in
 * StudentEnrollment::enrolledSubjectIds() which filters by all four columns.
 *
 * A later session (March 22, 2026 — "Flexible Multi-Term / Irregular Scope Fix")
 * changed the PRIMARY call path in both:
 *
 *   - StudentFeeController::store()  → now calls enrolledSubjectIdsForYear()
 *   - StudentFeeController::create() → enrollmentsMap builder queries by (user_id, school_year)
 *
 * Both of these queries omit `semester`, so MySQL stops using the 4-column index
 * after `school_year` and cannot apply the `status` filter from the index.
 *
 * The new index (user_id, school_year, status) matches both year-wide query patterns
 * exactly and covers the `status = 'enrolled'` filter within the index:
 *
 *   enrolledSubjectIdsForYear():
 *     WHERE user_id = ? AND school_year = ? AND status = 'enrolled'
 *
 *   enrollmentsMap builder:
 *     WHERE status = 'enrolled' AND user_id IN (...)
 *     → MySQL uses the user_id prefix for the IN() scan; school_year + status follow.
 *
 * The original 4-column index is retained because enrolledSubjectIds() (semester-scoped)
 * still exists and is used as a fallback / available for future single-term use cases.
 * Having both indexes is fine — MySQL will pick the more selective one per query.
 *
 * INDEX COLUMN ORDER RATIONALE
 * ----------------------------
 * user_id   — highest selectivity (one student); always present in WHERE
 * school_year — narrows to one academic year; present in year-wide queries
 * status    — low cardinality (only 3 values) but eliminates dropped/completed rows;
 *             placing it last lets MySQL use the prefix for range scans
 *
 * After editing, run: php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->index(
                ['user_id', 'school_year', 'status'],
                'se_user_year_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropIndex('se_user_year_status_idx');
        });
    }
};