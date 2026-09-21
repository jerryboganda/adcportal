<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Print permissions.
 *
 * Printing used to be an implicit side effect of `invoice manage` / `report
 * manage` / `manage`-style views: anyone who could open a screen could put a
 * patient's document on paper, and nothing recorded that they had. The catalog
 * now names the five print capabilities explicitly, and this migration grants
 * them to the system roles that already printed — so existing clinics notice
 * nothing except that the permission matrix finally lets them REVOKE printing
 * for a role (a locum who may read but not print, for instance).
 */
return new class extends Migration
{
    public function up(): void
    {
        PermissionCatalog::sync();

        // Access is checked as "any of [specific print permission, the legacy
        // manage permission]" so a tenant that has not re-saved its roles keeps
        // working exactly as before, while a tenant that revokes printing gets
        // a real revocation.
        $bundles = PermissionCatalog::defaultBundles();

        foreach (Role::query()->whereIn('name', array_keys($bundles))->get() as $role) {
            $perms = $bundles[$role->name];

            if (in_array($role->name, PermissionCatalog::UNDELETABLE_ROLES, true)) {
                $perms = array_values(array_unique(array_merge($perms, PermissionCatalog::ADMIN_LOCKOUT_FLOOR)));
            }

            $role->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', $perms)->pluck('id')->all()
            );
        }
    }

    public function down(): void
    {
        // Permissions are a security contract: retiring a key that controllers
        // still check would silently widen access. The catalog sync is
        // idempotent, so nothing is removed here.
    }
};
