<?php

namespace Database\Seeders\Traits;

use App\Models\User;

/**
 * GetAdminUserTrait
 *
 * Provides safe admin/accounting user lookup for seeders.
 * Eliminates hardcoded user IDs (like `created_by => 1`) which break
 * when the actual admin user has a different ID.
 */
trait GetAdminUserTrait
{
    /**
     * Get a valid admin or accounting user ID.
     * 
     * Tries in order:
     * 1. First admin user
     * 2. First accounting user (fallback if no admin)
     * 3. Throws exception if neither exists
     * 
     * @return int User ID of an admin or accounting staff member
     * @throws \Exception If no admin or accounting user found
     */
    protected function getOrFindAdminUserId(): int
    {
        // Try admin first
        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            return $admin->id;
        }

        // Fallback to accounting
        $accounting = User::where('role', 'accounting')->first();
        if ($accounting) {
            return $accounting->id;
        }

        // No valid user found
        throw new \Exception(
            'No admin or accounting user found in database. ' .
            'Ensure ComprehensiveUserSeeder or a similar seeder runs first to create admin/accounting users.'
        );
    }
}
