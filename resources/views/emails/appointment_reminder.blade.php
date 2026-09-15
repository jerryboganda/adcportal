@component('mail::message')
# {{ $r['clinicName'] }} — appointment reminder

Hello **{{ $r['patientName'] }}**,

This is a friendly reminder of your upcoming imaging appointment:

@component('mail::table')
| | |
|-|-|
| **Token** | {{ $r['tokenNumber'] }} |
| **Study** | {{ $r['serviceName'] }} |
| **Date** | {{ $r['date'] }} |
| **Time** | {{ $r['time'] }} |
@endcomponent

@if (! empty($r['roomNumber']))
Your examination room is **{{ $r['roomNumber'] }}**. Please arrive 15 minutes early with any previous imaging discs or reports and your referral letter.

@else
Please arrive 15 minutes early with any previous imaging discs or reports and your referral letter.
@endif

If you need to reschedule, contact us as soon as possible at {{ $r['clinicPhone'] ?? 'our front desk' }}.

Regards,
{{ $r['clinicName'] }}
@endcomponent
