<?php

namespace App\Mail;

use App\Models\StudentPaymentTerm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentDueReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public StudentPaymentTerm $paymentTerm,
        public string $studentName,
        public string $email,
        public float $dueAmount,
        public ?string $dueDate = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Due Notice - CCDI Account Portal',
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.payment-due-reminder',
            with: [
                'paymentTerm' => $this->paymentTerm,
                'studentName' => $this->studentName,
                'dueAmount' => $this->dueAmount,
                'dueDate' => $this->dueDate,
                'termName' => $this->paymentTerm->term_name ?? 'Payment Term',
                'actionUrl' => route('student.account'),
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
