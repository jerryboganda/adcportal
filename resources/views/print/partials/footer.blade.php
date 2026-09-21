@if(! empty($document['footerNotes']))
    <div class="pd-footer">
        @foreach($document['footerNotes'] as $note)
            @if(trim((string) $note) !== '')
                <div class="pd-footer-note">{{ $note }}</div>
            @endif
        @endforeach
    </div>
@endif
