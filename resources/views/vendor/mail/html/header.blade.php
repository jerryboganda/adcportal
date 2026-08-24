@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'ADC - Amad Diagnostic Centre')
<img src="{{ asset('uploads/logo/logo_dark.png') }}" class="logo" alt="ADC - Amad Diagnostic Centre Logo" style="max-height:40px;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
