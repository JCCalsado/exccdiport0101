<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_irregular flag to users table.
 *
 * Regular   (is_irregular = false) → single flat "Tuition Fee" per semester
 * Irregular (is_irregular = true)  → custom subject-by-subject assessment
 *                                    (admin picks subjects from any year/sem)
 *
 * The admin can override this flag per-assessment on the Create Assessment form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_irregular')->default(false)->after('year_level');
            $table->index('is_irregular');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_irregular']);
            $table->dropColumn('is_irregular');
        });
    }
};