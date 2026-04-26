<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., "CS101"
            $table->string('name'); // e.g., "Introduction to Programming"
            $table->integer('units'); // e.g., 3
            $table->decimal('price_per_unit', 12, 2)->default(0); // Price per unit
            $table->string('year_level'); // 1st Year, 2nd Year, etc.
            $table->string('semester'); // 1st Sem, 2nd Sem, Summer
            $table->string('course'); // BS Computer Science, BS IT, etc.
            $table->text('description')->nullable();
            $table->boolean('has_lab')->default(false);
            $table->decimal('lab_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['year_level', 'semester', 'course']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};