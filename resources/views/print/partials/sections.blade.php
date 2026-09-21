@php $lastGroup = null; @endphp
@foreach(($document['sections'] ?? []) as $section)
    @php
        $body = trim((string) ($section['body'] ?? ''));
        $group = $section['group'] ?? null;
    @endphp
    @if($body === '' && $group === null)
        @continue
    @endif
    @if($group !== null && $group !== $lastGroup)
        <div class="pd-section"><div class="pd-section-group">{{ $group }}</div></div>
        @php $lastGroup = $group; @endphp
    @endif
    @if($body !== '')
        <div class="pd-section {{ ($section['emphasis'] ?? false) ? 'pd-section--emphasis' : '' }}">
            <div class="pd-section-title pd-keep-next">{{ $section['label'] }}</div>
            <div class="pd-section-body">{{ $body }}</div>
        </div>
    @endif
@endforeach
