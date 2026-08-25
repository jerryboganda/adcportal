<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Radiology Report') }} — {{ $report->appointment->patientDisplayName() }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #1a202c; margin: 40px 48px; }
        .letterhead { display:flex; justify-content:space-between; align-items:flex-start; border-bottom: 3px solid #0f4c81; padding-bottom: 12px; margin-bottom: 18px; }
        .clinic-name { font-size: 20px; font-weight: 700; color: #0f4c81; }
        .clinic-sub { color:#64748b; font-size:10px; }
        .watermark { position: fixed; top: 42%; left: 50%; transform: translate(-50%,-50%) rotate(-28deg);
            font-size: 64px; font-weight: 800; color: rgba(220,38,38,{{ $report->type === 'final' ? '0.08' : '0.16' }});
            text-transform: uppercase; letter-spacing: .2em; white-space: nowrap; z-index:-1;}
        h1 { font-size: 15px; text-transform: uppercase; letter-spacing: .08em; color:#0f4c81; margin: 14px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;}
        table.meta { width:100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td { padding: 2px 6px; vertical-align: top; font-size: 11px; }
        table.meta td.k { width: 110px; color: #64748b; font-weight: 600; }
        .section p { line-height: 1.55; white-space: pre-wrap; text-align: justify; }
        footer { margin-top: 34px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 10px; color: #64748b;
            display: flex; justify-content: space-between; }
        .sig { margin-top: 30px; display: flex; justify-content: flex-end; }
        .sig .box { text-align: center; min-width: 240px; }
        .sig .line { border-top: 1px solid #334155; padding-top: 5px; font-size: 11px; }
        .critical { background:#fef2f2; border:1px solid #ef4444; color:#b91c1c; padding:6px 10px; border-radius:4px; font-weight:700; margin-bottom:10px; font-size:11px;}
    </style>
</head>
<body>

@if($report->type !== 'final')
    <div class="watermark">{{ ucfirst($report->type) }}</div>
@endif

<div class="letterhead">
    <div>
        <div class="clinic-name">{{ getCompanyAllSetting()['title_text'] ?? config('app.name') }}</div>
        <div class="clinic-sub">Radiology &amp; Imaging Services</div>
    </div>
    <div class="clinic-sub" style="text-align:right">
        {{ __('Report') }} #: R-{{ $report->appointment_id }}-V{{ $report->version }}<br>
        {{ __('Issued') }}: {{ optional($report->signed_at ?? $report->created_at)->format('d M Y H:i') }}
    </div>
</div>

@if($report->critical_flag)
    <div class="critical">⚠ {{ __('CRITICAL FINDING — referring physician must be notified immediately.') }}</div>
@endif

<h1>{{ __('Patient & Study') }}</h1>
<table class="meta">
    <tr><td class="k">{{ __('Patient') }}</td><td>{{ $report->appointment->patientDisplayName() }}
        ({{ __('MRN') }}: {{ optional($report->appointment->CustomerData)->mrn ?? '-' }})</td>
        <td class="k">{{ __('Study #') }}</td><td>#APP{{ str_pad((string)$report->appointment_id, 4, '0', STR_PAD_LEFT) }}</td></tr>
    <tr><td class="k">{{ __('Procedure') }}</td><td>{{ optional($report->appointment->ServiceData)->name ?? '-' }}</td>
        <td class="k">{{ __('Study Date') }}</td><td>{{ $report->appointment->date }} {{ \Illuminate\Support\Str::substr((string)$report->appointment->time, 0, 5) }}</td></tr>
    <tr><td class="k">{{ __('Referrer') }}</td><td>{{ optional($report->appointment->ReferrerData)->name ?? '-' }}</td>
        <td class="k">{{ __('Type') }}</td><td style="font-weight:700">{{ strtoupper($report->type) }}</td></tr>
</table>

<div class="section">
    @if(!empty($report->clinical_history))
        <h1>{{ __('Clinical History') }}</h1><p>{{ $report->clinical_history }}</p>
    @endif
    @if(!empty($report->technique))
        <h1>{{ __('Technique') }}</h1><p>{{ $report->technique }}</p>
    @endif
    @if(!empty($report->comparison))
        <h1>{{ __('Comparison') }}</h1><p>{{ $report->comparison }}</p>
    @endif
    <h1>{{ __('Findings') }}</h1><p>{{ $report->findings }}</p>
    <h1>{{ __('Impression') }}</h1><p>{{ $report->impression }}</p>
    @if(!empty($report->recommendations))
        <h1>{{ __('Recommendations') }}</h1><p>{{ $report->recommendations }}</p>
    @endif
</div>

<div class="sig">
    <div class="box">
        <div style="font-family:'Brush Script MT',cursive;font-size:22px;">{{ optional($report->signer)->name ?? '' }}</div>
        <div class="line">
            {{ optional($report->signer)->name ?? __('Awaiting signature') }}<br>
            {{ __('Radiologist') }} · {{ now()->format('d M Y') }}
        </div>
    </div>
</div>

<footer>
    <span>{{ __('This report was generated electronically by :app.', ['app' => config('app.name')]) }}</span>
    <span>R-{{ $report->appointment_id }}-V{{ $report->version }}</span>
</footer>
</body>
</html>
