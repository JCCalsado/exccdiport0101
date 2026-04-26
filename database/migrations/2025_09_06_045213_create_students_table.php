<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('student_id')->unique(); // CCDI Student ID
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_initial')->nullable();
            $table->string('email')->unique();
            $table->string('course');
            $table->string('year_level');
            $table->date('birthday')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_balance', 10, 2)->default(0); // Total amount owed
            $table->enum('status', ['enrolled', 'graduated', 'inactive'])->default('enrolled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};