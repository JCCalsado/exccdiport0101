<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Any active admin can view the user list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Active admins can view any staff profile.
     * Users can always view their own profile.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }

        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Admin can create Accounting staff only.
     * Creating additional Admin accounts is forbidden.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Admin can update Accounting department users only.
     * Admin-to-Admin editing is blocked in the controller layer.
     * Users may edit their own profile.
     */
    public function update(User $user, User $model): bool
    {
        // Self-edit always allowed
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }

        // Admin may only edit Accounting department staff, not other Admins
        if ($user->isAdmin() && $user->is_active && $model->department === 'Accounting') {
            return true;
        }

        return false;
    }

    /**
     * Hard delete is never allowed — deactivate instead.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Admin can activate/deactivate Accounting users only.
     * Cannot deactivate other Admins or themselves.
     */
    public function manageAdmins(User $user, User $model): bool
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return false;
        }

        // Cannot deactivate yourself
        if ($user->id === $model->id) {
            return false;
        }

        // Can only manage Accounting department users
        return $model->department === 'Accounting';
    }

    public function acceptTerms(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function isAdmin(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }
}