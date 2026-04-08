@component('mail::message')
@if($type === 'success')
# ✓ Account Update
@elseif($type === 'warning')
# ⚠ Important Notice
@elseif($type === 'error')
# ✗ Account Alert
@else
# Account Notification
@endif

Hello {{ $studentName }},

{{ $title }}

---

{{ $message }}

---

@if($actionUrl && $actionLabel)
@component('mail::button', ['url' => $actionUrl])
{{ $actionLabel }}
@endcomponent
@elseif($actionUrl)
@component('mail::button', ['url' => $actionUrl])
View Details
@endcomponent
@endif

If you have any questions or need further assistance, please log into your account at the CCDI Account Portal.

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'secondary'])
Go to Dashboard
@endcomponent

---

**CCDI Account Portal**
Computer Communication Development Institute
@endcomponent
