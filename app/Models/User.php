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


class User extends Authenticatable implements LaratrustUser,MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile_no',
        'email_verified_at',
        'password',
        'remember_token',
        'type',
        'platform_role',
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
        'department',
        'initials',
        'capabilities',
        'last_login_at',
        'two_factor_secret',
        'two_factor_enabled_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'capabilities' => 'array',
        'last_login_at' => 'datetime',
        'two_factor_secret' => 'encrypted',
        'two_factor_enabled_at' => 'datetime',
    ];

    /** Portal capability flags consumed by the React RBAC matrix. */
    public const PORTAL_CAPABILITIES = [
        'canSignReports',
        'canVoidInvoices',
        'canOverrideScreening',
        'canEditMasters',
        'canAccessPacs',
    ];

    public function portalRole(): string
    {
        // Map the app's role names onto the React SPA role vocabulary. Roles are
        // evaluated for the ACTIVE tenant only, so a multi-tenant member always
        // carries the role their current membership grants.
        $activeBusiness = (int) (function_exists('getActiveBusiness') ? getActiveBusiness($this->id) : ($this->business_id ?: $this->active_business ?: 0));
        $roles = $activeBusiness > 0
            ? \App\Services\TenantAuthorizer::roleNamesFor($this, $activeBusiness)
            : $this->getRoles();

        $primary = $roles[0] ?? null;

        return match ($primary) {
            'radiologist' => 'radiologist',
            'technician' => 'technologist',
            'receptionist' => 'receptionist',
            'billing' => 'billing',
            'customer' => 'patient',
            'admin' => 'admin',
            // Custom tenant roles keep their own name; the SPA treats it as
            // cosmetic only — navigation is permission-driven.
            null => 'admin',
            default => (string) $primary,
        };
    }

    /** Platform (control-plane) identity: vendor staff, never tenant staff. */
    public function isPlatformAdmin(): bool
    {
        return \App\Services\PlatformAuthorizer::isPlatformAdmin($this);
    }

    /** Control-plane capability list ('*' = unrestricted for super admin). */
    public function platformCapabilities(): array
    {
        return \App\Services\PlatformAuthorizer::capabilities($this);
    }

    public function memberships()
    {
        return $this->hasMany(TenantMembership::class);
    }

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
                if ($permission) {
                    $client_role->givePermission($permission);
                }
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
                if ($permission) {
                    $staff_role->givePermission($permission);
                }
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
                if ($permission) {
                    $customer_role->givePermission($permission);
                }
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
            "title_text" => !empty($admin_settings['title_text']) ? $admin_settings['title_text'] : "PolytronX - Enterprise PACS & RIS",
            "footer_text" => !empty($admin_settings['footer_text']) ? $admin_settings['footer_text'] :"Copyright © PolytronX - Enterprise PACS & RIS | Powered By PolytronX - Business Digitalized",
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
