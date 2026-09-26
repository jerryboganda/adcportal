<?php

use App\Models\Currency;
use App\Models\User;
use App\Models\Business;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;








if (!function_exists('getAdminAllSetting')) {
    function getAdminAllSetting($key = null)
    {
        // Single-clinic app: system-level settings live in rows with business = 0.
        $settings = Cache::rememberForever('admin_settings', function () {
            return Setting::where('business', 0)->pluck('value', 'key')->toArray();
        });

        if ($key) {
            return $settings[$key] ?? null;
        }

        return $settings;
    }
}

if (!function_exists('getCompanyAllSetting')) {
    function getCompanyAllSetting($user_id = null, $business = null)
    {
        // Multi-tenant: settings are scoped to the caller's clinic, cached per tenant.
        $businessId = $business ?: getActiveBusiness($user_id);

        if ($businessId == 0) {
            return [];
        }

        return Cache::rememberForever("company_settings_business_{$businessId}", function () use ($businessId) {
            return Setting::where('business', $businessId)->pluck('value', 'key')->toArray();
        });
    }
}

if (!function_exists('comapnySettingCacheForget')) {
    function comapnySettingCacheForget($businessId = null)
    {
        try {
            Cache::forget('company_settings_single'); // legacy key
            Cache::forget('company_settings_business_'.($businessId ?: getActiveBusiness()));
        } catch (\Exception $e) {
            \Log::error('comapnySettingCacheForget :'.$e->getMessage());
        }

        return true;
    }
}


if (!function_exists('company_setting')) {
    function company_setting($key, $user_id = null, $business = null)
    {
        if ($key) {
            $company_settings = getCompanyAllSetting();
            $setting = null;
            if (!empty($company_settings)) {
                $setting = (array_key_exists($key, $company_settings)) ? $company_settings[$key] : null;
            }
            return $setting;
        }
    }
}


if (!function_exists('comapnySettingCacheForget')) {
    function comapnySettingCacheForget()
    {
        try {
            Cache::forget('company_settings_single');
        } catch (\Exception $e) {
            \Log::error('comapnySettingCacheForget :' . $e->getMessage());
        }
    }
}


if (!function_exists('flush_active_business_cache')) {
    /**
     * Reset the per-process active-business memoization. HTTP requests start
     * with fresh process state naturally; tests and long-running workers
     * must call this when identity or tenant context changes.
     */
    function flush_active_business_cache(): void
    {
        \App\Support\RuntimeContext::reset();
    }
}

if (!function_exists('getActiveBusiness')) {
    /**
     * Multi-tenant: the active tenant is the authenticated user's clinic.
     * Platform staff hold no tenant context of their own — a tenant context
     * exists only while a verified break-glass support session is active
     * (server-side session value, never a client-supplied id). Console /
     * seeding context (no auth) falls back to the first business so artisan
     * commands keep working.
     */
    function getActiveBusiness($user_id = null)
    {
        $user = $user_id ? User::find($user_id) : Auth::user();

        if ($user) {
            $key = $user->id.'|'.($user_id ? 'b' : 'r');
            if (! isset(\App\Support\RuntimeContext::$activeBusiness[$key])) {
                if ($user->isPlatformAdmin()) {
                    $businessId = (int) (app()->bound('session') ? session('support_context') : 0);
                } else {
                    $businessId = (int) ($user->business_id ?: $user->active_business ?: 0);
                    if ($businessId === 0 && $user->type === 'admin') {
                        $businessId = (int) (Business::where('created_by', $user->id)->value('id') ?? 0);
                    }
                }
                \App\Support\RuntimeContext::$activeBusiness[$key] = $businessId;
            }

            return \App\Support\RuntimeContext::$activeBusiness[$key];
        }

        return (int) (Business::first()->id ?? 0);
    }
}



if (!function_exists('creatorId')) {
    /**
     * Multi-tenant: the acting user records authorship. In console/seeding
     * context, falls back to the tenant owner (admin) of the active business.
     */
    function creatorId()
    {
        if (Auth::check()) {
            return Auth::id();
        }

        static $fallback = null;
        if ($fallback === null) {
            $businessId = getActiveBusiness();
            $admin = $businessId
                ? User::where('type', 'admin')->where(function ($q) use ($businessId) {
                    $q->where('business_id', $businessId)->orWhere('created_by', $businessId);
                })->first()
                : null;
            $admin = $admin ?? User::where('type', 'admin')->first();
            $fallback = $admin ? $admin->id : 0;
        }

        return $fallback;
    }
}



if (!function_exists('getActiveLanguage')) {
    function getActiveLanguage()
    {
        if ((Auth::check()) && (!empty(Auth::user()->lang))) {
            return Auth::user()->lang;
        } else {
            if (in_array(\Request::route()->getName(), ['appointments.form', 'appointment.form.submit', 'appointments.done', 'appointment.duration', 'get.staff.data', 'appointment.rtl'])) {
                return 'en';
            } else {
                $admin_settings = getAdminAllSetting();
                return !empty($admin_settings['defult_language']) ? $admin_settings['defult_language'] : 'en';
            }
        }
    }
}



