<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make payments.description nullable.
 *
 * The original migration defined description as NOT NULL (string without default).
 * StudentPaymentService sometimes provides no description (e.g. quick staff payments),
 * which causes a DB-level IntegrityConstraintViolation.
 *
 * We also backfill any existing NULL rows with a safe default so the constraint
 * change does not leave dirty data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill existing rows that may have empty descriptions
        DB::table('payments')
            ->whereNull('description')
            ->orWhere('description', '')
            ->update(['description' => 'Payment']);

        Schema::table('payments', function (Blueprint $table) {
            $table->string('description')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Restore the NOT NULL constraint (backfill nulls first)
        DB::table('payments')
            ->whereNull('description')
            ->update(['description' => 'Payment']);

        Schema::table('payments', function (Blueprint $table) {
            $table->string('description')->nullable(false)->change();
        });
    }
};