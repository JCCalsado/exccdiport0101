<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a composite index on student_enrollments for fast enrollment lookup.
 *
 * The query pattern in StudentEnrollment::enrolledSubjectIds() and the
 * enrollmentsMap builder in StudentFeeController::create() both filter by:
 *
 *   WHERE user_id = ? AND school_year = ? AND semester = ? AND status = 'enrolled'
 *
 * Without this index, MySQL performs a full scan of the table per student.
 * With the index, it resolves to a single index range scan — negligible cost
 * even with thousands of enrollment rows.
 *
 * Index name is shortened to stay within MySQL's 64-character identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            // Composite index: user_id → school_year → semester → status
            // Column order matches the WHERE clause filter selectivity:
            //   user_id   (high selectivity — one student)
            //   school_year + semester (narrow to one term)
            //   status    (used as a final filter for 'enrolled' only)
            $table->index(
                ['user_id', 'school_year', 'semester', 'status'],
                'se_user_year_sem_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropIndex('se_user_year_sem_status_idx');
        });
    }
};