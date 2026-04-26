<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\User;

class FeeManagementPolicy
{
    /**
     * Only admins can manage system fees
     */
    public function manageSystemFees(User $user): bool
    {
        return $user->role === UserRoleEnum::ADMIN;
    }
}
