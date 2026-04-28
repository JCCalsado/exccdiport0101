<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address_house_no', 100)->nullable()->after('address');
            $table->string('address_street', 255)->nullable()->after('address_house_no');
            $table->string('address_barangay', 255)->nullable()->after('address_street');
            $table->string('address_municipality', 255)->nullable()->after('address_barangay');
            $table->string('address_province', 100)->nullable()->default('Sorsogon')->after('address_municipality');
        });

        // Migrate existing free-text address into address_street so no data is lost.
        // The old `address` column is kept nullable and will be dropped in down().
        DB::statement("
            UPDATE users
            SET address_street = address
            WHERE address IS NOT NULL
              AND address != ''
              AND address_street IS NULL
        ");

        // Drop the old single address column now that data is preserved.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone');
        });

        // Restore address column from street for rollback safety.
        DB::statement("
            UPDATE users
            SET address = address_street
            WHERE address_street IS NOT NULL
        ");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'address_house_no',
                'address_street',
                'address_barangay',
                'address_municipality',
                'address_province',
            ]);
        });
    }
};