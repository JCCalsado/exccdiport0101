@component('mail::message')
# Enrollment Status Update

Hello {{ $studentName }},

Your enrollment workflow status has been updated.

## Enrollment Information
- **Status:** {{ $workflowStatus }}
- **School Year:** {{ $schoolYear }}
@if($semester)
- **Semester:** {{ $semester }}
@endif

## Status Message
{{ $statusMessage }}

@if($nextStepDescription)
## What's Next?
{{ $nextStepDescription }}
@endif

@component('mail::button', ['url' => $actionUrl])
Review Your Enrollment
@endcomponent

Please log into your account for complete details regarding your enrollment status and any required actions.

If you have questions about your enrollment status or need assistance, please contact the academic office or student services.

---

**CCDI Account Portal**
Computer Communication Development Institute
@endcomponent
