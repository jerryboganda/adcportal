<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant RBAC access-control layer:
 *  - user_permission_overrides (tenant-scoped per-user allow/deny overrides);
 *  - businesses.permissions_version (cache-busting signal for SPA sessions);
 *  - the permission catalog sync + backfill of the default role bundles so
 *    every EXISTING tenant keeps exactly its current access and gains only
 *    the module views + role administration (admins) + the missing
 *    `user logs history` grant (previously enforced but never seeded).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_permission_overrides')) {
            Schema::create('user_permission_overrides', function (Blueprint $table) {
                $table->id();
                // Tenant-scoped BY DESIGN: an override granted in one clinic
                // must never leak into another for multi-tenant members.
                $table->unsignedBigInteger('business_id')->index();
                $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->index();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unsignedBigInteger('permission_id');
                $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
                $table->string('mode'); // 'allow' | 'deny'
                $table->timestamps();
                $table->unique(['business_id', 'user_id', 'permission_id']);
            });
        }

        if (! Schema::hasColumn('businesses', 'permissions_version')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->unsignedBigInteger('permissions_version')->default(1);
            });
        }

        PermissionCatalog::sync();

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
        if (Schema::hasColumn('businesses', 'permissions_version')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('permissions_version');
            });
        }

        Schema::dropIfExists('user_permission_overrides');
    }
};
