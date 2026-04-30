<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentFeePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any student fees.
     * Admin: view-only. Accounting: full access.
     */
    public function viewAny(User $user): bool
    {
        return in_array($this->getRoleValue($user), ['admin', 'accounting'], true);
    }

    /**
     * Determine if the user can view student fee details.
     * Admin: view-only. Accounting: full access. Students: own record only.
     */
    public function view(User $user, User $student): bool
    {
        $role = $this->getRoleValue($user);

        if (in_array($role, ['admin', 'accounting'], true)) {
            return true;
        }

        return $user->id === $student->id;
    }

    /**
     * Only accounting can create student fee assessments.
     * Admin is view-only.
     */
    public function create(User $user): bool
    {
        return $this->getRoleValue($user) === 'accounting';
    }

    /**
     * Only accounting can update student fee assessments.
     * Admin is view-only.
     */
    public function update(User $user, User $student): bool
    {
        return $this->getRoleValue($user) === 'accounting';
    }

    /**
     * Only accounting can record payments.
     * Admin is view-only.
     */
    public function recordPayment(User $user): bool
    {
        return $this->getRoleValue($user) === 'accounting';
    }

    /**
     * Helper: normalize role to string regardless of enum or string storage.
     */
    private function getRoleValue(User $user): string
    {
        $role = $user->role;

        if (is_object($role)) {
            return $role->value ?? (string) $role;
        }

        return (string) $role;
    }
}