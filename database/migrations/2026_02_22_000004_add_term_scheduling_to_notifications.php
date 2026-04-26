<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add term-specific scheduling
            if (!Schema::hasColumn('notifications', 'term_ids')) {
                $table->json('term_ids')->nullable()->after('type')->comment('JSON array of payment term IDs to target');
            }
            
            if (!Schema::hasColumn('notifications', 'target_term_name')) {
                $table->string('target_term_name')->nullable()->after('term_ids')->comment('Target specific term by name (e.g., "Upon Registration", "Prelim")');
            }
            
            if (!Schema::hasColumn('notifications', 'trigger_days_before_due')) {
                $table->integer('trigger_days_before_due')->nullable()->after('target_term_name')->comment('Show notification N days before term due date');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'term_ids')) {
                    $table->dropColumn('term_ids');
                }
                if (Schema::hasColumn('notifications', 'target_term_name')) {
                    $table->dropColumn('target_term_name');
                }
                if (Schema::hasColumn('notifications', 'trigger_days_before_due')) {
                    $table->dropColumn('trigger_days_before_due');
                }
            });
        }
    }
};
