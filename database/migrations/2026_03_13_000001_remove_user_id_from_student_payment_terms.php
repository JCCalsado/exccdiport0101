<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * StudentPaymentTerm should always be accessed through StudentAssessment.
     * The duplicate user_id creates an inconsistent query pattern and should be removed.
     * Going forward, queries must go: User -> StudentAssessment -> StudentPaymentTerm
     */
    public function up(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            // Drop the foreign key constraint first (required before dropping index)
            $table->dropForeign(['user_id']);
            // Drop the composite index
            $table->dropIndex(['user_id', 'student_assessment_id']);
            // Drop the redundant column
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This is a one-way migration. The user_id column was redundant and should not be restored.
     * Access StudentPaymentTerms through StudentAssessment -> User relationship instead.
     */
    public function down(): void
    {
        // This migration removes a redundant column. Rolling back would reintroduce the column,
        // defeating the purpose of the schema cleanup. Leave empty.
    }
};
