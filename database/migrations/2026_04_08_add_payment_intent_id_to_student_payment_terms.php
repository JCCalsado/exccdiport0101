<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            // Add payment_intent_id to link with PayMongo webhooks
            $table->string('payment_intent_id')->nullable()->unique()->after('balance');
            
            // Index for fast lookup during webhook processing
            $table->index('payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->dropIndex(['payment_intent_id']);
            $table->dropColumn('payment_intent_id');
        });
    }
};
