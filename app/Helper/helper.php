<?php

use App\Models\Currency;
use App\Models\Language;
use App\Models\Permission;
use App\Models\User;
use App\Models\Business;
use Illuminate\Support\Collection;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\BusinessHours;
use Carbon\Carbon;

if (!function_exists('getMenu')) {
    function getMenu()
    {
        $user = auth()->user();
        return Cache::rememberForever(
            'sidebar_menu_' . $user->id,
            function () use ($user) {
                try {
                    $menu = new \App\Classes\Menu($user);
                    event(new \App\Events\CompanyMenuEvent($menu));
                    return generateMenu($menu->menu, null);
                } catch (\Exception $e) {
                    \Log::error('Menu generation error: ' . $e->getMessage());
                    // Return empty div if menu generation fails
                    return '<div class="alert alert-danger">Menu generation failed</div>';
                }
            }
        );
    }
}

if (!function_exists('generateMenu')) {
    function generateMenu($menuItems, $parent = null,$printedGroups = [])
    {
        $html = '';

        // Group the items by the 'group' key
        $groupedItems = collect($menuItems)->groupBy('group');

        $groupOrder = ['base', 'appointments', 'codes & tickets', 'contacts & reports', 'others'];

        $sortedGroupedItems = $groupedItems->sortBy(function ($group, $key) use ($groupOrder) {
            // Return the index of the group in the $groupOrder array to define the sort order
            return array_search($key, $groupOrder);
        });
        // dd($sortedGroupedItems);
        foreach ($sortedGroupedItems as $group => $items)
        {

            {
                if ($group === 'base') {
                    // Process the items directly without a group name header
                    $printedGroups[] = $group; // Mark base as printed
                } else {
                    // Print the group header for all groups except 'base'
                    if ($parent === null && !in_array($group, $printedGroups)) {
                        $html .= '<li class="nav-main-title"><h3> '. ucfirst($group) .'</h3>';
                        $printedGroups[] = $group; // Mark this group as printed
                    }
                }

            }
            // Convert the collection of items to an array and filter based on parent
            $filteredItems = array_filter($items->toArray(), function ($item) use ($parent) {
                return $item['parent'] == $parent;
            });

            // Sort the filtered items by their order
            usort($filteredItems, function ($a, $b) {
                return $a['order'] - $b['order'];
            });
            // Loop through the filtered items and generate HTML
            foreach ($filteredItems as $item) {
                $hasChildren = hasChildren($menuItems, $item['name']);
                if ($item['parent'] == null) {
                    $html .= '<li class="dash-item dash-hasmenu">';
                } else {
                    $html .= '<li class="dash-item">';
                }

                if ($item['name'] == 'add-on-manager') {
                    $html .= '<a href="' . (!empty($item['route']) ? route($item['route']) : '#!') . '" class="dash-link d-flex align-items-center">';
                    if ($item['parent'] == null) {
                        $html .= ' <span class="dash-micon"><i class="ti ti-' . $item['icon'] . '"></i></span>
                        <div class="text-center"> <span class="dash-mtext">';
                        $html .= __($item['title']) . '</span> <span class="text-center d-block animate-charcter">Premium</span></div>';
                    }
                } else {
                    $html .= '<a href="' . (!empty($item['route']) ? route($item['route']) : '#!') . '" class="dash-link">';

                    if ($item['parent'] == null) {
                        $html .= ' <span class="dash-micon"><i class="ti ti-' . $item['icon'] . '"></i></span>
                        <span class="dash-mtext">';
                    }
                    $html .= __($item['title']) . '</span>';
                }

                if ($hasChildren) {
                    $html .= '<span class="dash-arrow"> <i data-feather="chevron-right"></i> </span> </a>';
                    $html .= '<ul class="dash-submenu">';
                    $html .= generateMenu($menuItems, $item['name'],$printedGroups);
                    $html .= '</ul>';
                } else {
                    $html .= '</a>';
                }

                $html .= '</li>';
            }
        }

        return $html;
    }
}

