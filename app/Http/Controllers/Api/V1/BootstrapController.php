<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\AdverseReaction;
use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\DicomNode;
use App\Models\DoctorDispatchLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Modality;
use App\Models\Referrer;
use App\Models\ReportTemplate;
use App\Models\RisNotificationTemplate;
use App\Models\ScreeningForm;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * One round-trip hydration of every collection the SPA works from.
 * Client-side filtering/sorting semantics are preserved — the payload is
 * small at single-clinic scale and revalidated on each mutation.
 */
class BootstrapController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $this->tenantId();

        // Patient-role users only need their own slice.
        if ($user->portalRole() === 'patient') {
            return $this->patientBootstrap($user, $tenantId);
        }

        $studies = \App\Models\Appointment::forClinic($tenantId)
            ->with(StudyController::eager())
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderByDesc('date_sort')
            ->orderBy('time')
            ->get();

        $invoices = Invoice::forClinic($tenantId)
            ->with(['items', 'payments.receiver', 'appointment'])
            ->orderByDesc('id')
            ->get();

        return $this->ok([
            'user' => ApiShape::currentUser($user),
            'studies' => $studies->map(fn ($a) => ApiShape::appointment($a))->all(),
            'invoices' => $invoices->map(fn ($i) => ApiShape::invoice($i))->all(),
            'patients' => Customer::where('business_id', $tenantId)
                ->orderBy('name')->get()
                ->map(fn ($c) => ApiShape::patient($c))->all(),
            'modalities' => Modality::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($m) => ApiShape::modality($m))->all(),
            'services' => Service::forClinic($tenantId)->with('modality')->orderBy('name')->get()
                ->map(fn ($s) => ApiShape::service($s))->all(),
            'referrers' => Referrer::where('business_id', $tenantId)->orderBy('name')->get()
                ->map(fn ($r) => ApiShape::referrer($r))->all(),
            'screeningForms' => ScreeningForm::forClinic($tenantId)->with('questions')->orderBy('name')->get()
                ->map(fn ($f) => ApiShape::screeningForm($f))->all(),
            'templates' => ReportTemplate::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($t) => ApiShape::reportTemplate($t))->all(),
            'staff' => User::where('business_id', $tenantId)->where('type', '!=', 'customer')->orderBy('name')->get()
                ->map(fn ($u) => ApiShape::staffUser($u))->all(),
            'clinicSettings' => ApiShape::clinicSettings($tenantId),
            'dicomNodes' => DicomNode::where('business_id', $tenantId)->orderBy('node_name')->get()
                ->map(fn ($n) => ApiShape::dicomNode($n))->all(),
            'notificationTemplates' => RisNotificationTemplate::where('business_id', $tenantId)->orderBy('name')->get()
                ->map(fn ($t) => ApiShape::notificationTemplate($t))->all(),
            'auditLogs' => $this->auditLogs($tenantId),
            'dispatches' => DoctorDispatchLog::where('business_id', $tenantId)->orderByDesc('id')->get()
                ->map(fn ($d) => ApiShape::doctorDispatch($d))->all(),
            'notifications' => AppNotification::where('business_id', $tenantId)->orderByDesc('id')->limit(200)->get()
                ->map(fn ($n) => ApiShape::appNotification($n))->all(),
            'inventoryItems' => InventoryItem::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($i) => ApiShape::inventoryItem($i))->all(),
            'inventoryTransactions' => InventoryTransaction::where('business_id', $tenantId)->with('performer')->orderByDesc('id')->limit(500)->get()
                ->map(fn ($t) => ApiShape::inventoryTransaction($t))->all(),
            'adverseReactions' => AdverseReaction::where('business_id', $tenantId)->orderByDesc('id')->get()
                ->map(fn ($r) => ApiShape::adverseReaction($r))->all(),
        ]);
    }

    /** Patients see their own studies/invoices only — enforced server-side. */
    private function patientBootstrap(User $user, int $tenantId): JsonResponse
    {
        $customer = Customer::where('user_id', $user->id)->where('business_id', $tenantId)->first();

        $studies = \App\Models\Appointment::forClinic($tenantId)
            ->where('customer_id', $customer?->id ?? 0)
            ->with(StudyController::eager())
            ->orderByDesc('date_sort')
            ->get();

        $invoices = Invoice::forClinic($tenantId)
            ->where('patient_id', $user->id)
            ->with(['items', 'payments', 'appointment'])
            ->orderByDesc('id')
            ->get();

        return $this->ok([
            'user' => ApiShape::currentUser($user),
            'studies' => $studies->map(fn ($a) => ApiShape::appointment($a))->all(),
            'invoices' => $invoices->map(fn ($i) => ApiShape::invoice($i))->all(),
            'patients' => $customer ? [ApiShape::patient($customer)] : [],
            'modalities' => Modality::forClinic($tenantId)->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($m) => ApiShape::modality($m))->all(),
            'services' => Service::forClinic($tenantId)->with('modality')->where('is_bookable_online', true)->orderBy('name')->get()
                ->map(fn ($s) => ApiShape::service($s))->all(),
            'referrers' => Referrer::where('business_id', $tenantId)->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($r) => ApiShape::referrer($r))->all(),
            'clinicSettings' => ApiShape::clinicSettings($tenantId),
            'notifications' => AppNotification::where('business_id', $tenantId)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($n) => ApiShape::appNotification($n))->all(),
        ]);
    }

    private function auditLogs(int $tenantId): array
    {
        $userIds = User::where('business_id', $tenantId)->pluck('id');

        return \App\Models\AuditLog::whereIn('user_id', $userIds)
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($l) => ApiShape::auditEntry($l))
            ->all();
    }
}
