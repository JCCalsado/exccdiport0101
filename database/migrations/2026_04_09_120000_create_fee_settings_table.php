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
        Schema::create('fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // tuition_per_unit, term_1_pct, misc_lms, etc.
            $table->string('label'); // Display name
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('category'); // 'rate', 'miscellaneous', 'other', 'term'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_settings');
    }
};
