<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped authorization. Permission checks NEVER span tenants: a user's
 * effective permission set for a request is the union of permissions carried
 * by the laratrust roles that belong to the ACTIVE tenant (a role belongs to
 * the tenant of the admin user who created it). Platform staff hold zero
 * tenant permissions unless an active, unexpired break-glass support session
 * grants them this tenant's admin permission set for troubleshooting.
 */
class TenantAuthorizer
{
    /** @var array<string, array<string>> per-request cache: "user:tenant" */
    private static array $cache = [];

    /** All permission names the user holds inside the given tenant. */
    public static function permissionsFor(User $user, int $businessId): array
    {
        if ($businessId <= 0) {
            return [];
        }

        $key = "{$user->id}:{$businessId}";
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        if (self::hasActiveSupportSession($user->id, $businessId)) {
            // Break-glass: the full operational permission set of this tenant.
            $permissions = self::tenantPermissions($businessId, null);
        } else {
            $permissions = self::tenantPermissions($businessId, $user->id);
        }

        return self::$cache[$key] = $permissions;
    }

    public static function allows(User $user, string $permission, int $businessId): bool
    {
        return in_array($permission, self::permissionsFor($user, $businessId), true);
    }

    /** Laratrust role names the user holds within the given tenant. */
    public static function roleNamesFor(User $user, int $businessId): array
    {
        if ($businessId <= 0) {
            return [];
        }

        $adminIds = self::tenantAdminIds($businessId);
        $roleIds = DB::table('role_user')->where('user_id', $user->id)->pluck('role_id');

        return Role::query()
            ->whereIn('id', $roleIds)
            ->whereIn('created_by', $adminIds)
            ->pluck('name')
            ->all();
    }

    public static function flushUser(int $userId): void
    {
        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, "{$userId}:")) {
                unset(self::$cache[$key]);
            }
        }
    }

    /** Union of permissions from a user's tenant roles — or ALL tenant roles for support. */
    private static function tenantPermissions(int $businessId, ?int $userId): array
    {
        $adminIds = self::tenantAdminIds($businessId);
        if ($adminIds->isEmpty()) {
            return [];
        }

        $roleQuery = Role::query()->whereIn('created_by', $adminIds);
        if ($userId !== null) {
            $roleIds = DB::table('role_user')->where('user_id', $userId)->pluck('role_id');
            $roleQuery->whereIn('id', $roleIds);
        }
        $roleIds = $roleQuery->pluck('id');
        if ($roleIds->isEmpty()) {
            return [];
        }

        return DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->whereIn('permission_role.role_id', $roleIds)
            ->pluck('permissions.name')
            ->unique()
            ->values()
            ->all();
    }

    private static function tenantAdminIds(int $businessId): \Illuminate\Support\Collection
    {
        return User::query()->where('business_id', $businessId)->pluck('id');
    }

    private static function hasActiveSupportSession(int $platformUserId, int $businessId): bool
    {
        return SupportSession::query()
            ->where('platform_user_id', $platformUserId)
            ->where('business_id', $businessId)
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
