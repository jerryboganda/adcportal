<?php

namespace App\Http\Resources;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DicomNode;
use App\Models\DoctorDispatchLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\AdverseReaction;
use App\Models\Invoice;
use App\Models\Modality;
use App\Models\RisNotificationTemplate;
use App\Models\Referrer;
use App\Models\ReportRelease;
use App\Models\ReportTemplate;
use App\Models\RadiologyReport;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StudyScreeningAnswer;
use App\Models\User;
use App\Models\DoseLog;
use App\Models\Plan;
use Carbon\Carbon;

/**
 * The single source of truth for the React portal's API contract.
 *
 * Every transformer below emits EXACTLY the shapes declared in
 * `src/types.ts` of the SPA: string identifiers, camelCase fields, and
 * display-formatted timestamps matching the conventions the views render.
 */
class ApiShape
{
    // ==================== primitives ====================

    public static function id($value): string
    {
        return (string) $value;
    }

    public static function time(?$dt): ?string
    {
        return $dt ? Carbon::parse($dt)->format('h:i A') : null;
    }

    public static function dateTime(?$dt): ?string
    {
        return $dt ? Carbon::parse($dt)->format('d M, h:i A') : null;
    }

    public static function human($dt): ?string
    {
        return $dt ? Carbon::parse($dt)->diffForHumans(short: true) : null;
    }

    // ==================== catalog / masters ====================

    public static function modality(Modality $m): array
    {
        return [
            'id' => self::id($m->id),
            'name' => $m->name,
            'code' => $m->code,
            'color' => $m->color ?: '#0080b6',
            'bufferMinutes' => (int) $m->buffer_minutes,
            'isActive' => (bool) $m->is_active,
        ];
    }

    public static function service(Service $s): array
    {
        return [
            'id' => self::id($s->id),
            'name' => $s->name,
            'code' => $s->code ?? '',
            'modalityId' => (int) $s->modality_id,
            'price' => (float) $s->price,
            'durationMinutes' => (int) ($s->duration_minutes ?? 15),
            'preparationInstructions' => (string) ($s->preparation_instructions ?? ''),
            'requiresScreening' => (bool) $s->requires_screening,
            'requiresContrast' => $s->contrast_type !== 'none',
        ];
    }

    public static function referrer(Referrer $r): array
    {
        return [
            'id' => self::id($r->id),
            'name' => $r->name,
            'clinicName' => (string) ($r->clinic ?? ''),
            'email' => (string) ($r->email ?? ''),
            'phone' => (string) ($r->phone ?? ''),
            'specialty' => (string) ($r->specialty ?? ''),
        ];
    }

    public static function patient(Customer $c): array
    {
        return [
            'id' => self::id($c->id),
            'mrn' => (string) ($c->mrn ?? ''),
            'name' => $c->name,
            'email' => (string) ($c->email ?? ''),
            'phone' => (string) ($c->phone ?? ''),
            'gender' => in_array($c->gender, ['male', 'female', 'other']) ? $c->gender : 'other',
            'dob' => $c->dob ? Carbon::parse($c->dob)->format('Y-m-d') : '',
            'age' => (int) ($c->age ?? 0),
            'bloodGroup' => (string) ($c->blood_group ?? ''),
            'medicalHistory' => (string) ($c->chronic_conditions ?? $c->description ?? ''),
            'allergies' => (string) ($c->allergies ?? ''),
        ];
    }

    public static function screeningForm(ScreeningForm $f): array
    {
        return [
            'id' => self::id($f->id),
            'name' => $f->name,
            'slug' => $f->slug,
            'description' => (string) ($f->description ?? ''),
            'modalityId' => $f->modality_id !== null ? (int) $f->modality_id : null,
            'questions' => $f->relationLoaded('questions')
                ? $f->questions->map(fn ($q) => self::screeningQuestion($q, $f->id))->all()
                : [],
            'isActive' => (bool) $f->is_active,
        ];
    }

    public static function screeningQuestion(ScreeningQuestion $q, $formId): array
    {
        return [
            'id' => self::id($q->id),
            'formId' => self::id($formId),
            'questionText' => $q->question_text,
            'helpText' => $q->help_text,
            'answerType' => $q->answer_type,
            'riskValue' => $q->risk_value,
            'isRiskBlocking' => (bool) $q->is_risk_blocking,
            'options' => $q->options,
            'sortOrder' => (int) $q->sort_order,
        ];
    }

