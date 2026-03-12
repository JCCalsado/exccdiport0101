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
     * Determine if the user can view a specific notification.
     *
     * Bug 9 fix — user_id ownership check missing:
     *   Previously the policy only checked target_role, so any student could
     *   load /notifications/{id} for a notification that was privately addressed
     *   to a different student (user_id = other student's ID). The policy
     *   passed because target_role was 'student' and the requesting user was
     *   also a student.
     *
     *   Fixed: when user_id is set on the notification, ONLY that specific
     *   user (or an admin) may view it.
     */
    public function view(User $user, Notification $notification): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Notification is privately addressed to a specific user — only that user may see it
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
     * Only admins can create notifications.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can update notifications.
     */
    public function update(User $user, Notification $notification): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can delete notifications.
     */
    public function delete(User $user, Notification $notification): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can restore notifications.
     */
    public function restore(User $user, Notification $notification): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can force-delete notifications.
     */
    public function forceDelete(User $user, Notification $notification): bool
    {
        return $user->isAdmin();
    }
}