if (!function_exists('hasChildren')) {
    function hasChildren($menuItems, $name)
    {
        foreach ($menuItems as $item) {
            if (isset($item['parent']) && $item['parent'] === $name) {
                return true;
            }
        }
        return false;
    }
}


if (!function_exists('getSettingMenu')) {
    function getSettingMenu()
    {
        $user = auth()->user();
        $menu = new \App\Classes\Menu($user);
        event(new \App\Events\CompanySettingMenuEvent($menu));
        return generateSettingMenu($menu->menu);
    }
}


if (!function_exists('generateSettingMenu')) {
    function generateSettingMenu($menuItems)
    {
        usort($menuItems, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        $html = '';
        foreach ($menuItems as $menu) {
            $html .= '<a href="#' . $menu['navigation'] . '" data-module="' . $menu['module'] . '" class="list-group-item list-group-item-action setting-menu-nav">' . $menu['title'] . '<div class="float-end"><i class="ti ti-chevron-right"></i></div></a>';
        }
        return $html;
    }
}
if (!function_exists('getSettings')) {
    function getSettings()
    {
        $user = auth()->user();
        $settings = getCompanyAllSetting();
        $html = new \App\Classes\Setting($user, $settings);
        event(new \App\Events\CompanySettingEvent($html));
        return generateSettings($html->html);
    }
}
if (!function_exists('generateSettings')) {
    function generateSettings($settingItems)
    {
        usort($settingItems, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        $html = '';
        foreach ($settingItems as $setting) {
            $html .= $setting['html'];
        }
        return $html;
    }
}

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
        // Single-clinic app: one settings set. Arguments kept for call-site compatibility.
        return Cache::rememberForever('company_settings_single', function () {
            return Setting::where('business', '!=', 0)->pluck('value', 'key')->toArray();
        });
    }
}

