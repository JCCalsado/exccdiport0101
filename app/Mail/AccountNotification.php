<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $studentName,
        public string $notificationTitle,
        public string $notificationMessage,
        public string $notificationType = 'info', // info, warning, error, success
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificationTitle . ' - CCDI Account Portal',
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.account-notification',
            with: [
                'studentName' => $this->studentName,
                'title' => $this->notificationTitle,
                'message' => $this->notificationMessage,
                'type' => $this->notificationType,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'dashboardUrl' => route('student.dashboard'),
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
