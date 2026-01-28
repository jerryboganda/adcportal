<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BanktransferController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Company\SettingsController as CompanySettingsController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SuperAdmin\SettingsController as SuperAdminSettingsController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\BusinessHolidayController;
use App\Http\Controllers\CustomStatusController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\ThemeSettingController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\SubscribeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
// Route::get('appointments/{slug}/{appointment?}', [AppointmentController::class, 'appointmentForm'])->name('appointments.form');
Route::any('appointments/{slug}/{appointment?}', [AppointmentController::class, 'appointmentForm'])->name('appointments.form');
Route::post('appointment-book', [AppointmentController::class, 'appointmentFormSubmit'])->name('appointment.form.submit');
Route::get('appointments/{slug}/{id}', [AppointmentController::class, 'appointmentDone'])->name('appointments.done');
Route::post('appointment-duration', [AppointmentController::class, 'appointmentDuration'])->name('appointment.duration');
Route::get('get-staff-data', [StaffController::class, 'getStaffData'])->name('get.staff.data');
Route::get('get-services-by-category', [AppointmentController::class, 'getServicesByCategory'])->name('get.services.by.category');
Route::get('appointment/rtl', [AppointmentController::class, 'appointmentRtlSetting'])->name('appointment.rtl');
Route::post('check-user-data', [AppointmentController::class, 'checkUser'])->name('check.user.data');

Route::resource('contacts', ContactUsController::class);
Route::get('/contacts/{id}/description', [ContactUsController::class, 'description'])->name('contact.description');
Route::resource('subscribes', SubscribeController::class);

// for checking online appointment for theme
Route::get('check-service-online-meeting/{businessSlug}', [ServiceController::class, 'checkServiceOnlineMeeting'])->name('check.service.online.meeting');

// for checking online appointment for form layout
Route::get('check-service-online-meeting-form-layout/{businessSlug}', [ServiceController::class, 'checkServiceOnlineMeetingFormLayout'])->name('check.service.online.meeting.form.layout');

// Auth::routes();
require __DIR__ . '/auth.php';

Route::get('/register/{lang?}', [RegisteredUserController::class, 'create'])->name('register.lang');
Route::get('/login/{lang?}', [AuthenticatedSessionController::class, 'create'])->name('login.lang');
Route::get('/forgot-password/{lang?}', [PasswordResetLinkController::class, 'create'])->name('password.request.lang');
Route::get('/verify-email/{lang?}', [EmailVerificationPromptController::class, '__invoke'])->name('verification.notice.lang');

