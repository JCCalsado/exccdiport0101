<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only active admins can view the list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Only active admins can view other users. Users can always view their own.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Only active admins can create new admins.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Users can update their own profile; only active admins update others.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id && $user->is_active) {
            return true;
        }
        return $user->isAdmin() && $user->is_active;
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
        return $user->isAdmin() && $user->is_active;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Only active admins can activate/deactivate admin accounts.
     */
    public function manageAdmins(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function acceptTerms(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Check if user is an active admin (for general authorization).
     */
    public function isAdmin(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }
}