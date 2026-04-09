<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('paymongo_source_id')->nullable()->after('reference_number');
            $table->string('paymongo_payment_id')->nullable()->after('paymongo_source_id');
            $table->string('proof_of_payment')->nullable()->after('paymongo_payment_id');
            $table->text('notes')->nullable()->after('proof_of_payment');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'paymongo_source_id',
                'paymongo_payment_id',
                'proof_of_payment',
                'notes',
            ]);
        });
    }
};