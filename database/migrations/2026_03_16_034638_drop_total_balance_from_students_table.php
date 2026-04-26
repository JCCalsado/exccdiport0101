<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the redundant `total_balance` column from the `students` table.
 *
 * WHY:
 *   Balance was maintained in TWO places:
 *     1. students.total_balance  (written by AccountService::recalculate + Student::update)
 *     2. accounts.balance        (written exclusively by AccountService::recalculate)
 *
 *   Any partial failure, race condition, or code path that skips recalculate()
 *   caused these two values to diverge silently.  The dashboard
 *   (AccountingDashboardController) queried accounts.balance via a JOIN, while
 *   legacy Vue pages read student.total_balance, so two different numbers could
 *   appear side-by-side in the same session.
 *
 * AFTER THIS MIGRATION:
 *   - `accounts.balance` is the single authoritative source.
 *   - Every balance read MUST go through `$user->account->balance`.
 *   - `AccountService::recalculate()` is the only writer.
 *
 * DOWN:
 *   Restores the column with a default of 0 so the rollback is safe.
 *   Run `php artisan db:seed --class=BackfillStudentTotalBalanceSeeder` after
 *   rolling back if you need the historic values restored from accounts.balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('total_balance');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Re-add with default 0; values must be backfilled from accounts.balance
            // if this rollback is applied to a live database.
            $table->decimal('total_balance', 10, 2)->default(0)->after('student_number');
        });
    }
};