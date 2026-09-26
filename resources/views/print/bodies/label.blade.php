{{-- A label is a FIXED 63.5 x 25.4 mm tag, not a shrunk receipt.

     It used to include the shared letterhead and the shared key/value table,
     which measured 51 mm of content on a 25.4 mm tag: the browser and both PDF
     engines faithfully printed that as THREE tags per patient, with the barcode
     pushed onto the second one. A tag carries identity on the left and the
     symbol on the right, and nothing that is not read at the desk. --}}
<table class="pd-label">
    <tr>
        <td class="pd-label-clinic" colspan="2">{{ $document['branding']['name'] }}</td>
    </tr>
    <tr>
        <td class="pd-label-id">
            @foreach($document['meta'] as $row)
                <div class="pd-label-row">
                    <span class="pd-label-key">{{ $row['label'] }}</span>
                    <span class="pd-label-value">{{ $row['value'] }}</span>
                </div>
            @endforeach
        </td>
        <td class="pd-label-symbol">@include('print.partials.codes')</td>
    </tr>
</table>
