<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class PermissionTableSeeder extends Seeder
{
    /**
     * Radiology clinic RBAC.
     *
     * Roles: admin, manager, receptionist, technician (radiographer),
     * radiologist, customer (patient portal).
     */
    public function run()
    {
        // Single-clinic app: one admin role holding every permission.
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

        $permissions = [

            // Clinic / masters
            'clinic manage', 'clinic edit',
            'location create', 'location edit', 'location delete',
            'category create', 'category edit', 'category delete',
            'modality manage', 'modality create', 'modality edit', 'modality delete',
            'room manage', 'room create', 'room edit', 'room delete',
            'service create', 'service edit', 'service delete',
            'staff create', 'staff edit', 'staff delete',
            'holiday create', 'holiday edit', 'holiday delete',

            // Patients & referrers
            'customer manage', 'customer create', 'customer edit', 'customer delete',
            'referrer manage', 'referrer create', 'referrer edit', 'referrer delete',

            // Studies (appointments) & workflow
            'appointment manage', 'appointment create', 'appointment edit', 'appointment delete',
            'study checkin',                       // reception desk
            'study screen',                        // safety screening answers
            'study acquire',                       // technologist worklist + dose log
            'study assign',                        // assign radiologist
            'study cancel',                        // cancel / no-show

            // Reporting
            'report manage',                       // worklist access
            'report create', 'report edit', 'report sign', 'report release',
            'report template manage', 'report template create', 'report template edit', 'report template delete',

            // Billing
            'invoice manage', 'invoice create', 'invoice edit', 'invoice delete',
            'invoice payment',                     // record payments

            // Users & system
            'user manage', 'user create', 'user edit', 'user delete',
            'user profile manage', 'user reset password', 'user login manage', 'user logs history',
            'roles manage', 'roles create', 'roles edit', 'roles delete',
            'permission manage',
            'setting manage', 'setting storage manage',
            'status manage', 'status create', 'status update', 'status delete',
            'contact manage', 'contact delete',
            'theme manage', 'theme edit',
            'language manage', 'language create', 'language delete',
            'email template manage', 'notification template manage',
        ];

        // Legacy aliases kept so pre-overhaul code keeps working.
        $legacy = [
            'business manage' => 'clinic manage',
            'business edit' => 'clinic edit',
            'business update' => 'clinic edit',
            'subscriber manage' => 'setting manage',
            'subscriber delete' => 'setting manage',
            'blog manage' => 'theme manage',
            'blog create' => 'theme manage',
            'blog edit' => 'theme manage',
            'blog delete' => 'theme manage',
            'testimonial manage' => 'theme manage',
            'testimonial create' => 'theme manage',
            'testimonial edit' => 'theme manage',
            'testimonial delete' => 'theme manage',
        ];

        $role = Role::where('name', 'admin')->where('guard_name', 'web')->exists();
        if (!$role) {
            Role::create(['name' => 'admin', 'created_by' => $admin->id]);
        }
        $adminRole = Role::where('name', 'admin')->first();

        foreach ($permissions as $value) {
            $permission = Permission::where('name', $value)->first();
            if (empty($permission)) {
                $permission = Permission::create([
                    'name' => $value,
                    'guard_name' => 'web',
                    'module' => 'General',
                    'created_by' => $admin->id,
                    "created_at" => date('Y-m-d H:i:s'),
                    "updated_at" => date('Y-m-d H:i:s'),
                ]);
            }
            if (!$adminRole->hasPermission($value)) {
                $adminRole->givePermission($permission);
            }
        }

        // Staff-facing roles get curated bundles.
        $roleBundles = [
            'manager' => [
                'appointment manage', 'appointment create', 'appointment edit', 'appointment delete',
                'study checkin', 'study screen', 'study assign', 'study cancel',
                'report manage', 'report create', 'report edit', 'report release',
                'invoice manage', 'invoice create', 'invoice edit', 'invoice payment',
                'customer manage', 'customer create', 'customer edit', 'customer delete',
                'referrer manage', 'referrer create', 'referrer edit', 'referrer delete',
                'service create', 'service edit',
                'staff create', 'staff edit',
                'modality manage',
            ],
            'receptionist' => [
                'appointment manage', 'appointment create', 'appointment edit',
                'study checkin', 'study screen', 'study cancel',
                'customer manage', 'customer create', 'customer edit',
                'referrer manage', 'referrer create', 'referrer edit',
                'invoice create', 'invoice payment',
                'report release',
            ],
            'technician' => [
                'appointment manage',
                'study checkin', 'study screen', 'study acquire',
                'report manage',
            ],
            'radiologist' => [
                'appointment manage',
                'report manage', 'report create', 'report edit', 'report sign', 'report release',
            ],
        ];

        foreach ($roleBundles as $roleName => $bundle) {
            $r = Role::where('name', $roleName)->first();
            if (!$r) {
                $r = Role::create(['name' => $roleName, 'created_by' => $admin->id]);
            }
            foreach ($bundle as $permName) {
                $perm = Permission::where('name', $permName)->first();
                if ($perm && !$r->hasPermission($permName)) {
                    $r->givePermission($perm);
                }
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
