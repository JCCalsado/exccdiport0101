<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            // Add carryover tracking columns if they don't exist
            if (!Schema::hasColumn('student_payment_terms', 'carryover_from_term_id')) {
                $table->unsignedBigInteger('carryover_from_term_id')->nullable()->after('status');
                $table->decimal('carryover_amount', 10, 2)->default(0)->after('carryover_from_term_id');
                
                // Add foreign key for carryover
                $table->foreign('carryover_from_term_id')
                    ->references('id')
                    ->on('student_payment_terms')
                    ->nullOnDelete();
            } else {
                // Columns exist, just add carryover_amount if it doesn't
                if (!Schema::hasColumn('student_payment_terms', 'carryover_amount')) {
                    $table->decimal('carryover_amount', 10, 2)->default(0)->after('carryover_from_term_id');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            // Drop foreign key if it exists
            try {
                $table->dropForeign(['carryover_from_term_id']);
            } catch (\Exception $e) {
                // Foreign key doesn't exist, continue
            }

            // Drop columns if they exist
            if (Schema::hasColumn('student_payment_terms', 'carryover_from_term_id')) {
                $table->dropColumn('carryover_from_term_id');
            }
            if (Schema::hasColumn('student_payment_terms', 'carryover_amount')) {
                $table->dropColumn('carryover_amount');
            }
        });
    }
};
