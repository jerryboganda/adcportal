@php $foot = $document['pageFoot'] ?? []; @endphp
@if($document['paper'] === 'a4')
    {{-- Repeated on every printed page. Browser print cannot count pages or
         suppress the dialog's own header/footer, so the document states its
         identity here instead of relying on a URL/title/date the user cannot
         control. The server PDF states page numbers itself. --}}
    <div class="pd-pagefoot">
        <span class="pd-pagefoot-cell pd-pagefoot-left">{{ $foot['left'] ?? '' }}</span>
        <span class="pd-pagefoot-cell pd-pagefoot-center">{{ $foot['center'] ?? '' }}</span>
        <span class="pd-pagefoot-cell pd-pagefoot-right">{{ $foot['right'] ?? '' }}</span>
    </div>
@endif
