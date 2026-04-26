<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_assessment_id')->constrained('student_assessments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('term_name'); // Upon Registration, Prelim, Midterm, Semi-Final, Final
            $table->integer('term_order'); // 1-5
            $table->decimal('percentage', 5, 2); // Percentage of total assessment
            $table->decimal('amount', 12, 2); // Original amount for this term
            $table->decimal('balance', 12, 2)->default(0); // Current unpaid balance (includes carryover)
            $table->date('due_date'); // When payment is due
            $table->string('status')->default('pending'); // pending, partial, paid, overdue
            $table->text('remarks')->nullable(); // Carryover information
            $table->dateTime('paid_date')->nullable(); // When payment was made
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['user_id', 'student_assessment_id']);
            $table->index(['term_order', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_payment_terms');
    }
};