    public static function reportTemplate(ReportTemplate $t): array
    {
        return [
            'id' => self::id($t->id),
            'name' => $t->name,
            'code' => null,
            'modalityId' => (int) ($t->modality_id ?? 0),
            'clinicalHistory' => (string) ($t->clinical_history ?? ''),
            'technique' => (string) ($t->technique ?? ''),
            'findings' => (string) ($t->findings ?? ''),
            'impression' => (string) ($t->impression ?? ''),
            'recommendations' => (string) ($t->recommendations ?? ''),
        ];
    }

    // ==================== studies (appointments) ====================

    public static function studyScreeningAnswer(StudyScreeningAnswer $a): array
    {
        return [
            'appointmentId' => self::id($a->appointment_id),
            'questionId' => self::id($a->screening_question_id),
            'questionText' => (string) optional($a->question)->question_text,
            'answerValue' => (string) ($a->answer_value ?? ''),
            'isRisk' => (bool) $a->is_risk,
            'overrideReason' => $a->override_reason,
            'answeredBy' => optional($a->answerer)->name ?? 'Staff',
            'answeredAt' => self::time($a->created_at),
        ];
    }

    public static function doseLog(DoseLog $d): array
    {
        return [
            'appointmentId' => self::id($d->appointment_id),
            'doseValue' => (float) ($d->dose_value ?? 0),
            'doseUnit' => (string) ($d->dose_unit ?? ''),
            'dlpValue' => $d->dlp_value !== null ? (float) $d->dlp_value : null,
            'kvp' => $d->kvp !== null ? (float) $d->kvp : null,
            'mas' => $d->mas !== null ? (float) $d->mas : null,
            'sliceCount' => $d->slice_count,
            'seriesCount' => $d->series_count,
            'contrastAgent' => $d->contrast_agent,
            'contrastVolumeMl' => $d->contrast_volume_ml !== null ? (float) $d->contrast_volume_ml : null,
            'contrastFlowRate' => $d->contrast_flow_rate,
            'cannulaSite' => $d->cannula_site,
            'salineFlushMl' => $d->saline_flush_ml !== null ? (float) $d->saline_flush_ml : null,
            'techniqueNotes' => $d->technique_notes,
            'qcPassed' => (bool) ($d->qc_passed ?? true),
            'recordedAt' => self::time($d->created_at),
            'recordedBy' => optional($d->recorder)->name ?? 'Technologist',
        ];
    }

    public static function reportRelease(ReportRelease $r): array
    {
        return [
            'id' => self::id($r->id),
            'reportId' => self::id($r->report_id),
            'channel' => $r->channel,
            'recipientEmail' => $r->recipient_email,
            'releasedAt' => self::time($r->released_at),
            'releasedBy' => optional($r->releaser)->name ?? 'Reception',
        ];
    }

    public static function radiologyReport(RadiologyReport $r): array
    {
        return [
            'id' => self::id($r->id),
            'appointmentId' => self::id($r->appointment_id),
            'version' => (int) $r->version,
            'type' => $r->type,
            'parentReportId' => $r->parent_report_id ? self::id($r->parent_report_id) : null,
            'clinicalHistory' => (string) ($r->clinical_history ?? ''),
            'technique' => (string) ($r->technique ?? ''),
            'comparison' => (string) ($r->comparison ?? ''),
            'findings' => (string) ($r->findings ?? ''),
            'impression' => (string) ($r->impression ?? ''),
            'recommendations' => (string) ($r->recommendations ?? ''),
            'criticalFlag' => (bool) $r->critical_flag,
            'authoredBy' => optional($r->author)->name ?? 'Radiologist',
            'signedBy' => $r->signed_by ? (optional($r->signer)->name).' ('.($r->signer->department ?? 'Radiologist').')' : null,
            'signedAt' => $r->signed_at ? self::time($r->signed_at) : null,
            'lockedAt' => $r->locked_at ? self::dateTime($r->locked_at) : null,
            'pdfPath' => $r->pdf_path,
            'releases' => $r->relationLoaded('releases')
                ? $r->releases->map(fn ($rel) => self::reportRelease($rel))->all()
                : [],
        ];
    }

