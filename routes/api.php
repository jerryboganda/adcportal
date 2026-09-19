<?php

use App\Http\Controllers\Api\V1\AccessControlController;
use App\Http\Controllers\Api\V1\AppNotificationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MastersController;
use App\Http\Controllers\Api\V1\Platform\PlatformAuditController;
use App\Http\Controllers\Api\V1\Platform\PlatformBrandingController;
use App\Http\Controllers\Api\V1\Platform\PlatformInfrastructureController;
use App\Http\Controllers\Api\V1\Platform\PlatformIntegrationController;
use App\Http\Controllers\Api\V1\Platform\PlatformOperationsController;
use App\Http\Controllers\Api\V1\Platform\PlatformStepUpController;
use App\Http\Controllers\Api\V1\Platform\PlatformOverviewController;
use App\Http\Controllers\Api\V1\Platform\PlatformPlanController;
use App\Http\Controllers\Api\V1\Platform\PlatformSupportSessionController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantController;
use App\Http\Controllers\Api\V1\Platform\PlatformUserController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportingController;
use App\Http\Controllers\Api\V1\ReportingPreferenceController;
use App\Http\Controllers\Api\V1\PublicTenantContextController;
use App\Http\Controllers\Api\V1\QueueDisplayController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StaffUserController;
use App\Http\Controllers\Api\V1\StudyController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — React SPA contract (/api/v1)
|--------------------------------------------------------------------------
| Same-origin Sanctum SPA cookie sessions. Tenant routes are permission-
| checked inside the controller (tenant-scoped), platform routes carry the
| `platform` guard with explicit control-plane capabilities.
*/

