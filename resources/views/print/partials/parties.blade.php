@if(! empty($document['parties']))
    <table class="pd-parties pd-block">
        <tr>
            @foreach($document['parties'] as $party)
                <td>
                    <div class="pd-party pd-keep">
                        <div class="pd-party-title">{{ $party['title'] }}</div>
                        <table>
                            @foreach($party['rows'] as $row)
                                <tr class="pd-party-row">
                                    <td class="pd-party-label">{{ $row['label'] }}</td>
                                    <td class="pd-party-value">{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </td>
            @endforeach
            @for($i = count($document['parties']); $i < 2; $i++)
                <td></td>
            @endfor
        </tr>
    </table>
@endif
