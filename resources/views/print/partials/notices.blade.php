@foreach(($document['notices'] ?? []) as $notice)
    <div class="pd-notice pd-keep pd-notice--{{ $notice['tone'] ?? 'muted' }}">{{ $notice['text'] }}</div>
@endforeach
