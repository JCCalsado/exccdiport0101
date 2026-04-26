<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // 'general' = standard announcement
            // 'payment_due' = payment reminder shown prominently on Account Overview
            // 'payment_approved' = auto-created by workflow on approval
            // 'payment_rejected' = auto-created by workflow on rejection
            $table->string('type')->default('general')->after('target_role');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
};
