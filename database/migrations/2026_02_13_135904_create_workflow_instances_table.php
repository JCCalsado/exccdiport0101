<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->morphs('workflowable'); // Polymorphic relation
            $table->string('current_step');
            $table->string('status'); // 'pending', 'in_progress', 'completed', 'rejected'
            $table->json('step_history'); // Track step transitions
            $table->json('metadata')->nullable(); // Additional context data
            $table->foreignId('initiated_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};