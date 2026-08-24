<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Company\SettingsController as CompanySettingsController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\UserController;
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
use App\Http\Controllers\ReferrerController;

/*
|--------------------------------------------------------------------------
| Web Routes — Single-Clinic Application
|--------------------------------------------------------------------------
|
| The SaaS / multi-business layer has been removed. All booking routes
| operate on THE single clinic (no slug). Public registration is disabled.
|
*/

// ======================= Public Booking (single clinic, no slug) =======================
Route::get('booking', [AppointmentController::class, 'appointmentForm'])->name('appointments.form');
Route::post('booking', [AppointmentController::class, 'appointmentFormSubmit'])->name('appointment.form.submit');
Route::get('booking/done/{id}', [AppointmentController::class, 'appointmentDone'])->name('appointments.done');
Route::post('appointment-duration', [AppointmentController::class, 'appointmentDuration'])->name('appointment.duration');
Route::get('get-staff-data', [StaffController::class, 'getStaffData'])->name('get.staff.data');
Route::get('get-services-by-category', [AppointmentController::class, 'getServicesByCategory'])->name('get.services.by.category');
Route::get('appointment/rtl', [AppointmentController::class, 'appointmentRtlSetting'])->name('appointment.rtl');
Route::post('check-user-data', [AppointmentController::class, 'checkUser'])->name('check.user.data');

Route::resource('contacts', ContactUsController::class);
Route::get('/contacts/{id}/description', [ContactUsController::class, 'description'])->name('contact.description');
Route::resource('subscribes', SubscribeController::class);

// for checking online appointment for theme
Route::get('check-service-online-meeting', [ServiceController::class, 'checkServiceOnlineMeeting'])->name('check.service.online.meeting');

// for checking online appointment for form layout
Route::get('check-service-online-meeting-form-layout', [ServiceController::class, 'checkServiceOnlineMeetingFormLayout'])->name('check.service.online.meeting.form.layout');

require __DIR__ . '/auth.php';

Route::get('/login/{lang?}', [AuthenticatedSessionController::class, 'create'])->name('login.lang');
Route::get('/forgot-password/{lang?}', [PasswordResetLinkController::class, 'create'])->name('password.request.lang');
Route::get('/verify-email/{lang?}', [EmailVerificationPromptController::class, '__invoke'])->name('verification.notice.lang');

Route::get('/', [HomeController::class, 'index'])->name('start');