Route::prefix('v1')->group(function () {

    Route::get('/health', function () {
        try {
            Illuminate\Support\Facades\DB::select('select 1');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'down';
        }

        return response()->json([
            'ok' => $db === 'ok',
            'db' => $db,
            'time' => now()->toIso8601String(),
            'version' => config('ris.app_version'),
        ]);
    });

// ---------- public ----------

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,60');

// Public plan catalog (signup + subscription gate views).
Route::get('/plans', [PlatformPlanController::class, 'publicIndex']);

// Two-factor: the login challenge endpoint is intentionally public — the
// "identity" it acts on is a server-side pending flag stashed during login,
// never a client-supplied user id. Management routes live in the auth group.
Route::get('/two-factor/status', [TwoFactorController::class, 'status']);
Route::post('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->middleware('throttle:login');
Route::post('/two-factor/challenge/cancel', [TwoFactorController::class, 'cancelChallenge']);

// Public presentation lookup for the login screen: resolves the brand of a
// DNS-verified custom domain. Cosmetic only — never an authorization input.
Route::get('/tenant-context', [PublicTenantContextController::class, 'show'])->middleware('throttle:60,1');

// Waiting-room TV kiosk feed: the display key IS the credential (rotatable
// from the staff console). Minimal-PHI payload, throttled per IP — see
// QueueDisplayController for the exposure contract.
Route::get('/public/queue-display', [QueueDisplayController::class, 'publicShow'])->middleware('throttle:20,1');

// ---------- authenticated: identity + context (never tenant-gated) ----------

Route::middleware(['auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/memberships', [TenantContextController::class, 'memberships']);
    Route::post('/tenant/switch', [TenantContextController::class, 'switchTenant']);
    Route::post('/tenant/enter', [TenantContextController::class, 'enter']);
    Route::post('/tenant/leave', [TenantContextController::class, 'leave']);
});

// ---------- authenticated tenant plane ----------

// `throttle:tenant` = aggregate per-active-tenant API budget (noisy-neighbor
// guard); the per-user `throttle:api` from the api group still applies below it.
Route::middleware(['auth', 'tenant.active', 'throttle:tenant'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);
    Route::get('/bootstrap', [BootstrapController::class, 'index']);

    // Two-factor enrollment / management (authenticated platform staff).
    Route::post('/two-factor/setup', [TwoFactorController::class, 'setup']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable']);

    // Live Queue TV: staff console feed (`queue view` enforced server-side)
    // + admin display-link/announcement settings (`setting manage`).
    Route::get('/queue/display', [QueueDisplayController::class, 'show']);
    Route::get('/queue/display/settings', [QueueDisplayController::class, 'settings']);
    Route::put('/queue/display/settings', [QueueDisplayController::class, 'saveSettings']);

    // Studies (booking + pipeline + screening)
    Route::get('/studies', [StudyController::class, 'index']);
    Route::post('/studies', [StudyController::class, 'store']);
    Route::put('/studies/{appointment}', [StudyController::class, 'update'])->whereNumber('appointment');
    Route::post('/studies/{appointment}/transition', [StudyController::class, 'transition'])->whereNumber('appointment');
    Route::get('/studies/{appointment}/screening', [StudyController::class, 'screeningForm'])->whereNumber('appointment');
    Route::post('/studies/{appointment}/screening', [StudyController::class, 'submitScreening'])->whereNumber('appointment');

    // Radiology reports
    Route::post('/studies/{appointment}/reports', [ReportController::class, 'store'])->whereNumber('appointment');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
    Route::put('/reports/{report}', [ReportController::class, 'update'])->whereNumber('report');
    Route::post('/reports/{report}/sign', [ReportController::class, 'sign'])->whereNumber('report');
    Route::post('/reports/{report}/addendum', [ReportController::class, 'addendum'])->whereNumber('report');
    Route::post('/reports/{report}/release', [ReportController::class, 'release'])->whereNumber('report');
    Route::get('/reports/{report}/pdf', [ReportController::class, 'downloadPdf'])->whereNumber('report');

    // Radiologist reporting module: server-side reading worklist, template
    // resolution/library, curated macros, report search, priors and the
    // critical-result communication log.
    Route::prefix('reporting')->group(function () {
        Route::get('/worklist', [ReportingController::class, 'worklist']);
        Route::get('/studies/{appointment}', [ReportingController::class, 'study'])->whereNumber('appointment');
        Route::get('/roster', [ReportingController::class, 'roster']);

        // The radiologist's own setup, per user AND per clinic.
        Route::get('/preferences', [ReportingPreferenceController::class, 'show']);
        Route::put('/preferences', [ReportingPreferenceController::class, 'update']);
        Route::post('/views', [ReportingPreferenceController::class, 'storeView']);
        Route::put('/views/{view}', [ReportingPreferenceController::class, 'updateView'])->whereNumber('view');
        Route::delete('/views/{view}', [ReportingPreferenceController::class, 'destroyView'])->whereNumber('view');

        // Dictation: the clinic's own self-hosted transcription, when configured.
        Route::get('/dictation', [ReportingController::class, 'dictation']);
        Route::post('/dictation/transcribe', [ReportingController::class, 'transcribe']);

        Route::get('/templates', [ReportingController::class, 'templates']);
        Route::post('/templates/resolve', [ReportingController::class, 'resolveTemplate']);

        Route::get('/macros', [ReportingController::class, 'macros']);
        Route::post('/macros', [ReportingController::class, 'storeMacro']);
        Route::put('/macros/{macro}', [ReportingController::class, 'updateMacro'])->whereNumber('macro');
        Route::delete('/macros/{macro}', [ReportingController::class, 'destroyMacro'])->whereNumber('macro');
        Route::post('/macros/{macro}/use', [ReportingController::class, 'useMacro'])->whereNumber('macro');

        Route::get('/reports', [ReportingController::class, 'reportSearch']);
        Route::post('/reports/manual', [ReportingController::class, 'storeManualReport']);
        Route::get('/priors/{appointment}', [ReportingController::class, 'priors'])->whereNumber('appointment');

        Route::get('/critical-findings/{appointment}', [ReportingController::class, 'criticalFindings'])->whereNumber('appointment');
        Route::post('/critical-findings/{appointment}', [ReportingController::class, 'storeCriticalFinding'])->whereNumber('appointment');

        Route::post('/studies/{appointment}/assign', [ReportingController::class, 'assign'])->whereNumber('appointment');
    });

    // Billing
    Route::get('/invoices', [BillingController::class, 'index']);
    Route::post('/studies/{appointment}/invoices', [BillingController::class, 'store'])->whereNumber('appointment');
    Route::post('/invoices/{invoice}/items', [BillingController::class, 'addItem'])->whereNumber('invoice');
    Route::post('/invoices/{invoice}/payments', [BillingController::class, 'addPayment'])->whereNumber('invoice');
    Route::post('/invoices/{invoice}/void', [BillingController::class, 'void'])->whereNumber('invoice');
    Route::get('/invoices/{invoice}/pdf', [BillingController::class, 'downloadPdf'])->whereNumber('invoice');

    // Masters
    Route::post('/modalities', [MastersController::class, 'storeModality']);
    Route::put('/modalities/{modality}', [MastersController::class, 'updateModality'])->whereNumber('modality');
    Route::delete('/modalities/{modality}', [MastersController::class, 'destroyModality'])->whereNumber('modality');
    Route::post('/rooms', [MastersController::class, 'storeRoom']);
    Route::put('/rooms/{room}', [MastersController::class, 'updateRoom'])->whereNumber('room');
    Route::delete('/rooms/{room}', [MastersController::class, 'destroyRoom'])->whereNumber('room');
    Route::post('/payment-methods', [MastersController::class, 'storePaymentMethod']);
    Route::put('/payment-methods/{paymentMethod}', [MastersController::class, 'updatePaymentMethod'])->whereNumber('paymentMethod');
    Route::delete('/payment-methods/{paymentMethod}', [MastersController::class, 'destroyPaymentMethod'])->whereNumber('paymentMethod');
    Route::post('/services', [MastersController::class, 'storeService']);
    Route::put('/services/{service}', [MastersController::class, 'updateService'])->whereNumber('service');
    Route::delete('/services/{service}', [MastersController::class, 'destroyService'])->whereNumber('service');
    Route::post('/referrers', [MastersController::class, 'storeReferrer']);
    Route::put('/referrers/{referrer}', [MastersController::class, 'updateReferrer'])->whereNumber('referrer');
    Route::delete('/referrers/{referrer}', [MastersController::class, 'destroyReferrer'])->whereNumber('referrer');
    Route::post('/screening-forms', [MastersController::class, 'storeScreeningForm']);
    Route::put('/screening-forms/{form}', [MastersController::class, 'updateScreeningForm'])->whereNumber('form');
    Route::post('/screening-forms/{form}/toggle', [MastersController::class, 'toggleScreeningForm'])->whereNumber('form');
    Route::delete('/screening-forms/{form}', [MastersController::class, 'destroyScreeningForm'])->whereNumber('form');
    Route::post('/report-templates', [MastersController::class, 'storeReportTemplate']);
    Route::put('/report-templates/{template}', [MastersController::class, 'updateReportTemplate'])->whereNumber('template');
    Route::delete('/report-templates/{template}', [MastersController::class, 'destroyReportTemplate'])->whereNumber('template');
    Route::post('/report-templates/{template}/duplicate', [MastersController::class, 'duplicateReportTemplate'])->whereNumber('template');
    Route::post('/report-templates/{template}/archive', [MastersController::class, 'archiveReportTemplate'])->whereNumber('template');

    // Inventory & clinical safety
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/items', [InventoryController::class, 'storeItem']);
    Route::post('/inventory/transactions', [InventoryController::class, 'storeTransaction']);
    Route::post('/inventory/adverse-reactions', [InventoryController::class, 'storeAdverseReaction']);

    // Staff RBAC
    Route::get('/staff', [StaffUserController::class, 'index']);
    Route::post('/staff', [StaffUserController::class, 'store']);
    Route::put('/staff/{staff}', [StaffUserController::class, 'update'])->whereNumber('staff');
    Route::delete('/staff/{staff}', [StaffUserController::class, 'destroy'])->whereNumber('staff');

    // Roles & permissions administration (tenant-scoped RBAC control center)
    Route::get('/access/catalog', [AccessControlController::class, 'catalog']);
    Route::get('/access/roles', [AccessControlController::class, 'roles']);
    Route::post('/access/roles', [AccessControlController::class, 'storeRole']);
    Route::patch('/access/roles/{role}', [AccessControlController::class, 'updateRole'])->whereNumber('role');
    Route::put('/access/roles/{role}/permissions', [AccessControlController::class, 'syncPermissions'])->whereNumber('role');
    Route::post('/access/roles/{role}/duplicate', [AccessControlController::class, 'duplicateRole'])->whereNumber('role');
    Route::delete('/access/roles/{role}', [AccessControlController::class, 'destroyRole'])->whereNumber('role');
    Route::get('/access/users/{user}/effective', [AccessControlController::class, 'userEffective'])->whereNumber('user');
    Route::put('/access/users/{user}/overrides', [AccessControlController::class, 'syncOverrides'])->whereNumber('user');

    // Settings / platform surfaces
    Route::get('/clinic', [SettingsController::class, 'showClinic']);
    Route::put('/clinic', [SettingsController::class, 'updateClinic']);
    Route::post('/dicom-nodes', [SettingsController::class, 'storeDicomNode']);
    Route::put('/dicom-nodes/{node}', [SettingsController::class, 'updateDicomNode'])->whereNumber('node');
    Route::delete('/dicom-nodes/{node}', [SettingsController::class, 'destroyDicomNode'])->whereNumber('node');
    Route::post('/dicom-nodes/{node}/ping', [SettingsController::class, 'pingDicomNode'])->whereNumber('node');
    Route::put('/notification-templates/{template}', [SettingsController::class, 'updateNotificationTemplate'])->whereNumber('template');
    Route::get('/audit-logs', [SettingsController::class, 'auditLogs']);
    Route::get('/backup', [SettingsController::class, 'exportBackup']);
    Route::post('/backup/reset-demo', [SettingsController::class, 'resetDemo']);
    Route::post('/dispatches', [SettingsController::class, 'storeDispatch']);

    // Tenant-side white-label view (READ-ONLY): branding is platform-managed,
    // clinic admins may see their presentation settings but never edit them.
    Route::get('/settings/branding', [SettingsController::class, 'showBranding']);

    // Notification center
    Route::get('/notifications', [AppNotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [AppNotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [AppNotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [AppNotificationController::class, 'destroy'])->whereNumber('id');
    Route::delete('/notifications', [AppNotificationController::class, 'clear']);
});

// ---------- platform control plane (SaaS vendor) ----------

Route::middleware(['auth', 'platform'])->prefix('platform')->group(function () {
    // Step-up re-authentication: mutating platform actions below require a
    // fresh password confirmation; EnsureStepUpAuth answers 428 until then.
    Route::get('/step-up', [PlatformStepUpController::class, 'status']);
    Route::post('/step-up', [PlatformStepUpController::class, 'confirm'])->middleware('throttle:5,1');

    // Overview / stats
    Route::get('/overview', [PlatformOverviewController::class, 'index']);

    // Tenants: directory, provisioning, 360 view, lifecycle
    Route::get('/tenants', [PlatformTenantController::class, 'index']);
    Route::post('/tenants', [PlatformTenantController::class, 'store'])->middleware('platform.step-up');
    Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->whereNumber('tenant');
    Route::patch('/tenants/{tenant}', [PlatformTenantController::class, 'updateSubscription'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::put('/tenants/{tenant}/features', [PlatformTenantController::class, 'updateFeatures'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::get('/tenants/{tenant}/usage', [PlatformTenantController::class, 'usage'])->whereNumber('tenant');
    Route::get('/tenants/{tenant}/audit', [PlatformTenantController::class, 'tenantAudit'])->whereNumber('tenant');
    Route::get('/tenants/{tenant}/export', [PlatformTenantController::class, 'export'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/activate', [PlatformTenantController::class, 'activate'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/suspend', [PlatformTenantController::class, 'suspend'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/reactivate', [PlatformTenantController::class, 'reactivate'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/offboard', [PlatformTenantController::class, 'offboard'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/terminate', [PlatformTenantController::class, 'terminate'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/provision-retry', [PlatformTenantController::class, 'retryProvisioning'])->middleware('platform.step-up')->whereNumber('tenant');

    // Tenant administration: user accounts + facilities inside one tenant
    Route::post('/tenants/{tenant}/users', [PlatformTenantController::class, 'storeUser'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::patch('/tenants/{tenant}/users/{user}', [PlatformTenantController::class, 'updateUser'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('user');
    Route::post('/tenants/{tenant}/users/{user}/reset-password', [PlatformTenantController::class, 'resetUserPassword'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('user');
    Route::post('/tenants/{tenant}/facilities', [PlatformTenantController::class, 'storeFacility'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::patch('/tenants/{tenant}/facilities/{location}', [PlatformTenantController::class, 'updateFacility'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('location');
    Route::delete('/tenants/{tenant}/facilities/{location}', [PlatformTenantController::class, 'destroyFacility'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('location');

    // Deployment topology: placement catalog + per-tenant re-placement
    Route::get('/infrastructure', [PlatformInfrastructureController::class, 'index']);
    Route::patch('/tenants/{tenant}/deployment', [PlatformInfrastructureController::class, 'updateDeployment'])->middleware('platform.step-up')->whereNumber('tenant');

    // White-label branding + custom domain registry
    Route::get('/tenants/{tenant}/branding', [PlatformBrandingController::class, 'index'])->whereNumber('tenant');
    Route::put('/tenants/{tenant}/branding', [PlatformBrandingController::class, 'update'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/domains', [PlatformBrandingController::class, 'storeDomain'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::post('/tenants/{tenant}/domains/{domain}/verify', [PlatformBrandingController::class, 'verifyDomain'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('domain');
    Route::post('/tenants/{tenant}/domains/{domain}/primary', [PlatformBrandingController::class, 'makePrimary'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('domain');
    Route::delete('/tenants/{tenant}/domains/{domain}', [PlatformBrandingController::class, 'destroyDomain'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('domain');

    // Tenant integration registry (encrypted secrets, tenant/facility scoped)
    Route::get('/tenants/{tenant}/integrations', [PlatformIntegrationController::class, 'index'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/integrations', [PlatformIntegrationController::class, 'store'])->middleware('platform.step-up')->whereNumber('tenant');
    Route::patch('/tenants/{tenant}/integrations/{integration}', [PlatformIntegrationController::class, 'update'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('integration');
    Route::post('/tenants/{tenant}/integrations/{integration}/secrets', [PlatformIntegrationController::class, 'rotateSecrets'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('integration');
    Route::post('/tenants/{tenant}/integrations/{integration}/probe', [PlatformIntegrationController::class, 'probe'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('integration');
    Route::post('/tenants/{tenant}/integrations/{integration}/test', [PlatformIntegrationController::class, 'testDelivery'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('integration');
    Route::delete('/tenants/{tenant}/integrations/{integration}', [PlatformIntegrationController::class, 'destroy'])->middleware('platform.step-up')->whereNumber('tenant')->whereNumber('integration');

    // Operations & observability: system + tenant health, failed-job
    // inspection/retry, entitlement reconciliation (§80/§81)
    Route::get('/operations', [PlatformOperationsController::class, 'index']);
    Route::get('/operations/jobs', [PlatformOperationsController::class, 'jobs']);
    Route::post('/operations/jobs/{uuid}/retry', [PlatformOperationsController::class, 'retryJob'])->middleware('platform.step-up');
    Route::delete('/operations/jobs/{uuid}', [PlatformOperationsController::class, 'forgetJob'])->middleware('platform.step-up');
    Route::post('/tenants/{tenant}/entitlements/reconcile', [PlatformOperationsController::class, 'reconcileEntitlements'])->middleware('platform.step-up')->whereNumber('tenant');

    // Plans
    Route::get('/plans', [PlatformPlanController::class, 'index']);
    Route::post('/plans', [PlatformPlanController::class, 'store'])->middleware('platform.step-up');
    Route::patch('/plans/{plan}', [PlatformPlanController::class, 'update'])->middleware('platform.step-up')->whereNumber('plan');

    // Platform staff
    Route::get('/users', [PlatformUserController::class, 'index']);
    Route::post('/users', [PlatformUserController::class, 'store'])->middleware('platform.step-up');
    Route::patch('/users/{user}', [PlatformUserController::class, 'update'])->middleware('platform.step-up')->whereNumber('user');

    // Break-glass support sessions
    Route::get('/support-sessions', [PlatformSupportSessionController::class, 'index']);
    Route::post('/support-sessions', [PlatformSupportSessionController::class, 'store'])->middleware('platform.step-up');
    Route::post('/support-sessions/{session}/end', [PlatformSupportSessionController::class, 'end'])->middleware('platform.step-up')->whereNumber('session');

    // Platform audit stream
    Route::get('/audit', [PlatformAuditController::class, 'index']);
});
});
