<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            // Make due_date nullable to support payment terms without set due dates
            $table->date('due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            // Set any null values to a default before making not nullable
            DB::table('student_payment_terms')->whereNull('due_date')->delete();
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