Route::middleware(['auth', 'verified'])->group(function () {

    // Role & Permission
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    // dashboard
    Route::get('/dashboard', [HomeController::class, 'Dashboard'])->name('dashboard');
    Route::get('/appointment-dashboard/{staff?}', [HomeController::class, 'AppointmentDashboard'])->name('appointment.dashboard');
    Route::any('dashboard-index', [HomeController::class, 'Dashboard'])->name('dashboard.index');
    Route::get('/home', [HomeController::class, 'Dashboard'])->name('home');

    // settings
    Route::resource('settings', SettingsController::class);
    Route::post('settings-save', [CompanySettingsController::class, 'store'])->name('settings.save');
    Route::post('company/settings-save', [CompanySettingsController::class, 'store'])->name('company.settings.save');
    Route::post('company/system-settings-save', [CompanySettingsController::class, 'SystemStore'])->name('company.system.setting.store');
    Route::post('comapny-currency-settings', [CompanySettingsController::class, 'saveCompanyCurrencySettings'])->name('company.setting.currency.settings');
    Route::post('currency-settings', [CompanySettingsController::class, 'saveCurrencySettings'])->name('super.admin.currency.settings');

    Route::post('company-setting-save', [CompanySettingsController::class, 'companySettingStore'])->name('company.setting.save');
    Route::post('company/week-settings-save', [CompanySettingsController::class, 'weekStore'])->name('company.week.setting.store');
    Route::post('/update-note-value', [CompanySettingsController::class, 'companyupdateNoteValue'])->name('admin.update.note.value');
    Route::post('company/update-note-value', [CompanySettingsController::class, 'companyupdateNoteValue'])->name('company.update.note.value');

    Route::post('company/custom-js-save', [CompanySettingsController::class, 'CustomJsStore'])->name('company.custom.js.store');
    Route::post('super-admin/custom-js-save', [CompanySettingsController::class, 'CustomJsStore'])->name('super.admin.custom.js.save');
    Route::post('company/custom-css-save', [CompanySettingsController::class, 'CustomCssStore'])->name('company.custom.css.store');
    Route::post('super-admin/custom-css-save', [CompanySettingsController::class, 'CustomCssStore'])->name('super.admin.custom.css.save');
    Route::post('company/default-status-save', [CompanySettingsController::class, 'DefaultStatusStore'])->name('company.default.status.store');

    Route::post('company/booking-mode-save', [CompanySettingsController::class, 'bookingModeStore'])->name('company.booking.mode.store');

    Route::post('email-settings-save', [SettingsController::class, 'mailStore'])->name('email.setting.store');
    Route::post('test-mail', [SettingsController::class, 'testMail'])->name('test.mail');
    Route::post('test-mail-send', [SettingsController::class, 'sendTestMail'])->name('test.mail.send');
    Route::post('email-notification-settings-save', [SettingsController::class, 'mailNotificationStore'])->name('email.notification.setting.store');

    Route::post('storage-settings-save', [CompanySettingsController::class, 'storageStore'])->name('storage.setting.store');
    Route::post('seo/setting/save', [CompanySettingsController::class, 'seoSetting'])->name('seo.setting.save');
    Route::post('cookie-settings-save', [CompanySettingsController::class, 'CookieSetting'])->name('cookie.setting.store');
    Route::post('super-admin/settings-save', [CompanySettingsController::class, 'store'])->name('super.admin.settings.save');
    Route::post('super-admin/system-settings-save', [CompanySettingsController::class, 'SystemStore'])->name('super.admin.system.setting.store');

    Route::get('/setting/section/{module}/{methord?}', [SettingsController::class, 'getSettingSection'])->name('setting.section.get');

    // users
    Route::resource('users', UserController::class);
    Route::get('users/list/view', [UserController::class, 'List'])->name('users.list.view');
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::post('edit-profile', [UserController::class, 'editprofile'])->name('edit.profile');
    Route::post('change-password', [UserController::class, 'updatePassword'])->name('update.password');
    Route::any('user-reset-password/{id}', [UserController::class, 'UserPassword'])->name('users.reset');
    Route::get('user-login/{id}', [UserController::class, 'LoginManage'])->name('users.login');
    Route::post('user-reset-password/{id}', [UserController::class, 'UserPasswordReset'])->name('user.password.update');
    Route::post('user-unable', [UserController::class, 'UserUnable'])->name('user.unable');
    Route::get('user-verified/{id}', [UserController::class, 'verifeduser'])->name('user.verified');

    // User Log
    Route::get('users/logs/history', [UserController::class, 'UserLogHistory'])->name('users.userlog.history');
    Route::get('users/logs/{id}', [UserController::class, 'UserLogView'])->name('users.userlog.view');
    Route::delete('users/logs/destroy/{id}', [UserController::class, 'UserLogDestroy'])->name('users.userlog.destroy');

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

    // category
    Route::resource('category', CategoryController::class);

    // service
    Route::resource('service', ServiceController::class);

    // staff
    Route::resource('staff', StaffController::class);

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

    // Print Token
    Route::get('appointment/{id}/print-token', [AppointmentController::class, 'printToken'])->name('appointment.print-token');

    // Referring Doctors
    Route::resource('referrer', ReferrerController::class);
    Route::post('referrer/{referrer}/toggle-status', [ReferrerController::class, 'toggleStatus'])->name('referrer.toggle-status');

    // custom field
    Route::post('business/custom-field-setting/{id}', [CustomFieldController::class, 'CustomFieldSetting'])->name('custom-field.setting');
    Route::post('/delete-field', [CustomFieldController::class, 'destroy'])->name('delete.field');

    // custom status
    Route::resource('custom-status', CustomStatusController::class);

    // Files
    Route::post('business/files-setting/{id}', [FileController::class, 'Filesetting'])->name('files.setting');

    // customer
    Route::resource('customer', CustomerController::class);
    Route::get('customer-list', [CustomerController::class, 'customerList'])->name('customer.list');

    // business hours
    Route::resource('business-hours', BusinessHoursController::class);

    // business holidays
    Route::resource('business-holiday', BusinessHolidayController::class);

    // clinic (formerly "business") settings — single clinic record
    // Keep legacy route names working while the UI runs in single-clinic mode.
    Route::get('business', [BusinessController::class, 'index'])->name('business.index');
    Route::get('business/create', [BusinessController::class, 'create'])->name('business.create');
    Route::post('business', [BusinessController::class, 'store'])->name('business.store');
    Route::get('business/{id}', [BusinessController::class, 'show'])->name('business.show');
    Route::get('clinic/edit/{id?}', [BusinessController::class, 'edit'])->name('business.edit');
    Route::match(['put', 'patch'], 'clinic/update/{id?}', [BusinessController::class, 'update'])->name('business.update');
    Route::get('clinic/manage/{id?}', [BusinessController::class, 'businessManage'])->name('business.manage');
    Route::delete('business/{id}', [BusinessController::class, 'destroy'])->name('business.destroy');
    Route::post('business/theme/update', [BusinessController::class, 'BusinessThemeUpdate'])->name('business.theme.update');
    Route::post('business/check', [BusinessController::class, 'businessCheck'])->name('business.check');
    Route::post('business/domain-setting/{id}', [BusinessController::class, 'domainsetting'])->name('business.domain-setting');
    Route::post('business/slot-capacity-setting/{id}', [BusinessController::class, 'slotCapacitysetting'])->name('slot.capacity-setting');
    Route::post('business/appointment-reminder-setting/{id}', [BusinessController::class, 'appointmentRemindersetting'])->name('appointment.reminder-setting');

    // theme customize
    Route::get('themes/{id}/customize/{business}', [ThemeSettingController::class, 'themeCustomize'])->name('business.customize');
    Route::get('themes/{id}/customize/{slug}/{sub_slug}/{business}', [ThemeSettingController::class, 'customize_theme'])->name('customize.edit');
    Route::post('themes/{business}/{id}/customize', [ThemeSettingController::class, 'customize_theme_update'])->name('customize.update');
    Route::post('file-get', [ThemeSettingController::class, 'imageFileGet'])->name('file.get');

    // blog
    Route::get('themes/{id}/manage-blog/{business}', [BlogController::class, 'blogManage'])->name('blog.manage');
    Route::get('themes/{id}/blog/{business}', [BlogController::class, 'blogCreate'])->name('blog.create');
    Route::resource('blogs', BlogController::class);

    // testimonial
    Route::get('themes/{id}/manage-testimonial/{business}', [TestimonialController::class, 'testimonialManage'])->name('testimonial.manage');
    Route::get('themes/{id}/testimonial/{business}', [TestimonialController::class, 'testimonialCreate'])->name('testimonial.create');
    Route::resource('testimonials', TestimonialController::class);

    // Email Templates
    Route::resource('email-templates', EmailTemplateController::class);
    Route::get('email_template_lang/{id}/{lang?}', [EmailTemplateController::class, 'show'])->name('manage.email.language');
    Route::put('email_template_store/{pid}', [EmailTemplateController::class, 'storeEmailLang'])->name('store.email.language');
    Route::resource('email_template', EmailTemplateController::class);

    // notification
    Route::resource('notification-template', NotificationController::class);
    Route::get('notification-template/{id}/{lang?}', [NotificationController::class, 'show'])->name('manage.notification.language');
    Route::post('notification-template/{pid}', [NotificationController::class, 'storeNotificationLang'])->name('store.notification.language');

    // Routes For OnlineAppointment Option.
    Route::get('online-appointment-create/{serviceId}/{businessId}', [ServiceController::class, 'createOnlineAppointment'])->name('create.online.appointment');
    Route::post('save-online-meeting-setting/{serviceId}', [ServiceController::class, 'saveOnlineMeetingSetting'])->name('save.online.meeting.setting');

});

Route::middleware(['web'])->group(function () {
    Route::get('find-appointment', [HomeController::class, 'findAppointment'])->name('find.appointment');
    Route::post('track-appointment', [HomeController::class, 'trackAppointment'])->name('track.appointment');
});

// cookie consent page
Route::get('cookie/consent', [CompanySettingsController::class, 'CookieConsent'])->name('cookie.consent');

// cache
Route::get('/config-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return redirect()->back()->with('success', 'Cache Clear Successfully');
})->name('config.cache');
