<?php

namespace App\Events;

use App\Models\User;
use App\Models\StudentPaymentTerm;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DueAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public StudentPaymentTerm $term,
        public ?int $triggeredBy = null,  // ← carry admin ID safely
    ) {}
}