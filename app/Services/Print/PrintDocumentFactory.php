<?php

namespace App\Services\Print;

use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\CriticalFindingLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Modality;
use App\Models\RadiologyReport;
use App\Models\Referrer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TenantBranding;
use App\Services\ShiftLedgerService;
use App\Support\Print\Code128;
use App\Support\Print\PaperProfile;
use App\Support\Print\PrintArtifactRegistry;
use App\Support\Print\PrintDocument;
use App\Support\Print\PrintFormat;
use App\Support\Print\PrintSettings;
use App\Support\ReportStructure;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Builds the canonical print document model for every artifact.
 *
 * Nothing downstream is allowed to reach back into the database or invent a
 * number: a renderer receives finished strings ("Rs. 2,000.00", "21 Sep 2026,
 * 03:40 PM"), finished rows, and vector barcode geometry. That is what stops the
 * preview, the browser print and the server PDF from disagreeing.
 *
 * MONEY IS NEVER RECOMPUTED HERE. Totals come from the invoice's persisted
 * columns, which the billing service owns; this class only formats them. A
 * printed financial document that derives its own arithmetic is a second
 * accounting system.
 *
 * Tenant scoping is explicit (`business_id` on every query) and a document that
 * does not belong to the tenant throws — the caller maps that to 404, never to
 * another clinic's paper.
 */
final class PrintDocumentFactory
{
    private array $settings;

    private array $clinic;

    private string $currency;

    private string $dateFormat;

    /** @var array<string, mixed>|null */
    private ?array $logoCache = null;

    public function __construct(private readonly int $businessId)
    {
        $this->settings = PrintSettings::for($businessId);
        $this->clinic = ApiShape::clinicSettings($businessId);
        $this->currency = PrintSettings::currencyPrefix($businessId, $this->clinic['currencySymbol'] ?? null);
        $this->dateFormat = (string) $this->settings['dateFormat'];
    }

    /**
     * @param  array{reprint?: bool, countedCash?: float, supervisor?: string, notes?: string, shiftName?: string, cashier?: string, period?: string, requestedBy?: string, draft?: bool}  $options
     */
    public function build(string $artifact, string $id, ?string $paper = null, ?string $deviceId = null, array $options = []): PrintDocument
    {
        if (! PrintArtifactRegistry::exists($artifact)) {
            throw new InvalidArgumentException("Unknown print artifact [{$artifact}].");
        }

        $profile = PrintSettings::profile($this->businessId, $artifact, $paper, $deviceId);

        [$data, $lines] = match ($artifact) {
            'invoice' => $this->invoice($this->invoiceModel($id), $profile),
            'receipt' => $this->receipt($this->invoiceModel($id), $profile),
            // `draft` resolves the id as a STUDY, not a report: the reporting
            // workspace prints an unsaved buffer before any report row exists,
            // and it must still get the real A4 letterhead rather than a second
            // hand-built layout. The narrative is filled in by the client.
            'report' => ($options['draft'] ?? false)
                ? $this->draftReport($this->appointmentModel($id), $profile)
                : $this->report($this->reportModel($id), $profile),
            'token' => $this->token($this->appointmentModel($id), $profile),
            'label' => $this->label($this->appointmentModel($id), $profile),
            'manifest' => $this->manifest($id, $profile),
            'fee-schedule' => $this->feeSchedule($profile),
            'doctor-settlement' => $this->doctorSettlement($id, $profile, $options),
            'shift-closing' => $this->shiftClosing($id, $profile, $options),
        };

        if (($options['reprint'] ?? false) === true && $this->settings['markReprints']) {
            $data['marks'][] = ['code' => 'REPRINT', 'label' => 'REPRINT', 'tone' => 'warn'];
        }

        $data['lineCountEstimate'] = $lines;

        return new PrintDocument($artifact, $profile, $data);
    }

    // ==================== artifacts ====================

    /** @return array{0: array<string, mixed>, 1: int} */
    private function invoice(Invoice $invoice, PaperProfile $paper): array
    {
        $patient = $this->patientFor($invoice);
        $appointment = $invoice->appointment;
        $status = $this->invoiceStatus($invoice);

        $data = $this->shell(
            (string) $invoice->invoice_number,
            'Tax Invoice',
            'TAX INVOICE',
            $status,
            $invoice->status === Invoice::STATUS_VOID ? [['code' => 'VOID', 'label' => 'VOID', 'tone' => 'critical']] : [],
        );

        $data['meta'] = [
            ['label' => 'Invoice no', 'value' => (string) $invoice->invoice_number],
            ['label' => 'Issued', 'value' => PrintFormat::dateTime($invoice->issued_at ?? $invoice->created_at, $this->dateFormat)],
            ['label' => 'Token', 'value' => PrintFormat::text((string) ($appointment?->token_number ?? ''))],
            ['label' => 'Study date', 'value' => $appointment ? PrintFormat::dateTime($appointment->date ?? null, 'd M Y').' '.PrintFormat::text((string) ($appointment->time ?? ''), '') : '—'],
        ];

        $data['parties'] = [
            ['title' => 'Patient', 'rows' => $this->patientRows($patient, $invoice)],
            ['title' => 'Account', 'rows' => array_values(array_filter([
                ['label' => 'Referring doctor', 'value' => PrintFormat::text($appointment?->ReferrerData?->name, 'Self / Walk-in')],
                ['label' => 'Payment mode', 'value' => $invoice->payments->isNotEmpty()
                    ? $invoice->payments->map(fn ($p) => strtoupper((string) $p->method))->unique()->implode(', ')
                    : 'Pending'],
                $invoice->notes ? ['label' => 'Remarks / panel', 'value' => PrintFormat::narrative($invoice->notes)] : null,
                ['label' => 'Prepared by', 'value' => PrintFormat::text($invoice->created_by ? (string) \App\Models\User::query()->whereKey($invoice->created_by)->value('name') : '', 'Counter staff')],
            ]))],
        ];

        foreach ($invoice->items as $index => $item) {
            $data['items'][] = [
                'index' => $index + 1,
                'description' => PrintFormat::text((string) $item->description),
                'sub' => PrintFormat::text((string) ($item->serviceData?->code ?? ''), ''),
                'quantity' => PrintFormat::quantity($item->quantity),
                'unitPrice' => $this->money($item->unit_price),
                'discount' => ((float) $item->discount) > 0 ? '-'.$this->moneyCompact($item->discount) : '—',
                'lineTotal' => $this->money((float) ($item->line_total ?? $item->computeLineTotal())),
            ];
        }

        $data['totals'] = $this->invoiceTotals($invoice);

        foreach ($invoice->payments->sortBy('paid_at') as $payment) {
            $data['payments'][] = [
                'when' => PrintFormat::dateTime($payment->paid_at, $this->dateFormat),
                'method' => strtoupper(PrintFormat::text((string) $payment->method)),
                'reference' => PrintFormat::text((string) $payment->reference),
                'amount' => $this->money((float) $payment->amount),
                'isRefund' => ((float) $payment->amount) < 0,
            ];
        }

        $data['sections'] = [[
            'label' => 'Terms & conditions',
            'body' => implode("\n", [
                '1. Please retain this official computerized bill for collecting printed radiology reports and films.',
                '2. Online reports are accessible via the patient portal using the patient MRN.',
                '3. Diagnostic fee once paid is non-refundable once examination acquisition has commenced.',
            ]),
            'tone' => 'muted',
        ]];

        $data['signature'] = [
            'caption' => 'For '.$this->clinicName(),
            'name' => PrintFormat::text((string) ($this->clinic['name'] ?? ''), $this->clinicName()),
            'role' => 'Accounts Officer / Cashier',
            'lines' => array_values(array_filter([
                PrintFormat::text((string) ($this->clinic['taxId'] ?? ''), '') !== '—' ? 'Tax ID: '.$this->clinic['taxId'] : null,
            ])),
            'statement' => '',
            'reference' => '',
        ];

        $footer = PrintSettings::footerText($this->businessId, 'invoice', (string) ($this->clinic['invoiceFooterDisclaimer'] ?? ''));
        $data['footerNotes'] = array_values(array_filter([
            $footer,
            'Computer generated document — no physical signature required.',
        ]));

        $data['codes'] = [$this->code((string) $invoice->invoice_number, (string) $invoice->invoice_number, 0.26, 9.0, 60.0)];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function receipt(Invoice $invoice, PaperProfile $paper): array
    {
        $patient = $this->patientFor($invoice);
        $appointment = $invoice->appointment;

        $data = $this->shell((string) $invoice->invoice_number, 'Payment Receipt', 'PAYMENT RECEIPT', $this->invoiceStatus($invoice), []);

        $data['meta'] = [
            ['label' => 'Receipt', 'value' => (string) $invoice->invoice_number],
            ['label' => 'Token', 'value' => PrintFormat::text((string) ($appointment?->token_number ?? ''))],
            ['label' => 'Patient', 'value' => PrintFormat::text($patient?->name ?? $invoice->patient?->name)],
            ['label' => 'MRN', 'value' => PrintFormat::text($patient?->mrn)],
            ['label' => 'Date', 'value' => PrintFormat::dateTime($invoice->issued_at ?? $invoice->created_at, $this->dateFormat)],
        ];

        foreach ($invoice->items as $index => $item) {
            $data['items'][] = [
                'index' => $index + 1,
                'description' => PrintFormat::text((string) $item->description),
                'quantity' => PrintFormat::quantity($item->quantity),
                'lineTotal' => $this->moneyCompact((float) ($item->line_total ?? $item->computeLineTotal())),
            ];
        }

        $data['totals'] = array_map(function (array $row): array {
            $row['value'] = $this->moneyCompact((float) ($row['raw'] ?? 0));

            return $row;
        }, $this->invoiceTotals($invoice));

        foreach ($invoice->payments->sortBy('paid_at') as $payment) {
            $data['payments'][] = [
                'when' => PrintFormat::dateTime($payment->paid_at, 'd M Y h:i A'),
                'method' => strtoupper(PrintFormat::text((string) $payment->method)),
                'reference' => PrintFormat::text((string) $payment->reference),
                'amount' => $this->moneyCompact((float) $payment->amount),
                'isRefund' => ((float) $payment->amount) < 0,
            ];
        }

        $data['footerNotes'] = array_values(array_filter([
            PrintSettings::footerText($this->businessId, 'receipt', 'Keep this receipt for report dispatch.'),
            'Reports available on the patient portal with the MRN above.',
        ]));

        $data['codes'] = [$this->code((string) $invoice->invoice_number, (string) $invoice->invoice_number, 0.24, 8.0, 66.0)];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function report(RadiologyReport $report, PaperProfile $paper): array
    {
        $report->loadMissing(['appointment.ServiceData.modality', 'appointment.CustomerData', 'appointment.ReferrerData', 'appointment.doseLog', 'author', 'signer', 'template', 'parentReport']);
        $appointment = $report->appointment;
        $patient = $appointment?->CustomerData;
        $signed = $report->isSigned();
        $isAddendum = $report->type === 'addendum';

        $kind = match (true) {
            $isAddendum => 'ADDENDUM — REPORT v'.$report->version,
            $report->critical_flag => strtoupper(PrintFormat::text($report->type, 'draft')).' — CRITICAL',
            default => strtoupper(PrintFormat::text($report->type, 'draft')).' REPORT',
        };

        $marks = [];
        if (! $signed) {
            $marks[] = ['code' => 'DRAFT', 'label' => 'DRAFT — NOT SIGNED', 'tone' => 'critical'];
        }
        if ($report->critical_flag) {
            $marks[] = ['code' => 'CRITICAL', 'label' => 'CRITICAL FINDING', 'tone' => 'critical'];
        }

        $status = [
            'code' => $signed ? 'signed' : 'draft',
            'label' => $signed
                ? PrintFormat::text($report->statusLabel(), 'Signed').' v'.$report->version
                : 'Unsigned draft v'.$report->version,
            'tone' => $signed ? 'success' : 'warn',
        ];

        $data = $this->shell('R-'.$report->id.'-v'.$report->version, 'Radiology Report', $kind, $status, $marks);

        $data['meta'] = [
            ['label' => 'Report', 'value' => 'R-'.$report->id.' • v'.$report->version],
            ['label' => 'Accession / token', 'value' => PrintFormat::text((string) ($appointment?->token_number ?? ''))],
            ['label' => 'Study date', 'value' => PrintFormat::dateTime($appointment?->date ?? null, 'd M Y').' '.PrintFormat::text((string) ($appointment?->time ?? ''), '')],
        ];

        $data['parties'] = [
            ['title' => 'Patient', 'rows' => [
                ['label' => 'Patient', 'value' => PrintFormat::text($patient?->name ?? $appointment?->patientDisplayName())],
                ['label' => 'MRN', 'value' => PrintFormat::text($patient?->mrn)],
                ['label' => 'Age / gender', 'value' => PrintFormat::text($patient?->age !== null ? $patient->age.' y' : null).' / '.PrintFormat::text($patient?->gender ? ucfirst((string) $patient->gender) : null)],
                ['label' => 'Date of birth', 'value' => PrintFormat::date($patient?->dob ?? null, 'd M Y')],
            ]],
            ['title' => 'Study', 'rows' => [
                ['label' => 'Examination', 'value' => PrintFormat::text($appointment?->ServiceData?->name)],
                ['label' => 'Modality', 'value' => PrintFormat::text(($appointment?->ServiceData?->modality?->code ?? null), '—')],
                ['label' => 'Referred by', 'value' => PrintFormat::text($appointment?->ReferrerData?->name, 'Self / Walk-in')],
                ['label' => 'Priority', 'value' => strtoupper(PrintFormat::text((string) ($appointment?->priority ?? ''), 'Routine'))],
            ]],
        ];

        if ($appointment?->doseLog) {
            $dose = $appointment->doseLog;
            $data['notices'][] = [
                'tone' => 'muted',
                'text' => 'Dose record: '.$dose->dose_value.' '.$dose->dose_unit
                    .($dose->dlp_value ? ' • DLP '.$dose->dlp_value.' mGy·cm' : '')
                    .($dose->contrast_agent ? ' • '.$dose->contrast_agent.' '.$dose->contrast_volume_ml.' mL' : ''),
            ];
        }

        if ($report->critical_flag) {
            $data['notices'][] = [
                'tone' => 'critical',
                'text' => 'CRITICAL FINDING — immediate clinician communication required. Documented under the critical result communication record below.',
            ];
        }

        $structured = ReportStructure::render($report->template?->structured_fields, $report->structured_values);
        foreach (preg_split('/\r\n|\r|\n/', (string) $structured) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $value] = array_pad(explode(': ', $line, 2), 2, '');
            $data['observations'][] = ['label' => $label, 'value' => PrintFormat::text($value)];
        }

        $parent = $isAddendum ? $report->parentReport : null;
        if ($parent) {
            foreach ($this->reportSections($parent) as $section) {
                $section['group'] = 'Original Report (v'.$parent->version.' • signed '.PrintFormat::dateTime($parent->signed_at, $this->dateFormat).')';
                $data['sections'][] = $section;
            }
            $addendumBody = [];
            if (PrintFormat::narrative($report->findings) !== '') {
                $addendumBody[] = PrintFormat::narrative($report->findings);
            }
            $data['sections'][] = [
                'label' => 'Addendum',
                'body' => implode("\n\n", $addendumBody),
                'group' => 'Addendum',
            ];
            $data['sections'][] = [
                'label' => 'Recommendations',
                'body' => PrintFormat::narrative($report->recommendations),
                'group' => 'Addendum',
            ];
        } else {
            foreach ($this->reportSections($report) as $section) {
                $data['sections'][] = $section;
            }
        }

        $logs = CriticalFindingLog::where('business_id', $this->businessId)
            ->where('appointment_id', $report->appointment_id)
            ->orderByDesc('communicated_at')
            ->get();

        if ($logs->isNotEmpty()) {
            $data['criticalLogs'] = $logs->map(fn (CriticalFindingLog $log) => [
                'notifiedTo' => PrintFormat::text((string) $log->notified_to).(($log->notified_role ?? '') !== '' ? ' ('.$log->notified_role.')' : ''),
                'method' => strtoupper(str_replace('_', ' ', (string) $log->method)),
                'readBack' => $log->read_back_verified ? 'Verified' : 'Not verified',
                'when' => PrintFormat::dateTime($log->communicated_at, $this->dateFormat),
            ])->all();
            $data['criticalLogHeading'] = 'Critical Result Communication Record';
        }

        $signer = $report->signer ?? $report->author;
        $data['signature'] = [
            'statement' => $signed
                ? 'Electronically signed on '.PrintFormat::dateTimeWithZone($report->signed_at, $this->dateFormat)
                : 'Unsigned draft — the identity below is the reporting radiologist who authored this working copy.',
            'name' => $signed
                ? PrintFormat::text((string) $signer?->name)
                : PrintFormat::text((string) $signer?->name),
            'role' => PrintFormat::text((string) ($signer?->department ?? ''), 'Consultant Radiologist'),
            'lines' => array_values(array_filter([
                $this->clinicName(),
                PrintFormat::text((string) ($this->clinic['pmcRegistrationNo'] ?? ''), '') !== '—' ? 'PMC Reg: '.$this->clinic['pmcRegistrationNo'] : null,
            ])),
            'caption' => 'Electronic signature',
            'reference' => 'Report R-'.$report->id.' • v'.$report->version.($report->template_version ? ' • template rev '.$report->template_version : ''),
            'imageUrl' => null,
        ];

        $data['footerNotes'] = array_values(array_filter([
            PrintFormat::text((string) ($this->clinic['reportLegalDisclaimer'] ?? ''), ''),
            $this->clinicName().' — radiology report R-'.$report->id.' (v'.$report->version.'), generated '.PrintFormat::dateTime(now(), $this->dateFormat).'.',
        ]));

        $data['pageFoot'] = [
            'left' => $this->clinicName(),
            'center' => PrintFormat::text($patient?->name ?? $appointment?->patientDisplayName()).' • '.PrintFormat::text($patient?->mrn),
            'right' => 'R-'.$report->id.' v'.$report->version,
        ];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function token(Appointment $appointment, PaperProfile $paper): array
    {
        $appointment->loadMissing(['ServiceData.modality', 'CustomerData']);
        $patient = $appointment->CustomerData;
        $service = $appointment->ServiceData;

        $data = $this->shell((string) ($appointment->token_number ?? $appointment->id), 'Queue Slip', 'QUEUE TOKEN SLIP', [
            'code' => 'issued',
            'label' => 'Please wait for your token to be called',
            'tone' => 'default',
        ], []);

        $data['tokenNumber'] = PrintFormat::text((string) ($appointment->token_number ?? ''), '—');
        $data['roomLabel'] = PrintFormat::text((string) ($appointment->room_number ?? ''), '');

        $data['meta'] = [
            ['label' => 'Token', 'value' => PrintFormat::text((string) ($appointment->token_number ?? ''))],
            ['label' => 'Patient', 'value' => PrintFormat::text($patient?->name ?? $appointment->patientDisplayName())],
            ['label' => 'MRN', 'value' => PrintFormat::text($patient?->mrn)],
            ['label' => 'Examination', 'value' => PrintFormat::text($service?->name)],
            ['label' => 'Scheduled', 'value' => PrintFormat::date($appointment->date ?? null, 'd M Y').' '.PrintFormat::text((string) ($appointment->time ?? ''), '')],
            ['label' => 'Suite', 'value' => PrintFormat::text((string) ($appointment->room_number ?? ''), 'Reception')],
        ];

        $prep = PrintFormat::narrative($service?->preparation_instructions ?: 'Please proceed to the waiting lounge. The technologist will call your token number.');
        $data['notices'][] = ['tone' => 'muted', 'text' => 'Patient preparation: '.$prep];

        $data['footerNotes'] = array_values(array_filter([
            PrintSettings::footerText($this->businessId, 'receipt', ''),
            'Keep this slip with you until your examination is complete.',
        ]));

        $data['codes'] = [$this->code((string) ($appointment->token_number ?? $appointment->id), 'Token '.(string) ($appointment->token_number ?? ''), 0.3, 9.0, 66.0)];
        $data['lineCountEstimate'] = 22;

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function label(Appointment $appointment, PaperProfile $paper): array
    {
        $appointment->loadMissing(['ServiceData.modality', 'CustomerData']);
        $patient = $appointment->CustomerData;
        $mrn = (string) ($patient?->mrn ?? '');

        $data = $this->shell($mrn !== '' ? $mrn : (string) $appointment->id, 'Patient Label', 'PATIENT / FILM LABEL', null, []);

        $data['meta'] = [
            ['label' => 'Patient', 'value' => PrintFormat::text($patient?->name ?? $appointment->patientDisplayName())],
            ['label' => 'MRN / age / sex', 'value' => PrintFormat::text($mrn).' • '.PrintFormat::text($patient?->age !== null ? $patient->age.'y' : null).' '.strtoupper(PrintFormat::text((string) ($patient?->gender ?? ''), '-'))],
            ['label' => 'Study', 'value' => PrintFormat::text($appointment->ServiceData?->name)],
            ['label' => 'Token / modality', 'value' => PrintFormat::text((string) ($appointment->token_number ?? '')).' • '.PrintFormat::text((string) ($appointment->ServiceData?->modality?->code ?? ''), '—')],
        ];

        $encode = $mrn !== '' ? $mrn : (string) ($appointment->token_number ?? '');
        $data['codes'] = [$this->code($encode, $encode, 0.24, 6.0, 52.0)];
        $data['lineCountEstimate'] = 10;

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function manifest(string $date, PaperProfile $paper): array
    {
        $day = $this->validDate($date);

        $appointments = Appointment::query()
            ->where('business_id', $this->businessId)
            ->whereDate('date', $day)
            ->with(['CustomerData', 'ServiceData'])
            ->orderBy('token_number')
            ->get();

        $invoices = Invoice::query()
            ->where('business_id', $this->businessId)
            ->whereIn('appointment_id', $appointments->pluck('id')->all())
            ->get()
            ->keyBy('appointment_id');

        $data = $this->shell('manifest-'.$day, 'Daily Reception Manifest', 'DAILY FRONT DESK MANIFEST', [
            'code' => 'issued',
            'label' => $appointments->count().' studies registered',
            'tone' => 'default',
        ], []);

        $data['meta'] = [
            ['label' => 'Date', 'value' => PrintFormat::date($day, 'd M Y')],
            ['label' => 'Total registered', 'value' => (string) $appointments->count()],
            ['label' => 'Printed', 'value' => PrintFormat::dateTime(now(), $this->dateFormat)],
        ];

        $data['columns'] = [
            ['key' => 'token', 'label' => 'Token', 'align' => 'left', 'width' => '12%'],
            ['key' => 'mrn', 'label' => 'MRN', 'align' => 'left', 'width' => '16%'],
            ['key' => 'patient', 'label' => 'Patient name', 'align' => 'left', 'width' => '24%'],
            ['key' => 'procedure', 'label' => 'Procedure', 'align' => 'left', 'width' => '22%'],
            ['key' => 'slot', 'label' => 'Slot', 'align' => 'left', 'width' => '8%'],
            ['key' => 'payment', 'label' => 'Payment', 'align' => 'left', 'width' => '9%'],
            ['key' => 'signature', 'label' => 'Signature', 'align' => 'left', 'width' => '9%'],
        ];

        foreach ($appointments as $appointment) {
            $invoice = $invoices->get($appointment->id);
            $data['rows'][] = [
                'token' => PrintFormat::text((string) ($appointment->token_number ?? '')),
                'mrn' => PrintFormat::text((string) ($appointment->CustomerData?->mrn ?? '')),
                'patient' => PrintFormat::text($appointment->CustomerData?->name ?? $appointment->patientDisplayName()),
                'procedure' => PrintFormat::text($appointment->ServiceData?->name),
                'slot' => PrintFormat::text((string) ($appointment->time ?? '')),
                'payment' => $invoice === null
                    ? 'NOT BILLED'
                    : ((float) $invoice->balance_due <= 0 ? 'PAID' : 'DUE'),
                'signature' => '',
            ];
        }

        $data['signature'] = [
            'caption' => 'Clerk signature',
            'name' => 'Reception clerk',
            'role' => 'Front desk hand-over',
            'lines' => [],
            'statement' => '',
            'reference' => '',
        ];

        $data['pageFoot'] = [
            'left' => $this->clinicName(),
            'center' => 'Reception manifest — '.PrintFormat::date($day, 'd M Y'),
            'right' => (string) $appointments->count().' studies',
        ];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function feeSchedule(PaperProfile $paper): array
    {
        $modalities = Modality::query()
            ->where('business_id', $this->businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $services = Service::query()
            ->where('business_id', $this->businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $data = $this->shell('fee-schedule', 'Official Fee Schedule', 'OFFICIAL FEE SCHEDULE', [
            'code' => 'issued',
            'label' => $services->count().' procedures',
            'tone' => 'default',
        ], []);

        $data['meta'] = [
            ['label' => 'Effective', 'value' => PrintFormat::date(now(), 'd M Y')],
            ['label' => 'Currency', 'value' => $this->currency],
        ];

        $data['columns'] = [
            ['key' => 'code', 'label' => 'Code', 'align' => 'left', 'width' => '14%'],
            ['key' => 'description', 'label' => 'Procedure description', 'align' => 'left', 'width' => '46%'],
            ['key' => 'duration', 'label' => 'Duration', 'align' => 'left', 'width' => '14%'],
            ['key' => 'prep', 'label' => 'Preparation', 'align' => 'left', 'width' => '12%'],
            ['key' => 'fee', 'label' => 'Fee', 'align' => 'right', 'width' => '14%'],
        ];

        foreach ($modalities as $modality) {
            $rows = $services->where('modality_id', $modality->id);
            if ($rows->isEmpty()) {
                continue;
            }
            foreach ($rows as $service) {
                $data['rows'][] = [
                    'code' => PrintFormat::text((string) $service->code),
                    'description' => PrintFormat::text((string) $service->name),
                    'duration' => $service->slotMinutes().' min',
                    'prep' => PrintFormat::text((string) ($service->preparation_instructions ?? ''), '—'),
                    'fee' => $this->money($service->price),
                    'group' => $modality->name.' ('.$modality->code.')',
                ];
            }
        }

        $data['pageFoot'] = [
            'left' => $this->clinicName(),
            'center' => 'Official fee schedule',
            'right' => PrintFormat::date(now(), 'd M Y'),
        ];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function doctorSettlement(string $referrerId, PaperProfile $paper, array $options): array
    {
        $referrer = Referrer::query()
            ->where('business_id', $this->businessId)
            ->findOrFail($this->numericId($referrerId));

        $period = $this->validPeriod($options['period'] ?? null);
        [$start, $end] = [Carbon::parse($period.'-01')->startOfMonth(), Carbon::parse($period.'-01')->endOfMonth()];

        $commissionPercent = (float) ($this->clinic['referralCommissionPercent'] ?? 0);

        $appointments = Appointment::query()
            ->where('business_id', $this->businessId)
            ->where('referrer_id', $referrer->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with(['CustomerData', 'ServiceData'])
            ->orderBy('date')
            ->get();

        $invoices = Invoice::query()
            ->where('business_id', $this->businessId)
            ->whereIn('appointment_id', $appointments->pluck('id')->all())
            ->get()
            ->keyBy('appointment_id');

        $data = $this->shell('settlement-'.$referrer->id.'-'.$period, 'Referral Settlement', 'REFERRAL SETTLEMENT SHEET', [
            'code' => 'issued',
            'label' => $period,
            'tone' => 'default',
        ], []);

        $data['meta'] = [
            ['label' => 'Referrer', 'value' => PrintFormat::text((string) $referrer->name)],
            ['label' => 'Clinic', 'value' => PrintFormat::text((string) ($referrer->clinic ?? ''))],
            ['label' => 'Specialty', 'value' => PrintFormat::text((string) ($referrer->specialty ?? ''))],
            ['label' => 'Period', 'value' => $start->format('d M Y').' — '.$end->format('d M Y')],
            ['label' => 'Commission', 'value' => PrintFormat::number($commissionPercent, 2).'% of billed fee'],
        ];

        $data['columns'] = [
            ['key' => 'date', 'label' => 'Date', 'align' => 'left', 'width' => '12%'],
            ['key' => 'token', 'label' => 'Token', 'align' => 'left', 'width' => '10%'],
            ['key' => 'patient', 'label' => 'Patient', 'align' => 'left', 'width' => '28%'],
            ['key' => 'investigation', 'label' => 'Investigation', 'align' => 'left', 'width' => '24%'],
            ['key' => 'fee', 'label' => 'Fee', 'align' => 'right', 'width' => '13%'],
            ['key' => 'share', 'label' => 'Referral share', 'align' => 'right', 'width' => '13%'],
        ];

        $totalFee = 0.0;
        $totalShare = 0.0;
        foreach ($appointments as $appointment) {
            $invoice = $invoices->get($appointment->id);
            $fee = (float) ($invoice?->total ?? $appointment->ServiceData?->price ?? 0);
            $share = round($fee * $commissionPercent / 100, 2);
            $totalFee += $fee;
            $totalShare += $share;

            $data['rows'][] = [
                'date' => PrintFormat::date($appointment->date ?? null, 'd M Y'),
                'token' => PrintFormat::text((string) ($appointment->token_number ?? '')),
                'patient' => PrintFormat::text($appointment->CustomerData?->name ?? $appointment->patientDisplayName()),
                'investigation' => PrintFormat::text($appointment->ServiceData?->name),
                'fee' => $this->money($fee),
                'share' => $this->money($share),
            ];
        }

        $data['totals'] = [
            ['label' => 'Studies in period', 'value' => (string) $appointments->count(), 'emphasis' => false, 'tone' => 'default'],
            ['label' => 'Total billed fee', 'value' => $this->money($totalFee), 'emphasis' => false, 'tone' => 'default'],
            ['label' => 'Total referral share payable', 'value' => $this->money($totalShare), 'emphasis' => true, 'tone' => 'default'],
        ];

        $data['signature'] = [
            'caption' => 'Accounts division',
            'name' => PrintFormat::text((string) ($options['requestedBy'] ?? ''), 'Accounts Division'),
            'role' => 'Authorised signatory',
            'lines' => [$this->clinicName()],
            'statement' => 'Statement generated from persisted study and invoice records for the period above.',
            'reference' => 'Referrer R-'.$referrer->id.' • '.$period,
        ];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function shiftClosing(string $date, PaperProfile $paper, array $options): array
    {
        $day = $this->validDate($date);
        $ledger = app(ShiftLedgerService::class)->forDay($this->businessId, $day);

        $cashExpected = app(ShiftLedgerService::class)->cashExpected($ledger);
        $counted = (float) ($options['countedCash'] ?? 0);
        $discrepancy = round($counted - $cashExpected, 2);

        $data = $this->shell('shift-'.$day, 'Shift Cash Closing', 'SHIFT CASH CLOSING & RECONCILIATION', [
            'code' => $discrepancy === 0.0 ? 'balanced' : ($discrepancy > 0 ? 'surplus' : 'shortage'),
            'label' => $discrepancy === 0.0
                ? 'Drawer balanced'
                : ($discrepancy > 0 ? 'Surplus '.$this->moneyCompact($discrepancy) : 'Shortage '.$this->moneyCompact(abs($discrepancy))),
            'tone' => $discrepancy === 0.0 ? 'success' : ($discrepancy > 0 ? 'warn' : 'critical'),
        ], []);

        $data['meta'] = [
            ['label' => 'Shift / date', 'value' => PrintFormat::text((string) ($options['shiftName'] ?? ''), 'Counter shift').' — '.PrintFormat::date($day, 'd M Y')],
            ['label' => 'Cashier', 'value' => PrintFormat::text((string) ($options['cashier'] ?? ''), 'Counter cashier')],
            ['label' => 'Supervisor', 'value' => PrintFormat::text((string) ($options['supervisor'] ?? ''), 'Accounts supervisor')],
            ['label' => 'Closed at', 'value' => PrintFormat::dateTime(now(), $this->dateFormat)],
            ['label' => 'Invoices handled', 'value' => (string) $ledger['invoiceCount']],
            ['label' => 'Payments recorded', 'value' => (string) $ledger['paymentCount']],
        ];

        $data['columns'] = [
            ['key' => 'channel', 'label' => 'Collection channel', 'align' => 'left', 'width' => '58%'],
            ['key' => 'count', 'label' => 'Count', 'align' => 'left', 'width' => '14%'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'right', 'width' => '28%'],
        ];

        foreach ($ledger['byMethod'] as $row) {
            $data['rows'][] = [
                'channel' => strtoupper((string) $row['method']),
                'count' => (string) $row['count'],
                'amount' => $this->money((float) $row['total']),
            ];
        }

        $data['totals'] = [
            ['label' => 'Total collected in shift', 'value' => $this->money($ledger['totalCollected']), 'emphasis' => true, 'tone' => 'default'],
            ['label' => 'Refunds issued', 'value' => $this->moneyDelta($ledger['refundedTotal']), 'emphasis' => false, 'tone' => ($ledger['refundedTotal'] < 0 ? 'warn' : 'default')],
            ['label' => 'Cash expected in drawer', 'value' => $this->money($cashExpected), 'emphasis' => false, 'tone' => 'default'],
            ['label' => 'Cash counted (declared)', 'value' => $this->money($counted), 'emphasis' => false, 'tone' => 'default'],
            ['label' => 'Variance', 'value' => $this->moneyDelta($discrepancy), 'emphasis' => true, 'tone' => $discrepancy === 0.0 ? 'success' : ($discrepancy > 0 ? 'warn' : 'critical')],
        ];

        $data['sections'] = array_values(array_filter([[
            'label' => 'Shift remarks',
            'body' => PrintFormat::narrative($options['notes'] ?? ''),
            'tone' => 'muted',
        ], [
            'label' => 'Basis of preparation',
            'body' => implode("\n", [
                'Collections, refunds and the cash position are derived from the persisted payment ledger for '.PrintFormat::date($day, 'd M Y').'.',
                'The counted drawer amount is declared by the cashier at closing and is not a system-calculated value.',
            ]),
            'tone' => 'muted',
        ]]));

        $data['signature'] = [
            'caption' => 'Hand-over',
            'name' => PrintFormat::text((string) ($options['cashier'] ?? ''), 'Shift cashier'),
            'role' => 'Shift cashier — counted & handed over',
            'lines' => ['Supervisor: '.PrintFormat::text((string) ($options['supervisor'] ?? ''), 'Accounts supervisor')],
            'statement' => '',
            'reference' => 'Shift ledger '.$day,
        ];

        $data['pageFoot'] = [
            'left' => $this->clinicName(),
            'center' => 'Shift cash closing — '.PrintFormat::date($day, 'd M Y'),
            'right' => PrintFormat::text((string) ($options['shiftName'] ?? ''), 'Counter shift'),
        ];

        return [$data, $this->estimateLines($data, $paper)];
    }

    /**
     * An A4 report scaffold for a study whose report has not been saved yet.
     *
     * The client replaces the narrative sections, the marks and the signature
     * with the editor buffer; everything else (letterhead, patient identity,
     * study context, paper geometry, stylesheet) is the server's, so a draft
     * proof print is the final document's layout marked DRAFT rather than a
     * different design that happens to be printed before signing.
     *
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function draftReport(Appointment $appointment, PaperProfile $paper): array
    {
        $appointment->loadMissing(['ServiceData.modality', 'CustomerData', 'ReferrerData', 'doseLog']);
        $patient = $appointment->CustomerData;

        $data = $this->shell(
            'study-'.$appointment->id.'-draft',
            'Radiology Report',
            'DRAFT REPORT',
            ['code' => 'draft', 'label' => 'Unsigned draft — not a filed report', 'tone' => 'warn'],
            [['code' => 'DRAFT', 'label' => 'DRAFT — NOT SIGNED', 'tone' => 'critical']],
        );

        $data['meta'] = [
            ['label' => 'Report', 'value' => 'Unsaved working copy'],
            ['label' => 'Accession / token', 'value' => PrintFormat::text((string) ($appointment->token_number ?? ''))],
            ['label' => 'Study date', 'value' => PrintFormat::dateTime($appointment->date ?? null, 'd M Y').' '.PrintFormat::text((string) ($appointment->time ?? ''), '')],
        ];

        $data['parties'] = [
            ['title' => 'Patient', 'rows' => [
                ['label' => 'Patient', 'value' => PrintFormat::text($patient?->name ?? $appointment->patientDisplayName())],
                ['label' => 'MRN', 'value' => PrintFormat::text($patient?->mrn)],
                ['label' => 'Age / gender', 'value' => PrintFormat::text($patient?->age !== null ? $patient->age.' y' : null).' / '.PrintFormat::text($patient?->gender ? ucfirst((string) $patient->gender) : null)],
                ['label' => 'Date of birth', 'value' => PrintFormat::date($patient?->dob ?? null, 'd M Y')],
            ]],
            ['title' => 'Study', 'rows' => [
                ['label' => 'Examination', 'value' => PrintFormat::text($appointment->ServiceData?->name)],
                ['label' => 'Modality', 'value' => PrintFormat::text(($appointment->ServiceData?->modality?->code ?? null), '—')],
                ['label' => 'Referred by', 'value' => PrintFormat::text($appointment->ReferrerData?->name, 'Self / Walk-in')],
                ['label' => 'Priority', 'value' => strtoupper(PrintFormat::text((string) ($appointment->priority ?? ''), 'Routine'))],
            ]],
        ];

        $data['notices'][] = [
            'tone' => 'critical',
            'text' => 'This is an UNSAVED working copy. It is not a signed report and must not be filed, dispatched or acted on as one.',
        ];

        $data['footerNotes'] = array_values(array_filter([
            PrintFormat::text((string) ($this->clinic['reportLegalDisclaimer'] ?? ''), ''),
            $this->clinicName().' — draft proof print, generated '.PrintFormat::dateTime(now(), $this->dateFormat).'.',
        ]));

        $data['signature'] = [
            'statement' => 'Not signed — draft working copy.',
            'name' => PrintFormat::text(auth()->user()?->name, 'Reporting radiologist'),
            'role' => 'Consultant Radiologist',
            'lines' => [$this->clinicName()],
            'caption' => 'Electronic signature',
            'reference' => 'Study #'.(string) ($appointment->token_number ?? $appointment->id),
        ];

        $data['pageFoot'] = [
            'left' => $this->clinicName(),
            'center' => PrintFormat::text($patient?->name ?? $appointment->patientDisplayName()).' • '.PrintFormat::text($patient?->mrn),
            'right' => 'STUDY-'.$appointment->id.' DRAFT',
        ];

        return [$data, $this->estimateLines($data, $paper)];
    }

    // ==================== shared pieces ====================

    /** @return list<array{label: string, value: string}> */
    private function patientRows(?Customer $patient, Invoice $invoice): array
    {
        $user = $invoice->patient;

        return array_values(array_filter([
            ['label' => 'Name', 'value' => PrintFormat::text($patient?->name ?? $user?->name)],
            ['label' => 'MRN', 'value' => PrintFormat::text($patient?->mrn)],
            ['label' => 'Age / sex', 'value' => PrintFormat::text($patient?->age !== null ? $patient->age.' years' : null).' / '.strtoupper(PrintFormat::text((string) ($patient?->gender ?? ''), '—'))],
            ['label' => 'Date of birth', 'value' => PrintFormat::date($patient?->dob ?? null, 'd M Y')],
            ['label' => 'Contact', 'value' => PrintFormat::text((string) ($patient?->phone ?? $user?->mobile_no ?? ''))],
            ['label' => 'CNIC', 'value' => PrintFormat::text((string) ($patient?->cnic ?? ''), '')],
        ]));
    }

    /** @return list<array{label: string, value: string, raw: float, emphasis: bool, tone: string}> */
    private function invoiceTotals(Invoice $invoice): array
    {
        $rows = [[
            'label' => 'Subtotal',
            'value' => $this->money($invoice->subtotal),
            'raw' => (float) $invoice->subtotal,
            'emphasis' => false,
            'tone' => 'default',
        ]];

        if ((float) $invoice->discount_total > 0) {
            $rows[] = [
                'label' => 'Discount / concession',
                'value' => '-'.$this->moneyCompact($invoice->discount_total),
                'raw' => -1 * (float) $invoice->discount_total,
                'emphasis' => false,
                'tone' => 'success',
            ];
        }

        if ((float) $invoice->tax_amount > 0) {
            $rows[] = [
                'label' => 'Tax ('.PrintFormat::number($invoice->tax_rate, 2).'%)',
                'value' => $this->money($invoice->tax_amount),
                'raw' => (float) $invoice->tax_amount,
                'emphasis' => false,
                'tone' => 'default',
            ];
        }

        $rows[] = ['label' => 'Net total payable', 'value' => $this->money($invoice->total), 'raw' => (float) $invoice->total, 'emphasis' => true, 'tone' => 'default'];
        $rows[] = ['label' => 'Amount paid', 'value' => $this->money($invoice->paid_total), 'raw' => (float) $invoice->paid_total, 'emphasis' => false, 'tone' => 'success'];
        $rows[] = [
            'label' => 'Balance due',
            'value' => $this->money($invoice->balance_due),
            'raw' => (float) $invoice->balance_due,
            'emphasis' => true,
            'tone' => ((float) $invoice->balance_due > 0 ? 'warn' : 'success'),
        ];

        return $rows;
    }

    /** @return array{code: string, label: string, tone: string} */
    private function invoiceStatus(Invoice $invoice): array
    {
        return match ($invoice->status) {
            Invoice::STATUS_PAID => ['code' => 'paid', 'label' => (float) $invoice->total <= 0 ? 'WAIVED / PAID IN FULL' : 'PAID IN FULL', 'tone' => 'success'],
            Invoice::STATUS_PARTIAL => ['code' => 'partial', 'label' => 'PARTIALLY PAID', 'tone' => 'warn'],
            Invoice::STATUS_VOID => ['code' => 'void', 'label' => 'VOID', 'tone' => 'critical'],
            Invoice::STATUS_DRAFT => ['code' => 'draft', 'label' => 'DRAFT — NOT ISSUED', 'tone' => 'warn'],
            default => ['code' => 'issued', 'label' => 'OUTSTANDING', 'tone' => 'critical'],
        };
    }

    /**
     * Report narrative sections, in clinical order. Text is preserved verbatim
     * (line breaks included) — a printed report must not silently reformat the
     * radiologist's measurements, lists or symbols.
     *
     * @return list<array{label: string, body: string, emphasis?: bool, tone?: string}>
     */
    private function reportSections(RadiologyReport $report): array
    {
        $sections = [];

        foreach ([
            ['Clinical indication', $report->clinical_history, false],
            ['Technique', $report->technique, false],
            ['Comparison', $report->comparison, false],
            ['Findings', $report->findings, false],
            ['Impression', $report->impression, true],
            ['Recommendations', $report->recommendations, false],
        ] as [$label, $body, $emphasis]) {
            $text = PrintFormat::narrative($body);
            if ($text === '') {
                continue;
            }
            $sections[] = [
                'label' => $label,
                'body' => $text,
                'emphasis' => $emphasis,
                'tone' => $emphasis ? 'accent' : 'default',
            ];
        }

        return $sections;
    }

    /** @return array<string, mixed> */
    private function shell(string $key, string $title, string $kindLabel, ?array $status, array $marks): array
    {
        return [
            'documentKey' => $key,
            'title' => $title,
            'kindLabel' => $kindLabel,
            'status' => $status,
            'marks' => array_values($marks),
            'branding' => $this->branding(),
            'generatedAt' => PrintFormat::dateTime(now(), $this->dateFormat),
            'meta' => [],
            'parties' => [],
            'items' => [],
            'totals' => [],
            'payments' => [],
            'sections' => [],
            'observations' => [],
            'criticalLogs' => [],
            'codes' => [],
            'rows' => [],
            'columns' => [],
            'notices' => [],
            'footerNotes' => [],
            'signature' => null,
            'pageFoot' => ['left' => $this->clinicName(), 'center' => '', 'right' => $key],
            'options' => [
                'showLogo' => (bool) $this->settings['showLogo'],
                'showBarcode' => (bool) $this->settings['showBarcode'],
                'showSignature' => (bool) $this->settings['showSignature'],
                'currency' => $this->currency,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function branding(): array
    {
        $clinic = $this->clinic;
        $addressLine = trim(implode(', ', array_filter([(string) ($clinic['address'] ?? ''), (string) ($clinic['city'] ?? '')])));

        $contact = [];
        if (($clinic['phone'] ?? '') !== '') {
            $contact[] = 'Tel: '.$clinic['phone'];
        }
        if (($clinic['emergencyPhone'] ?? '') !== '') {
            $contact[] = 'Emergency: '.$clinic['emergencyPhone'];
        }
        if (($clinic['email'] ?? '') !== '') {
            $contact[] = $clinic['email'];
        }
        if (($clinic['website'] ?? '') !== '') {
            $contact[] = $clinic['website'];
        }

        $logo = $this->logo();

        return [
            'name' => $this->clinicName(),
            'shortName' => $this->clinicName(),
            'tagline' => PrintFormat::text((string) ($clinic['headerTagline'] ?? ''), ''),
            'branch' => PrintFormat::text((string) ($clinic['branch'] ?? ''), ''),
            'addressLine' => $addressLine,
            'contactLine' => implode(' • ', $contact),
            'registrations' => array_values(array_filter([
                ($clinic['pmcRegistrationNo'] ?? '') !== '' ? 'PMC Reg: '.$clinic['pmcRegistrationNo'] : null,
                ($clinic['pnraLicenseNo'] ?? '') !== '' ? 'PNRA: '.$clinic['pnraLicenseNo'] : null,
                ($clinic['taxId'] ?? '') !== '' ? 'Tax ID: '.$clinic['taxId'] : null,
            ])),
            'logoUrl' => $logo['url'],
            'logoDataUri' => $logo['dataUri'],
            'reportHeader' => PrintFormat::text((string) ($logo['reportHeader'] ?? ''), ''),
            'reportFooter' => PrintFormat::text((string) ($logo['reportFooter'] ?? ''), ''),
            'currency' => $this->currency,
        ];
    }

    /**
     * Signed tenant branding: a public URL for the browser and a data URI for the
     * PDF engines (DomPDF cannot fetch a URL, and a broken remote reference must
     * never degrade a clinical document).
     *
     * @return array{url: string|null, dataUri: string|null, reportHeader: string|null, reportFooter: string|null}
     */
    private function logo(): array
    {
        if ($this->logoCache !== null) {
            return $this->logoCache;
        }

        $branding = TenantBranding::where('business_id', $this->businessId)->first();
        $url = $branding?->logo_url;
        $dataUri = null;

        if (! empty($url)) {
            $relative = ltrim((string) (parse_url((string) $url, PHP_URL_PATH) ?: ''), '/');

            foreach ([public_path($relative), storage_path('app/public/'.$relative)] as $candidate) {
                if ($relative !== '' && is_file($candidate)) {
                    $mime = mime_content_type($candidate) ?: 'image/png';
                    // An unbounded logo would bloat every document; 2 MB is
                    // generous for a letterhead mark.
                    if (filesize($candidate) <= 2 * 1024 * 1024) {
                        $dataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($candidate));
                    }
                    break;
                }
            }
        }

        return $this->logoCache = [
            'url' => $url ? (string) $url : null,
            'dataUri' => $dataUri,
            'reportHeader' => $branding?->report_header,
            'reportFooter' => $branding?->report_footer,
        ];
    }

    /** @return array{kind: string, value: string, label: string, svg: string, dataUri: string} */
    private function code(string $value, string $label, float $moduleMm, float $heightMm, float $maxWidthMm): array
    {
        $clean = Code128::sanitize($value);

        return [
            'kind' => 'barcode',
            'value' => $clean,
            'label' => $label,
            'svg' => Code128::svg($clean, $moduleMm, $heightMm, 10, $maxWidthMm),
            'dataUri' => Code128::dataUri($clean, $moduleMm, $heightMm, 10, $maxWidthMm),
        ];
    }

    private function money(float|int|string|null $amount): string
    {
        return PrintFormat::money($amount, $this->currency);
    }

    private function moneyCompact(float|int|string|null $amount): string
    {
        return PrintFormat::moneyCompact($amount, $this->currency);
    }

    /**
     * A signed amount. A refund is shown as `-Rs. 1,500.00` and a shortfall in
     * the drawer as `-Rs. 200.00`: the sign carries the meaning, so the document
     * never needs colour to say that money went out.
     */
    private function moneyDelta(float|int|string|null $amount): string
    {
        return PrintFormat::moneyDelta($amount, $this->currency);
    }

    private function clinicName(): string
    {
        return PrintFormat::text((string) ($this->clinic['name'] ?? ''), 'Diagnostic Centre');
    }

    private function invoiceModel(string $id): Invoice
    {
        $invoice = Invoice::query()
            ->where('business_id', $this->businessId)
            ->with(['items.serviceData', 'payments', 'patient', 'appointment.ReferrerData', 'appointment.CustomerData'])
            ->find($this->numericId($id));

        if (! $invoice) {
            throw new InvalidArgumentException('Invoice not found in this tenant.');
        }

        return $invoice;
    }

    private function reportModel(string $id): RadiologyReport
    {
        $report = RadiologyReport::query()
            ->where('business_id', $this->businessId)
            ->find($this->numericId($id));

        if (! $report) {
            throw new InvalidArgumentException('Report not found in this tenant.');
        }

        return $report;
    }

    private function appointmentModel(string $id): Appointment
    {
        $appointment = Appointment::query()
            ->where('business_id', $this->businessId)
            ->with(['ServiceData.modality', 'CustomerData'])
            ->find($this->numericId($id));

        if (! $appointment) {
            throw new InvalidArgumentException('Study not found in this tenant.');
        }

        return $appointment;
    }

    private function patientFor(Invoice $invoice): ?Customer
    {
        if ($invoice->appointment?->CustomerData) {
            return $invoice->appointment->CustomerData;
        }

        return Customer::where('business_id', $this->businessId)
            ->where('user_id', $invoice->patient_id)
            ->first();
    }

    private function numericId(string $id): int
    {
        if (! ctype_digit($id)) {
            throw new InvalidArgumentException('Print document id must be numeric.');
        }

        return (int) $id;
    }

    private function validDate(string $date): string
    {
        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid document date.');
        }
    }

    private function validPeriod(?string $period): string
    {
        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            return $period;
        }

        return now()->format('Y-m');
    }

    /**
     * A rough line count, used only to size a roll-paper PDF page (DomPDF has no
     * `auto` page height). Over-estimating costs a little blank paper at the
     * foot of a receipt; under-estimating would clip content, so the estimate is
     * deliberately generous.
     */
    private function estimateLines(array $data, PaperProfile $paper): int
    {
        $lines = count($data['meta'] ?? []);
        $lines += count($data['parties'] ?? []) * 2;
        $lines += count($data['items'] ?? []) * 2;
        $lines += count($data['totalLines'] ?? []);
        $lines += count($data['totals'] ?? []) * 1.5;
        $lines += count($data['payments'] ?? []) * 2;
        $lines += count($data['rows'] ?? []) * 2;
        $lines += count($data['observations'] ?? []);
        $lines += count($data['criticalLogs'] ?? []) * 2;

        foreach ($data['sections'] ?? [] as $section) {
            $body = (string) ($section['body'] ?? '');
            $lines += 2 + max(1, (int) ceil(strlen($body) / 78));
        }

        foreach (($data['notices'] ?? []) as $notice) {
            $lines += 1 + (int) ceil(strlen((string) ($notice['text'] ?? '')) / 40);
        }

        $lines += count($data['footerNotes'] ?? []);
        $lines += 6; // letterhead, rules, signature block

        if ($paper->paper() === PaperProfile::A4) {
            return (int) $lines;
        }

        return (int) ceil($lines);
    }
}
