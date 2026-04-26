<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fees');
        
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., "TUITION-2025-1ST"
            $table->string('name'); // e.g., "Tuition Fee"
            $table->string('category'); // academic, miscellaneous, laboratory, etc.
            $table->decimal('amount', 12, 2);
            $table->string('year_level'); // 1st Year, 2nd Year, etc.
            $table->string('semester'); // 1st Sem, 2nd Sem, Summer
            $table->string('school_year'); // e.g., "2025-2026"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['year_level', 'semester', 'school_year']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};