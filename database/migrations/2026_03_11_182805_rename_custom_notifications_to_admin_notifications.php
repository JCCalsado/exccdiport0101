<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURAL FIX: Resolve the dual-table collision.
 *
 * Previously `notifications` was used for BOTH:
 *   1. Custom admin announcements (App\Models\Notification)
 *   2. Laravel's built-in database notification channel ($user->notify(...))
 *
 * Laravel's database channel expects a specific schema (UUID primary key,
 * notifiable_type/id, data JSON, read_at). Our custom table uses a bigint
 * auto-increment PK with title/message/target_role columns — fundamentally
 * incompatible.
 *
 * This migration:
 *   - Renames the custom admin announcements table → admin_notifications
 *   - Drops the patch columns added by 2026_03_04_create_laravel_notifications_table
 *     (notifiable_id, notifiable_type, data, read_at, uuid) from admin_notifications
 *     since they don't belong there and were never populated
 *   - Creates a proper `notifications` table for Laravel's database channel
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Rename the custom table ──────────────────────────────────
        Schema::rename('notifications', 'admin_notifications');

        // ── Step 2: Drop the patch columns that were incorrectly added ───────
        //    These columns belong to Laravel's channel, not our custom model.
        Schema::table('admin_notifications', function (Blueprint $table) {
            $columns = Schema::getColumnListing('admin_notifications');

            if (in_array('notifiable_type', $columns)) {
                $table->dropColumn('notifiable_type');
            }
            if (in_array('notifiable_id', $columns)) {
                $table->dropColumn('notifiable_id');
            }
            if (in_array('data', $columns)) {
                $table->dropColumn('data');
            }
            if (in_array('read_at', $columns)) {
                $table->dropColumn('read_at');
            }
            if (in_array('uuid', $columns)) {
                // Drop the uuid index first if it exists
                foreach (Schema::getIndexes('admin_notifications') as $index) {
                    if (str_contains($index['name'], 'uuid')) {
                        $table->dropIndex($index['name']);
                        break;
                    }
                }
                $table->dropColumn('uuid');
            }
        });

        // ── Step 3: Create the proper Laravel database notification table ────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop the proper Laravel notifications table
        Schema::dropIfExists('notifications');

        // Rename admin_notifications back
        if (Schema::hasTable('admin_notifications')) {
            Schema::rename('admin_notifications', 'notifications');
        }
    }
};