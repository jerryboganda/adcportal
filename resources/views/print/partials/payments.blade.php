@if(! empty($document['payments']))
    @if($document['paper'] === 'a4')
        <div class="pd-section pd-keep-next">
            <div class="pd-section-title">Payment transactions &amp; receipts</div>
        </div>
        <table class="pd-table">
            <thead>
                <tr>
                    <th class="pd-th">Received at</th>
                    <th class="pd-th">Mode</th>
                    <th class="pd-th">Reference</th>
                    <th class="pd-th pd-r">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($document['payments'] as $payment)
                    <tr>
                        <td class="pd-td pd-nowrap">{{ $payment['when'] }}</td>
                        <td class="pd-td pd-upper">{{ $payment['isRefund'] ? 'REFUND — '.$payment['method'] : $payment['method'] }}</td>
                        <td class="pd-td">{{ $payment['reference'] }}</td>
                        <td class="pd-td pd-r pd-nowrap {{ $payment['isRefund'] ? 'pd-tone-critical' : 'pd-tone-success' }}">
                            {{ $payment['isRefund'] ? '' : '' }}{{ $payment['amount'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table class="pd-table pd-keep">
            @foreach($document['payments'] as $payment)
                <tr>
                    <td>{{ $payment['isRefund'] ? 'REFUND '.$payment['method'] : $payment['method'] }}</td>
                    <td class="pd-r pd-nowrap">{{ $payment['amount'] }}</td>
                </tr>
                <tr>
                    <td class="pd-items-sub">{{ $payment['when'] }}{{ ($payment['reference'] ?? '—') !== '—' ? ' • '.$payment['reference'] : '' }}</td>
                    <td></td>
                </tr>
            @endforeach
        </table>
    @endif
@endif
