@php
    $brand = $document['branding'];
    $showLogo = ($document['options']['showLogo'] ?? true) && ! empty($brand['logoDataUri']);
    $logo = $brand['logoDataUri'] ?? null;
@endphp

@if($document['paper'] === 'a4')
    <table class="pd-header">
        <tr>
            <td style="width: 62%;">
                @if($showLogo)
                    <img class="pd-logo" src="{{ $logo }}" alt="{{ $brand['name'] }}">
                @endif
                <div class="pd-org">{{ $brand['name'] }}</div>
                @if(($brand['tagline'] ?? '') !== '')
                    <div class="pd-org-tagline">{{ $brand['tagline'] }}</div>
                @endif
                <div class="pd-org-lines">
                    @if(($brand['branch'] ?? '') !== ''){{ $brand['branch'] }}<br>@endif
                    @if(($brand['addressLine'] ?? '') !== ''){{ $brand['addressLine'] }}<br>@endif
                    @if(($brand['contactLine'] ?? '') !== ''){{ $brand['contactLine'] }}<br>@endif
                    @foreach(($brand['registrations'] ?? []) as $registration)
                        <span>{{ $registration }}</span>@if(! $loop->last) &bull; @endif
                    @endforeach
                </div>
            </td>
            <td class="pd-doctype">
                <span class="pd-doctype-label">{{ $document['kindLabel'] }}</span>
                @if(! empty($document['status']))
                    <div class="pd-doctype-status pd-tone-{{ $document['status']['tone'] ?? 'default' }}">{{ $document['status']['label'] }}</div>
                @endif
                <div class="pd-org-lines">{{ $document['documentKey'] }}</div>
            </td>
        </tr>
    </table>
    <div class="pd-rule-strong pd-block"></div>
@else
    {{-- Thermal/label letterhead: a simplified identity, never a shrunk A4 header. --}}
    <div class="pd-c">
        @if($showLogo)
            <img class="pd-logo" style="max-height: 12mm;" src="{{ $logo }}" alt="{{ $brand['name'] }}">
        @endif
        <div class="pd-b pd-upper">{{ $brand['name'] }}</div>
        @if(($brand['tagline'] ?? '') !== '')
            <div>{{ $brand['tagline'] }}</div>
        @endif
        @if(($brand['addressLine'] ?? '') !== '')
            <div>{{ $brand['addressLine'] }}</div>
        @endif
        @if(($brand['contactLine'] ?? '') !== '')
            <div>{{ $brand['contactLine'] }}</div>
        @endif
        @foreach(($brand['registrations'] ?? []) as $registration)
            <div>{{ $registration }}</div>
        @endforeach
        <div class="pd-b pd-upper" style="margin-top: 1mm;">{{ $document['kindLabel'] }}</div>
        @if(! empty($document['status']))
            <div class="pd-b">{{ $document['status']['label'] }}</div>
        @endif
    </div>
    <div class="pd-rule-dashed"></div>
@endif
