<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->onDelete('cascade');
            $table->string('step_name');
            $table->foreignId('approver_id')->constrained('users');
            $table->string('status'); // 'pending', 'approved', 'rejected'
            $table->text('comments')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['workflow_instance_id', 'step_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_approvals');
    }
};