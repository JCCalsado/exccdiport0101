<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentFeePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any student fees
     */
    public function viewAny(User $user): bool
    {
        // Get the role value properly (handles both string and enum)
        $roleValue = $this->getRoleValue($user);
        return in_array($roleValue, ['admin', 'accounting']);
    }

    /**
     * Determine if the user can view student fee details
     */
    public function view(User $user, User $student): bool
    {
        // Get the role value properly
        $roleValue = $this->getRoleValue($user);
        
        // Admin and accounting can view any student
        if (in_array($roleValue, ['admin', 'accounting'])) {
            return true;
        }

        // Students can only view their own fees
        return $user->id === $student->id;
    }

    /**
     * Determine if the user can create student fees
     */
    public function create(User $user): bool
    {
        $roleValue = $this->getRoleValue($user);
        return in_array($roleValue, ['admin', 'accounting']);
    }

    /**
     * Determine if the user can update student fees
     */
    public function update(User $user, User $student): bool
    {
        $roleValue = $this->getRoleValue($user);
        return in_array($roleValue, ['admin', 'accounting']);
    }

    /**
     * Determine if the user can record payments
     */
    public function recordPayment(User $user): bool
    {
        $roleValue = $this->getRoleValue($user);
        return in_array($roleValue, ['admin', 'accounting']);
    }

    /**
     * Helper method to get role value from User model
     * Handles both string and enum types
     */
    private function getRoleValue(User $user): string
    {
        $role = $user->role;
        
        // If it's an object (enum), get the value
        if (is_object($role)) {
            return $role->value ?? (string) $role;
        }
        
        // If it's already a string, return it
        return (string) $role;
    }
}