    public static function appointment(Appointment $a): array
    {
        $patient = $a->CustomerData;
        $service = $a->ServiceData;
        $modality = $service?->modality_id ? $service->modality : null;
        $latestReport = $a->relationLoaded('radiologyReports')
            ? ($a->radiologyReports->first() ?: null)
            : null;

        return [
            'id' => self::id($a->id),
            'tokenNumber' => (string) ($a->token_number ?? ''),
            'patientId' => self::id($a->customer_id),
            'patient' => $patient ? self::patient($patient) : null,
            'serviceId' => (int) $a->service_id,
            'service' => $service ? self::service($service) : null,
            'modalityId' => (int) ($modality?->id ?? 0),
            'modality' => $modality ? self::modality($modality) : null,
            'referrerId' => $a->referrer_id !== null ? (int) $a->referrer_id : null,
            'referrer' => ($a->relationLoaded('referrer') && $a->referrer) ? self::referrer($a->referrer) : null,
            'date' => $a->date_sort ? substr((string) $a->date_sort, 0, 10) : (string) $a->date,
            'time' => self::time($a->time),
            'priority' => $a->priority ?: 'routine',
            'workflowState' => $a->workflow_state,
            'cancelReason' => $a->cancel_reason,
            'rejectReason' => $a->reject_reason,
            'screeningRequired' => (bool) $a->screening_required,
            'screeningCleared' => (bool) $a->screening_cleared,
            'screeningAnswers' => $a->relationLoaded('screeningAnswers')
                ? $a->screeningAnswers->map(fn ($ans) => self::studyScreeningAnswer($ans))->all()
                : [],
            'performedByStaff' => optional($a->performedBy)->name,
            'assignedRadiologistId' => $a->assigned_radiologist_id ? self::id($a->assigned_radiologist_id) : null,
            'assignedRadiologistName' => optional($a->assignedRadiologist)->name,
            'checkedInAt' => $a->checked_in_at ? self::time($a->checked_in_at) : null,
            'preparingAt' => $a->preparing_at ? self::time($a->preparing_at) : null,
            'inProgressAt' => $a->in_progress_at ? self::time($a->in_progress_at) : null,
            'acquiredAt' => $a->acquired_at ? self::time($a->acquired_at) : null,
            'readingAt' => $a->acquired_at ? self::time($a->acquired_at) : null,
            'reportedAt' => $a->reported_at ? self::time($a->reported_at) : null,
            'deliveredAt' => $a->delivered_at ? self::time($a->delivered_at) : null,
            'doseLog' => $a->relationLoaded('doseLog') && $a->doseLog ? self::doseLog($a->doseLog) : null,
            'report' => $latestReport ? self::radiologyReport($latestReport) : null,
            'roomNumber' => (string) ($a->room_number ?? ''),
            'notes' => $a->notes,
        ];
    }

    // ==================== billing ====================

    public static function invoice(Invoice $inv): array
    {
        $patient = $inv->patient_id
            ? Customer::where('user_id', $inv->patient_id)->first()
            : null;

        return [
            'id' => self::id($inv->id),
            'invoiceNumber' => $inv->invoice_number,
            'patientId' => self::id($inv->patient_id),
            'patient' => $patient ? self::patient($patient) : null,
            'appointmentId' => $inv->appointment_id ? self::id($inv->appointment_id) : '',
            'appointmentToken' => (string) optional($inv->appointment)->token_number ?? '',
            'subtotal' => (float) $inv->subtotal,
            'discountTotal' => (float) $inv->discount_total,
            'taxRate' => (float) $inv->tax_rate,
            'taxAmount' => (float) $inv->tax_amount,
            'total' => (float) $inv->total,
            'paidTotal' => (float) $inv->paid_total,
            'balanceDue' => (float) $inv->balance_due,
            'status' => $inv->status,
            'notes' => (string) ($inv->notes ?? ''),
            'items' => $inv->relationLoaded('items')
                ? $inv->items->map(fn ($i) => [
                    'id' => self::id($i->id),
                    'serviceId' => $i->service_id !== null ? (int) $i->service_id : null,
                    'description' => $i->description,
                    'quantity' => (int) $i->quantity,
                    'unitPrice' => (float) $i->unit_price,
                    'discount' => (float) $i->discount,
                    'lineTotal' => (float) ($i->line_total ?: ($i->unit_price * $i->quantity - $i->discount)),
                ])->all()
                : [],
            'payments' => $inv->relationLoaded('payments')
                ? $inv->payments->map(fn ($p) => [
                    'id' => self::id($p->id),
                    'amount' => (float) $p->amount,
                    'method' => $p->method,
                    'reference' => (string) ($p->reference ?? ''),
                    'paidAt' => self::time($p->paid_at),
                    'receivedBy' => optional($p->receiver)->name ?? 'Staff',
                ])->all()
                : [],
            'createdAt' => self::time($inv->created_at),
            'issuedAt' => $inv->issued_at ? self::time($inv->issued_at) : null,
            'voidedAt' => $inv->voided_at ? self::time($inv->voided_at) : null,
        ];
    }

