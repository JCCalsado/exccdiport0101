<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('school_year'); // e.g., "2025-2026"
            $table->string('semester'); // 1st Sem, 2nd Sem, Summer
            $table->enum('status', ['enrolled', 'dropped', 'completed'])->default('enrolled');
            $table->timestamps();
            
            // Prevent duplicate enrollments - with shortened index name
            $table->unique(['user_id', 'subject_id', 'school_year', 'semester'], 'student_enroll_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};