<?php

use App\Http\Controllers\Api\V1\AppNotificationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MastersController;
use App\Http\Controllers\Api\V1\Platform\PlatformAuditController;
use App\Http\Controllers\Api\V1\Platform\PlatformInfrastructureController;
use App\Http\Controllers\Api\V1\Platform\PlatformOverviewController;
use App\Http\Controllers\Api\V1\Platform\PlatformPlanController;
use App\Http\Controllers\Api\V1\Platform\PlatformSupportSessionController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantController;
use App\Http\Controllers\Api\V1\Platform\PlatformUserController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\PublicTenantContextController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StaffUserController;
use App\Http\Controllers\Api\V1\StudyController;
use App\Http\Controllers\Api\V1\TenantContextController;
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
            'version' => 'v2-saas',
        ]);
    });

// ---------- public ----------

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,60');

// Public plan catalog (signup + subscription gate views).
Route::get('/plans', [PlatformPlanController::class, 'publicIndex']);

// Public presentation lookup for the login screen: resolves the brand of a
// DNS-verified custom domain. Cosmetic only — never an authorization input.
Route::get('/tenant-context', [PublicTenantContextController::class, 'show'])->middleware('throttle:60,1');

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

    // Studies (booking + pipeline + screening)
    Route::get('/studies', [StudyController::class, 'index']);
    Route::post('/studies', [StudyController::class, 'store']);
    Route::put('/studies/{appointment}', [StudyController::class, 'update'])->whereNumber('appointment');
    Route::post('/studies/{appointment}/transition', [StudyController::class, 'transition'])->whereNumber('appointment');
    Route::get('/studies/{appointment}/screening', [StudyController::class, 'screeningForm'])->whereNumber('appointment');
    Route::post('/studies/{appointment}/screening', [StudyController::class, 'submitScreening'])->whereNumber('appointment');

    // Radiology reports
    Route::post('/studies/{appointment}/reports', [ReportController::class, 'store'])->whereNumber('appointment');
    Route::put('/reports/{report}', [ReportController::class, 'update'])->whereNumber('report');
    Route::post('/reports/{report}/sign', [ReportController::class, 'sign'])->whereNumber('report');
    Route::post('/reports/{report}/release', [ReportController::class, 'release'])->whereNumber('report');
    Route::get('/reports/{report}/pdf', [ReportController::class, 'downloadPdf'])->whereNumber('report');

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

    // Notification center
    Route::get('/notifications', [AppNotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [AppNotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [AppNotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [AppNotificationController::class, 'destroy'])->whereNumber('id');
    Route::delete('/notifications', [AppNotificationController::class, 'clear']);
});

// ---------- platform control plane (SaaS vendor) ----------

Route::middleware(['auth', 'platform'])->prefix('platform')->group(function () {
    // Overview / stats
    Route::get('/overview', [PlatformOverviewController::class, 'index']);

    // Tenants: directory, provisioning, 360 view, lifecycle
    Route::get('/tenants', [PlatformTenantController::class, 'index']);
    Route::post('/tenants', [PlatformTenantController::class, 'store']);
    Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->whereNumber('tenant');
    Route::patch('/tenants/{tenant}', [PlatformTenantController::class, 'updateSubscription'])->whereNumber('tenant');
    Route::put('/tenants/{tenant}/features', [PlatformTenantController::class, 'updateFeatures'])->whereNumber('tenant');
    Route::get('/tenants/{tenant}/usage', [PlatformTenantController::class, 'usage'])->whereNumber('tenant');
    Route::get('/tenants/{tenant}/audit', [PlatformTenantController::class, 'tenantAudit'])->whereNumber('tenant');
    Route::get('/tenants/{tenant}/export', [PlatformTenantController::class, 'export'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/activate', [PlatformTenantController::class, 'activate'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/suspend', [PlatformTenantController::class, 'suspend'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/reactivate', [PlatformTenantController::class, 'reactivate'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/offboard', [PlatformTenantController::class, 'offboard'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/terminate', [PlatformTenantController::class, 'terminate'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/provision-retry', [PlatformTenantController::class, 'retryProvisioning'])->whereNumber('tenant');

    // Tenant administration: user accounts + facilities inside one tenant
    Route::post('/tenants/{tenant}/users', [PlatformTenantController::class, 'storeUser'])->whereNumber('tenant');
    Route::patch('/tenants/{tenant}/users/{user}', [PlatformTenantController::class, 'updateUser'])->whereNumber('tenant')->whereNumber('user');
    Route::post('/tenants/{tenant}/users/{user}/reset-password', [PlatformTenantController::class, 'resetUserPassword'])->whereNumber('tenant')->whereNumber('user');
    Route::post('/tenants/{tenant}/facilities', [PlatformTenantController::class, 'storeFacility'])->whereNumber('tenant');
    Route::patch('/tenants/{tenant}/facilities/{location}', [PlatformTenantController::class, 'updateFacility'])->whereNumber('tenant')->whereNumber('location');
    Route::delete('/tenants/{tenant}/facilities/{location}', [PlatformTenantController::class, 'destroyFacility'])->whereNumber('tenant')->whereNumber('location');

    // Deployment topology: placement catalog + per-tenant re-placement
    Route::get('/infrastructure', [PlatformInfrastructureController::class, 'index']);
    Route::patch('/tenants/{tenant}/deployment', [PlatformInfrastructureController::class, 'updateDeployment'])->whereNumber('tenant');

    // White-label branding + custom domain registry
    Route::get('/tenants/{tenant}/branding', [PlatformBrandingController::class, 'index'])->whereNumber('tenant');
    Route::put('/tenants/{tenant}/branding', [PlatformBrandingController::class, 'update'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/domains', [PlatformBrandingController::class, 'storeDomain'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/domains/{domain}/verify', [PlatformBrandingController::class, 'verifyDomain'])->whereNumber('tenant')->whereNumber('domain');
    Route::post('/tenants/{tenant}/domains/{domain}/primary', [PlatformBrandingController::class, 'makePrimary'])->whereNumber('tenant')->whereNumber('domain');
    Route::delete('/tenants/{tenant}/domains/{domain}', [PlatformBrandingController::class, 'destroyDomain'])->whereNumber('tenant')->whereNumber('domain');

    // Plans
    Route::get('/plans', [PlatformPlanController::class, 'index']);
    Route::post('/plans', [PlatformPlanController::class, 'store']);
    Route::patch('/plans/{plan}', [PlatformPlanController::class, 'update'])->whereNumber('plan');

    // Platform staff
    Route::get('/users', [PlatformUserController::class, 'index']);
    Route::post('/users', [PlatformUserController::class, 'store']);
    Route::patch('/users/{user}', [PlatformUserController::class, 'update'])->whereNumber('user');

    // Break-glass support sessions
    Route::get('/support-sessions', [PlatformSupportSessionController::class, 'index']);
    Route::post('/support-sessions', [PlatformSupportSessionController::class, 'store']);
    Route::post('/support-sessions/{session}/end', [PlatformSupportSessionController::class, 'end'])->whereNumber('session');

    // Platform audit stream
    Route::get('/audit', [PlatformAuditController::class, 'index']);
});
});
