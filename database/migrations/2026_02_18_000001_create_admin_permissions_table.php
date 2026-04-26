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
        // Admin permissions lookup table
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'manage_users', 'manage_fees'
            $table->string('description');
            $table->string('category'); // e.g., 'admin', 'accounting', 'system'
            $table->timestamps();

            $table->index('category');
        });

        // Admin role permissions pivot table
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->enum('admin_type', ['super', 'manager', 'operator']);
            $table->foreignId('permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_type', 'permission_id']);
            $table->index('admin_type');
        });

        // User-specific permission overrides
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->timestamp('granted_at');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
    }
};
