<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StudentPaymentTerm;
use App\Enums\UserRoleEnum;

class StudentPaymentTermPolicy
{
    /**
     * Determine if the user can manage payment terms
     */
    public function managePaymentTerms(User $user): bool
    {
        return $user->role === UserRoleEnum::ADMIN;
    }

    /**
     * Determine if the user can update a payment term
     */
    public function update(User $user, StudentPaymentTerm $paymentTerm): bool
    {
        return $user->role === UserRoleEnum::ADMIN;
    }
}
