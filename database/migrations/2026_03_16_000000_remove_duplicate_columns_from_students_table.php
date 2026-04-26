<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove duplicate columns from students table.
     * All personal data (first_name, last_name, email, etc.) should be read from users table.
     * Students table now contains ONLY student-specific fields:
     * - user_id (FK)
     * - student_id (CCDI ID)
     * - student_number (workflow identifier)
     * - total_balance
     * - enrollment_status
     * - enrollment_date
     * - metadata (json)
     * - SoftDeletes (deleted_at)
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop unique constraint on email first (required for SQLite compatibility)
            // In SQLite, you cannot drop a column that's referenced by a unique index
            $table->dropUnique(['email']);
        });

        Schema::table('students', function (Blueprint $table) {
            // Drop duplicate columns that exist in users table
            $table->dropColumn([
                'last_name',
                'first_name',
                'middle_initial',
                'email',
                'course',
                'year_level',
                'birthday',
                'phone',
                'address',
                'date_of_birth',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This is a one-way migration. Restoring duplicate columns is not recommended.
     * If you need to rollback, manually restore the students table schema or use fresh database.
     */
    public function down(): void
    {
        // This migration removes duplicate columns that should never be in students table.
        // Rolling back would reintroduce schema duplication, which is not supported.
        // Leave empty to prevent issues with existing data.
    }
};
