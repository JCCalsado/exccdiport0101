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
            // Admin-specific fields
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }
            if (!Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'permissions')) {
                $table->json('permissions')->nullable()->after('terms_accepted_at');
            }
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable()->after('permissions');
            }
            // admin_type is deprecated and will be dropped in a later migration

            // Audit fields
            if (!Schema::hasColumn('users', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('department');
            }
            if (!Schema::hasColumn('users', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('updated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Safely drop columns (FK constraints will be handled automatically)
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->dropColumn('terms_accepted_at');
            }
            if (Schema::hasColumn('users', 'permissions')) {
                $table->dropColumn('permissions');
            }
            if (Schema::hasColumn('users', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('users', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
        });
    }
};
