<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'referrer manage',
            'referrer create',
            'referrer edit',
            'referrer delete',
        ];

        foreach ($permissions as $permission) {
            // Check if permission doesn't exist before creating
            $exists = DB::table('permissions')->where('name', $permission)->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => $permission,
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'description' => ucwords(str_replace('_', ' ', $permission)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Optionally: Add these permissions to existing admin/owner roles
        // Get all role IDs that should have referrer permissions (e.g., admin, owner roles)
        $adminRoles = DB::table('roles')
            ->whereIn('name', ['owner', 'admin', 'Admin', 'Owner'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->pluck('id');

        foreach ($adminRoles as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $exists = DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();
                
                if (!$exists) {
                    DB::table('permission_role')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'referrer manage',
            'referrer create',
            'referrer edit',
            'referrer delete',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->pluck('id');

        // Remove from permission_role first
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();

        // Remove permissions
        DB::table('permissions')->whereIn('name', $permissions)->delete();
    }
};
