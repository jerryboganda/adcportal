<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;


class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Artisan::call('cache:forget spatie.permission.cache');
        Artisan::call('cache:clear');

        // Super Admin
        $admin = User::where('type','super admin')->first();
        if(empty($admin))
        {
            $admin = new User();
            $admin->name = 'Super Admin';
            $admin->email = 'superadmin@example.com';
            $admin->password = Hash::make('1234');
            $admin->email_verified_at = date('Y-m-d H:i:s');
            $admin->type = 'super admin';
            $admin->active_status = 1;
            $admin->active_business = 0;
            $admin->avatar = 'uploads/users-avatar/avatar.png';
            $admin->dark_mode = 0;
            $admin->lang = 'en';
            $admin->business_id = 0;
            $admin->created_by = 0;
            $admin->save();

            $role = Role::where('name','super admin')->where('guard_name','web')->exists();
            if(!$role)
            {
                $superAdminRole        = Role::create(
                    [
                        'name' => 'super admin',
                        'created_by' => 0,
                    ]
                );
            }
            $role_r = Role::where('name','super admin')->first();
            $admin->addRole($role_r);
        }

        $adnin_permission = [
            'user manage',
            'user create',
            'user edit',
            'user delete',
            'user profile manage',
            'user reset password',
            'user login manage',
            'user logs history',
            'setting manage',
            'setting storage manage',
            'coupon manage',
            'coupon create',
            'coupon edit',
            'coupon delete',
            'plan manage',
            'plan create',
            'plan edit',
            'plan delete',
            'plan orders',
            'module manage',
            'module add',
            'module remove',
            'module edit',
            'language manage',
            'language create',
            'language delete',
            'email template manage',
            'notification template manage'
        ];

        $compnay_permission = [
            'business manage',
            'business create',
            'business edit',
            'business delete',
            'business update',
            'location create',
            'location edit',
            'location delete',
            'service create',
            'service edit',
            'service delete',
            'staff create',
            'staff edit',
            'staff delete',
            'category create',
            'category edit',
            'category delete',
            'holiday create',
            'holiday edit',
            'holiday delete',
            'appointment manage',
            'appointment create',
            'appointment edit',
            'appointment delete',
            'customer manage',
            'customer create',
            'customer edit',
            'customer delete',
            'referrer manage',
            'referrer create',
            'referrer edit',
            'referrer delete',
            'user manage',
            'user create',
            'user edit',
            'user delete',
            'user profile manage',
            'user reset password',
            'user login manage',
            'user logs history',
            'roles manage',
            'roles create',
            'roles edit',
            'roles delete',
            'plan manage',
            'plan purchase',
            'plan subscribe',
            'plan orders',
            'setting manage',
            'status manage',
            'status create',
            'status update',
            'status delete',
            'blog manage',
            'blog create',
            'blog edit',
            'blog delete',
            'testimonial manage',
            'testimonial create',
            'testimonial edit',
            'testimonial delete',
            'contact manage',
            'contact delete',
            'subscriber manage',
            'subscriber delete',
            'theme manage',
            'theme edit',

        ];


        $superAdminRole  = Role::where('name','super admin')->first();
        foreach ($adnin_permission  as $key => $value)
        {
            $permission = Permission::where('name',$value)->first();
            if(empty($permission))
            {
                $permission = Permission::create(
                    [
                        'name' => $value,
                        'guard_name' => 'web',
                        'module' => 'General',
                        'created_by' => $admin->id,
                        "created_at" => date('Y-m-d H:i:s'),
                        "updated_at" => date('Y-m-d H:i:s')
                    ]
                );
            }
            if(!$superAdminRole->hasPermission($value))
            {
                $superAdminRole->givePermission($permission);
            }
        }
        // Company ..
        $role = Role::where('name','company')->where('guard_name','web')->exists();
        if(!$role)
        {
            $company_role        = Role::create(
                [
                    'name' => 'company',
                    'created_by' => $admin->id,
                ]
            );
        }
        $company_role = Role::where('name','company')->first();
        foreach ($compnay_permission as $key => $value)
        {
            $permission = Permission::where('name',$value)->first();
            if(empty($permission))
            {
                $permission = Permission::create(
                    [
                        'name' => $value,
                        'guard_name' => 'web',
                        'module' => 'General',
                        'created_by' => $admin->id,
                        "created_at" => date('Y-m-d H:i:s'),
                        "updated_at" => date('Y-m-d H:i:s')
                    ]
                );
            }
            if(!$company_role->hasPermission($value))
            {
                $company_role->givePermission($permission);
            }
        }


        $company = User::where('type','company')->first();
        try{

            $assigned_role = $company->roles->first();
        }catch(\Exception $e){
            $assigned_role = null;
        }
        if(!$assigned_role && !empty($company))
        {
            $company->addRole($company_role);
        }
    }
}
