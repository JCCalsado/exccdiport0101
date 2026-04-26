@component('mail::message')
# Payment Due Notice

Hello {{ $studentName }},

This is a reminder that a payment is due for your ongoing enrollment.

## Payment Due Details
- **Term:** {{ $termName }}
- **Amount Due:** ₱{{ number_format($dueAmount, 2) }}
@if($dueDate)
- **Due Date:** {{ $dueDate }}
@endif

Please settle this payment at your earliest convenience to avoid any disruption to your enrollment status.

@component('mail::button', ['url' => $actionUrl])
Make Payment
@endcomponent

## Payment Instructions
You can submit your payment through the CCDI Account Portal by logging into your account and following the payment submission process.

If you have questions or need assistance, please contact the accounting office.

---

**CCDI Account Portal**
Computer Communication Development Institute
@endcomponent
