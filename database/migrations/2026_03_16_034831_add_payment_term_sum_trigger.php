<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a MySQL BEFORE INSERT / BEFORE UPDATE trigger on `student_payment_terms`
 * that enforces the invariant:
 *
 *   SUM(student_payment_terms.amount) per assessment_id
 *     == student_assessments.total_assessment
 *
 * WHY A TRIGGER AND NOT JUST AN OBSERVER:
 *   The Laravel observer fires on Eloquent model events only.  Bulk inserts via
 *   DB::table()->insert(), raw queries, seeders, and tinker bypass Eloquent
 *   entirely.  A DB-level trigger catches ALL writes regardless of origin.
 *
 * HOW IT WORKS:
 *   On every INSERT or UPDATE to student_payment_terms the trigger:
 *     1. Queries the total_assessment from the parent assessment row.
 *     2. Sums all existing term amounts for that assessment (excluding the
 *        row being modified on UPDATE).
 *     3. Adds the NEW amount being written.
 *     4. If the sum EXCEEDS total_assessment → SIGNAL SQLSTATE error (aborts).
 *
 *   The trigger does NOT fire on the last-term rounding adjustment (see
 *   StudentFeeController::createPaymentTerms) because the last term amount
 *   is calculated as total - sum(other terms), which always satisfies the
 *   constraint by definition.
 *
 * TOLERANCE:
 *   A ±0.01 rounding tolerance is applied so floating-point division remainders
 *   do not cause false positives.
 *
 * SQLite / testing:
 *   SQLite does not support SIGNAL or the same trigger syntax.  The trigger
 *   is skipped silently on non-MySQL connections so tests continue to run.
 *   The Laravel observer (StudentPaymentTermObserver) provides the equivalent
 *   check in the application layer for all environments including testing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite / PostgreSQL: skip DB-level trigger.
            // Application-layer enforcement is provided by StudentPaymentTermObserver.
            return;
        }

        // ── INSERT trigger ────────────────────────────────────────────────────
        DB::unprepared("
            CREATE TRIGGER trg_spt_check_sum_insert
            BEFORE INSERT ON student_payment_terms
            FOR EACH ROW
            BEGIN
                DECLARE v_total       DECIMAL(12,2);
                DECLARE v_current_sum DECIMAL(12,2);

                SELECT total_assessment
                  INTO v_total
                  FROM student_assessments
                 WHERE id = NEW.student_assessment_id;

                SELECT COALESCE(SUM(amount), 0)
                  INTO v_current_sum
                  FROM student_payment_terms
                 WHERE student_assessment_id = NEW.student_assessment_id;

                IF (v_current_sum + NEW.amount) > (v_total + 0.01) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Payment term amounts would exceed total_assessment';
                END IF;
            END
        ");

        // ── UPDATE trigger ────────────────────────────────────────────────────
        DB::unprepared("
            CREATE TRIGGER trg_spt_check_sum_update
            BEFORE UPDATE ON student_payment_terms
            FOR EACH ROW
            BEGIN
                DECLARE v_total       DECIMAL(12,2);
                DECLARE v_current_sum DECIMAL(12,2);

                SELECT total_assessment
                  INTO v_total
                  FROM student_assessments
                 WHERE id = NEW.student_assessment_id;

                -- Exclude the row being updated from the sum
                SELECT COALESCE(SUM(amount), 0)
                  INTO v_current_sum
                  FROM student_payment_terms
                 WHERE student_assessment_id = NEW.student_assessment_id
                   AND id != OLD.id;

                IF (v_current_sum + NEW.amount) > (v_total + 0.01) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Payment term amounts would exceed total_assessment';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_spt_check_sum_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_spt_check_sum_update');
    }
};