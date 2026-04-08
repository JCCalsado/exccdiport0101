# Email Templates Guide - CCDI Account Portal

## Overview

The CCDI Account Portal includes four Laravel Mailable classes for sending notification emails to students. Each Mailable has a corresponding Blade template view.

### Created Templates

1. **PaymentConfirmation** - Sent after successful payment
2. **PaymentDueReminder** - Sent to notify students of upcoming payment due dates
3. **AccountNotification** - Sent for general account notifications and alerts
4. **EnrollmentWorkflowUpdate** - Sent for enrollment status changes

---

## Using Laravel Mailables

### 1. Payment Confirmation

```php
use App\Mail\PaymentConfirmation;
use Illuminate\Support\Facades\Mail;

$transaction = Transaction::find($id);

Mail::to($student->email)->send(new PaymentConfirmation(
    transaction: $transaction,
    amount: 5000.00,
    paymentMethod: 'GCash',
    referenceNumber: 'REF-123456',
    studentName: $student->first_name . ' ' . $student->last_name,
));
```

### 2. Payment Due Reminder

```php
use App\Mail\PaymentDueReminder;
use Illuminate\Support\Facades\Mail;

$paymentTerm = StudentPaymentTerm::find($id);

Mail::to($student->email)->send(new PaymentDueReminder(
    paymentTerm: $paymentTerm,
    studentName: $student->name,
    email: $student->email,
    dueAmount: 2500.00,
    dueDate: $paymentTerm->due_date?->format('F d, Y'),
));
```

### 3. Account Notification

```php
use App\Mail\AccountNotification;
use Illuminate\Support\Facades\Mail;

Mail::to($student->email)->send(new AccountNotification(
    studentName: $student->name,
    notificationTitle: 'Account Status Update',
    notificationMessage: 'Your enrollment has been approved for the current semester.',
    notificationType: 'success',
    actionUrl: route('student.account'),
    actionLabel: 'View Account',
));
```

### 4. Enrollment Workflow Update

```php
use App\Mail\EnrollmentWorkflowUpdate;
use Illuminate\Support\Facades\Mail;

Mail::to($student->email)->send(new EnrollmentWorkflowUpdate(
    studentName: $student->name,
    workflowStatus: 'Approved',
    statusMessage: 'Your enrollment application has been reviewed and approved.',
    schoolYear: '2025-2026',
    semester: '1st Semester',
    nextStepDescription: 'You may now proceed to select your courses.',
    actionUrl: route('student.account'),
));
```

---

## Integration with Resend (Optional)

If using **Resend** instead of direct Laravel mail drivers:

### Setup in `.env`

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="CCDI Account Portal"
RESEND_KEY=re_xxxxxxxxxxxxx
```

### Option A: Use Laravel Mailables with Resend Gateway

The Mailables above work with Resend automatically when configured as the mail driver. No additional changes needed.

### Option B: Use Resend's Template ID System

For better tracking and A/B testing in Resend dashboard:

1. **Create templates in Resend dashboard** (https://resend.com/templates)
   - Payment Confirmation
   - Payment Due Reminder
   - Account Notification
   - Enrollment Workflow Update

2. **Store template IDs** in `.env`:

```env
RESEND_TEMPLATE_PAYMENT_CONFIRMATION=xxxx
RESEND_TEMPLATE_PAYMENT_DUE_REMINDER=xxxx
RESEND_TEMPLATE_ACCOUNT_NOTIFICATION=xxxx
RESEND_TEMPLATE_ENROLLMENT_WORKFLOW=xxxx
```

3. **Create a service class** to use Resend template IDs:

```php
// app/Services/ResendEmailService.php
use Resend;

class ResendEmailService
{
    public function sendPaymentConfirmation($studentEmail, $data)
    {
        Resend::emails()->send([
            'from' => 'onboarding@resend.dev',
            'to' => $studentEmail,
            'template_id' => config('services.resend.template_ids.payment_confirmation'),
            'variables' => [
                'student_name' => $data['studentName'],
                'amount' => $data['amount'],
                'reference_number' => $data['referenceNumber'],
            ],
        ]);
    }
}
```

---

## Production Checklist

- [ ] Configure mail driver (`.env` - MAIL_MAILER)
- [ ] Set `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`
- [ ] Test email sending in local environment
- [ ] Set up email queue for production (`QUEUE_CONNECTION=database`)
- [ ] Add email templates to `.mailmap` if using custom templates
- [ ] Verify email styling on common email clients
- [ ] Set up Resend webhook for bounce/complaint handling
- [ ] Monitor email delivery rates in Resend dashboard

---

## File Locations

- **Mailables:** `app/Mail/`
- **Views:** `resources/mails/`
- **Config:** `config/mail.php`, `config/services.php`

---

## Notes

- All Mailables queue by default (`ShouldQueue` interface)
- Ensure `QUEUE_CONNECTION` is set (e.g., `database`, `redis`)
- Views use Laravel Mail Markdown components (`@component('mail::...')`)
- All emails include the CCDI footer with branding

