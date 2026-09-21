@if(! empty($document['columns']))
    <table class="pd-table pd-block">
        <thead>
            <tr>
                @foreach($document['columns'] as $column)
                    <th class="pd-th {{ ($column['align'] ?? 'left') === 'right' ? 'pd-r' : (($column['align'] ?? 'left') === 'center' ? 'pd-c' : '') }}"
                        style="width: {{ $column['width'] ?? 'auto' }};">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $lastGroup = null; @endphp
            @forelse(($document['rows'] ?? []) as $row)
                @php $group = $row['group'] ?? null; @endphp
                @if($group !== null && $group !== $lastGroup)
                    <tr class="pd-group-row">
                        <td colspan="{{ count($document['columns']) }}">{{ $group }}</td>
                    </tr>
                    @php $lastGroup = $group; @endphp
                @endif
                <tr>
                    @foreach($document['columns'] as $column)
                        @php
                            $value = $row[$column['key']] ?? '';
                            $align = $column['align'] ?? 'left';
                        @endphp
                        <td class="pd-td {{ $align === 'right' ? 'pd-r pd-nowrap' : ($align === 'center' ? 'pd-c' : '') }}">{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="pd-td pd-row-empty" colspan="{{ count($document['columns']) }}">No records for this document.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endif
