<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case ADMIN = 'admin';
    case ACCOUNTING = 'accounting';
    case STUDENT = 'student';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Admin',
            self::ACCOUNTING => 'Accounting Staff',
            self::STUDENT => 'Student',
        };
    }
}
