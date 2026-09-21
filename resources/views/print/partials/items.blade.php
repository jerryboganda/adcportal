@if(! empty($document['items']))
    @if($document['paper'] === 'a4')
        <div class="pd-section pd-keep-next">
            <div class="pd-section-title">Itemised services &amp; charges</div>
        </div>
        <table class="pd-table">
            <thead>
                <tr>
                    <th class="pd-th pd-c" style="width: 9mm;">#</th>
                    <th class="pd-th">Service / procedure / consumable</th>
                    <th class="pd-th pd-c" style="width: 14mm;">Qty</th>
                    <th class="pd-th pd-r" style="width: 30mm;">Unit rate</th>
                    <th class="pd-th pd-r" style="width: 26mm;">Discount</th>
                    <th class="pd-th pd-r" style="width: 32mm;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($document['items'] as $item)
                    <tr>
                        <td class="pd-td pd-c">{{ $item['index'] }}</td>
                        <td class="pd-td">
                            <div class="pd-b">{{ $item['description'] }}</div>
                            @if(($item['sub'] ?? '') !== '')
                                <div class="pd-items-sub">{{ $item['sub'] }}</div>
                            @endif
                        </td>
                        <td class="pd-td pd-c pd-nowrap">{{ $item['quantity'] }}</td>
                        <td class="pd-td pd-r pd-nowrap">{{ $item['unitPrice'] }}</td>
                        <td class="pd-td pd-r pd-nowrap pd-tone-success">{{ $item['discount'] ?? '—' }}</td>
                        <td class="pd-td pd-r pd-nowrap pd-b">{{ $item['lineTotal'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        {{-- A desktop table on 80 mm wraps every row into noise: the receipt
             stacks the description above a single amount instead. --}}
        <div class="pd-rule-dashed"></div>
        @foreach($document['items'] as $item)
            <table class="pd-table pd-keep">
                <tr>
                    <td class="pd-label-value">{{ $item['description'] }}</td>
                    <td class="pd-r pd-nowrap pd-b">{{ $item['lineTotal'] }}</td>
                </tr>
                @if(($item['quantity'] ?? '1') !== '1' && ($item['quantity'] ?? '1') !== '')
                    <tr>
                        <td class="pd-items-sub">{{ $item['quantity'] }} × {{ $item['unitPrice'] ?? '' }}</td>
                        <td class="pd-r pd-items-sub">{{ $item['discount'] ?? '' }}</td>
                    </tr>
                @endif
            </table>
        @endforeach
        <div class="pd-rule-dashed"></div>
    @endif
@endif
