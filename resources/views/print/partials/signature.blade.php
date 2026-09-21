@if(! empty($document['signature']) && ($document['options']['showSignature'] ?? true))
    @php $sig = $document['signature']; @endphp
    <table class="pd-signature pd-keep">
        <tr>
            <td style="width: 50%;">
                <div class="pd-signature-name">{{ $sig['name'] ?? '' }}</div>
                <div>{{ $sig['role'] ?? '' }}</div>
                @foreach(($sig['lines'] ?? []) as $line)
                    @if($line !== '')
                        <div class="pd-muted">{{ $line }}</div>
                    @endif
                @endforeach
            </td>
            <td class="pd-r" style="width: 50%;">
                <div class="pd-signature-line pd-muted">{{ $sig['caption'] ?? 'Signature' }}</div>
                @if(($sig['statement'] ?? '') !== '')
                    <div class="pd-signature-statement">{{ $sig['statement'] }}</div>
                @endif
                @if(($sig['reference'] ?? '') !== '')
                    <div class="pd-signature-ref">{{ $sig['reference'] }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif
