@if(! empty($document['marks']))
    <div class="pd-marks">
        @foreach($document['marks'] as $mark)
            <span class="pd-mark pd-mark--{{ $mark['tone'] ?? 'warn' }}{{ ($mark['code'] ?? '') === 'DRAFT' ? ' pd-mark--draft' : '' }}">{{ $mark['label'] ?? $mark['code'] }}</span>
        @endforeach
    </div>
@endif
