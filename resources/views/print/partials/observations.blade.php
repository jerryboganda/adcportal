@if(! empty($document['observations']))
    <div class="pd-section pd-keep-next">
        <div class="pd-section-title">Structured measurements &amp; observations</div>
    </div>
    <table class="pd-table">
        <tbody>
            @foreach($document['observations'] as $row)
                <tr class="pd-obs-row">
                    <td class="pd-obs-label">{{ $row['label'] }}</td>
                    <td class="pd-b">{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
