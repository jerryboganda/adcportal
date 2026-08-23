<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;


class PermissionTableSeeder extends Seeder
{
    /**
     * Seed the admin role with all clinic permissions.
     *
     * @return void
     */
    public function run()
    {
        Artisan::call('cache:forget spatie.permission.cache');
        Artisan::call('cache:clear');

        // Single-clinic app: one admin role holding every permission.
        // (No super admin / company split, no plan/coupon/module permissions.)
        $admin = User::where('type', 'admin')->first();
        if (empty($admin)) {
            $admin = new User();
            $admin->name = 'Admin';
            $admin->email = 'admin@example.com';
            $admin->password = bcrypt('1234');
            $admin->email_verified_at = date('Y-m-d H:i:s');
            $admin->type = 'admin';
            $admin->active_status = 1;
            $admin->avatar = 'uploads/users-avatar/avatar.png';
            $admin->dark_mode = 0;
            $admin->lang = 'en';
            $admin->save();
        }

        $admin_permissions = [
            'business manage',
            'business edit',
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
            'permission manage',
            'setting manage',
            'setting storage manage',
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
            'language manage',
            'language create',
            'language delete',
            'email template manage',
            'notification template manage'
        ];

        $role = Role::where('name', 'admin')->where('guard_name', 'web')->exists();
        if (!$role) {
            Role::create(
                [
                    'name' => 'admin',
                    'created_by' => $admin->id,
                ]
            );
        }
        $adminRole = Role::where('name', 'admin')->first();

        foreach ($admin_permissions as $key => $value) {
            $permission = Permission::where('name', $value)->first();
            if (empty($permission)) {
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
            if (!$adminRole->hasPermission($value)) {
                $adminRole->givePermission($permission);
            }
        }

        // Assign the admin role to the admin user.
        try {
            $assigned_role = $admin->roles->first();
        } catch (\Exception $e) {
            $assigned_role = null;
        }
        if (!$assigned_role) {
            $admin->addRole($adminRole);
        }
    }
}
