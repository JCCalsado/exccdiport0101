@component('mail::message')
# Payment Confirmation

Hello {{ $studentName }},

Thank you for your payment! Your payment has been successfully processed.

## Payment Details
- **Amount Paid:** ₱{{ number_format($amount, 2) }}
- **Payment Method:** {{ $paymentMethod }}
- **Reference Number:** {{ $referenceNumber }}
- **Date:** {{ now()->format('F d, Y') }}

Your account has been updated and the balance has been adjusted accordingly.

@component('mail::button', ['url' => $actionUrl])
View Your Account
@endcomponent

If you have any questions regarding this payment, please contact the accounting office.

---

**CCDI Account Portal**
Computer Communication Development Institute
@endcomponent
