<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add user_ids JSON column to admin_notifications.
 *
 * Allows a single notification to target multiple specific students
 * without creating one row per student. Coexists with the existing
 * user_id (single-student) column — both are checked in scopeForUser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            // Stored after user_id. Nullable — null means "not a multi-student notif".
            $table->json('user_ids')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropColumn('user_ids');
        });
    }
};