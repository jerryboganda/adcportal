<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Events\DefaultData;
use App\Events\GivePermissionToRole;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements LaratrustUser,MustVerifyEmail,JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile_no',
        'email_verified_at',
        'password',
        'remember_token',
        'type',
        'active_status',
        'active_business',
        'avatar',
        'dark_mode',
        'requested_plan',
        'messenger_color',
        'active_plan',
        'billing_type',
        'active_module',
        'plan_expire_date',
        'total_user',
        'total_business',
        'seeder_run',
        'business_id',
        'created_by',
        'lang',
        'is_enable_login',
        'is_disable',
        'trial_expire_date',
        'is_trial_done',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public static $not_edit_role = [
        'admin',
        'manager',
        'customer',
        'staff'
    ];
    public  $not_emp_type = [
        'admin',
        'customer',
    ];
    public function scopeEmp($query)
    {
        return $query->whereNotIn('type', $this->not_emp_type);
    }
    public static function hex2rgb($hex)
    {
        $hex = str_replace("#", "", $hex);

        if(strlen($hex) == 3)
        {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        }
        else
        {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $rgb = array(
            $r,
            $g,
            $b,
        );

        return $rgb; // returns an array with the rgb values
    }
    public static function getFontColor($color_code)
    {
        $rgb = self::hex2rgb($color_code);
        $R   = $G = $B = $C = $L = $color = '';

        $R = (floor($rgb[0]));
        $G = (floor($rgb[1]));
        $B = (floor($rgb[2]));

        $C = [
            $R / 255,
            $G / 255,
            $B / 255,
        ];

        for($i = 0; $i < count($C); ++$i)
        {
            if($C[$i] <= 0.03928)
            {
                $C[$i] = $C[$i] / 12.92;
            }
            else
            {
                $C[$i] = pow(($C[$i] + 0.055) / 1.055, 2.4);
            }
        }

        $L = 0.2126 * $C[0] + 0.7152 * $C[1] + 0.0722 * $C[2];

        if($L > 0.179)
        {
            $color = 'black';
        }
        else
        {
            $color = 'white';
        }

        return $color;
    }

    public function MakeRole()
    {
        $data = [];
        $staff_role_permission = [
            'user profile manage',
            'user logs history',

        ];
        $client_role_permission = [
            'user profile manage',
            'user logs history',

        ];
        $customer_role_permission = [
            'customer manage',
            'customer create',
            'customer edit',
            'customer delete',

        ];
        $client_role = Role::where('name','manager')->where('created_by',$this->id)->where('guard_name','web')->first();
        if(empty($client_role))
        {
            $client_role                   = new Role();
            $client_role->name             = 'manager';
            $client_role->guard_name       = 'web';
            $client_role->module           = 'Base';
            $client_role->created_by       = $this->id;
            $client_role->save();

            foreach($client_role_permission as $permission_c){
                $permission = Permission::where('name',$permission_c)->first();
                $client_role->givePermission($permission);
            }
        }
        $staff_role = Role::where('name','staff')->where('created_by',$this->id)->where('guard_name','web')->first();
        if(empty($staff_role))
        {
            $staff_role                   = new Role();
            $staff_role->name             = 'staff';
            $staff_role->guard_name       = 'web';
            $staff_role->module           = 'Base';
            $staff_role->created_by       = $this->id;
            $staff_role->save();

            foreach($staff_role_permission as $permission_s){
                $permission = Permission::where('name',$permission_s)->first();
                $staff_role->givePermission($permission);
            }
        }
        $customer_role = Role::where('name','customer')->where('created_by',$this->id)->where('guard_name','web')->first();
        if(empty($customer_role))
        {
            $customer_role                   = new Role();
            $customer_role->name             = 'customer';
            $customer_role->guard_name       = 'web';
            $customer_role->module           = 'Base';
            $customer_role->created_by       = $this->id;
            $customer_role->save();

            foreach($customer_role_permission as $permission_cu){
                $permission = Permission::where('name',$permission_cu)->first();
                $customer_role->givePermission($permission);
            }
        }

        $data['client_role'] = $client_role;
        $data['staff_role'] = $staff_role;
        $data['customer_role'] = $customer_role;

        return $data;
    }
    public static function CompanySetting($id = null,$businee_id = null)
    {
        // Single-clinic app: seed the one clinic's settings (business id resolved automatically).
        $business_id = getActiveBusiness();
        if ($business_id == 0) {
            return;
        }
        $admin_settings = getAdminAllSetting();


        $company_setting = [
            "currency_format" => !empty($admin_settings['currency_format']) ? $admin_settings['currency_format'] : "1",
            "defult_currancy" => !empty($admin_settings['defult_currancy']) ? $admin_settings['defult_currancy'] : "USD",
            "defult_currancy_symbol" => !empty($admin_settings['defult_currancy_symbol']) ? $admin_settings['defult_currancy_symbol'] : "$",
            "defult_language" => !empty($admin_settings['defult_language']) ? $admin_settings['defult_language'] : 'en',
            "defult_timezone" => !empty($admin_settings['defult_timezone']) ? $admin_settings['defult_timezone'] : 'Asia/Kolkata',
            "site_currency_symbol_position" => "pre",
            "site_date_format" => "d-m-Y",
            "site_time_format" => "g:i A",
            "title_text" => !empty($admin_settings['title_text']) ? $admin_settings['title_text'] : "ADC - Amad Diagnostic Centre",
            "footer_text" => !empty($admin_settings['footer_text']) ? $admin_settings['footer_text'] :"Copyright © ADC - Amad Diagnostic Centre | Powered By PolytronX - Business Digitalized",
            "site_rtl" => !empty($admin_settings['site_rtl']) ? $admin_settings['site_rtl'] : "off",
            "cust_darklayout" => !empty($admin_settings['cust_darklayout']) ? $admin_settings['cust_darklayout'] :"off",
            "site_transparent" => !empty($admin_settings['site_transparent']) ? $admin_settings['site_transparent'] : "on",
            "color" => "theme-1",

        ];
        foreach ($company_setting as $key => $value) {
            // Define the data to be updated or inserted
            $data = [
                'key' => $key,
                'business' => $business_id,
                'created_by' => creatorId(),
            ];
            // Check if the record exists, and update or insert accordingly
            Setting::updateOrInsert($data, ['value' => $value]);
        }
        comapnySettingCacheForget();
    }
    public function ActiveBusinessName()
    {
        $name = $this->name;
        $business = Business::find(getActiveBusiness());
        if($business)
        {
            $name = $business->name;
        }
        return $name;
    }
}
