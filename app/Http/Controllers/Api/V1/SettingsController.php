<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\DicomNode;
use App\Models\DoctorDispatchLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\AdverseReaction;
use App\Models\AppNotification;
use App\Models\RisNotificationTemplate;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Clinic configuration surfaces: profile settings, DICOM node registry
 * (real TCP reachability probe), notification templates, staff RBAC,
 * audit trail and database backup/restore.
 */
class SettingsController extends BaseApiController
{
    // ==================== clinic profile ====================

    public function showClinic(): JsonResponse
    {
        return $this->ok(['clinic' => ApiShape::clinicSettings($this->tenantId())]);
    }

    /**
     * Tenant-side white-label view — READ-ONLY by design. Branding is
     * platform-managed (control-plane write path is the only writer, proven
     * 403 for tenant admins in PlatformAccessTest). This endpoint closes the
     * visibility asymmetry: clinic/hospital admins can SEE the presentation
     * settings that govern their portal, with an explicit pointer for change
     * requests instead of an edit form that would silently do nothing.
     */
    public function showBranding(): JsonResponse
    {
        $tenant = \App\Models\Business::find($this->tenantId());
        abort_unless($tenant, 404, 'No active tenant.');

        $domains = \App\Models\TenantDomain::where('business_id', $tenant->id)
            ->orderByDesc('is_primary')
            ->orderBy('host')
            ->get(['host', 'is_primary', 'verified_at']);

        return $this->ok([
            'branding' => \App\Services\TenantBrandingService::forTenant($tenant),
            'overridden' => \App\Models\TenantBranding::where('business_id', $tenant->id)->exists(),
            'domains' => $domains->map(fn ($d) => [
                'host' => $d->host,
                'isPrimary' => (bool) $d->is_primary,
                'verified' => $d->verified_at !== null,
            ])->all(),
            'managedBy' => 'platform',
            'changeHint' => 'Contact platform support to request changes - the platform team applies and audits them on your behalf.',
        ]);
    }

    public function updateClinic(Request $request): JsonResponse
    {
        $this->denyUnless('setting manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'emergencyPhone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'pnraLicenseNo' => ['nullable', 'string', 'max:100'],
            'pmcRegistrationNo' => ['nullable', 'string', 'max:100'],
            'taxId' => ['nullable', 'string', 'max:150'],
            'currencySymbol' => ['nullable', 'string', 'max:8'],
            'headerTagline' => ['nullable', 'string', 'max:255'],
            'invoiceFooterDisclaimer' => ['nullable', 'string', 'max:1000'],
            'reportLegalDisclaimer' => ['nullable', 'string', 'max:1000'],
            'requireScreeningSignOff' => ['nullable', 'boolean'],
            'enableCriticalFindingsAlerts' => ['nullable', 'boolean'],
            'autoSendWhatsappReport' => ['nullable', 'boolean'],
            'sendAppointmentReminders' => ['nullable', 'boolean'],
            'reminderHours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'referralCommissionPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'ris_clinic_profile', 'business' => $this->tenantId()],
            ['value' => json_encode($validated), 'created_by' => Auth::id()]
        );

        $tenant = \App\Models\Business::find($this->tenantId());
        $tenant?->update(['name' => $validated['name']]);

        comapnySettingCacheForget($this->tenantId());

        $this->audit('clinic_profile_updated', $tenant ?? Auth::user(), [
            'summary' => "Updated clinic registration and profile for {$validated['name']}",
        ]);

