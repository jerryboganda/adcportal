@if(! empty($document['tokenNumber']))
    <div class="pd-hero pd-keep">
        <div class="pd-hero-label">Queue token number</div>
        <div class="pd-hero-value">{{ $document['tokenNumber'] }}</div>
        @if(($document['roomLabel'] ?? '') !== '')
            <div class="pd-hero-room">{{ $document['roomLabel'] }}</div>
        @endif
    </div>
@endif
