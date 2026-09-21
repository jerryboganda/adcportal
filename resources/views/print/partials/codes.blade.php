@if(! empty($document['codes']) && ($document['options']['showBarcode'] ?? true))
    <div class="pd-codes">
        @foreach($document['codes'] as $code)
            @if(($code['dataUri'] ?? '') !== '')
                {{-- The symbol is vector geometry generated ONCE by the server:
                     the PDF engines take it as an SVG image (DomPDF has no
                     inline-SVG or script support), the SPA draws the same
                     markup inline. Printers faithfully paint it at physical
                     size, so a scanned receipt matches a scanned PDF. --}}
                <div class="pd-code pd-keep">
                    <img src="{{ $code['dataUri'] }}" alt="{{ $code['label'] ?? $code['value'] }}">
                    <div class="pd-code-value">{{ $code['label'] ?? $code['value'] }}</div>
                </div>
            @endif
        @endforeach
    </div>
@endif
