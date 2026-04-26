<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REFACTOR: Remove subject-based assessment, replace with manual unit entry.
 *
 * Instead of selecting subjects from a pre-loaded curriculum database,
 * the accounting staff will now manually enter:
 *   - lec_units  → number of lecture units (from the matriculation form)
 *   - lab_units  → number of lab units (from the matriculation form)
 *   - lab_subjects → number of lab subjects (for lab fee × per-subject charge)
 *
 * Fee computation formula (AY 2025-2026):
 *   Tuition     = lec_units × ₱364.00
 *   Lab Fee     = lab_subjects × ₱1,656.00
 *   Misc Fee    = ₱6,956.00 (fixed per semester)
 *   ─────────────────────────────────────────
 *   Total       = tuition + lab_fee + misc_fee
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            // Add the three manual-entry columns after 'semester'
            $table->unsignedTinyInteger('lec_units')->default(0)->after('semester')
                ->comment('Lecture units enrolled — entered manually by accounting staff');

            $table->unsignedTinyInteger('lab_units')->default(0)->after('lec_units')
                ->comment('Lab units enrolled — informational only, not used in fee formula');

            $table->unsignedTinyInteger('lab_subjects')->default(0)->after('lab_units')
                ->comment('Number of subjects with laboratory — each adds ₱1,656 to total');
        });

        // Drop the pivot table that linked assessments → subjects (if it exists)
        // Replace 'student_assessment_subjects' with your actual pivot table name if different
        if (Schema::hasTable('student_assessment_subjects')) {
            Schema::dropIfExists('student_assessment_subjects');
        }
    }

    public function down(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            $table->dropColumn(['lec_units', 'lab_units', 'lab_subjects']);
        });

        // NOTE: We do NOT recreate student_assessment_subjects on rollback
        // because re-seeding the curriculum is outside scope of this migration.
    }
};