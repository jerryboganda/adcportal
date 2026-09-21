@if(! empty($document['totals']))
    @if($document['paper'] === 'a4')
        <table class="pd-totals pd-keep">
            <tr>
                <td style="width: 55%;"></td>
                <td style="width: 45%;">
                    <table class="pd-table">
                        @foreach($document['totals'] as $row)
                            <tr class="pd-total-row {{ ($row['emphasis'] ?? false) ? 'pd-total-row--emphasis' : '' }}">
                                <td class="pd-total-label pd-tone-{{ $row['tone'] ?? 'default' }}">{{ $row['label'] }}</td>
                                <td class="pd-total-value pd-tone-{{ $row['tone'] ?? 'default' }}">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @else
        <div class="pd-rule-dashed"></div>
        <table class="pd-table pd-keep">
            @foreach($document['totals'] as $row)
                <tr class="pd-total-row {{ ($row['emphasis'] ?? false) ? 'pd-total-row--emphasis' : '' }}">
                    <td class="pd-total-label pd-tone-{{ $row['tone'] ?? 'default' }}">{{ $row['label'] }}</td>
                    <td class="pd-total-value pd-tone-{{ $row['tone'] ?? 'default' }}">{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </table>
        <div class="pd-rule-dashed"></div>
    @endif
@endif
