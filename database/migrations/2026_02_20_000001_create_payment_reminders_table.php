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
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_payment_term_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Reminder details
            $table->string('type')->default('payment_due'); // payment_due, approaching_due, overdue, partial_payment
            $table->text('message');
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            
            // Status tracking
            $table->string('status')->default('sent'); // sent, read, dismissed
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            
            // Delivery methods
            $table->boolean('in_app_sent')->default(true);
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            
            // Reminder scheduling
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('trigger_reason')->default('admin_update'); // admin_update, scheduled_job, due_date_change, threshold_reached
            
            // Audit trail
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable(); // Additional context
            
            $table->timestamps();
            
            // Indexes for queries
            $table->index(['user_id', 'status']);
            $table->index(['student_assessment_id', 'status']);
            $table->index(['type', 'created_at']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
