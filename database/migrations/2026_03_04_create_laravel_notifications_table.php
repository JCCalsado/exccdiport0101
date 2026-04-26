<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * The project has two different notification systems:
     * 1. Custom "notifications" table for admin announcements (title, message, etc.)
     * 2. Laravel's notification system for database notifications (ApprovalRequired, etc.)
     * 
     * Since the custom table already exists and is being used for announcements,
     * we need to update it to support Laravel's notification format by adding
     * the necessary columns.
     */
    public function up(): void
    {
        // Add Laravel notification columns to the existing notifications table
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Check if columns don't already exist before adding
                if (!Schema::hasColumn('notifications', 'type')) {
                    $table->string('type')->nullable();
                }
                if (!Schema::hasColumn('notifications', 'data')) {
                    $table->json('data')->nullable();
                }
                if (!Schema::hasColumn('notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable();
                }
                if (!Schema::hasColumn('notifications', 'notifiable_id')) {
                    $table->unsignedBigInteger('notifiable_id')->nullable();
                }
                if (!Schema::hasColumn('notifications', 'notifiable_type')) {
                    $table->string('notifiable_type')->nullable();
                }
                if (!Schema::hasColumn('notifications', 'uuid')) {
                    $table->uuid('uuid')->nullable()->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Remove only the Laravel notification columns that were added.
     * Do NOT drop the entire table — it contains admin announcements data.
     */
    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Drop only the columns that were added in up()
                $columnsToDropIfExist = ['type', 'data', 'read_at', 'notifiable_id', 'notifiable_type', 'uuid'];
                
                foreach ($columnsToDropIfExist as $column) {
                    if (Schema::hasColumn('notifications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
