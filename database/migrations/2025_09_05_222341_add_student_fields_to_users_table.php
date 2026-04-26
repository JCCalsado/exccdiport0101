<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id')->nullable()->unique()->after('id');
            $table->string('address')->nullable()->after('email');
            $table->string('profile_picture')->nullable()->after('address');
            $table->date('birthday')->nullable()->after('profile_picture');
            $table->string('phone')->nullable()->after('birthday');
            $table->string('course')->nullable()->after('phone');
            $table->string('year_level')->nullable()->after('course');
            $table->enum('status', ['active','graduated','dropped'])->default('active')->after('year_level');

            // Add indexes
            $table->index('course');
            $table->index('year_level');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes FIRST (before columns, since indexes depend on columns)
            // Check if columns exist before dropping indexes
            if (Schema::hasColumn('users', 'course')) {
                $table->dropIndex('users_course_index');
            }
            if (Schema::hasColumn('users', 'year_level')) {
                $table->dropIndex('users_year_level_index');
            }
            if (Schema::hasColumn('users', 'status')) {
                $table->dropIndex('users_status_index');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // Drop only columns that exist (defensive against later migrations that may have renamed/removed them)
            $columnsToDropIfExist = ['student_id','address','profile_picture','birthday','phone','course','year_level','status'];
            foreach ($columnsToDropIfExist as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};