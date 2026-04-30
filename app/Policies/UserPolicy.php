<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Any active admin can view the user list (view-only).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Active admins can view other users. Users can always view their own profile.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }

        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Admin cannot create new users.
     * User creation is not permitted through the admin panel anymore.
     * (Registration flow handles new users separately.)
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Users can update their own profile settings.
     * Admin can no longer update other users' records.
     */
    public function update(User $user, User $model): bool
    {
        // A user may always edit their own profile
        return $user->id === $model->id && $user->is_active;
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
     * Admin can no longer activate/deactivate accounts.
     * This action has been removed from the admin role.
     */
    public function manageAdmins(User $user, User $model): bool
    {
        return false;
    }

    public function acceptTerms(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Check if user is an active admin.
     */
    public function isAdmin(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }
}