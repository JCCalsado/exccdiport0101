<?php

namespace App\Events;

use App\Models\PaymentReminder;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReminderGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PaymentReminder $reminder,
        public User $student,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->student->id}");
    }

    public function broadcastAs(): string
    {
        return 'payment.reminder.generated';
    }

    public function broadcastWith(): array
    {
        return [
            'reminder_id' => $this->reminder->id,
            'type' => $this->reminder->type,
            'message' => $this->reminder->message,
            'outstanding_balance' => $this->reminder->outstanding_balance,
            'created_at' => $this->reminder->created_at,
        ];
    }
}
