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
            // Drop the composite index first
            $table->dropIndex(['user_id', 'student_assessment_id']);
            // Drop the foreign key constraint
            $table->dropForeign(['user_id']);
            // Drop the redundant column
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->after('student_assessment_id')
                ->constrained('users')
                ->onDelete('cascade');
        });
    }
};