if (!function_exists('admin_setting')) {
    function admin_setting($key)
    {
        if ($key) {
            $admin_settings = getAdminAllSetting();
            $setting = (array_key_exists($key, $admin_settings)) ? $admin_settings[$key] : null;
            return $setting;
        }
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

if (!function_exists('AdminSettingCacheForget')) {
    function AdminSettingCacheForget()
    {
        try {
            Cache::forget('admin_settings');
        } catch (\Exception $e) {
            \Log::error('AdminSettingCacheForget :' . $e->getMessage());
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

if (!function_exists('sideMenuCacheForget')) {
    function sideMenuCacheForget($type = null)
    {
        if ($type == 'all') {
            Cache::flush();
            return true;
        }

        foreach (User::select('id')->pluck('id') as $id) {
            try {
                Cache::forget('sidebar_menu_' . $id);
            } catch (\Exception $e) {
                \Log::error('sideMenuCacheForget :' . $e->getMessage());
            }
        }

        return true;
    }
}

if (!function_exists('getActiveBusiness')) {
    function getActiveBusiness($user_id = null)
    {
        // Single-clinic app: always THE clinic's id (0 until the clinic record is seeded).
        static $businessId = null;
        if ($businessId === null) {
            $businessId = optional(Business::first())->id ?? 0;
        }
        return $businessId;
    }
}

if (!function_exists('getBusiness')) {
    function getBusiness()
    {
        // Single-clinic app: returns the single clinic record.
        static $business = null;
        if ($business === null && Auth::check()) {
            $business = Business::find(getActiveBusiness());
        }
        return $business ? collect([$business]) : collect();
    }
}


if (!function_exists('creatorId')) {
    function creatorId()
    {
        // Single-clinic app: everything belongs to the admin user.
        static $adminId = null;
        if ($adminId === null) {
            $admin = User::where('type', 'admin')->first() ?? User::orderBy('id')->first();
            $adminId = $admin ? $admin->id : 0;
        }
        return $adminId;
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

if (!function_exists('languages')) {
    function languages()
    {

        try {
            $arrLang = Language::where('status', 1)->get()->pluck('name', 'code')->toArray();
        } catch (\Throwable $th) {
            $arrLang = [
                "ar" => "Arabic",
                "da" => "Danish",
                "de" => "German",
                "en" => "English",
                "es" => "Spanish",
                "fr" => "French",
                "it" => "Italian",
                "ja" => "Japanese",
                "nl" => "Dutch",
                "pl" => "Polish",
                "pt" => "Portuguese",
                "ru" => "Russian",
                "tr" => "Turkish"
            ];
        }
        return $arrLang;
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

if (!function_exists('multi_upload_file')) {
    function multi_upload_file($request, $key_name, $name, $path, $custom_validation = [])
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

                $file = $request;
                $key_validation = $key_name . '*';

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
                $validator = Validator::make(array($key_name => $request), [
                    $key_validation => $validation
                ]);
                if ($validator->fails()) {
                    $res = [
                        'flag' => 0,
                        'msg' => $validator->messages()->first(),
                    ];


                    return $res;
                } else {

                    $name = $name;

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
                    'msg' => 'not set configration',
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

if (!function_exists('check_file')) {
    function check_file($path)
    {
        if (!empty($path)) {
            $storage_settings = getAdminAllSetting();
            if (isset($storage_settings['storage_setting']) == null || $storage_settings['storage_setting'] == 'local') {

                return file_exists(base_path($path));
            } else {

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
                }
                try {
                    return  Storage::disk($storage_settings['storage_setting'])->exists($path);
                } catch (\Throwable $th) {
                    return 0;
                }
            }
        } else {
            return 0;
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
if (!function_exists('get_base_file')) {
    function get_base_file($path)
    {
        $admin_settings = getAdminAllSetting();
        if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 's3') {
            config(
                [
                    'filesystems.disks.s3.key' => $admin_settings['s3_key'],
                    'filesystems.disks.s3.secret' => $admin_settings['s3_secret'],
                    'filesystems.disks.s3.region' => $admin_settings['s3_region'],
                    'filesystems.disks.s3.bucket' => $admin_settings['s3_bucket'],
                    // 'filesystems.disks.s3.url' => $admin_settings['s3_url'],
                    // 'filesystems.disks.s3.endpoint' => $admin_settings['s3_endpoint'],
                ]
            );

            return Storage::disk('s3')->url($path);
        } else if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 'wasabi') {
            config(
                [
                    'filesystems.disks.wasabi.key' => $admin_settings['wasabi_key'],
                    'filesystems.disks.wasabi.secret' => $admin_settings['wasabi_secret'],
                    'filesystems.disks.wasabi.region' => $admin_settings['wasabi_region'],
                    'filesystems.disks.wasabi.bucket' => $admin_settings['wasabi_bucket'],
                    'filesystems.disks.wasabi.root' => $admin_settings['wasabi_root'],
                    'filesystems.disks.wasabi.endpoint' => $admin_settings['wasabi_url']
                ]
            );
            return Storage::disk('wasabi')->url($path);
        } else {
            return base_path($path);
        }
    }
}
if (!function_exists('delete_file')) {
    function delete_file($path)
    {
        if (check_file($path)) {
            $storage_settings = getAdminAllSetting();
            if (isset($storage_settings['storage_setting'])) {
                if ($storage_settings['storage_setting'] == 'local') {
                    return File::delete($path);
                } else {
                    if ($storage_settings['storage_setting'] == 's3') {
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
                    } else if ($storage_settings['storage_setting'] == 'wasabi') { {
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
                        }
                        return Storage::disk($storage_settings['storage_setting'])->delete($path);
                    }
                }
            }
        }
    }
}

if (!function_exists('get_size')) {
    function get_size($url)
    {
        $url = str_replace(' ', '%20', $url);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);

        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);
        return $size;
    }
}
if (!function_exists('delete_folder')) {
    function delete_folder($path)
    {
        $storage_settings = getAdminAllSetting();
        if (isset($storage_settings['storage_setting'])) {

            if ($storage_settings['storage_setting'] == 'local') {
                if (is_dir(Storage::path($path))) {
                    return \File::deleteDirectory(Storage::path($path));
                }
            } else {
                if ($storage_settings['storage_setting'] == 's3') {
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
                } else if ($storage_settings['storage_setting'] == 'wasabi') {
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
                }
                return Storage::disk($storage_settings['storage_setting'])->deleteDirectory($path);
            }
        }
    }
}
if (!function_exists('delete_directory')) {
    function delete_directory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
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
if (!function_exists('error_res')) {
    function error_res($msg = "", $args = array())
    {
        $msg       = $msg == "" ? "error" : $msg;
        $msg_id    = 'error.' . $msg;
        $converted = \Lang::get($msg_id, $args);
        $msg       = $msg_id == $converted ? $msg : $converted;
        $json      = array(
            'flag' => 0,
            'msg' => $msg,
        );

        return $json;
    }
}

if (!function_exists('success_res')) {
    function success_res($msg = "", $args = array())
    {
        $msg       = $msg == "" ? "success" : $msg;
        $json      = array(
            'flag' => 1,
            'msg' => $msg,
        );

        return $json;
    }
}

if (!function_exists('GetDeviceType')) {
    function GetDeviceType($user_agent)
    {
        $mobile_regex = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tablet_regex = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';
        if (preg_match_all($mobile_regex, $user_agent)) {
            return 'mobile';
        } else {
            if (preg_match_all($tablet_regex, $user_agent)) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
    }
}

// Get Cache Size
if (!function_exists('CacheSize')) {
    function CacheSize()
    {
        //start for cache clear
        $file_size = 0;
        foreach (\File::allFiles(storage_path('/framework')) as $file) {
            $file_size += $file->getSize();
        }
        $file_size = number_format($file_size / 1000000, 4);

        return $file_size;
    }
}


if (!function_exists('sidebar_logo')) {
    function sidebar_logo()
    {
        $admin_settings = getAdminAllSetting();
        if (\Auth::check()) {
            $company_settings = getCompanyAllSetting();

            if ((isset($company_settings['cust_darklayout']) ? $company_settings['cust_darklayout'] : 'off') == 'on') {
                if (!empty($company_settings['logo_light'])) {
                    if (check_file($company_settings['logo_light'])) {
                        return $company_settings['logo_light'];
                    } else {
                        return 'uploads/logo/logo_light.png';
                    }
                } else {
                    if (!empty($admin_settings['logo_light'])) {
                        if (check_file($admin_settings['logo_light'])) {
                            return $admin_settings['logo_light'];
                        } else {
                            return 'uploads/logo/logo_light.png';
                        }
                    } else {
                        return 'uploads/logo/logo_light.png';
                    }
                }
            } else {
                if (!empty($company_settings['logo_dark'])) {
                    if (check_file($company_settings['logo_dark'])) {
                        return $company_settings['logo_dark'];
                    } else {
                        return 'uploads/logo/logo_dark.png';
                    }
                } else {
                    if (!empty($admin_settings['logo_dark'])) {
                        if (check_file($admin_settings['logo_dark'])) {
                            return $admin_settings['logo_dark'];
                        } else {
                            return 'uploads/logo/logo_dark.png';
                        }
                    } else {
                        return 'uploads/logo/logo_dark.png';
                    }
                }
            }
        } else {
            if ((isset($admin_settings['cust_darklayout']) ? $admin_settings['cust_darklayout'] : 'off') == 'on') {
                if (!empty($admin_settings['logo_light'])) {
                    if (check_file($admin_settings['logo_light'])) {
                        return $admin_settings['logo_light'];
                    } else {
                        return 'uploads/logo/logo_light.png';
                    }
                } else {
                    return 'uploads/logo/logo_light.png';
                }
            } else {
                if (!empty($admin_settings['logo_dark'])) {
                    if (check_file($admin_settings['logo_dark'])) {
                        return $admin_settings['logo_dark'];
                    } else {
                        return 'uploads/logo/logo_dark.png';
                    }
                } else {
                    return 'uploads/logo/logo_dark.png';
                }
            }
        }
    }
}

if (!function_exists('light_logo')) {
    function light_logo()
    {
        if (\Auth::check()) {
            $company_settings = getCompanyAllSetting();
            $logo_light = isset($company_settings['logo_light']) ? $company_settings['logo_light'] : 'uploads/logo/logo_light.png';
        } else {
            $admin_settings = getAdminAllSetting();
            $logo_light = isset($admin_settings['logo_light']) ? $admin_settings['logo_light'] : 'uploads/logo/logo_light.png';
        }
        if (check_file($logo_light)) {
            return $logo_light;
        } else {
            return 'uploads/logo/logo_dark.png';
        }
    }
}

if (!function_exists('dark_logo')) {
    function dark_logo()
    {
        if (\Auth::check()) {
            $company_settings = getCompanyAllSetting();
            $logo_dark = isset($company_settings['logo_dark']) ? $company_settings['logo_dark'] : 'uploads/logo/logo_dark.png';
        } else {
            $admin_settings = getAdminAllSetting();
            $logo_dark = isset($admin_settings['logo_dark']) ? $admin_settings['logo_dark'] : 'uploads/logo/logo_dark.png';
        }
        if (check_file($logo_dark)) {
            return $logo_dark;
        } else {
            return 'uploads/logo/logo_dark.png';
        }
    }
}

if (!function_exists('currency_format')) {
    function currency_format($price, $company_id = null, $business = null)
    {

        return number_format($price, company_setting('currency_format', $company_id, $business), '.', '');
    }
}

if (!function_exists('currency_format_with_sym')) {

    function currency_format_with_sym($price, $company_id = null, $business = null)
    {
        if (!empty($company_id) && empty($business)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($business)) {
            $company_settings = getCompanyAllSetting($company_id, $business);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $symbol_position = 'pre';
        $symbol = '₨'; // Default to PKR symbol
        $format = '1';
        $currency_space = null;
        $number = explode('.', $price);
        $length = strlen(trim($number[0]));

        if (isset($company_settings['site_currency_symbol_position']) && $company_settings['site_currency_symbol_position'] == "post") {
            $symbol_position = 'post';
        }

        if (isset($company_settings['defult_currancy_symbol'])) {
            $symbol = $company_settings['defult_currancy_symbol'];
        }

        if (isset($company_settings['currency_format'])) {
            $format = $company_settings['currency_format'];
        }

        if ($length > 3) {
            $decimal_separator  = isset($company_settings['float_number']) && $company_settings['float_number'] === 'dot' ? '.' : ',';
            $thousand_separator = isset($company_settings['thousand_separator']) && $company_settings['thousand_separator'] === 'dot' ? '.' : ',';
        } else {
            $decimal_separator  = isset($company_settings['decimal_separator']) && $company_settings['decimal_separator'] === 'dot'  ? '.' : ',';
            $thousand_separator = isset($company_settings['thousand_separator']) && $company_settings['thousand_separator'] === 'dot' ? '.' : ',';
        }

        if (isset($company_settings['currency_space'])) {
            $currency_space = isset($company_settings['currency_space']) ? $company_settings['currency_space'] : '';
        }
        if (isset($company_settings['site_currency_symbol_name'])) {
            $defult_currancy = $company_settings['defult_currancy'];
            $defult_currancy_symbol = $company_settings['defult_currancy_symbol'];
            $symbol = $company_settings['site_currency_symbol_name'] == 'symbol' ? $defult_currancy_symbol : $defult_currancy;
        }
        $price = number_format($price, $format, $decimal_separator, $thousand_separator);

        return (($symbol_position == "pre") ? $symbol : '') . ($currency_space == 'withspace' ? ' ' : '') . $price . ($currency_space == 'withspace' ? ' ' : '') . (($symbol_position == "post") ? $symbol : '');
     }

}




if (!function_exists('company_date_formate')) {
    function company_date_formate($date, $company_id = null, $business = null)
    {

        if (!empty($company_id) && empty($business)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($business)) {
            $company_settings = getCompanyAllSetting($company_id, $business);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $date_formate = !empty($company_settings['site_date_format']) ? $company_settings['site_date_format'] : 'd-m-y';

        return date($date_formate, strtotime($date));
    }
}

if (!function_exists('super_currency_format_with_sym')) {
    function super_currency_format_with_sym($price)
    {
        $admin_settings = getAdminAllSetting();

        $symbol_position = 'pre';
        $symbol = '$';
        $format = '1';
        $currency_space = null;
        $number = explode('.', $price);
        $length = strlen(trim($number[0]));

        if (isset($admin_settings['site_currency_symbol_position']) && $admin_settings['site_currency_symbol_position'] == "post") {
            $symbol_position = 'post';
        }

        if (isset($admin_settings['defult_currancy_symbol'])) {
            $symbol = $admin_settings['defult_currancy_symbol'];
        }

        if (isset($admin_settings['currency_format'])) {
            $format = $admin_settings['currency_format'];
        }

        if ($length > 3) {
            $decimal_separator  = isset($admin_settings['float_number']) && $admin_settings['float_number'] === 'dot' ? '.' : ',';
            $thousand_separator = isset($admin_settings['thousand_separator']) && $admin_settings['thousand_separator'] === 'dot' ? '.' : ',';
        } else {
            $decimal_separator  = isset($admin_settings['decimal_separator']) && $admin_settings['decimal_separator'] === 'dot'  ? '.' : ',';
            $thousand_separator = isset($admin_settings['thousand_separator']) && $admin_settings['thousand_separator'] === 'dot' ? '.' : ',';
        }

        if (isset($admin_settings['currency_space'])) {
            $currency_space = isset($admin_settings['currency_space']) ? $admin_settings['currency_space'] : '';
        }
        if (isset($admin_settings['site_currency_symbol_name'])) {
            $defult_currancy = $admin_settings['defult_currancy'];
            $defult_currancy_symbol = $admin_settings['defult_currancy_symbol'];
            $symbol = $admin_settings['site_currency_symbol_name'] == 'symbol' ? $defult_currancy_symbol : $defult_currancy;
        }
        $price = number_format($price, $format, $decimal_separator, $thousand_separator);

        return (($symbol_position == "pre") ? $symbol : '') . ($currency_space == 'withspace' ? ' ' : '') . $price . ($currency_space == 'withspace' ? ' ' : '') . (($symbol_position == "post") ? $symbol : '');
    }

}
if (!function_exists('company_datetime_formate')) {
    function company_datetime_formate($date, $company_id = null, $business = null)
    {
        $company_settings = getCompanyAllSetting($company_id, $business);
        $date_formate = !empty($company_settings['site_date_format']) ? $company_settings['site_date_format'] : 'd-m-y';
        $time_formate = !empty($company_settings['site_time_format']) ? $company_settings['site_time_format'] : 'H:i';
        return date($date_formate . ' ' . $time_formate, strtotime($date));
    }
}
if (!function_exists('company_Time_formate')) {
    function company_Time_formate($time, $company_id = null, $business = null)
    {
        if (!empty($company_id) && empty($business)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($business)) {
            $company_settings = getCompanyAllSetting($company_id, $business);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $time_formate = !empty($company_settings['site_time_format']) ? $company_settings['site_time_format'] : 'H:i';
        return date($time_formate, strtotime($time));
    }
}
if (!function_exists('timeSlot')) {
    function timeSlot($serviceId = null, $date = null, $flexibleData = null)
    {
        $service = Service::find($serviceId);
        $company_settings = getCompanyAllSetting($service->created_by, $service->business_id);
        $maximum_slot = isset($company_settings['maximum_slot']) ? $company_settings['maximum_slot'] : '1';

        if ($date && !empty($service)) {
            $booked_appointment = Appointment::where('service_id', $serviceId)->where('date', $date)->where('business_id', $service->business_id)->where('created_by', $service->created_by)->select('time')->get()->toArray();

            $selectedDate = Carbon::createFromFormat('d-m-Y', $date);
            $dayName = $selectedDate->format('l');                              //get dayname using date

            $businessday = BusinessHours::where('created_by', $service->created_by)->where('business_id', $service->business_id)->where('day_name', $dayName)->first();

            $duration = $service->duration;
            $start_time = Carbon::createFromFormat('H:i:s', isset($businessday->start_time) ? $businessday->start_time : '09:30:00');
            $end_time = Carbon::createFromFormat('H:i:s', isset($businessday->end_time) ? $businessday->end_time : '18:00:00');
            $break_times = isset($businessday->break_hours) ? json_decode($businessday->break_hours, true) : '';

            $timeSlots = [];
            $currentSlot = clone $start_time;
            // $now = Carbon::now($company_settings['defult_timezone'])->format('H:i');    //get current time
            $now = Carbon::now($company_settings['defult_timezone']);
            $isToday = $selectedDate->isToday();

            // If the selected date is today, use current time as the cutoff
            if ($isToday) {
                $now = $now->format('H:i');
            } else {
                // If the date is tomorrow or later, ignore the current time and start from business start time
                $now = $start_time->format('H:i');
            }

            if (is_array($break_times)) {
                foreach ($break_times as $break) {
                    $breakStart = Carbon::createFromFormat('H:i', $break['start']);
                    $breakEnd = Carbon::createFromFormat('H:i', $break['end']);
                    // Add time slots before the break, excluding booked slots
                    while ($currentSlot->addMinutes((int) $duration)->lt($breakStart)) {
                        $slot = [
                            'start' => $currentSlot->copy()->subMinutes((int) $duration)->format('H:i'),
                            'end' => $currentSlot->format('H:i'),
                            'service_id' => $service->id
                        ];
                        // Skip slots before the current time
                        if ($currentSlot->lt($now)) {
                            continue;
                        }

                        $bookedCount = isSlotBooked($slot, $booked_appointment);
                        if ($bookedCount < $maximum_slot) {
                            $timeSlots[] = $slot;
                        }
                    }
                    // Skip time slots during the break
                    if ($currentSlot->lte($breakEnd)) {
                        $currentSlot = $breakEnd->copy();
                    }
                }
            }


            // Add remaining time slots after the last break, excluding booked slots
            while ($currentSlot->addMinutes((int) $duration)->lte($end_time)) {
                $slot = [
                    'start' => $currentSlot->copy()->subMinutes((int) $duration)->format('H:i'),
                    'end' => $currentSlot->format('H:i'),
                    'service_id' => $service->id
                ];

                // Skip slots before the current time
                if ($currentSlot->lt($now)) {
                    continue;
                }

                $bookedCount = isSlotBooked($slot, $booked_appointment);
                if ($bookedCount < $maximum_slot) {
                    $timeSlots[] = $slot;
                }
            }
            // Special hours (FlexibleHours is a core feature in the single-clinic app)
            if (!is_null($flexibleData)) {
                $selectedDate = Carbon::createFromFormat('d-m-Y', $date);
                $dayName = $selectedDate->format('D');

                $filtered_flexible_data = $flexibleData->filter(function ($flexible_day) use ($dayName) {
                    $flexible_data = json_decode($flexible_day->days, true);
                    return isset($flexible_data[$dayName]) && $flexible_data[$dayName] === 'on';
                });
                foreach ($filtered_flexible_data as $data) {
                    $startSpecial = Carbon::createFromFormat('H:i:s', $data->start_time);
                    $endSpecial = Carbon::createFromFormat('H:i:s', $data->end_time);
                    $timeSlots = removeSlotsBetweenSpecialHours($timeSlots, $startSpecial, $endSpecial, $data->id);
                }
            }

            return $timeSlots;
        }
    }
}

function removeSlotsBetweenSpecialHours($slots, $startSpecial, $endSpecial, $flexible_id)
{
    $newSlots = [];
    $specialSlotAdded = false;

    foreach ($slots as $slot) {
        $start = strtotime($slot['start']);
        $end = strtotime($slot['end']);
        $startSpecialTime = strtotime($startSpecial);
        $endSpecialTime = strtotime($endSpecial);

        if ($end <= $startSpecialTime || $start >= $endSpecialTime) {
            // Slot does not fall between special hours, keep it
            $newSlots[] = $slot;
        } else {
            // Slot falls between special hours, remove it
            if (!$specialSlotAdded) {
                // Add new slot only once
                $newSlots[] = ['start' => $startSpecial->format('H:i'), 'end' => $endSpecial->format('H:i'), 'flexible_id' => $flexible_id];
                $specialSlotAdded = true;
            }
        }
    }

    return $newSlots;
}

if (!function_exists('isSlotBooked')) {
    function isSlotBooked($slot, $bookedAppointments)
    {
        $currentStart = Carbon::createFromFormat('H:i', $slot['start']);
        $currentEnd = Carbon::createFromFormat('H:i', $slot['end']);

        $count = 0;

        foreach ($bookedAppointments as $bookedSlot) {
            // Extract start and end times from the booked slot string
            [$bookedStartTime, $bookedEndTime] = explode('-', $bookedSlot['time']);

            $bookedStart = Carbon::createFromFormat('H:i', $bookedStartTime);
            $bookedEnd = Carbon::createFromFormat('H:i', $bookedEndTime);

            // Check if the current slot overlaps with any booked slot
            if (($currentStart->gte($bookedStart) && $currentStart->lt($bookedEnd)) ||
                ($currentEnd->gt($bookedStart) && $currentEnd->lte($bookedEnd))
            ) {
                $count++;

                // return true; // Slot is booked
            }
        }
        return $count;
        // return false; // Slot is not booked

    }
}


if (!function_exists('EmbeddedCode')) {
    function EmbeddedCode($business = null)
    {
        // Single-clinic app: fixed booking URL, no slug.
        $route = route('appointments.form');

        return '<iframe src="' . $route . '" width="100%" height="700px"></iframe>';
    }
}


// Return Currency Symbol , Currency format & Currency Sybmool position ( create for query optimization)
if (!function_exists('get_currency_format_and_symbol')) {
    function get_currency_format_and_symbol($company_id = null, $business = null)
    {
        if (!empty($company_id) && empty($company_id)) {
            $company_settings = getCompanyAllSetting($company_id);
        } else if (!empty($company_id) && !empty($business)) {
            $company_settings = getCompanyAllSetting($company_id, $business);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $symbol_position = 'pre';
        $currancy_symbol = '$';
        $currancy_format = 1;
        if (isset($company_settings['site_currency_symbol_position'])) {
            $symbol_position = $company_settings['site_currency_symbol_position'];
        }
        if (isset($company_settings['defult_currancy_symbol'])) {
            $currancy_symbol = $company_settings['defult_currancy_symbol'];
        }
        if (isset($company_settings['currency_format'])) {
            $currancy_format = $company_settings['currency_format'];
        }

        $data = [
            'currency_symbol_position' =>  $symbol_position,
            'currancy_symbol' =>  $currancy_symbol,
            'currancy_format' => $currancy_format
        ];
        return $data;
    }
}