// module page before login
Route::get('add-on', [HomeController::class, 'Software'])->name('apps.software');
Route::get('add-on/details/{slug}', [HomeController::class, 'SoftwareDetails'])->name('software.details');
Route::get('pricing', [HomeController::class, 'Pricing'])->name('apps.pricing');
Route::get('/', [HomeController::class, 'index'])->name('start');
Route::middleware(['auth', 'verified'])->group(function () {

    //Role & Permission
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);



    //dashbord
    // if (module_is_active('GoogleAuthentication')) {
    //     Route::get('/dashboard', [HomeController::class, 'Dashboard'])->name('dashboard')->middleware(
    //         [
    //             '2fa',
    //         ]
    //     );
    //     Route::get('/home', [HomeController::class, 'Dashboard'])->name('home')->middleware(
    //         [
    //             '2fa',
    //         ]
    //     );
    //     Route::get('/appointment-dashboard/{staff?}', [HomeController::class, 'AppointmentDashboard'])->name('appointment.dashboard')->middleware(
    //         [
    //             '2fa',
    //         ]
    //     );

    // } else {
    Route::get('/dashboard', [HomeController::class, 'Dashboard'])->name('dashboard');
    Route::get('/appointment-dashboard/{staff?}', [HomeController::class, 'AppointmentDashboard'])->name('appointment.dashboard');
    Route::any('dashboard-index', [HomeController::class, 'Dashboard'])->name('dashboard.index');
    Route::get('/home', [HomeController::class, 'Dashboard'])->name('home');
    // }
    Route::any('dashboard-index', [HomeController::class, 'Dashboard'])->name('dashboard.index');
    // settings
    Route::resource('settings', SettingsController::class);
    Route::post('settings-save', [CompanySettingsController::class, 'store'])->name('settings.save');
    Route::post('company/settings-save', [CompanySettingsController::class, 'store'])->name('company.settings.save');
    Route::post('super-admin/settings-save', [SuperAdminSettingsController::class, 'store'])->name('super.admin.settings.save');
    Route::post('super-admin/system-settings-save', [SuperAdminSettingsController::class, 'SystemStore'])->name('super.admin.system.setting.store');
    Route::post('company/system-settings-save', [CompanySettingsController::class, 'SystemStore'])->name('company.system.setting.store');
    Route::post('comapny-currency-settings', [CompanySettingsController::class, 'saveCompanyCurrencySettings'])->name('company.setting.currency.settings');

    Route::post('currency-settings', [SuperAdminSettingsController::class, 'saveCurrencySettings'])->name('super.admin.currency.settings');

    Route::post('company-setting-save', [CompanySettingsController::class, 'companySettingStore'])->name('company.setting.save');
    Route::post('company/week-settings-save', [CompanySettingsController::class, 'weekStore'])->name('company.week.setting.store');
    Route::post('/update-note-value', [SuperAdminSettingsController::class, 'updateNoteValue'])->name('admin.update.note.value');
    Route::post('company/update-note-value', [CompanySettingsController::class, 'companyupdateNoteValue'])->name('company.update.note.value');

    Route::post('company/custom-js-save', [CompanySettingsController::class, 'CustomJsStore'])->name('company.custom.js.store');
    Route::post('company/custom-css-save', [CompanySettingsController::class, 'CustomCssStore'])->name('company.custom.css.store');
    Route::post('company/default-status-save', [CompanySettingsController::class, 'DefaultStatusStore'])->name('company.default.status.store');

    Route::post('company/booking-mode-save', [CompanySettingsController::class, 'bookingModeStore'])->name('company.booking.mode.store');

    Route::post('email-settings-save', [SettingsController::class, 'mailStore'])->name('email.setting.store');
    Route::post('test-mail', [SettingsController::class, 'testMail'])->name('test.mail');
    Route::post('test-mail-send', [SettingsController::class, 'sendTestMail'])->name('test.mail.send');
    Route::post('email-notification-settings-save', [SettingsController::class, 'mailNotificationStore'])->name('email.notification.setting.store');

    Route::post('storage-settings-save', [SuperAdminSettingsController::class, 'storageStore'])->name('storage.setting.store');
    Route::post('seo/setting/save', [SuperAdminSettingsController::class, 'seoSetting'])->name('seo.setting.save');

    Route::get('/setting/section/{module}/{methord?}', [SettingsController::class, 'getSettingSection'])->name('setting.section.get');

    // bank-transfer
    Route::resource('bank-transfer-request', BanktransferController::class);
    Route::post('bank-transfer-setting', [BanktransferController::class, 'setting'])->name('bank.transfer.setting');
    Route::post('/bank/transfer/pay', [BanktransferController::class, 'planPayWithBank'])->name('plan.pay.with.bank');

    //users
    Route::resource('users', UserController::class);
    Route::get('users/list/view', [UserController::class, 'List'])->name('users.list.view');
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::post('edit-profile', [UserController::class, 'editprofile'])->name('edit.profile');
    Route::post('change-password', [UserController::class, 'updatePassword'])->name('update.password');
    Route::any('user-reset-password/{id}', [UserController::class, 'UserPassword'])->name('users.reset');
    Route::get('user-login/{id}', [UserController::class, 'LoginManage'])->name('users.login');
    Route::post('user-reset-password/{id}', [UserController::class, 'UserPasswordReset'])->name('user.password.update');
    Route::get('users/{id}/login-with-company', [UserController::class, 'LoginWithCompany'])->name('login.with.company');
    Route::get('company-info/{id}', [UserController::class, 'CompnayInfo'])->name('company.info');
    Route::post('user-unable', [UserController::class, 'UserUnable'])->name('user.unable');
    Route::get('business-links/{id}', [UserController::class, 'BusinessLinks'])->name('business.links');
    Route::get('user-verified/{id}', [UserController::class, 'verifeduser'])->name('user.verified');

    //User Log
    Route::get('users/logs/history', [UserController::class, 'UserLogHistory'])->name('users.userlog.history');
    Route::get('users/logs/{id}', [UserController::class, 'UserLogView'])->name('users.userlog.view');
    Route::delete('users/logs/destroy/{id}', [UserController::class, 'UserLogDestroy'])->name('users.userlog.destroy');


    // impersonating
    Route::get('login-with-company/exit', [UserController::class, 'ExitCompany'])->name('exit.company');

    // Language
    Route::get('/lang/change/{lang}', [LanguageController::class, 'changeLang'])->name('lang.change');
    Route::get('langmanage/{lang?}/{module?}', [LanguageController::class, 'index'])->name('lang.index');
    Route::get('create-language', [LanguageController::class, 'create'])->name('create.language');
    Route::post('langs/{lang?}/{module?}', [LanguageController::class, 'storeData'])->name('lang.store.data');
    Route::post('disable-language', [LanguageController::class, 'disableLang'])->name('disablelanguage');
    Route::any('store-language', [LanguageController::class, 'store'])->name('store.language');
    Route::delete('/lang/{id}', [LanguageController::class, 'destroy'])->name('lang.destroy');
    Route::get('export/lang/json', [LanguageController::class, 'exportLangJson'])->name('export.lang.json');
    Route::get('import/lang/json/upload', [LanguageController::class, 'importLangJsonUpload'])->name('import.lang.json.upload');
    Route::post('import/lang/json', [LanguageController::class, 'importLangJson'])->name('import.lang.json');
    // End Language

    // location
    Route::resource('location', LocationController::class);
    // End location

    // category
    Route::resource('category', CategoryController::class);
    // End category

    // service
    Route::resource('service', ServiceController::class);
    // End service

    // service
    Route::resource('staff', StaffController::class);
    // End service

    // appointment
    Route::resource('appointment', AppointmentController::class);

    Route::post('appointment/list', [AppointmentController::class, 'index'])->name('appointment.list.index');

    Route::get('appointment-calendar', [AppointmentController::class, 'appointmentCalendar'])->name('appointment.calendar');
    Route::get('appointment-details/{id}', [AppointmentController::class, 'appointmentDetails'])->name('appointment.details');


    Route::get('appointment-status-change/{id}', [AppointmentController::class, 'appointmentStatusChange'])->name('appointment.status.change');
    Route::post('appointment-status-update/{id}', [AppointmentController::class, 'appointmentStatusUpdate'])->name('appointment.status.update');

    Route::post('appointment-attachment-destroy/{id}', [AppointmentController::class, 'appointmentAttachmentDelete'])->name('appointment.attachment.destroy');

    // Appointment Reports
    Route::get('appointment/{id}/reports', [AppointmentController::class, 'showReports'])->name('appointment.reports');
    Route::post('appointment/{id}/reports/upload', [AppointmentController::class, 'uploadReport'])->name('appointment.reports.upload');
    Route::delete('appointment/reports/{reportId}', [AppointmentController::class, 'deleteReport'])->name('appointment.reports.delete');
    Route::get('appointment/reports/{reportId}/download', [AppointmentController::class, 'downloadReport'])->name('appointment.reports.download');
    // End appointment

    // custom field
    Route::post('business/custom-field-setting/{id}', [CustomFieldController::class, 'CustomFieldSetting'])->name('custom-field.setting');
    Route::post('/delete-field', [CustomFieldController::class, 'destroy'])->name('delete.field');
    // End custom field

    // custom status
    Route::resource('custom-status', CustomStatusController::class);
    // End custom status

    // Files
    Route::post('business/files-setting/{id}', [FileController::class, 'Filesetting'])->name('files.setting');
    // End Files

    // customer
    Route::resource('customer', CustomerController::class);
    Route::get('customer-list', [CustomerController::class, 'customerList'])->name('customer.list');
    // End customer

    // business hours
    Route::resource('business-hours', BusinessHoursController::class);
    // End business hours

    // business hours
    Route::resource('business-holiday', BusinessHolidayController::class);
    // End business hours

    // business
    Route::resource('business', BusinessController::class);
    Route::get('manage/business/', [BusinessController::class, 'ManageBusiness'])->name('manage.business');
    Route::post('business/theme/update', [BusinessController::class, 'BusinessThemeUpdate'])->name('business.theme.update');
    Route::get('business/{id}/manage', [BusinessController::class, 'businessManage'])->name('business.manage');
    Route::get('business/change/{id}', [BusinessController::class, 'change'])->name('business.change');
    Route::post('business/check', [BusinessController::class, 'businessCheck'])->name('business.check');
    Route::post('business/domain-setting/{id}', [BusinessController::class, 'domainsetting'])->name('business.domain-setting');
    Route::post('business/slot-capacity-setting/{id}', [BusinessController::class, 'slotCapacitysetting'])->name('slot.capacity-setting');
    Route::post('business/appointment-reminder-setting/{id}', [BusinessController::class, 'appointmentRemindersetting'])->name('appointment.reminder-setting');
    // end business

    // theme customize
    Route::get('themes/{id}/customize/{business}', [ThemeSettingController::class, 'themeCustomize'])->name('business.customize');
    Route::get('themes/{id}/customize/{slug}/{sub_slug}/{business}', [ThemeSettingController::class, 'customize_theme'])->name('customize.edit');
    Route::post('themes/{business}/{id}/customize', [ThemeSettingController::class, 'customize_theme_update'])->name('customize.update');
    Route::post('file-get', [ThemeSettingController::class, 'imageFileGet'])->name('file.get');
    // end theme customize


    // blog
    Route::get('themes/{id}/manage-blog/{business}', [BlogController::class, 'blogManage'])->name('blog.manage');
    Route::get('themes/{id}/blog/{business}', [BlogController::class, 'blogCreate'])->name('blog.create');
    Route::resource('blogs', BlogController::class);
    // End blog

    // testimonial
    Route::get('themes/{id}/manage-testimonial/{business}', [TestimonialController::class, 'testimonialManage'])->name('testimonial.manage');
    Route::get('themes/{id}/testimonial/{business}', [TestimonialController::class, 'testimonialCreate'])->name('testimonial.create');
    Route::resource('testimonials', TestimonialController::class);
    // End testimonial

    // Plans
    Route::resource('plans', PlanController::class);

    Route::get('plan/list', [PlanController::class, 'PlanList'])->name('plan.list');
    Route::post('plan/store', [PlanController::class, 'PlanStore'])->name('plan.store');

    Route::get('plan/active', [PlanController::class, 'ActivePlans'])->name('active.plans');
    Route::any('plan/package-data', [PlanController::class, 'PackageData'])->name('package.data');
    Route::get('plan/plan-buy/{id}', [PlanController::class, 'PlanBuy'])->name('plan.buy');
    Route::get('plan/plan-trial/{id}', [PlanController::class, 'PlanTrial'])->name('plan.trial');
    Route::get('plan/order', [PlanController::class, 'orders'])->name('plan.order.index');
    Route::get('add-one/detail/{id}', [PlanController::class, 'AddOneDetail'])->name('add-one.detail');
    Route::post('add-one/detail/save/{id}', [PlanController::class, 'AddOneDetailSave'])->name('add-one.detail.save');

    Route::get('plan/order-refund/{id}', [PlanController::class, 'planRefund'])->name('order.refund');
    Route::post('plan-enable', [PlanController::class, 'planEnable'])->name('plan.enable');


    // Route::post('company/settings-save', [CompanySettingsController::class, 'store'])->name('company.settings.save');
    // Route::post('super-admin/settings-save', [SuperAdminSettingsController::class, 'store'])->name('super.admin.settings.save');
    // Route::post('storage-settings-save', [SuperAdminSettingsController::class, 'storageStore'])->name('storage.setting.store');

    Route::post('super-admin/custom-js-save', [SuperAdminSettingsController::class, 'customJsStore'])->name('super.admin.custom.js.save');
    Route::post('super-admin/custom-css-save', [SuperAdminSettingsController::class, 'customCssStore'])->name('super.admin.custom.css.save');

    Route::post('cookie-settings-save', [SuperAdminSettingsController::class, 'CookieSetting'])->name('cookie.setting.store');


    // Coupon
    Route::resource('coupons', CouponController::class);
    Route::get('/apply-coupon', [CouponController::class, 'applyCoupon'])->name('apply.coupon');
    // end Coupon

    // Module Install
    Route::get('modules/list', [ModuleController::class, 'index'])->name('module.index');
    Route::get('modules/add', [ModuleController::class, 'add'])->name('module.add');
    Route::post('install-modules', [ModuleController::class, 'install'])->name('module.install');
    Route::post('remove-modules/{module}', [ModuleController::class, 'remove'])->name('module.remove');
    Route::post('modules-enable', [ModuleController::class, 'enable'])->name('module.enable');
    Route::get('cancel/add-on/{name}', [ModuleController::class, 'CancelAddOn'])->name('cancel.add.on');
    // End Module Install

    // Email Templates
    Route::resource('email-templates', EmailTemplateController::class);
    Route::get('email_template_lang/{id}/{lang?}', [EmailTemplateController::class, 'show'])->name('manage.email.language');
    Route::put('email_template_store/{pid}', [EmailTemplateController::class, 'storeEmailLang'])->name('store.email.language');
    // Route::put('email_template_status/{id}', [EmailTemplateController::class, 'updateStatus'])->name('status.email.language');
    Route::resource('email_template', EmailTemplateController::class);
    // End Email Templates

    //notification
    Route::resource('notification-template', NotificationController::class);
    Route::get('notification-template/{id}/{lang?}', [NotificationController::class, 'show'])->name('manage.notification.language');
    Route::post('notification-template/{pid}', [NotificationController::class, 'storeNotificationLang'])->name('store.notification.language');

    // Routes For OnlineAppointment Option.
    Route::get('online-appointment-create/{serviceId}/{businessId}', [ServiceController::class, 'createOnlineAppointment'])->name('create.online.appointment');
    Route::post('save-online-meeting-setting/{serviceId}', [ServiceController::class, 'saveOnlineMeetingSetting'])->name('save.online.meeting.setting');


});

