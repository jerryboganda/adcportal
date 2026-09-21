@if(! empty($document['criticalLogs']))
    <div class="pd-section pd-keep-next">
        <div class="pd-section-title">{{ $document['criticalLogHeading'] ?? 'Critical Result Communication Record' }}</div>
    </div>
    <table class="pd-table">
        <thead>
            <tr>
                <th class="pd-th">Communicated to</th>
                <th class="pd-th">Method</th>
                <th class="pd-th">Read-back</th>
                <th class="pd-th">When</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document['criticalLogs'] as $log)
                <tr>
                    <td class="pd-td">{{ $log['notifiedTo'] }}</td>
                    <td class="pd-td">{{ $log['method'] }}</td>
                    <td class="pd-td">{{ $log['readBack'] }}</td>
                    <td class="pd-td pd-nowrap">{{ $log['when'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
