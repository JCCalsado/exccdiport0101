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
        Schema::table('notifications', function (Blueprint $table) {
            // Add support for targeting specific users
            $table->unsignedBigInteger('user_id')->nullable()->after('target_role');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Add activation/deactivation toggle
            $table->boolean('is_active')->default(true)->after('user_id');
            
            // Add completion flag (automatically set when payment is complete)
            $table->boolean('is_complete')->default(false)->after('is_active');
            
            // Track when notification was dismissed by user
            $table->timestamp('dismissed_at')->nullable()->after('is_complete');
            
            // Add index for efficient querying by user and active status
            $table->index(['user_id', 'is_active']);
            $table->index(['target_role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Safely drop FK if exists
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // FK may not exist or was already dropped
                }
                
                // Safely drop indexes if they exist
                try {
                    $table->dropIndex(['user_id', 'is_active']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                
                try {
                    $table->dropIndex(['target_role', 'is_active']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                
                // Drop columns only if they exist
                $columnsToDrop = ['user_id', 'is_active', 'is_complete', 'dismissed_at'];
                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('notifications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
