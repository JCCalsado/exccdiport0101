<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Transaction $transaction,
        public float $amount,
        public string $paymentMethod,
        public string $referenceNumber,
        public ?string $studentName = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - CCDI Account Portal',
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.payment-confirmation',
            with: [
                'transaction' => $this->transaction,
                'amount' => $this->amount,
                'paymentMethod' => $this->paymentMethod,
                'referenceNumber' => $this->referenceNumber,
                'studentName' => $this->studentName,
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