    // ==================== staff / RBAC ====================

    public static function staffUser(User $u): array
    {
        $caps = $u->capabilities ?? [];
        $defaults = array_fill_keys(User::PORTAL_CAPABILITIES, false);

        return [
            'id' => self::id($u->id),
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->portalRole(),
            'department' => (string) ($u->department ?? ''),
            'phone' => (string) ($u->mobile_no ?? ''),
            'initials' => (string) ($u->initials ?? ''),
            'isActive' => (bool) $u->active_status,
            'canSignReports' => (bool) ($caps['canSignReports'] ?? $defaults['canSignReports']),
            'canVoidInvoices' => (bool) ($caps['canVoidInvoices'] ?? $defaults['canVoidInvoices']),
            'canOverrideScreening' => (bool) ($caps['canOverrideScreening'] ?? $defaults['canOverrideScreening']),
            'canEditMasters' => (bool) ($caps['canEditMasters'] ?? $defaults['canEditMasters']),
            'canAccessPacs' => (bool) ($caps['canAccessPacs'] ?? $defaults['canAccessPacs']),
            'lastLogin' => $u->last_login_at ? self::dateTime($u->last_login_at) : 'Never',
        ];
    }

    public static function currentUser(User $u): array
    {
        $business = $u->business_id ? Business::find($u->business_id) : null;

        return [
            ...self::staffUser($u),
            'businessId' => (int) getActiveBusiness($u->id),
            'businessName' => $business?->name ?? $u->name,
            'subscriptionStatus' => $business?->subscription_status ?? 'active',
            'isPlatformAdmin' => $u->type === 'super_admin',
        ];
    }

    // ==================== settings / platform ====================

