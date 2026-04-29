<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $studentName,
        public string  $notificationTitle,
        public string  $notificationMessage,
        public string  $notificationType = 'info',
        public ?string $actionUrl        = null,
        public ?string $actionLabel      = null,
        public ?string $dueDate          = null,
        public ?string $startDate        = null,
        public ?string $endDate          = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificationTitle . ' - CCDI Account Portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.account-notification',
            with: [
                'studentName'  => $this->studentName,
                'notifTitle'   => $this->notificationTitle,
                'notifBody'    => $this->notificationMessage,
                'notifType'    => $this->notificationType,
                'actionUrl'    => $this->actionUrl,
                'actionLabel'  => $this->actionLabel,
                'dueDate'      => $this->dueDate,
                'startDate'    => $this->startDate,
                'endDate'      => $this->endDate,
                'dashboardUrl' => route('student.dashboard'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}