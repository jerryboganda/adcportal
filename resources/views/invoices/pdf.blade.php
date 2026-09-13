<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40px 44px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1a202c; font-size: 11px; line-height: 1.5; }
        .header { border-bottom: 3px solid #0284c7; padding-bottom: 10px; margin-bottom: 14px; }
        .clinic-name { font-size: 18px; font-weight: bold; color: #0284c7; margin: 0; }
        .clinic-meta { color: #4a5568; font-size: 9px; margin-top: 3px; }
        .status { float: right; font-size: 10px; font-weight: bold; border: 1px solid #4a5568; padding: 3px 8px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #edf2f7; text-align: left; padding: 5px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .totals td { border: none; padding: 2px 8px; }
        .grand { font-size: 13px; font-weight: bold; }
        .footer { margin-top: 20px; border-top: 1px solid #cbd5e0; padding-top: 8px; font-size: 9px; color: #4a5568; }
    </style>
</head>
<body>
    @php
        $settings = \App\Http\Resources\ApiShape::clinicSettings($invoice->business_id);
        $patient = \App\Models\Customer::where('user_id', $invoice->patient_id)->first();
    @endphp

    <div class="header">
        <span class="status">{{ strtoupper($invoice->status) }}</span>
        <p class="clinic-name">{{ $settings['name'] }}</p>
        <div class="clinic-meta">
            {{ trim($settings['address'].' '.$settings['city']) }} @if($settings['phone']) • {{ $settings['phone'] }} @endif<br>
            @if($settings['taxId']) {{ $settings['taxId'] }} @endif
        </div>
    </div>

    <table>
        <tr>
            <td><strong>Invoice</strong><br>{{ $invoice->invoice_number }}</td>
            <td><strong>Date</strong><br>{{ \Carbon\Carbon::parse($invoice->issued_at ?? $invoice->created_at)->format('d M Y, h:i A') }}</td>
            <td><strong>Patient</strong><br>{{ $patient?->name ?? '—' }}<br>{{ $patient?->mrn }}</td>
            <td><strong>Token</strong><br>{{ $invoice->appointment?->token_number ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr><th>Description</th><th class="right">Qty</th><th class="right">Unit Price</th><th class="right">Discount</th><th class="right">Total</th></tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->discount, 2) }}</td>
                    <td class="right">{{ number_format((float) ($item->line_total ?: $item->unit_price * $item->quantity), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="right" style="width:70%"></td><td class="right">Subtotal: {{ $settings['currencySymbol'] }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td class="right"></td><td class="right">Discount: −{{ $settings['currencySymbol'] }} {{ number_format((float) $invoice->discount_total, 2) }}</td></tr>
        @if((float) $invoice->tax_amount > 0)
            <tr><td class="right"></td><td class="right">Tax ({{ $invoice->tax_rate }}%): {{ $settings['currencySymbol'] }} {{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
        @endif
        <tr><td class="right"></td><td class="right grand">Total: {{ $settings['currencySymbol'] }} {{ number_format((float) $invoice->total, 2) }}</td></tr>
        <tr><td class="right"></td><td class="right">Paid: {{ $settings['currencySymbol'] }} {{ number_format((float) $invoice->paid_total, 2) }}</td></tr>
        <tr><td class="right"></td><td class="right grand">Balance Due: {{ $settings['currencySymbol'] }} {{ number_format((float) $invoice->balance_due, 2) }}</td></tr>
    </table>

    @if($invoice->payments->count())
        <h2 style="font-size:11px;text-transform:uppercase;color:#0284c7;">Payments</h2>
        <table>
            <thead><tr><th>Received At</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr></thead>
            <tbody>
                @foreach($invoice->payments as $payment)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($payment->paid_at)->format('d M Y, h:i A') }}</td>
                        <td>{{ strtoupper($payment->method) }}</td>
                        <td>{{ $payment->reference ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ $settings['invoiceFooterDisclaimer'] }}
    </div>
</body>
</html>
