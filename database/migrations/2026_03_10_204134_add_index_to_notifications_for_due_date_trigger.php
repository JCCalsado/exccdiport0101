<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance index for the forDueDateTrigger scope.
 * The scope does a correlated subquery on student_payment_terms;
 * this index makes it fast even with thousands of terms.
 *
 * NOTE: hasIndex() uses Schema::getIndexes() which is driver-agnostic
 * and works with both MySQL (production) and SQLite (testing).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index for the trigger_days_before_due scope on notifications
        if (Schema::hasTable('notifications') && ! $this->hasIndex('notifications', 'notifications_trigger_active_idx')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['is_active', 'is_complete', 'trigger_days_before_due'], 'notifications_trigger_active_idx');
            });
        }

        // Index on student_payment_terms for the correlated subquery
        if (Schema::hasTable('student_payment_terms') && ! $this->hasIndex('student_payment_terms', 'spt_balance_due_idx')) {
            Schema::table('student_payment_terms', function (Blueprint $table) {
                $table->index(['student_assessment_id', 'balance', 'due_date'], 'spt_balance_due_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndexIfExists('notifications_trigger_active_idx');
            });
        }

        if (Schema::hasTable('student_payment_terms')) {
            Schema::table('student_payment_terms', function (Blueprint $table) {
                $table->dropIndexIfExists('spt_balance_due_idx');
            });
        }
    }

    /**
     * Driver-agnostic index existence check.
     * Works with MySQL, SQLite, PostgreSQL, and SQL Server.
     * Replaces the previous MySQL-only `SHOW INDEX FROM` raw query.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};