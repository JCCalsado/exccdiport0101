<?php
namespace App\Notifications;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
class PaymentDueNotification extends Notification
{
    use Queueable;
    public function __construct(
        private string $termName,
        private float $balance,
        private Carbon $dueDate,
    ) {}
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
    public function toMail(object $notifiable): MailMessage
    {
        $daysUntilDue = (int) now()->diffInDays($this->dueDate, false);
        $daysLabel = $daysUntilDue === 0
            ? 'due today'
            : ($daysUntilDue > 0
                ? "{$daysUntilDue} days remaining"
                : abs($daysUntilDue) . ' days overdue');
        return (new MailMessage)
            ->subject('Payment Reminder - CCDI Portal')
            ->greeting('Good day!')
            ->line('This is a reminder that you have an upcoming payment due.')
            ->line('-----------------------------------')
            ->line('**Term:** ' . $this->termName)
            ->line('**Amount Due:** ₱' . number_format($this->balance, 2))
            ->line('**Due Date:** ' . $this->dueDate->format('F d, Y') . ' (' . $daysLabel . ')')
            ->line('-----------------------------------')
            ->line('Please settle your payment on or before the due date to avoid penalties.')
            ->line('Thank you!')
            ->salutation('- CCDI Payment Portal')
            ->action('Pay Now', route('student.account', ['tab' => 'payment']));
    }
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type'      => 'payment_due',
            'title'     => "{$this->termName} Payment Due",
            'message'   => 'Amount due: ₱' . number_format($this->balance, 2) . ' by ' . $this->dueDate->format('M d, Y'),
            'term_name' => $this->termName,
            'amount'    => $this->balance,
            'due_date'  => $this->dueDate->toDateString(),
            'icon'      => 'alert-circle',
            'color'     => 'warning',
        ]);
    }
}
