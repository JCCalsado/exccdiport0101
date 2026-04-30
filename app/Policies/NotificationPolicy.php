<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Notification;

class NotificationPolicy
{
    /**
     * All authenticated users can list notifications.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user may view a notification they are entitled to see.
     *
     * Admin is view-only — they can read notifications but cannot manage them.
     * Ownership/role scoping still applies (a student cannot view another student's
     * private notification simply because they are authenticated).
     */
    public function view(User $user, Notification $notification): bool
    {
        // Admin: view-only, but only notifications targeted at 'admin' or 'all'
        if ($user->isAdmin()) {
            return in_array($notification->target_role, ['admin', 'all'], true)
                || $notification->user_id === $user->id;
        }

        // Notification privately addressed to a specific user — only that user may see it
        if ($notification->user_id !== null) {
            return $notification->user_id === $user->id;
        }

        // Broadcast notification — must match role or be for everyone
        $roleString = $user->role instanceof \BackedEnum
            ? $user->role->value
            : (string) $user->role;

        return in_array($notification->target_role, [$roleString, 'all'], true);
    }

    /**
     * Only accounting staff can create notifications.
     * Admin is view-only.
     */
    public function create(User $user): bool
    {
        return $user->role->value === 'accounting';
    }

    /**
     * Only accounting staff can update notifications.
     * Admin is view-only.
     */
    public function update(User $user, Notification $notification): bool
    {
        return $user->role->value === 'accounting';
    }

    /**
     * Only accounting staff can delete notifications.
     * Admin is view-only.
     */
    public function delete(User $user, Notification $notification): bool
    {
        return $user->role->value === 'accounting';
    }

    /**
     * Only accounting staff can restore notifications.
     */
    public function restore(User $user, Notification $notification): bool
    {
        return $user->role->value === 'accounting';
    }

    /**
     * Only accounting staff can force-delete notifications.
     */
    public function forceDelete(User $user, Notification $notification): bool
    {
        return $user->role->value === 'accounting';
    }
}