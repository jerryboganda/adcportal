<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice') }} {{ $invoice->invoice_number }}</title>
    @php
        $settings = getCompanyAllSetting();
        $clinicName = $settings['title_text'] ?? config('app.name');
        $logoPath = null;
        foreach (['light_logo', 'logo_dark', 'dark_logo'] as $logoKey) {
            if (!empty($settings[$logoKey]) && file_exists(public_path($settings[$logoKey]))) {
                $logoPath = public_path($settings[$logoKey]);
                break;
            }
        }
        if ($logoPath === null && file_exists(public_path('uploads/logo/logo_dark.png'))) {
            $logoPath = public_path('uploads/logo/logo_dark.png');
        }
        $accent = (isset($settings['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $settings['color'])) ? $settings['color'] : '#0f4c81';
    @endphp
    <style>
        body { font-family:'Helvetica','Arial',sans-serif; font-size:12px; color:#1a202c; margin:36px 44px; }
        .head { display:flex; justify-content:space-between; border-bottom:3px solid <?php echo e($accent); ?>; padding-bottom:10px; margin-bottom:16px;}
        h1{font-size:18px;color:<?php echo e($accent); ?>;margin:0;} h2{font-size:13px;margin:0;color:#64748b;font-weight:normal;}
        table.items { width:100%; border-collapse:collapse; margin-top:14px;}
        table.items th { background:#f1f5f9; text-align:left; padding:7px 9px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #cbd5e1;}
        table.items td { padding:6px 9px; border-bottom:1px solid #e2e8f0;}
        .totals { width:290px; margin-left:auto; margin-top:12px;}
        .totals td { padding:4px 8px;}
        .totals tr.grand td { border-top:2px solid #334155; font-size:14px; font-weight:800; }
        .status { display:inline-block; background:#eff6ff; color:#1d4ed8; border-radius:4px; padding:2px 10px; font-size:10px; text-transform:uppercase; letter-spacing:.08em; font-weight:700;}
        footer { margin-top:30px; border-top:1px solid #cbd5e1; padding-top:8px; font-size:10px; color:#64748b;}
        table.payments { width:100%; border-collapse:collapse; margin-top:18px; }
        table.payments th { background:#f8fafc; text-align:left; padding:5px 9px; font-size:10px; text-transform:uppercase; border-bottom:1px solid #cbd5e1; }
        table.payments td { padding:5px 9px; border-bottom:1px solid #e2e8f0; font-size:11px; }
        .stamp {
            display:inline-block; border:3px double #16a34a; color:#16a34a;
            padding:6px 22px; font-size:20px; font-weight:800; letter-spacing:.25em;
            text-transform:uppercase; transform:rotate(-8deg);
            -webkit-transform:rotate(-8deg); border-radius:6px; opacity:.85;
        }
        .logo-cell img { max-height:56px; max-width:210px; }
    </style>
</head>
<body>
<div class="head">
    <div>
        @if($logoPath)
            <div class="logo-cell"><img src="<?php echo e($logoPath); ?>" alt="<?php echo e($clinicName); ?>"></div>
        @endif
        <h1><?php echo e($clinicName); ?></h1>
        <h2>{{ __('Radiology & Imaging Services') }}</h2>
    </div>
    <div style="text-align:right">
        <h1>{{ __('INVOICE') }}</h1>
        <h2>{{ $invoice->invoice_number }}</h2>
        <span class="status">{{ ucfirst($invoice->status) }}</span>
    </div>
</div>

@if($invoice->status === \App\Models\Invoice::STATUS_PAID)
    <div style="text-align:right; margin:-14px 0 10px;">
        <span class="stamp">{{ __('Paid') }}</span>
    </div>
@endif

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
            <td style="text-align:right">{{ currency_format_with_sym($item->unit_price) }}</td>
            <td style="text-align:right">{{ currency_format_with_sym($item->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('Subtotal') }}</td><td style="text-align:right">{{ currency_format_with_sym($invoice->subtotal) }}</td></tr>
    <tr><td>{{ __('Tax') }} ({{ $invoice->tax_rate }}%)</td><td style="text-align:right">{{ currency_format_with_sym($invoice->tax_amount) }}</td></tr>
    <tr class="grand"><td>{{ __('TOTAL') }}</td><td style="text-align:right">{{ currency_format_with_sym($invoice->total) }}</td></tr>
    <tr><td>{{ __('Paid') }}</td><td style="text-align:right">{{ currency_format_with_sym($invoice->paid_total) }}</td></tr>
    <tr><td><strong>{{ __('Balance Due') }}</strong></td><td style="text-align:right"><strong>{{ currency_format_with_sym($invoice->balance_due) }}</strong></td></tr>
</table>

@if($invoice->payments->count())
    <table class="payments">
        <thead><tr><th>{{ __('Payment History') }}</th><th style="width:90px;text-align:center">{{ __('Method') }}</th>
            <th style="width:110px;text-align:right">{{ __('Amount') }}</th><th style="width:90px;text-align:right">{{ __('Date') }}</th></tr></thead>
        <tbody>
        @foreach($invoice->payments as $pay)
            <tr>
                <td>{{ $pay->reference ?: '—' }}</td>
                <td style="text-align:center">{{ ucfirst($pay->method) }}</td>
                <td style="text-align:right">{{ currency_format_with_sym($pay->amount) }}</td>
                <td style="text-align:right">{{ optional($pay->paid_at)->format('d M Y') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(!empty($invoice->notes))
    <p style="margin-top:20px"><em>{{ $invoice->notes }}</em></p>
@endif

<footer>{{ __('Thank you for choosing our clinic. This is a computer-generated invoice.') }}</footer>
</body>
</html>
