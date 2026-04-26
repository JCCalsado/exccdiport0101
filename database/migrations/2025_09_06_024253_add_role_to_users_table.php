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
        Schema::table('users', function (Blueprint $table) {
            // Add the 'role' column using string values directly
            $table->enum('role', ['admin', 'accounting', 'student'])
                  ->default('student') // Set default role to 'student'
                  ->after('email'); // Place the column after the 'email' column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the column if the migration is rolled back
            $table->dropColumn('role');
        });
    }
};