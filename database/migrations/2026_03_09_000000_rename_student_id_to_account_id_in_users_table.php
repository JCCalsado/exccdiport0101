<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('student_id', 'account_id');
        });

        // Rename the unique index
        Schema::table('users', function (Blueprint $table) {
            $table->unique('account_id')->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('account_id', 'student_id');
        });
    }
};
