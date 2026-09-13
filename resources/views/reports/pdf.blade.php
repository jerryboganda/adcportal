<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Radiology Report</title>
    <style>
        @page { margin: 42px 46px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1a202c; font-size: 11px; line-height: 1.5; }
        .header { border-bottom: 3px solid #0284c7; padding-bottom: 10px; margin-bottom: 14px; }
        .clinic-name { font-size: 18px; font-weight: bold; color: #0284c7; margin: 0; }
        .clinic-meta { color: #4a5568; font-size: 9px; margin-top: 3px; }
        .report-kind { float: right; font-size: 10px; font-weight: bold; color: #b91c1c; border: 1px solid #b91c1c; padding: 3px 8px; border-radius: 3px; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #0284c7; border-bottom: 1px solid #cbd5e0; padding-bottom: 2px; margin: 14px 0 6px; }
        .patient-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .patient-table td { padding: 2px 6px; vertical-align: top; }
        .label { color: #718096; width: 110px; }
        .critical { background: #fff5f5; border-left: 4px solid #b91c1c; padding: 6px 10px; margin: 8px 0; color: #742a2a; font-weight: bold; }
        .section-text { white-space: pre-line; margin: 0 0 6px; }
        .footer { margin-top: 24px; border-top: 1px solid #cbd5e0; padding-top: 8px; font-size: 9px; color: #4a5568; }
        .signature { margin-top: 22px; }
        .signature-line { border-top: 1px solid #1a202c; width: 240px; margin-top: 34px; padding-top: 3px; }
    </style>
</head>
<body>
    @php
        $settings = \App\Http\Resources\ApiShape::clinicSettings($report->business_id);
        $appointment = $report->appointment;
        $patient = $appointment->CustomerData;
    @endphp

    <div class="header">
        <span class="report-kind">{{ strtoupper($report->type) }} @if($report->critical_flag) — CRITICAL @endif</span>
        <p class="clinic-name">{{ $settings['name'] }}</p>
        <div class="clinic-meta">
            {{ $settings['headerTagline'] }}<br>
            {{ trim($settings['address'].' '.$settings['city']) }} @if($settings['phone']) • {{ $settings['phone'] }} @endif @if($settings['email']) • {{ $settings['email'] }} @endif
        </div>
    </div>

    <table class="patient-table">
        <tr><td class="label">Patient</td><td>{{ $appointment->patientDisplayName() }}</td><td class="label">MRN</td><td>{{ $patient?->mrn ?? '—' }}</td></tr>
        <tr><td class="label">Age / Gender</td><td>{{ $patient?->age ?? '—' }} / {{ $patient?->gender ?? '—' }}</td><td class="label">Date of Study</td><td>{{ \Carbon\Carbon::parse($appointment->date_sort ?? $appointment->date)->format('d M Y') }} {{ \Carbon\Carbon::parse($appointment->time)->format('h:i A') }}</td></tr>
        <tr><td class="label">Study</td><td>{{ $appointment->ServiceData?->name }} ({{ $appointment->ServiceData?->code }})</td><td class="label">Token</td><td>{{ $appointment->token_number }}</td></tr>
        <tr><td class="label">Referring Doctor</td><td>{{ $appointment->ReferrerData?->name ?? 'Self / Walk-in' }}</td><td class="label">Report Version</td><td>v{{ $report->version }}</td></tr>
        @if($appointment->doseLog)
            <tr>
                <td class="label">Dose Record</td>
                <td colspan="3">
                    {{ $appointment->doseLog->dose_value }} {{ $appointment->doseLog->dose_unit }}
                    @if($appointment->doseLog->contrast_agent) • {{ $appointment->doseLog->contrast_agent }} {{ $appointment->doseLog->contrast_volume_ml }} mL @endif
                </td>
            </tr>
        @endif
    </table>

    @if($report->critical_flag)
        <div class="critical">CRITICAL FINDING — immediate clinician communication required.</div>
    @endif

    @if($report->clinical_history)<h2>Clinical History</h2><p class="section-text">{{ $report->clinical_history }}</p>@endif
    @if($report->technique)<h2>Technique</h2><p class="section-text">{{ $report->technique }}</p>@endif
    @if($report->comparison)<h2>Comparison</h2><p class="section-text">{{ $report->comparison }}</p>@endif
    @if($report->findings)<h2>Findings</h2><p class="section-text">{{ $report->findings }}</p>@endif
    @if($report->impression)<h2>Impression</h2><p class="section-text"><strong>{{ $report->impression }}</strong></p>@endif
    @if($report->recommendations)<h2>Recommendations</h2><p class="section-text">{{ $report->recommendations }}</p>@endif

    <div class="signature">
        <div class="signature-line">
            {{ optional($report->signer ?? $report->author)->name }}<br>
            {{ optional($report->signer ?? $report->author)->department ?? 'Radiologist' }}
        </div>
    </div>

    <div class="footer">
        {{ $settings['reportLegalDisclaimer'] }}<br>
        Digitally signed on {{ \Carbon\Carbon::parse($report->signed_at ?? $report->created_at)->format('d M Y, h:i A') }} — Report ref {{ $report->id }} (v{{ $report->version }}).
    </div>
</body>
</html>
