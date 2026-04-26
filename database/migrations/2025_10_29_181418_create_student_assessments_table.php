<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('assessment_number')->unique(); // e.g., ASS-2025-0001
            $table->string('year_level');
            $table->string('semester');
            $table->string('school_year');
            $table->decimal('tuition_fee', 12, 2)->default(0);
            $table->decimal('other_fees', 12, 2)->default(0);
            $table->decimal('total_assessment', 12, 2)->default(0);
            $table->json('subjects')->nullable(); // Store enrolled subjects
            $table->json('fee_breakdown')->nullable(); // Store fee breakdown
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['user_id', 'school_year', 'semester']);
            $table->index('assessment_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_assessments');
    }
};