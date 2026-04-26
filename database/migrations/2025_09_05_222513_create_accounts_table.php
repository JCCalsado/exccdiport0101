<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('balance', 12, 2)->default(0.00); // positive = owes? (define in docs)
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounts');
    }
};
