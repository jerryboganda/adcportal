<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice') }} {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family:'Helvetica','Arial',sans-serif; font-size:12px; color:#1a202c; margin:36px 44px; }
        .head { display:flex; justify-content:space-between; border-bottom:3px solid #0f4c81; padding-bottom:10px; margin-bottom:16px;}
        h1{font-size:18px;color:#0f4c81;margin:0;} h2{font-size:13px;margin:0;color:#64748b;font-weight:normal;}
        table.items { width:100%; border-collapse:collapse; margin-top:14px;}
        table.items th { background:#f1f5f9; text-align:left; padding:7px 9px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #cbd5e1;}
        table.items td { padding:6px 9px; border-bottom:1px solid #e2e8f0;}
        .totals { width:290px; margin-left:auto; margin-top:12px;}
        .totals td { padding:4px 8px;}
        .totals tr.grand td { border-top:2px solid #334155; font-size:14px; font-weight:800; }
        .status { display:inline-block; background:#eff6ff; color:#1d4ed8; border-radius:4px; padding:2px 10px; font-size:10px; text-transform:uppercase; letter-spacing:.08em; font-weight:700;}
        footer { margin-top:30px; border-top:1px solid #cbd5e1; padding-top:8px; font-size:10px; color:#64748b;}
    </style>
</head>
<body>
<div class="head">
    <div>
        <h1>{{ getCompanyAllSetting()['title_text'] ?? config('app.name') }}</h1>
        <h2>{{ __('Radiology & Imaging Services') }}</h2>
    </div>
    <div style="text-align:right">
        <h1>{{ __('INVOICE') }}</h1>
        <h2>{{ $invoice->invoice_number }}</h2>
        <span class="status">{{ ucfirst($invoice->status) }}</span>
    </div>
</div>

<p><strong>{{ __('Bill To') }}:</strong> {{ optional($invoice->patient)->name ?? '-' }}
    @if(optional(optional($invoice->patient)->customer)->mrn) ({{ __('MRN') }}: {{ optional($invoice->patient)->customer->mrn }})@endif</p>
<p><strong>{{ __('Date') }}:</strong> {{ optional($invoice->issued_at ?? $invoice->created_at)->format('d M Y') }}
    &nbsp;·&nbsp; <strong>{{ __('Study') }}:</strong> #APP{{ str_pad((string)$invoice->appointment_id, 4, '0', STR_PAD_LEFT) }}</p>

<table class="items">
    <thead><tr><th>{{ __('Description') }}</th><th style="width:50px;text-align:center">{{ __('Qty') }}</th>
        <th style="width:110px;text-align:right">{{ __('Unit') }}</th><th style="width:100px;text-align:right">{{ __('Total') }}</th></tr></thead>
    <tbody>
    @foreach($invoice->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td style="text-align:center">{{ $item->quantity }}</td>
            <td style="text-align:right">{{ currency_format($item->unit_price) }}</td>
            <td style="text-align:right">{{ currency_format($item->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('Subtotal') }}</td><td style="text-align:right">{{ currency_format($invoice->subtotal) }}</td></tr>
    <tr><td>{{ __('Tax') }} (@{{ $invoice->tax_rate }}%)</td><td style="text-align:right">{{ currency_format($invoice->tax_amount) }}</td></tr>
    <tr class="grand"><td>{{ __('TOTAL') }}</td><td style="text-align:right">{{ currency_format($invoice->total) }}</td></tr>
    <tr><td>{{ __('Paid') }}</td><td style="text-align:right">{{ currency_format($invoice->paid_total) }}</td></tr>
    <tr><td><strong>{{ __('Balance Due') }}</strong></td><td style="text-align:right"><strong>{{ currency_format($invoice->balance_due) }}</strong></td></tr>
</table>

@if(!empty($invoice->notes))
    <p style="margin-top:20px"><em>{{ $invoice->notes }}</em></p>
@endif

<footer>{{ __('Thank you for choosing our clinic. This is a computer-generated invoice.') }}</footer>
</body>
</html>
