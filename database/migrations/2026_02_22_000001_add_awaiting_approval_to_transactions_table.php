<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'awaiting_approval' to the status enum in transactions table
        Schema::table('transactions', function (Blueprint $table) {
            // For MySQL, we need to change the enum to include the new value
            // SQLite doesn't support MODIFY, but the status column is already text
            // so it can store any of the enum values
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','paid','failed','cancelled','awaiting_approval') DEFAULT 'pending'");
            }
            // SQLite stores the status as TEXT, so no change needed
        });
    }

    public function down(): void
    {
        // Remove 'awaiting_approval' from the status enum
        Schema::table('transactions', function (Blueprint $table) {
            // For MySQL, revert the enum
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','paid','failed','cancelled') DEFAULT 'pending'");
            }
            // SQLite stores the status as TEXT, so no change needed
        });
    }
};
