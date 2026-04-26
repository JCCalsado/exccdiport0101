<?php

namespace App\Notifications;

use App\Models\WorkflowApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequired extends Notification
{
    use Queueable;

    public function __construct(public WorkflowApproval $approval)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $instance = $this->approval->workflowInstance;
        $workflow = $instance->workflow;

        return (new MailMessage)
            ->subject('Approval Required: ' . $workflow->name)
            ->line('You have a pending approval request.')
            ->line('Workflow: ' . $workflow->name)
            ->line('Step: ' . $this->approval->step_name)
            ->action('Review Approval', url("/approvals/{$this->approval->id}"))
            ->line('Please review and take action on this request.');
    }

    public function toArray($notifiable): array
    {
        return [
            'approval_id' => $this->approval->id,
            'workflow_name' => $this->approval->workflowInstance->workflow->name,
            'step_name' => $this->approval->step_name,
        ];
    }
}