Route::middleware(['web'])->group(function () {
    Route::get('find-appointment/{businessSlug}', [HomeController::class, 'findAppointment'])->name('find.appointment');
    Route::post('track-appointment/{businessSlug}', [HomeController::class, 'trackAppointment'])->name('track.appointment');
});

Route::get('module/reset', [ModuleController::class, 'ModuleReset'])->name('module.reset');
Route::post('guest/module/selection', [ModuleController::class, 'GuestModuleSelection'])->name('guest.module.selection');

// cookie
Route::get('cookie/consent', [SuperAdminSettingsController::class, 'CookieConsent'])->name('cookie.consent');

// DIAGNOSTIC ROUTES - Remove in production
Route::middleware(['auth'])->group(function () {
    Route::get('settings-diagnostic', function () {
        return view('diagnostic.settings-debug');
    })->name('settings.diagnostic');

    // AGGRESSIVE DEBUG PAGE
    Route::get('aggressive-debug', function () {
        return view('diagnostic.aggressive-debug');
    })->name('aggressive.debug');

    // NUCLEAR DEBUG PAGE - Bypasses all JavaScript
    Route::get('nuclear-debug', function () {
        return view('diagnostic.nuclear-debug');
    })->name('nuclear.debug');

    // SESSION TEST ROUTES
    Route::get('test-session-set', function () {
        session()->flash('success', 'TEST MESSAGE FROM SESSION!');
        session()->flash('test_value', 'Session is working!');
        \Log::info('Session flash set in test-session-set');
        \Log::info('Session ID: ' . session()->getId());
        \Log::info('Session success value: ' . session()->get('success'));
        return redirect('/test-session-get');
    });

    Route::get('test-session-get', function () {
        $success = session()->get('success');
        $test = session()->get('test_value');
        \Log::info('Session flash retrieved in test-session-get');
        \Log::info('Session ID: ' . session()->getId());
        \Log::info('Success message: ' . $success);
        \Log::info('Test value: ' . $test);
        return "Success: " . ($success ?: 'NOT SET') . "<br>Test: " . ($test ?: 'NOT SET');
    });

    // Check emergency log
    Route::get('check-emergency-log', function () {
        $logPath = storage_path('logs/emergency_debug.log');
        if (file_exists($logPath)) {
            return file_get_contents($logPath);
        }
        return 'No emergency log found';
    });

    // Check Laravel log
    Route::get('check-laravel-log', function () {
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = file($logPath);
            return implode('', array_slice($lines, -50)); // Last 50 lines
        }
        return 'No Laravel log found';
    });

    // Test direct database write
    Route::get('test-db-write', function () {
        try {
            $testKey = 'debug_test_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);

            \App\Models\Setting::updateOrInsert(
                ['key' => $testKey, 'business' => 0, 'created_by' => \Auth::user()->id],
                ['value' => $testValue, 'updated_at' => now()]
            );

            $verify = \App\Models\Setting::where('key', $testKey)->first();

            if ($verify) {
                return "✓ SUCCESS!\nKey: {$testKey}\nValue: {$verify->value}\nBusiness: {$verify->business}\nCreated By: {$verify->created_by}";
            } else {
                return "✗ FAILED - Setting not found after insert!";
            }
        } catch (\Exception $e) {
            return "✗ ERROR: " . $e->getMessage();
        }
    });

    // Check current settings
    Route::get('check-settings', function () {
        $userId = \Auth::user()->id;

        // Direct DB query
        $dbSettings = \App\Models\Setting::where('created_by', $userId)->where('business', 0)->get();

        // Via helper
        $helperSettings = getAdminAllSetting();

        $output = "<h2>DATABASE (Direct Query):</h2><pre>";
        foreach ($dbSettings->take(15) as $setting) {
            $output .= "{$setting->key} = " . substr($setting->value, 0, 100) . "\n";
        }
        $output .= "</pre>";

        $output .= "<h2>HELPER (getAdminAllSetting):</h2><pre>";
        foreach (array_slice($helperSettings, 0, 15, true) as $key => $value) {
            $output .= "{$key} = " . substr($value, 0, 100) . "\n";
        }
        $output .= "</pre>";

        $output .= "<h2>COMPARISON:</h2>";
        $titleInDb = \App\Models\Setting::where('key', 'title_text')->where('business', 0)->first();
        $titleFromHelper = getAdminAllSetting('title_text');
        $output .= "<p><strong>title_text in DB:</strong> " . ($titleInDb ? $titleInDb->value : 'NOT FOUND') . "</p>";
        $output .= "<p><strong>title_text from helper:</strong> " . ($titleFromHelper ?: 'NOT FOUND') . "</p>";

        $appInDb = \App\Models\Setting::where('key', 'app_name')->where('business', 0)->first();
        $appFromHelper = getAdminAllSetting('app_name');
        $output .= "<p><strong>app_name in DB:</strong> " . ($appInDb ? $appInDb->value : 'NOT FOUND') . "</p>";
        $output .= "<p><strong>app_name from helper:</strong> " . ($appFromHelper ?: 'NOT FOUND') . "</p>";

        return $output;
    });

    // FIX DUPLICATE SETTINGS - CRITICAL
    Route::get('fix-duplicate-settings-now', function () {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Super admin only');
        }

        $userId = \Auth::user()->id;
        $fixed = 0;
        $deleted = 0;

        // Get all settings for this user with business=0
        $allSettings = \App\Models\Setting::where('created_by', $userId)
            ->where('business', 0)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Group by key
        $grouped = $allSettings->groupBy('key');

        foreach ($grouped as $key => $settingsGroup) {
            if ($settingsGroup->count() > 1) {
                // Keep the most recently updated one (first in group due to orderBy)
                $keep = $settingsGroup->first();

                // Delete the rest
                foreach ($settingsGroup->skip(1) as $duplicate) {
                    $duplicate->delete();
                    $deleted++;
                }
                $fixed++;
            }
        }
        // Clear cache
        \Cache::forget('admin_settings');
        AdminSettingCacheForget();

        return "<h1 style='color: green; text-align: center; margin-top: 100px;'>✓ DUPLICATES FIXED!</h1>
                <p style='text-align: center; font-size: 20px;'>Fixed $fixed settings, deleted $deleted duplicates!</p>
                <p style='text-align: center;'><a href='/check-settings' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Check Settings Now</a></p>
                <p style='text-align: center;'><a href='/settings'>Go to Settings</a></p>";
    });

    // Direct fix route - just visit this URL
    Route::get('fix-settings-now', function () {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Super admin only');
        }

        $userId = \Auth::user()->id;

        // Fix all settings with wrong business ID
        $updated = \App\Models\Setting::where('created_by', $userId)
            ->where('business', '!=', 0)
            ->update(['business' => 0]);

        // Clear cache
        \Cache::forget('admin_settings');
        \Cache::flush();

        return "<h1 style='color: green; text-align: center; margin-top: 100px;'>✓ SUCCESS!</h1>
                <p style='text-align: center; font-size: 20px;'>Fixed $updated settings!</p>
                <p style='text-align: center;'><a href='/settings' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Settings</a></p>
                <p style='text-align: center;'><a href='/settings-diagnostic'>View Diagnostic Page</a></p>";
    })->name('settings.fix.now');

    Route::post('settings-diagnostic-action', function (\Illuminate\Http\Request $request) {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect()->back()->with('error', 'Super admin only');
        }

        $action = $request->input('action');

        if ($action === 'clear_cache') {
            \Cache::flush();
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('view:clear');
            return redirect()->back()->with('success', 'All caches cleared!');
        }

        if ($action === 'fix_business_ids') {
            $updated = \App\Models\Setting::where('created_by', \Auth::user()->id)
                ->where('business', '!=', 0)
                ->update(['business' => 0]);
            \Cache::forget('admin_settings');
            return redirect()->back()->with('success', "Updated $updated settings to business=0");
        }

        if ($action === 'migrate_settings') {
            $fromUser = $request->input('from_user');
            $toUser = $request->input('to_user');

            \DB::beginTransaction();
            try {
                // Delete any existing settings for to_user to avoid conflicts
                \App\Models\Setting::where('created_by', $toUser)
                    ->where('business', 0)
                    ->delete();

                // Update all settings from from_user to to_user
                $updated = \App\Models\Setting::where('created_by', $fromUser)
                    ->where('business', 0)
                    ->update(['created_by' => $toUser]);

                \DB::commit();
                \Cache::forget('admin_settings');

                return redirect()->back()->with('success', "Migrated $updated settings from user $fromUser to user $toUser");
            } catch (\Exception $e) {
                \DB::rollBack();
                return redirect()->back()->with('error', 'Migration failed: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('error', 'Invalid action');
    })->name('settings.diagnostic.action');

    // Logo-specific diagnostics
    Route::get('/logo-debug', function () {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Super admin only');
        }
        return view('diagnostic.logo-debug');
    })->name('logo.debug');

    Route::post('/clear-logo-cache-now', function () {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Super admin only');
        }

        \Cache::forget('admin_settings');
        AdminSettingCacheForget();
        comapnySettingCacheForget();

        return redirect('/logo-debug')->with('success', 'Cache cleared!');
    })->name('logo.cache.clear');

    Route::post('/fix-logo-duplicates-now', function () {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Super admin only');
        }

        $logoKeys = ['logo_dark', 'logo_light', 'favicon'];
        $deleted = 0;

        foreach ($logoKeys as $key) {
            $settings = \App\Models\Setting::where('key', $key)
                ->where('business', 0)
                ->where('created_by', \Auth::user()->id)
                ->orderBy('updated_at', 'desc')
                ->get();

            if ($settings->count() > 1) {
                // Keep the first (most recent), delete the rest
                $keep = $settings->first();
                foreach ($settings->skip(1) as $duplicate) {
                    $duplicate->delete();
                    $deleted++;
                }
            }
        }

        \Cache::forget('admin_settings');
        AdminSettingCacheForget();

        return redirect('/logo-debug')->with('success', "Deleted $deleted duplicate logo settings!");
    })->name('logo.duplicates.fix');

    Route::post('/reset-logo-settings', function () {
        if (!\Auth::user() || \Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Super admin only');
        }

        $logoKeys = ['logo_dark', 'logo_light', 'favicon'];
        $deleted = \App\Models\Setting::whereIn('key', $logoKeys)
            ->where('business', 0)
            ->where('created_by', \Auth::user()->id)
            ->delete();

        \Cache::forget('admin_settings');
        AdminSettingCacheForget();

        return redirect('/logo-debug')->with('success', "Reset complete! Deleted $deleted logo settings.");
    })->name('logo.settings.reset');
});


// cache
Route::get('/config-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return redirect()->back()->with('success', 'Cache Clear Successfully');
})->name('config.cache');

Route::get('composer/json', function () {
    $path = base_path('packages/workdo');
    $modules = \Illuminate\Support\Facades\File::directories($path);

    $moduleNames = array_map(function ($dir) {
        return basename($dir);
    }, $modules);

    $require = '';
    $repo = '';
    foreach ($moduleNames as $module) {
        $packageName = preg_replace('/([a-z])([A-Z])/', '$1-$2', $module);
        $require .= '"workdo/' . strtolower($packageName) . '": "dev-testing",';
        $repo .= '{
            "type": "path",
            "url": "packages/workdo/' . $module . '"
        },';
    }
    return $require . '<br><br><br>' . rtrim($repo, ',');
});
