<?php

use App\Http\Controllers\Api\V1\AppNotificationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MastersController;
use App\Http\Controllers\Api\V1\PlatformAdminController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StaffUserController;
use App\Http\Controllers\Api\V1\StudyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — React SPA contract (/api/v1)
|--------------------------------------------------------------------------
| Same-origin Sanctum SPA cookie sessions. Every protected route is
| permission-checked inside the controller and tenant-scoped to the
| authenticated user's clinic.
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

// ---------- authenticated ----------

Route::middleware(['auth', 'tenant.active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
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

    // Platform admin (super admin only)
    Route::get('/platform/tenants', [PlatformAdminController::class, 'tenants']);
    Route::put('/platform/tenants/{tenant}', [PlatformAdminController::class, 'updateTenant'])->whereNumber('tenant');
    Route::get('/platform/plans', [PlatformAdminController::class, 'plans']);
    Route::get('/platform/stats', [PlatformAdminController::class, 'platformStats']);
});
});
