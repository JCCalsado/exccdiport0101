<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Allows filtering Payment History per assessment (term-scoped PDF export)
            $table->unsignedBigInteger('student_assessment_id')->nullable()->after('student_id');
            $table->index('student_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['student_assessment_id']);
            $table->dropColumn('student_assessment_id');
        });
    }
};