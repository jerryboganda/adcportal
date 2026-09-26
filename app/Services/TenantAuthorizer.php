<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped authorization. Permission checks NEVER span tenants: a user's
 * effective permission set for a request is (permissions of the laratrust
 * roles that belong to the ACTIVE tenant) ∪ (tenant-scoped allow overrides)
 * − (tenant-scoped deny overrides). A role belongs to the tenant of the admin
 * user who created it; overrides are rows in user_permission_overrides and
 * therefore cannot leak across tenants. Platform staff hold zero tenant
 * permissions unless an active, unexpired break-glass support session grants
 * them this tenant's full admin permission set for troubleshooting.
 */
class TenantAuthorizer
{
    /** @var array<string, array<string>> per-request cache: "user:tenant" */
    private static array $cache = [];

    /** @var array<string, list<int>> per-request cache: "user:tenant" -> role ids */
    private static array $roleCache = [];

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
            $permissions = self::tenantRolePermissions($businessId, null);
        } else {
            $rolePermissions = self::tenantRolePermissions($businessId, $user->id);
            $denied = self::deniedPermissions($user->id, $businessId);

            $permissions = $denied === []
                ? array_values(array_unique(array_merge(
                    $rolePermissions,
                    self::allowedPermissions($user->id, $businessId)
                )))
                : array_values(array_diff(
                    array_unique(array_merge(
                        $rolePermissions,
                        self::allowedPermissions($user->id, $businessId)
                    )),
                    $denied
                ));
        }

        return self::$cache[$key] = $permissions;
    }

    public static function allows(User $user, string $permission, int $businessId): bool
    {
        return in_array($permission, self::permissionsFor($user, $businessId), true);
    }

    /**
     * Laratrust role ids the user holds within the given tenant, in a stable order.
     *
     * Ordered by id (creation order) so `roleNamesFor()[0]` and `roleIdsFor()[0]`
     * are always the SAME role — the SPA shows the name and writes back the id.
     */
    public static function roleIdsFor(User $user, int $businessId): array
    {
        if ($businessId <= 0) {
            return [];
        }

        // Memoized for the same reason permissionsFor() is: the staff list calls
        // this once per row AND portalRole() asks for the same lookup again, so
        // an uncached version costs two `role_user` + `roles` round trips per
        // person on a single admin screen.
        $key = "{$user->id}:{$businessId}";
        if (array_key_exists($key, self::$roleCache)) {
            return self::$roleCache[$key];
        }

        return self::$roleCache[$key] = Role::query()
            ->whereIn('id', DB::table('role_user')->where('user_id', $user->id)->pluck('role_id'))
            ->whereIn('created_by', self::tenantAdminIds($businessId))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Laratrust role names the user holds within the given tenant. */
    public static function roleNamesFor(User $user, int $businessId): array
    {
        $roleIds = self::roleIdsFor($user, $businessId);

        if ($roleIds === []) {
            return [];
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->orderBy('id')
            ->pluck('name')
            ->all();
    }

    /**
     * Full effective-access picture for the RBAC admin screens: the effective
     * set plus its provenance (role-derived, allow-overrides, deny-overrides).
     *
     * @return array{permissions: list<string>, role: list<string>, allowed: list<string>, denied: list<string>}
     */
    public static function effectiveWithSources(User $user, int $businessId): array
    {
        if ($businessId <= 0) {
            return ['permissions' => [], 'role' => [], 'allowed' => [], 'denied' => []];
        }

        $role = self::tenantRolePermissions($businessId, $user->id);
        $allowed = self::allowedPermissions($user->id, $businessId);
        $denied = self::deniedPermissions($user->id, $businessId);
        $permissions = array_values(array_diff(array_unique(array_merge($role, $allowed)), $denied));
        sort($permissions, SORT_STRING);

        return ['permissions' => $permissions, 'role' => $role, 'allowed' => $allowed, 'denied' => $denied];
    }

    public static function flushUser(int $userId): void
    {
        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, "{$userId}:")) {
                unset(self::$cache[$key]);
            }
        }

        foreach (array_keys(self::$roleCache) as $key) {
            if (str_starts_with($key, "{$userId}:")) {
                unset(self::$roleCache[$key]);
            }
        }
    }

    /**
     * Drop ALL memoized decisions. The cache is per-request by lifecycle
     * (every HTTP request starts with fresh process state), but tests and
     * long-running workers reuse the process — they must call this when the
     * database identity/context changes (ApiTestCase::setUp, tenant switch).
     */
    public static function flushAll(): void
    {
        self::$cache = [];
        self::$roleCache = [];
    }

    /** Union of permissions from a user's tenant roles — or ALL tenant roles for support. */
    private static function tenantRolePermissions(int $businessId, ?int $userId): array
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

    /** @return list<string> tenant-scoped per-user allow overrides */
    private static function allowedPermissions(int $userId, int $businessId): array
    {
        return DB::table('user_permission_overrides')
            ->join('permissions', 'permissions.id', '=', 'user_permission_overrides.permission_id')
            ->where('user_permission_overrides.user_id', $userId)
            ->where('user_permission_overrides.business_id', $businessId)
            ->where('user_permission_overrides.mode', 'allow')
            ->pluck('permissions.name')
            ->values()
            ->all();
    }

    /** @return list<string> tenant-scoped per-user deny overrides */
    private static function deniedPermissions(int $userId, int $businessId): array
    {
        return DB::table('user_permission_overrides')
            ->join('permissions', 'permissions.id', '=', 'user_permission_overrides.permission_id')
            ->where('user_permission_overrides.user_id', $userId)
            ->where('user_permission_overrides.business_id', $businessId)
            ->where('user_permission_overrides.mode', 'deny')
            ->pluck('permissions.name')
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
