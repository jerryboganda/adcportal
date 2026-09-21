@if(! empty($document['meta']))
    <table class="pd-meta pd-block">
        @foreach($document['meta'] as $row)
            <tr class="pd-meta-row">
                <td class="pd-meta-label">{{ $row['label'] }}</td>
                <td class="pd-meta-value">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>
@endif
