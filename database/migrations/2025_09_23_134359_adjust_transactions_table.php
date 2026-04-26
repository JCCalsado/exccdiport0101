<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Rename old `type` enum (charge/payment) → `kind`
            $table->enum('kind', ['charge','payment'])->default('charge')->after('payment_channel');

            // Change `type` to hold academic/business category
            $table->string('type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Rollback: only drop if column exists
            if (Schema::hasColumn('transactions', 'kind')) {
                $table->dropColumn('kind');
            }
            if (Schema::hasColumn('transactions', 'type')) {
                $table->enum('type', ['charge','payment'])->default('charge')->change();
            }
        });
    }
};