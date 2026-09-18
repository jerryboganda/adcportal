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
use App\Models\PaymentMethod;
use App\Models\Referrer;
use App\Models\ReportTemplate;
use App\Models\Room;
use App\Models\RisNotificationTemplate;
use App\Models\ScreeningForm;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * One round-trip hydration of every collection the SPA works from.
 * Client-side filtering/sorting semantics are preserved — the payload is
 * small at single-clinic scale and revalidated on each mutation.
 *
 * Every collection is gated by the SAME tenant permission that guards its
 * read/write API counterparts: a user only downloads the data they are
 * authorized to act on (a radiologist never receives invoices or the staff
 * directory; audit logs ship only to `user logs history` holders).
 */
class BootstrapController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        // Control plane: platform staff WITHOUT an active support-session
        // tenant context get the platform bootstrap — never a clinical payload.
        if ($user->isPlatformAdmin() && $this->tenantId() === 0) {
            return $this->ok([
                'user' => ApiShape::currentUser($user),
                'platform' => true,
            ]);
        }

        $tenantId = $this->tenantId();
        $tenant = \App\Models\Business::find($tenantId);

        // Patient-role users only need their own slice.
        if ($user->portalRole() === 'patient') {
            return $this->patientBootstrap($user, $tenantId, $tenant);
        }

        $payload = [
            'user' => ApiShape::currentUser($user),
            // Clinic identity/profile (name, address, disclaimers) is shared
            // operational context, not PHI — every staff session needs it.
            'clinicSettings' => ApiShape::clinicSettings($tenantId),
            'notifications' => AppNotification::where('business_id', $tenantId)->orderByDesc('id')->limit(200)->get()
                ->map(fn ($n) => ApiShape::appNotification($n))->all(),
            'entitlements' => $tenant ? \App\Services\EntitlementService::payload($tenant) : null,
            // White-label presentation for this tenant (server-resolved from the
            // authenticated principal — never from a client-supplied host).
            'branding' => $tenant ? \App\Services\TenantBrandingService::forTenant($tenant) : null,
        ];

        if ($this->allowsAny(['appointment manage', 'study checkin', 'study screen', 'study acquire', 'report manage', 'report create'])) {
            $payload['studies'] = \App\Models\Appointment::forClinic($tenantId)
                ->with(StudyController::eager())
                ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
                ->orderByDesc('date_sort')
                ->orderBy('time')
                ->get()
                ->map(fn ($a) => ApiShape::appointment($a))->all();
        }

        if ($this->allowsAny(['invoice manage', 'invoice create', 'invoice edit', 'invoice payment', 'invoice delete'])) {
            $payload['invoices'] = Invoice::forClinic($tenantId)
                ->with(['items', 'payments.receivedBy', 'appointment'])
                ->orderByDesc('id')
                ->get()
                ->map(fn ($i) => ApiShape::invoice($i))->all();
        }

        if ($this->allowsAny(['customer manage', 'appointment manage', 'study checkin', 'report manage'])) {
            $payload['patients'] = Customer::where('business_id', $tenantId)
                ->orderBy('name')->get()
                ->map(fn ($c) => ApiShape::patient($c))->all();
        }

        if ($this->allowsAny(['appointment manage', 'appointment create', 'appointment edit', 'study checkin', 'study acquire', 'report create', 'catalog view', 'setting manage'])) {
            $payload['modalities'] = Modality::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($m) => ApiShape::modality($m))->all();
            // Imaging suites (rooms): the full list incl. inactive rows —
            // booking filters by isActive, admins manage the rest.
            $payload['rooms'] = Room::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($r) => ApiShape::room($r))->all();
        }

        if ($this->allowsAny(['appointment manage', 'appointment create', 'appointment edit', 'study acquire', 'report create', 'invoice create', 'invoice edit', 'catalog view', 'setting manage'])) {
            $payload['services'] = Service::forClinic($tenantId)->with('modality')->orderBy('name')->get()
                ->map(fn ($s) => ApiShape::service($s))->all();
        }

        if ($this->allowsAny(['invoice payment', 'invoice create', 'setting manage'])) {
            // Tenant payment-method configuration (incl. inactive for admin editing).
            $payload['paymentMethods'] = PaymentMethod::forClinic($tenantId)->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn ($m) => ApiShape::paymentMethod($m))->all();
        }

        if ($this->allowsAny(['appointment create', 'appointment edit', 'referrer manage', 'referrer create', 'referrer edit', 'report release', 'doctors view', 'setting manage'])) {
            $payload['referrers'] = Referrer::where('business_id', $tenantId)->orderBy('name')->get()
                ->map(fn ($r) => ApiShape::referrer($r))->all();
        }

        if ($this->allowsAny(['study screen', 'setting manage'])) {
            $payload['screeningForms'] = ScreeningForm::forClinic($tenantId)->with('questions')->orderBy('name')->get()
                ->map(fn ($f) => ApiShape::screeningForm($f))->all();
        }

        if ($this->allowsAny(['report create', 'report edit', 'report template manage', 'setting manage'])) {
            $payload['templates'] = ReportTemplate::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($t) => ApiShape::reportTemplate($t))->all();
        }

        if ($this->allowsAny(['user manage', 'role view'])) {
            $payload['staff'] = User::where('business_id', $tenantId)->where('type', '!=', 'customer')->orderBy('name')->get()
                ->map(fn ($u) => ApiShape::staffUser($u))->all();
        }

        if ($this->allows('setting manage')) {
            $payload['dicomNodes'] = DicomNode::where('business_id', $tenantId)->orderBy('node_name')->get()
                ->map(fn ($n) => ApiShape::dicomNode($n))->all();
            $payload['notificationTemplates'] = RisNotificationTemplate::where('business_id', $tenantId)->orderBy('name')->get()
                ->map(fn ($t) => ApiShape::notificationTemplate($t))->all();
        }

        if ($this->allows('user logs history')) {
            $payload['auditLogs'] = $this->auditLogs($tenantId);
        }

        if ($tenant
            && \App\Services\FeatureResolver::enabled($tenant, 'dispatch')
            && $this->allowsAny(['report release', 'referrer manage'])) {
            $payload['dispatches'] = DoctorDispatchLog::where('business_id', $tenantId)->orderByDesc('id')->get()
                ->map(fn ($d) => ApiShape::doctorDispatch($d))->all();
        }

        if ($this->allowsAny(['setting manage', 'inventory view', 'study acquire'])) {
            $payload['inventoryItems'] = InventoryItem::forClinic($tenantId)->orderBy('name')->get()
                ->map(fn ($i) => ApiShape::inventoryItem($i))->all();
            $payload['inventoryTransactions'] = InventoryTransaction::where('business_id', $tenantId)->with('performer')->orderByDesc('id')->limit(500)->get()
                ->map(fn ($t) => ApiShape::inventoryTransaction($t))->all();
        }

        if ($this->allowsAny(['setting manage', 'study screen'])) {
            $payload['adverseReactions'] = AdverseReaction::where('business_id', $tenantId)->orderByDesc('id')->get()
                ->map(fn ($r) => ApiShape::adverseReaction($r))->all();
        }

        return $this->ok($payload);
    }

    /** Patients see their own studies/invoices only — enforced server-side. */
    private function patientBootstrap(User $user, int $tenantId, ?\App\Models\Business $tenant = null): JsonResponse
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
            'branding' => $tenant ? \App\Services\TenantBrandingService::forTenant($tenant) : null,
        ]);
    }

    /** ANY-of permission check against the active tenant. */
    private function allowsAny(array $permissions): bool
    {
        $user = auth()->user();

        foreach ($permissions as $permission) {
            if ($user && \App\Services\TenantAuthorizer::allows($user, $permission, $this->tenantId())) {
                return true;
            }
        }

        return false;
    }

    private function allows(string $permission): bool
    {
        return $this->allowsAny([$permission]);
    }

    private function auditLogs(int $tenantId): array
    {
        $userIds = User::where('business_id', $tenantId)->pluck('id');

        return \App\Models\AuditLog::query()
            ->where(fn ($q) => $q
                ->where('business_id', $tenantId)
                ->orWhereIn('user_id', $userIds))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($l) => ApiShape::auditEntry($l))
            ->all();
    }
}
