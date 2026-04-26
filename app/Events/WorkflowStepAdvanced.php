<?php

namespace App\Events;

use App\Models\WorkflowInstance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowStepAdvanced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkflowInstance $instance,
        public string $previousStep,
        public string $newStep
    ) {
    }
}