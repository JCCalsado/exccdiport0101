<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User   $user,
        public int    $transactionId,
        public float  $amount,
        public string $reference,
        public ?int   $triggeredBy = null,
    ) {}
}