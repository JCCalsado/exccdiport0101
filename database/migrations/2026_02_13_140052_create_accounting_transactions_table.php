<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->string('type'); // 'invoice', 'payment', 'refund', 'adjustment'
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status'); // 'draft', 'pending_approval', 'approved', 'paid', 'cancelled'
            
            // Polymorphic relation with shorter index name
            $table->unsignedBigInteger('transactionable_id');
            $table->string('transactionable_type');
            $table->index(['transactionable_type', 'transactionable_id'], 'transaction_morphable_idx');
            
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_transactions');
    }
};