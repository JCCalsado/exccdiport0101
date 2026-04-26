<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                  ->constrained('students')
                  ->onDelete('cascade');

            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('from_status', 30);
            $table->string('to_status', 30);

            // Optional reason/remarks supplied by the admin
            $table->text('reason')->nullable();

            // Which action triggered this change (e.g. reinstate, drop, manual_edit)
            $table->string('action', 50)->default('manual');

            $table->timestamps();

            // Indexes for quick lookups on a student's audit trail
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_status_logs');
    }
};