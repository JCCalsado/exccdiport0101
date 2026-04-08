<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentWorkflowUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $studentName,
        public string $workflowStatus,
        public string $statusMessage,
        public string $schoolYear,
        public ?string $semester = null,
        public ?string $nextStepDescription = null,
        public ?string $actionUrl = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Enrollment Workflow Update - CCDI Account Portal',
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.enrollment-workflow-update',
            with: [
                'studentName' => $this->studentName,
                'workflowStatus' => $this->workflowStatus,
                'statusMessage' => $this->statusMessage,
                'schoolYear' => $this->schoolYear,
                'semester' => $this->semester,
                'nextStepDescription' => $this->nextStepDescription,
                'actionUrl' => $this->actionUrl ?? route('student.account'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
