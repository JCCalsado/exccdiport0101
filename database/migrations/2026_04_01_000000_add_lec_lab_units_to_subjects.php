<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add separate lec_units and lab_units columns to subjects table.
     * 
     * Migration strategy:
     * 1. Add new columns with defaults
     * 2. Populate lec_units from existing units column
     * 3. Populate lab_units based on has_lab flag (if has_lab=true, assume 1 lab unit)
     * 4. Keep units column for backward compatibility (will be deprecated)
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->integer('lec_units')->default(0)->after('units');
            $table->integer('lab_units')->default(0)->after('lec_units');
        });

        // Migrate existing data:
        // - Copy units → lec_units
        // - If has_lab=true, set lab_units=1; else lab_units=0
        DB::update(
            'UPDATE subjects SET lec_units = units, lab_units = CASE WHEN has_lab = true THEN 1 ELSE 0 END'
        );
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['lec_units', 'lab_units']);
        });
    }
};
