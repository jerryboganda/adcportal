<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Radiology Report</title>
    <style>
        @page { margin: 40px 44px 56px 44px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1a202c; font-size: 11px; line-height: 1.5; }
        .header { border-bottom: 3px solid #0284c7; padding-bottom: 10px; margin-bottom: 14px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; }
        .logo { height: 46px; margin-right: 10px; }
        .clinic-name { font-size: 18px; font-weight: bold; color: #0284c7; margin: 0; }
        .clinic-meta { color: #4a5568; font-size: 9px; margin-top: 3px; }
        .report-kind { font-size: 10px; font-weight: bold; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 3px 8px; border-radius: 3px; white-space: nowrap; }
        .report-kind.critical { color: #b91c1c; border-color: #b91c1c; }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #0284c7; border-bottom: 1px solid #cbd5e0; padding-bottom: 2px; margin: 14px 0 6px; }
        h2.addendum { color: #7c3aed; border-bottom-color: #c4b5fd; }
        .patient-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; background: #f8fafc; }
        .patient-table td { padding: 3px 7px; vertical-align: top; }
        .label { color: #718096; width: 112px; }
        .critical { background: #fff5f5; border-left: 4px solid #b91c1c; padding: 6px 10px; margin: 8px 0; color: #742a2a; font-weight: bold; }
        .addendum-box { background: #faf5ff; border-left: 4px solid #7c3aed; padding: 8px 11px; margin: 8px 0; }
        .section-text { white-space: pre-line; margin: 0 0 6px; }
        .structured { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .structured th, .structured td { border: 1px solid #cbd5e0; padding: 3px 7px; text-align: left; font-size: 10px; }
        .structured th { background: #eef2f7; }
        .footer { position: fixed; bottom: -34px; left: 0; right: 0; border-top: 1px solid #cbd5e0; padding-top: 6px; font-size: 9px; color: #4a5568; }
        .signature { margin-top: 26px; }
        .signature-line { border-top: 1px solid #1a202c; width: 250px; margin-top: 36px; padding-top: 3px; }
        .signature-name { font-weight: bold; }
        .muted { color: #718096; font-size: 9px; }
        .stamp { font-size: 9px; color: #2f855a; }
    </style>
</head>
<body>
    @php
        use App\Models\CriticalFindingLog;
        use App\Models\TenantBranding;

        $settings = \App\Http\Resources\ApiShape::clinicSettings($report->business_id);
        $appointment = $report->appointment;
        $patient = $appointment->CustomerData;
        $service = $appointment->ServiceData;
        $signer = $report->signer ?? $report->author;

        // Branding logo: embedded only when the file is locally resolvable —
        // a broken remote reference must never degrade the clinical document.
        $logo = null;
        $logoUrl = TenantBranding::where('business_id', $report->business_id)->value('logo_url');
        if (! empty($logoUrl)) {
            $relative = ltrim((string) (parse_url($logoUrl, PHP_URL_PATH) ?: ''), '/');
            foreach ([public_path($relative), storage_path('app/public/'.$relative)] as $candidate) {
                if ($relative !== '' && is_file($candidate)) {
                    $logo = 'data:'.(mime_content_type($candidate) ?: 'image/png').';base64,'.base64_encode(file_get_contents($candidate));
                    break;
                }
            }
        }

        $parent = $report->type === 'addendum' ? $report->parentReport : null;
        $criticalLogs = CriticalFindingLog::where('business_id', $report->business_id)
            ->where('appointment_id', $report->appointment_id)
            ->orderByDesc('communicated_at')
            ->get();
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    @if($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
                    <p class="clinic-name">{{ $settings['name'] }}</p>
                    <div class="clinic-meta">
                        {{ $settings['headerTagline'] }}<br>
                        {{ trim($settings['address'].' '.$settings['city']) }}@if($settings['phone']) • {{ $settings['phone'] }}@endif @if($settings['email']) • {{ $settings['email'] }}@endif @if($settings['website']) • {{ $settings['website'] }}@endif<br>
                        @if($settings['pmcRegistrationNo']) PMC Reg: {{ $settings['pmcRegistrationNo'] }} @endif @if($settings['pnraLicenseNo']) • PNRA: {{ $settings['pnraLicenseNo'] }} @endif
                    </div>
                </td>
                <td style="text-align: right;">
                    <span class="report-kind @if($report->critical_flag) critical @endif">
                        @if($report->type === 'addendum')
                            ADDENDUM — REPORT v{{ $report->version }}
                        @else
                            {{ strtoupper($report->type ?: 'DRAFT') }} @if($report->critical_flag) — CRITICAL @endif
                        @endif
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <table class="patient-table">
        <tr>
            <td class="label">Patient</td><td>{{ $appointment->patientDisplayName() }}</td>
            <td class="label">MRN</td><td>{{ $patient?->mrn ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Age / Gender</td><td>{{ $patient?->age ?? '—' }} y / {{ ucfirst((string) ($patient?->gender ?? '—')) }}</td>
            <td class="label">Date of Birth</td><td>{{ $patient?->dob ? \Carbon\Carbon::parse($patient->dob)->format('d M Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Examination</td><td>{{ $service?->name }}</td>
            <td class="label">Modality</td><td>{{ $service?->modality?->name ?? '—' }} @if($service?->body_region)({{ $service->body_region }})@endif</td>
        </tr>
        <tr>
            <td class="label">Study Date</td>
            <td>
                {{ \Carbon\Carbon::parse($appointment->date_sort ?? $appointment->date)->format('d M Y') }}
                @if($appointment->time) {{ \Carbon\Carbon::parse($appointment->time)->format('h:i A') }} @endif
            </td>
            <td class="label">Accession / Token</td><td>{{ $appointment->token_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Referring Doctor</td><td>{{ $appointment->ReferrerData?->name ?? 'Self / Walk-in' }}</td>
            <td class="label">Priority</td><td>{{ strtoupper((string) $appointment->priority) }}</td>
        </tr>
        @if($appointment->doseLog)
            <tr>
                <td class="label">Dose Record</td>
                <td colspan="3">
                    {{ $appointment->doseLog->dose_value }} {{ $appointment->doseLog->dose_unit }}
                    @if($appointment->doseLog->dlp_value) • DLP {{ $appointment->doseLog->dlp_value }} mGy·cm @endif
                    @if($appointment->doseLog->contrast_agent) • {{ $appointment->doseLog->contrast_agent }} {{ $appointment->doseLog->contrast_volume_ml }} mL @endif
                </td>
            </tr>
        @endif
    </table>

    @if($report->critical_flag)
        <div class="critical">CRITICAL FINDING — immediate clinician communication required.</div>
    @endif

    @if($report->type === 'addendum' && $parent)
        <h2>Original Report (v{{ $parent->version }}, signed {{ optional($parent->signed_at)->format('d M Y, h:i A') }})</h2>
        @if($parent->clinical_history)<p class="section-text"><strong>Clinical history:</strong> {{ $parent->clinical_history }}</p>@endif
        @if($parent->findings)<p class="section-text"><strong>Findings:</strong> {{ $parent->findings }}</p>@endif
        @if($parent->impression)<p class="section-text"><strong>Impression:</strong> {{ $parent->impression }}</p>@endif

        <h2 class="addendum">Addendum</h2>
        <div class="addendum-box section-text">{{ $report->findings }}</div>
        @if($report->recommendations)<p class="section-text"><strong>Recommendations:</strong> {{ $report->recommendations }}</p>@endif
    @else
        @if($report->clinical_history)<h2>Clinical History</h2><p class="section-text">{{ $report->clinical_history }}</p>@endif
        @if($report->technique)<h2>Technique</h2><p class="section-text">{{ $report->technique }}</p>@endif
        @if($report->comparison)<h2>Comparison</h2><p class="section-text">{{ $report->comparison }}</p>@endif

        @php
            $structured = \App\Support\ReportStructure::render($report->template?->structured_fields, $report->structured_values);
        @endphp
        @if($structured !== '')
            <h2>Structured Measurements &amp; Observations</h2>
            <table class="structured">
                <tr><th style="width: 42%;">Parameter</th><th>Recorded value</th></tr>
                @foreach(preg_split('/\r\n|\r|\n/', $structured) as $line)
                    @php [$key, $value] = array_pad(explode(': ', $line, 2), 2, ''); @endphp
                    <tr><td>{{ $key }}</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif

        @if($report->findings)<h2>Findings</h2><p class="section-text">{{ $report->findings }}</p>@endif
        @if($report->impression)<h2>Impression</h2><p class="section-text"><strong>{{ $report->impression }}</strong></p>@endif
        @if($report->recommendations)<h2>Recommendations</h2><p class="section-text">{{ $report->recommendations }}</p>@endif
    @endif

    @if($criticalLogs->isNotEmpty())
        <h2>Critical Result Communication Record</h2>
        <table class="structured">
            <tr><th>Communicated to</th><th>Method</th><th>Read-back</th><th>When</th></tr>
            @foreach($criticalLogs as $log)
                <tr>
                    <td>{{ $log->notified_to }}@if($log->notified_role) ({{ $log->notified_role }})@endif</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $log->method)) }}</td>
                    <td>{{ $log->read_back_verified ? 'Verified' : 'Not verified' }}</td>
                    <td>{{ $log->communicated_at?->format('d M Y, h:i A') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="signature">
        @if($report->isSigned())
            <div class="stamp">Electronically signed on {{ optional($report->signed_at)->format('d M Y, h:i A') }}</div>
        @endif
        <div class="signature-line">
            <div class="signature-name">{{ optional($signer)->name ?? 'Reporting Radiologist' }}</div>
            {{ optional($signer)->department ?: 'Consultant Radiologist' }}<br>
            @if($settings['name']) {{ $settings['name'] }} <br> @endif
            <span class="muted">
                Report {{ $report->id }} • v{{ $report->version }}@if($report->template_version) • template rev {{ $report->template_version }}@endif
                @if($settings['pmcRegistrationNo']) • PMC {{ $settings['pmcRegistrationNo'] }}@endif
            </span>
        </div>
    </div>

    <div class="footer">
        {{ $settings['reportLegalDisclaimer'] }}<br>
        {{ $settings['name'] }} — radiology report {{ $report->id }} (v{{ $report->version }}), generated {{ now()->format('d M Y, h:i A') }}. This document is computer-generated; the digital signature above is the authorising identity.
    </div>
</body>
</html>
