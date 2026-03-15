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
                'date_of_birth', // Consolidate with users.birthday
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Restore columns in reverse order
            $table->string('address')->nullable()->after('email');
            $table->string('phone')->nullable()->after('birthday');
            $table->date('birthday')->nullable()->after('year_level');
            $table->string('year_level')->after('course');
            $table->string('course')->after('email');
            $table->string('email')->unique()->after('middle_initial');
            $table->string('middle_initial')->nullable()->after('first_name');
            $table->string('first_name')->after('last_name');
            $table->string('last_name')->after('student_number');
            $table->date('date_of_birth')->nullable()->after('address');
        });
    }
};
