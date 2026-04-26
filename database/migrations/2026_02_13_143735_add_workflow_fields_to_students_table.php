<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add workflow-related columns if they don't exist
            
            // Student number (unique identifier for workflows)
            if (!Schema::hasColumn('students', 'student_number')) {
                $table->string('student_number')->nullable()->unique()->after('student_id');
            }
            
            // Enrollment status for workflow tracking
            if (!Schema::hasColumn('students', 'enrollment_status')) {
                $table->string('enrollment_status')
                      ->default('pending')
                      ->after('year_level')
                      ->comment('pending, active, suspended, graduated');
            }
            
            // Enrollment date
            if (!Schema::hasColumn('students', 'enrollment_date')) {
                $table->date('enrollment_date')->nullable()->after('enrollment_status');
            }
            
            // Alternative date of birth field (if you want to use this instead of birthday)
            if (!Schema::hasColumn('students', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('birthday');
            }
            
            // Metadata for storing extra workflow-related data as JSON
            if (!Schema::hasColumn('students', 'metadata')) {
                $table->json('metadata')->nullable()->after('total_balance');
            }
            
            // Soft deletes for archiving students
            if (!Schema::hasColumn('students', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'student_number',
                'enrollment_status',
                'enrollment_date',
                'date_of_birth',
                'metadata',
                'deleted_at',
            ]);
        });
    }
};