        return $this->ok(['clinic' => ApiShape::clinicSettings($this->tenantId())]);
    }

    // ==================== DICOM nodes ====================

    public function storeDicomNode(Request $request): JsonResponse
    {
        $this->denyUnless('setting manage');
        $this->denyFeatureUnlessEnabled('dicom');

        $validated = $request->validate([
            'nodeName' => ['required', 'string', 'max:255'],
            'aeTitle' => ['required', 'string', 'max:64'],
            'ipAddress' => ['required', 'ip'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'modalityCode' => ['nullable', 'string', 'max:10'],
            'isWorklistSCP' => ['nullable', 'boolean'],
            'isStorageSCP' => ['nullable', 'boolean'],
        ]);

        $node = DicomNode::create([
            ...collect($validated)->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all(),
            'status' => 'unreachable',
            'business_id' => $this->tenantId(),
        ]);

        $this->audit('dicom_node_created', $node, [
            'summary' => "Added DICOM AE Title {$node->ae_title} ({$node->ip_address}:{$node->port})",
        ]);

        return response()->json(['data' => ['node' => ApiShape::dicomNode($node)]], 201);
    }

    public function updateDicomNode(Request $request, DicomNode $node): JsonResponse
    {
        $this->denyUnless('setting manage');
        $this->denyFeatureUnlessEnabled('dicom');

        if ($node->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'nodeName' => ['required', 'string', 'max:255'],
            'aeTitle' => ['required', 'string', 'max:64'],
            'ipAddress' => ['required', 'ip'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'modalityCode' => ['nullable', 'string', 'max:10'],
            'isWorklistSCP' => ['nullable', 'boolean'],
            'isStorageSCP' => ['nullable', 'boolean'],
        ]);

        $node->update(collect($validated)->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all());

        $this->audit('dicom_node_updated', $node, [
            'summary' => "Updated DICOM AE Title {$node->ae_title} ({$node->ip_address}:{$node->port})",
        ]);

        return $this->ok(['node' => ApiShape::dicomNode($node->fresh())]);
    }

    public function destroyDicomNode(DicomNode $node): JsonResponse
    {
        $this->denyUnless('setting manage');
        $this->denyFeatureUnlessEnabled('dicom');

        if ($node->business_id !== $this->tenantId()) {
            abort(404);
        }

        $ae = $node->ae_title;
        $node->delete();

        $this->audit('dicom_node_deleted', Auth::user(), [
            'summary' => "Deleted DICOM node {$ae}",
        ]);

        return $this->ok(['deleted' => true]);
    }

    /** Real TCP reachability probe. Not a DICOM C-ECHO — labelled as such. */
    public function pingDicomNode(DicomNode $node): JsonResponse
    {
        $this->denyUnless('setting manage');
        $this->denyFeatureUnlessEnabled('dicom');

        if ($node->business_id !== $this->tenantId()) {
            abort(404);
        }

        $result = $node->probe();

        $this->audit('dicom_node_probed', $node, [
            'summary' => sprintf('Probe %s — %s%s', $node->ae_title, $result['status'], $result['status'] === 'online' ? " ({$result['latency']}ms)" : ''),
        ]);

        return $this->ok(['node' => ApiShape::dicomNode($node->fresh()), 'probe' => $result]);
    }

    // ==================== notification templates ====================

    public function updateNotificationTemplate(Request $request, RisNotificationTemplate $template): JsonResponse
    {
        $this->denyUnless('setting manage');

        if ($template->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'templateBody' => ['sometimes', 'string', 'max:2000'],
            'enabled' => ['sometimes', 'boolean'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $template->update(collect($validated)->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all());

        $this->audit('notification_template_updated', $template, [
            'summary' => "Updated template {$template->name} for ".strtoupper($template->channel),
        ]);

        return $this->ok(['template' => ApiShape::notificationTemplate($template->fresh())]);
    }

    // ==================== audit trail ====================

    public function auditLogs(): JsonResponse
    {
        $this->denyUnless('user logs history');

        // Clinic scope: logs attributed to this tenant directly, or authored
        // by this clinic's staff (legacy rows predate business attribution).
        $userIds = \App\Models\User::where('business_id', $this->tenantId())->pluck('id');

        $logs = AuditLog::query()
            ->where(fn ($q) => $q
                ->where('business_id', $this->tenantId())
                ->orWhereIn('user_id', $userIds))
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return $this->ok(['logs' => $logs->map(fn ($l) => ApiShape::auditEntry($l))->all()]);
    }

    // ==================== backup / restore ====================

    public function exportBackup(): JsonResponse
    {
        $this->denyUnless('setting manage');

        $tenantId = $this->tenantId();

        return $this->ok([
            'exportedAt' => now()->toIso8601String(),
            'system' => 'PolytronX - RIS Portal',
            'version' => '3.0.0',
            'data' => [
                'patients' => \App\Models\Customer::where('business_id', $tenantId)->get()
                    ->map(fn ($c) => ApiShape::patient($c))->all(),
                'modalities' => \App\Models\Modality::forClinic($tenantId)->get()
                    ->map(fn ($m) => ApiShape::modality($m))->all(),
                'services' => \App\Models\Service::forClinic($tenantId)->get()
                    ->map(fn ($s) => ApiShape::service($s))->all(),
                'referrers' => \App\Models\Referrer::where('business_id', $tenantId)->get()
                    ->map(fn ($r) => ApiShape::referrer($r))->all(),
                'forms' => \App\Models\ScreeningForm::forClinic($tenantId)->with('questions')->get()
                    ->map(fn ($f) => ApiShape::screeningForm($f))->all(),
                'templates' => \App\Models\ReportTemplate::forClinic($tenantId)->get()
                    ->map(fn ($t) => ApiShape::reportTemplate($t))->all(),
                'studies' => \App\Models\Appointment::forClinic($tenantId)->get()
                    ->map(fn ($a) => ApiShape::appointment($a->load(StudyController::eager())))->all(),
                'invoices' => \App\Models\Invoice::forClinic($tenantId)->with(['items', 'payments'])->get()
                    ->map(fn ($i) => ApiShape::invoice($i))->all(),
                'inventoryItems' => InventoryItem::forClinic($tenantId)->get()
                    ->map(fn ($i) => ApiShape::inventoryItem($i))->all(),
                'adverseReactions' => AdverseReaction::where('business_id', $tenantId)->get()
                    ->map(fn ($r) => ApiShape::adverseReaction($r))->all(),
                'clinicSettings' => ApiShape::clinicSettings($tenantId),
            ],
        ]);
    }

    public function importBackup(): JsonResponse
    {
        $this->denyUnless('setting manage');

        // Deliberately disabled: restoring a snapshot would destructively
        // overwrite live clinical records (studies, invoices, reports).
        // Export remains fully available for archiving.
        abort(422, 'Snapshot import into a live tenant is disabled. Use JSON export for archiving.');
    }

    public function resetDemo(): JsonResponse
    {
        // Demo-only surface: inert (404) in production installs.
        if (! config('ris.demo_mode')) {
            abort(404);
        }

        $this->denyUnless('setting manage');

        $tenantId = $this->tenantId();
        $business = \App\Models\Business::find($tenantId);

        if (! $business || $business->tenant_code !== config('ris.demo_tenant_code')) {
            abort(403, 'Factory reset is only available for the demo clinic.');
        }

        DB::transaction(function () use ($tenantId) {
            $appointmentIds = \App\Models\Appointment::withTrashed()->where('business_id', $tenantId)->pluck('id');
            $invoiceIds = \App\Models\Invoice::withTrashed()->where('business_id', $tenantId)->pluck('id');
            $reportIds = \App\Models\RadiologyReport::withTrashed()->whereIn('appointment_id', $appointmentIds)->pluck('id');

            \App\Models\InvoiceItem::whereIn('invoice_id', $invoiceIds)->delete();
            \App\Models\InvoicePayment::whereIn('invoice_id', $invoiceIds)->delete();
            \App\Models\Invoice::withTrashed()->whereIn('id', $invoiceIds)->forceDelete();
            \App\Models\ReportRelease::whereIn('report_id', $reportIds)->delete();
            \App\Models\RadiologyReport::withTrashed()->whereIn('id', $reportIds)->forceDelete();
            \App\Models\StudyScreeningAnswer::whereIn('appointment_id', $appointmentIds)->delete();
            \App\Models\DoseLog::whereIn('appointment_id', $appointmentIds)->delete();
            \App\Models\AppointmentProcedure::whereIn('appointment_id', $appointmentIds)->delete();
            \App\Models\Appointment::withTrashed()->whereIn('id', $appointmentIds)->forceDelete();
            AppNotification::where('business_id', $tenantId)->delete();
            DoctorDispatchLog::where('business_id', $tenantId)->delete();
            InventoryTransaction::where('business_id', $tenantId)->delete();
            AdverseReaction::where('business_id', $tenantId)->delete();
        });

        $admin = Auth::user();
        app(\Database\Seeders\RisDemoData::class)->run($business, $admin);

        return $this->ok(['reset' => true]);
    }

    // ==================== doctor dispatch ====================

    public function storeDispatch(Request $request): JsonResponse
    {
        $this->denyUnless('report release');
        $this->denyFeatureUnlessEnabled('dispatch');

        $validated = $request->validate([
            'appointmentId' => ['required', 'integer'],
            'channel' => ['required', 'in:whatsapp,email,sms,portal'],
            'recipientContact' => ['required', 'string', 'max:255'],
        ]);

        $appointment = \App\Models\Appointment::forClinic($this->tenantId())->findOrFail($validated['appointmentId']);

        if (! $appointment->latestReport?->isSigned() && $appointment->radiologyReports()->whereNotNull('locked_at')->doesntExist()) {
            abort(422, 'Only signed reports can be dispatched.');
        }

        $status = 'pending';
        $failureDetail = null;

        $reportText = sprintf(
            'Imaging report for %s (study: %s, ref: %s) is ready. — %s',
            $appointment->patientDisplayName(),
            $appointment->ServiceData?->name ?? 'imaging',
            $appointment->token_number,
            \App\Models\Business::find($this->tenantId())?->name ?? 'your imaging centre'
        );

        if ($validated['channel'] === 'portal') {
            $status = 'delivered'; // signed report is already visible on the portal
        } elseif ($validated['channel'] === 'email') {
            // Tenant's OWN SMTP integration when configured; platform mail only
            // as the fallback for tenants without one.
            $smtp = \App\Models\TenantIntegration::where('business_id', $this->tenantId())
                ->where('type', 'email')->where('status', 'active')->first();

            if ($smtp && \App\Services\Delivery\EmailSender::tenantHasSmtp($smtp)) {
                $result = app(\App\Services\Delivery\DeliveryDispatcher::class)->sendNow($smtp, 'report_dispatch', [
                    'to' => $validated['recipientContact'],
                    'subject' => 'Imaging Report '.$appointment->token_number,
                    'message' => $reportText,
                ]);
                $status = $result->status === \App\Services\Delivery\DeliveryResult::SENT ? 'delivered' : 'failed';
                $failureDetail = $result->status === \App\Services\Delivery\DeliveryResult::SENT ? null : $result->detail;
            } else {
                try {
                    Mail::raw(
                        $reportText,
                        fn ($message) => $message->to($validated['recipientContact'])
                            ->subject('Imaging Report '.$appointment->token_number)
                    );
                    $status = 'delivered';
                } catch (\Throwable $e) {
                    report($e);
                    $status = 'pending';
                    $failureDetail = 'Platform mail transport failed: '.$e->getMessage();
                }
            }
        } elseif (in_array($validated['channel'], ['whatsapp', 'sms'], true)) {
            // REAL gateway delivery through the tenant's configured integration.
            $gateway = \App\Models\TenantIntegration::where('business_id', $this->tenantId())
                ->where('type', $validated['channel'])->where('status', 'active')->first();

            if ($gateway) {
                $result = app(\App\Services\Delivery\DeliveryDispatcher::class)->sendNow($gateway, 'report_dispatch', [
                    'to' => $validated['recipientContact'],
                    'message' => $reportText,
                ]);
                $status = $result->status === \App\Services\Delivery\DeliveryResult::SENT ? 'delivered' : 'failed';
                $failureDetail = $result->status === \App\Services\Delivery\DeliveryResult::SENT ? null : $result->detail;
            } else {
                // Truthful: with no gateway this dispatch can never leave —
                // record it as failed with the reason, not as a fake "pending".
                $status = 'failed';
                $failureDetail = "No active {$validated['channel']} integration is configured for this tenant — register one in the platform console.";
            }
        }

        $dispatch = DoctorDispatchLog::create([
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'referrer_id' => $appointment->referrer_id,
            'referrer_name' => $appointment->ReferrerData?->name,
            'study_name' => $appointment->ServiceData?->name,
            'channel' => $validated['channel'],
            'recipient_contact' => $validated['recipientContact'],
            'status' => $status,
            'failure_detail' => $failureDetail,
            'sent_by' => Auth::user()->name,
            'business_id' => $this->tenantId(),
        ]);

        $this->audit('dispatch_created', $appointment, [
            'summary' => sprintf(
                'Report for token %s sent to %s via %s (%s).',
                $appointment->token_number,
                $dispatch->referrer_name ?? $validated['recipientContact'],
                strtoupper($validated['channel']),
                $status,
            ),
        ]);

        $this->notify([
            'title' => 'Doctor Dispatch: '.strtoupper($validated['channel']),
            'message' => 'Report sent to '.($dispatch->referrer_name ?? $validated['recipientContact']).' for patient '.$appointment->patientDisplayName().' ('.$appointment->ServiceData?->name.').',
            'category' => 'dispatch',
            'priority' => 'low',
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'target_tab' => 'doctors',
            'action_label' => 'View Dispatch Hub',
        ]);

        return response()->json(['data' => ['dispatch' => ApiShape::doctorDispatch($dispatch)]], 201);
    }
}