// setConfigEmail ( SMTP )
if (!function_exists('SetConfigEmail')) {
    function SetConfigEmail($user_id = null, $business_id = null)
    {
        try {
            // Single-clinic app: one SMTP configuration.
            $company_settings = getCompanyAllSetting();

            config(
                [
                    'mail.driver' => $company_settings['mail_driver'],
                    'mail.host' => $company_settings['mail_host'],
                    'mail.port' => $company_settings['mail_port'],
                    'mail.encryption' => $company_settings['mail_encryption'],
                    'mail.username' => $company_settings['mail_username'],
                    'mail.password' => $company_settings['mail_password'],
                    'mail.from.address' => $company_settings['mail_from_address'],
                    'mail.from.name' => $company_settings['mail_from_name'],
                ]
            );
            return true;
        } catch (\Exception $e) {

            return false;
        }
    }
}

// file upload

if (!function_exists('upload_file')) {
    function upload_file($request, $key_name, $name, $path, $custom_validation = [])
    {
        try {
            $storage_settings = getAdminAllSetting();
            if (isset($storage_settings['storage_setting'])) {
                if ($storage_settings['storage_setting'] == 'wasabi') {
                    config(
                        [
                            'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                            'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                            'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                            'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                            'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                            'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                        ]
                    );
                    $max_size = !empty($storage_settings['wasabi_max_upload_size']) ? $storage_settings['wasabi_max_upload_size'] : '2048';
                    $mimes =  !empty($storage_settings['wasabi_storage_validation']) ? $storage_settings['wasabi_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                } else if ($storage_settings['storage_setting'] == 's3') {
                    config(
                        [
                            'filesystems.disks.s3.key' => $storage_settings['s3_key'],
                            'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                            'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                            'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                            // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                            // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                        ]
                    );
                    $max_size = !empty($storage_settings['s3_max_upload_size']) ? $storage_settings['s3_max_upload_size'] : '2048';
                    $mimes =  !empty($storage_settings['s3_storage_validation']) ? $storage_settings['s3_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                } else {
                    $max_size = !empty($storage_settings['local_storage_max_upload_size']) ? $storage_settings['local_storage_max_upload_size'] : '2048';
                    $mimes =  !empty($storage_settings['local_storage_validation']) ? $storage_settings['local_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                }
                if (is_array($request)) {
                    $request = new Illuminate\Http\Request($request);
                }
                $file = $request->$key_name;

                $extension = strtolower($file->getClientOriginalExtension());
                $allowed_extensions = explode(',', $mimes);
                if (empty($extension) || !in_array($extension, $allowed_extensions)) {
                    return [
                        'flag' => 0,
                        'msg' => 'The ' . $key_name . ' must be a file of type: ' . implode(', ', $allowed_extensions) . '.',
                    ];
                }

                if (count($custom_validation) > 0) {
                    $validation = $custom_validation;
                } else {
                    $validation = [
                        'mimes:' . $mimes,
                        'max:' . $max_size,
                    ];
                }
                $validator = Validator::make($request->all(), [
                    $key_name => $validation
                ]);
                if ($validator->fails()) {
                    $res = [
                        'flag' => 0,
                        'msg' => $validator->messages()->first(),
                    ];
                    return $res;
                } else {
                    $save = Storage::disk($storage_settings['storage_setting'])->putFileAs(
                        $path,
                        $file,
                        $name
                    );
                    if ($storage_settings['storage_setting'] == 'wasabi') {
                        $url = $save;
                    } elseif ($storage_settings['storage_setting'] == 's3') {
                        $url = $save;
                    } else {
                        $url = 'uploads/' . $save;
                    }
                    $res = [
                        'flag' => 1,
                        'msg'  => 'success',
                        'url'  => $url
                    ];
                    return $res;
                }
            } else {
                $res = [
                    'flag' => 0,
                    'msg' => 'not set configurations',
                ];
                return $res;
            }
        } catch (\Exception $e) {
            $res = [
                'flag' => 0,
                'msg' => $e->getMessage(),
            ];
            return $res;
        }
    }
}



if (!function_exists('get_file')) {
    function get_file($path)
    {

        $storage_settings = getAdminAllSetting();

        if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 's3') {
            config(
                [
                    'filesystems.disks.s3.key' => $storage_settings['s3_key'],
                    'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                    'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                    'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                    // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                    // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                ]
            );
            return Storage::disk('s3')->url($path);
        } else if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 'wasabi') {
            config(
                [
                    'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                    'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                    'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                    'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                    'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                    'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                ]
            );

            return Storage::disk('wasabi')->url($path);
        } else {
            return asset($path);
        }
    }
}

// if (!function_exists('makeEmailLang'))
// {
//     function makeEmailLang($lang)
//     {
//         $templates = EmailTemplate::all();
//         foreach ($templates as $template) {

//             $default_lang  = EmailTemplateLang::where('parent_id', '=', $template->id)->where('lang', 'LIKE', 'en')->first();

//             $emailTemplateLang              = new EmailTemplateLang();
//             $emailTemplateLang->parent_id   = $template->id;
//             $emailTemplateLang->lang        = $lang;
//             $emailTemplateLang->subject     = $default_lang->subject;
//             $emailTemplateLang->content     = $default_lang->content;
//             $emailTemplateLang->variables   = $default_lang->variables;
//             $emailTemplateLang->save();
//         }
//     }
// }



// Get Cache Size















// Return Currency Symbol , Currency format & Currency Sybmool position ( create for query optimization)