    public static function clinicSettings(int $businessId): array
    {
        $blob = Setting::where('business', $businessId)->where('key', 'ris_clinic_profile')->value('value');
        $data = $blob ? json_decode($blob, true) : [];

        $business = Business::find($businessId);

        return [
            'name' => $data['name'] ?? $business?->name ?? 'Diagnostic Centre',
            'branch' => $data['branch'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'phone' => $data['phone'] ?? '',
            'emergencyPhone' => $data['emergencyPhone'] ?? '',
            'email' => $data['email'] ?? '',
            'website' => $data['website'] ?? '',
            'pnraLicenseNo' => $data['pnraLicenseNo'] ?? '',
            'pmcRegistrationNo' => $data['pmcRegistrationNo'] ?? '',
            'taxId' => $data['taxId'] ?? '',
            'currencySymbol' => $data['currencySymbol'] ?? 'Rs.',
            'headerTagline' => $data['headerTagline'] ?? '',
            'invoiceFooterDisclaimer' => $data['invoiceFooterDisclaimer'] ?? '',
            'reportLegalDisclaimer' => $data['reportLegalDisclaimer'] ?? '',
            'requireScreeningSignOff' => (bool) ($data['requireScreeningSignOff'] ?? true),
            'enableCriticalFindingsAlerts' => (bool) ($data['enableCriticalFindingsAlerts'] ?? true),
            'autoSendWhatsappReport' => (bool) ($data['autoSendWhatsappReport'] ?? false),
        ];
    }

    public static function dicomNode(DicomNode $n): array
    {
        return [
            'id' => self::id($n->id),
            'nodeName' => $n->node_name,
            'aeTitle' => $n->ae_title,
            'ipAddress' => $n->ip_address,
            'port' => (int) $n->port,
            'modalityCode' => $n->modality_code,
            'isWorklistSCP' => (bool) $n->is_worklist_scp,
            'isStorageSCP' => (bool) $n->is_storage_scp,
            'status' => $n->status,
            'lastPingTime' => $n->last_ping_time,
            'lastPingLatencyMs' => $n->last_ping_latency_ms !== null ? (int) $n->last_ping_latency_ms : null,
        ];
    }

    public static function notificationTemplate(RisNotificationTemplate $t): array
    {
        return [
            'id' => self::id($t->id),
            'name' => $t->name,
            'category' => $t->category,
            'channel' => $t->channel,
            'subject' => $t->subject,
            'templateBody' => (string) $t->template_body,
            'enabled' => (bool) $t->enabled,
        ];
    }

    public static function appNotification(AppNotification $n): array
    {
        return [
            'id' => self::id($n->id),
            'title' => $n->title,
            'message' => $n->message,
            'category' => $n->category,
            'priority' => $n->priority,
            'timestamp' => self::human($n->created_at),
            'isRead' => (bool) $n->is_read,
            'appointmentId' => $n->appointment_id ? self::id($n->appointment_id) : null,
            'tokenNumber' => $n->token_number,
            'patientName' => $n->patient_name,
            'targetTab' => $n->target_tab,
            'actionLabel' => $n->action_label,
        ];
    }

    public static function doctorDispatch(DoctorDispatchLog $d): array
    {
        return [
            'id' => self::id($d->id),
            'appointmentId' => $d->appointment_id ? self::id($d->appointment_id) : '',
            'tokenNumber' => (string) ($d->token_number ?? ''),
            'patientName' => (string) ($d->patient_name ?? ''),
            'referrerId' => (int) ($d->referrer_id ?? 0),
            'referrerName' => (string) ($d->referrer_name ?? ''),
            'studyName' => (string) ($d->study_name ?? ''),
            'channel' => $d->channel,
            'recipientContact' => (string) ($d->recipient_contact ?? ''),
            'sentAt' => self::dateTime($d->created_at),
            'status' => $d->status,
            'sentBy' => (string) ($d->sent_by ?? ''),
        ];
    }

    // ==================== inventory ====================

    public static function inventoryItem(InventoryItem $i): array
    {
        return [
            'id' => self::id($i->id),
            'code' => $i->code,
            'name' => $i->name,
            'genericName' => (string) ($i->generic_name ?? ''),
            'category' => $i->category,
            'modality' => $i->modality,
            'unit' => (string) ($i->unit ?? ''),
            'currentStock' => (int) $i->current_stock,
            'minThreshold' => (int) $i->min_threshold,
            'unitCost' => (float) $i->unit_cost,
            'sellingPrice' => (float) $i->selling_price,
            'batches' => collect($i->batches ?? [])->map(fn ($b) => [
                'batchNumber' => $b['batch_number'] ?? $b['batchNumber'] ?? '',
                'expiryDate' => $b['expiry_date'] ?? $b['expiryDate'] ?? '',
                'quantity' => (int) ($b['quantity'] ?? 0),
                'receivedDate' => $b['received_date'] ?? $b['receivedDate'] ?? '',
            ])->values()->all(),
            'supplier' => (string) ($i->supplier ?? ''),
            'storageLocation' => (string) ($i->storage_location ?? ''),
            'requiresColdChain' => (bool) $i->requires_cold_chain,
            'isBillable' => (bool) $i->is_billable,
            'notes' => $i->notes,
        ];
    }

    public static function inventoryTransaction(InventoryTransaction $t): array
    {
        return [
            'id' => self::id($t->id),
            'itemId' => self::id($t->inventory_item_id),
            'itemName' => (string) ($t->item_name ?? ''),
            'type' => $t->type,
            'quantity' => (int) $t->quantity,
            'batchNumber' => (string) ($t->batch_number ?? ''),
            'timestamp' => self::dateTime($t->created_at),
            'performedBy' => optional($t->performer)->name ?? 'Staff',
            'appointmentId' => $t->appointment_id ? self::id($t->appointment_id) : null,
            'tokenNumber' => $t->token_number,
            'patientName' => $t->patient_name,
            'notes' => $t->notes,
        ];
    }

    public static function adverseReaction(AdverseReaction $r): array
    {
        return [
            'id' => self::id($r->id),
            'appointmentId' => $r->appointment_id ? self::id($r->appointment_id) : null,
            'tokenNumber' => (string) ($r->token_number ?? ''),
            'patientName' => (string) ($r->patient_name ?? ''),
            'modality' => $r->modality,
            'contrastAgent' => (string) ($r->contrast_agent ?? ''),
            'batchNumber' => (string) ($r->batch_number ?? ''),
            'administeredVolume' => (string) ($r->administered_volume ?? ''),
            'severity' => $r->severity,
            'symptoms' => $r->symptoms ?? [],
            'treatmentGiven' => (string) ($r->treatment_given ?? ''),
            'outcome' => $r->outcome,
            'reportedBy' => (string) ($r->reported_by ?? ''),
            'reportedAt' => self::dateTime($r->created_at),
            'supervisingDoctor' => (string) ($r->supervising_doctor ?? ''),
            'notes' => $r->notes,
        ];
    }

    // ==================== audit ====================

    /** action → [module, human label, default status] */
    public const AUDIT_MODULES = [
        'study_state_changed' => ['Study Workflow', 'Study State Changed', 'success'],
        'screening_submitted' => ['Safety Screening', 'Screening Submitted', 'success'],
        'report_saved' => ['Radiology Reporting', 'Report Saved', 'success'],
        'report_signed' => ['Radiology Reporting', 'Report Verified & Signed', 'success'],
        'report_released' => ['Report Dispatch', 'Report Released', 'success'],
        'invoice_created' => ['Billing & Invoicing', 'Invoice Created', 'success'],
        'invoice_voided' => ['Billing & Invoicing', 'Invoice Voided', 'warning'],
        'payment_recorded' => ['Billing & POS', 'Payment Collected', 'success'],
        'user_created' => ['RBAC & Access Control', 'Create Staff User Account', 'success'],
        'user_updated' => ['RBAC & Access Control', 'Update Staff User Permissions', 'success'],
        'user_deleted' => ['RBAC & Access Control', 'Revoke Staff User Account', 'warning'],
        'clinic_profile_updated' => ['System Settings', 'Update Clinic Master Profile', 'success'],
        'dicom_node_created' => ['PACS / DICOM Networking', 'Register DICOM Modality Node', 'success'],
        'dicom_node_updated' => ['PACS / DICOM Networking', 'Update DICOM Node Configuration', 'success'],
        'dicom_node_deleted' => ['PACS / DICOM Networking', 'Delete DICOM Node Configuration', 'warning'],
        'dicom_node_probed' => ['PACS / DICOM Networking', 'DICOM Node Reachability Probe', 'success'],
        'inventory_created' => ['Clinical Inventory', 'Create Inventory SKU', 'success'],
        'inventory_movement' => ['Clinical Inventory', 'Stock Movement', 'success'],
        'adverse_reaction_reported' => ['Clinical Safety', 'Adverse Reaction Reported', 'warning'],
        'dispatch_created' => ['Doctor Network', 'Report Dispatched to Doctor', 'success'],
        'tenant_registered' => ['Platform', 'Clinic Tenant Registered', 'success'],
        'login' => ['Security', 'Staff Sign-In', 'success'],
        'logout' => ['Security', 'Staff Sign-Out', 'success'],
    ];

    public static function auditEntry(AuditLog $log): array
    {
        [$module, $label, $status] = self::AUDIT_MODULES[$log->action] ?? ['System', ucfirst(str_replace('_', ' ', $log->action)), 'success'];

        $changes = $log->changes ?? [];
        $details = $changes['summary'] ?? collect($changes)
            ->reject(fn ($v, $k) => in_array($k, ['summary']))
            ->map(fn ($v, $k) => "{$k}: {$v}")
            ->implode('; ');

        $to = $changes['to'] ?? null;
        if (in_array($to, ['cancelled', 'no_show'], true)) {
            $status = 'warning';
        }

        return [
            'id' => self::id($log->id),
            'timestamp' => self::dateTime($log->created_at),
            'user' => optional($log->user)->name ?? 'System',
            'role' => optional($log->user)->portalRole() ?? 'system',
            'action' => $label,
            'module' => $module,
            'details' => (string) $details,
            'ipAddress' => $log->ip,
            'status' => $status,
        ];
    }

    public static function plan(Plan $p): array
    {
        return [
            'id' => self::id($p->id),
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => (string) ($p->description ?? ''),
            'priceMonthly' => (float) $p->price_monthly,
            'currency' => $p->currency,
            'trialDays' => (int) $p->trial_days,
            'maxUsers' => $p->max_users,
            'isActive' => (bool) $p->is_active,
        ];
    }

    public static function tenant(Business $b): array
    {
        return [
            'id' => self::id($b->id),
            'name' => $b->name,
            'slug' => $b->slug,
            'tenantCode' => $b->tenant_code,
            'subscriptionStatus' => $b->subscription_status,
            'plan' => $b->plan ? self::plan($b->plan) : null,
            'trialEndsAt' => $b->trial_ends_at?->toIso8601String(),
            'subscriptionEndsAt' => $b->subscription_ends_at?->toIso8601String(),
            'isActive' => $b->isSubscribable(),
            'createdAt' => $b->created_at?->toDateString(),
            'counts' => [
                'users' => $b->users()->count(),
                'studies' => $b->appointments()->count(),
            ],
        ];
    }
}

