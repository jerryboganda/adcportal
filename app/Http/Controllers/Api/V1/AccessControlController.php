<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Business;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantAuthorizer;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant RBAC administration: the permission catalog, per-tenant role
 * management (system + custom), role permission editing and tenant-scoped
 * per-user overrides.
 *
 * Security envelope:
 *  - reads require `role view`, writes require `role manage` (tenant-scoped);
 *  - every role touched here must belong to the ACTIVE tenant
 *    (roles.created_by ∈ users of this business) — cross-tenant ids 404;
 *  - grantable permissions are limited to the tenant catalog; platform
 *    capabilities live in config only and are structurally out of reach;
 *  - the admin role keeps the lockout floor on every save and can never be
 *    deleted, so a tenant cannot lock itself out of role administration.
 */
class AccessControlController extends BaseApiController
{
    public function catalog(): JsonResponse
    {
        $this->denyUnless('role view');

        return $this->ok(['catalog' => ApiShape::permissionCatalog()]);
    }

    public function roles(): JsonResponse
    {
        $this->denyUnless('role view');

        $roles = Role::query()
            ->whereIn('created_by', $this->tenantUserIds())
            ->with('permissions:id,name')
            ->get()
            ->sortBy([
                fn (Role $a, Role $b) => $this->systemRank($a) <=> $this->systemRank($b),
                fn (Role $a, Role $b) => strcasecmp($a->name, $b->name),
            ])
            ->values();

        $counts = DB::table('role_user')
            ->whereIn('role_id', $roles->pluck('id'))
            ->selectRaw('role_id, COUNT(DISTINCT user_id) as users')
            ->groupBy('role_id')
            ->pluck('users', 'role_id');

        return $this->ok([
            'roles' => $roles
                ->map(fn (Role $role) => ApiShape::accessRole($role, (int) ($counts[$role->id] ?? 0)))
                ->all(),
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->denyUnless('role manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'displayName' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        $name = $this->normalizeRoleName($validated['name']);
        $this->assertUniqueName($name);

        $permissions = $this->validatedPermissionSet($validated['permissions']);

        $role = DB::transaction(function () use ($name, $validated, $permissions) {
            $role = Role::create([
                'name' => $name,
                'display_name' => $validated['displayName'] ?? $validated['name'],
                'description' => $validated['description'] ?? null,
                'guard_name' => 'web',
                'module' => 'Custom',
                'created_by' => $this->tenantOwnerId(),
            ]);

            $role->permissions()->sync(
                Permission::whereIn('name', $permissions)->pluck('id')
            );

            return $role;
        });

        $this->bumpPermissionsVersion();
        $this->audit('role_created', $role, [
            'summary' => "Created role {$role->display_name} with ".count($permissions).' permissions',
        ]);

        return response()->json(['data' => ['role' => ApiShape::accessRole($role->fresh('permissions'))]], 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $this->denyUnless('role manage');
        $this->tenantRoleOrFail($role);

        $validated = $request->validate([
            'displayName' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $role->update([
            'display_name' => $validated['displayName'] ?? $role->display_name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $role->description,
        ]);

        $this->audit('role_updated', $role, [
            'summary' => "Renamed role to {$role->display_name}",
        ]);

        return $this->ok(['role' => ApiShape::accessRole($role->fresh('permissions'), $this->userCount($role))]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $this->denyUnless('role manage');
        $this->tenantRoleOrFail($role);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        $newSet = $this->validatedPermissionSet($validated['permissions']);

        if (in_array($role->name, PermissionCatalog::UNDELETABLE_ROLES, true)) {
            // Lockout guard: the admin role always keeps role administration,
            // staff management, settings and the audit trail.
            $newSet = PermissionCatalog::expand(
                array_unique(array_merge($newSet, PermissionCatalog::ADMIN_LOCKOUT_FLOOR))
            );
        }

        $old = $role->permissions->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($role, $newSet) {
            $role->permissions()->sync(
                Permission::whereIn('name', $newSet)->pluck('id')
            );
        });

        $this->flushRoleUsers($role);
        $this->bumpPermissionsVersion();
        $this->audit('role_permissions_updated', $role, [
            'summary' => "Changed permissions of role {$role->display_name}",
            'old' => $old,
            'new' => $newSet,
        ]);

        return $this->ok(['role' => ApiShape::accessRole($role->fresh('permissions'), $this->userCount($role))]);
    }

    public function duplicateRole(Request $request, Role $role): JsonResponse
    {
        $this->denyUnless('role manage');
        $this->tenantRoleOrFail($role);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:60'],
            'displayName' => ['nullable', 'string', 'max:100'],
        ]);

        $sourceName = $role->display_name ?: $role->name;
        $name = $this->normalizeRoleName($validated['name'] ?? ($role->name.'_copy'));
        $this->assertUniqueName($name);

        $permissions = $role->permissions->pluck('name')->all();

        $copy = DB::transaction(function () use ($role, $name, $validated, $sourceName, $permissions) {
            $copy = Role::create([
                'name' => $name,
                'display_name' => $validated['displayName'] ?? ('Copy of '.$sourceName),
                'description' => $role->description,
                'guard_name' => 'web',
                'module' => 'Custom',
                'created_by' => $this->tenantOwnerId(),
            ]);

            $copy->permissions()->sync(
                Permission::whereIn('name', $permissions)->pluck('id')
            );

            return $copy;
        });

        $this->bumpPermissionsVersion();
        $this->audit('role_duplicated', $copy, [
            'summary' => "Duplicated role {$sourceName} → {$copy->display_name}",
        ]);

        return response()->json(['data' => ['role' => ApiShape::accessRole($copy->fresh('permissions'))]], 201);
    }

    public function destroyRole(Role $role): JsonResponse
    {
        $this->denyUnless('role manage');
        $this->tenantRoleOrFail($role);

        if (in_array($role->name, PermissionCatalog::UNDELETABLE_ROLES, true)) {
            abort(response()->json([
                'message' => "The [{$role->name}] role is protected and cannot be deleted.",
                'error' => 'role_protected',
            ], 403));
        }

        $inUse = $this->userCount($role);
        if ($inUse > 0) {
            abort(response()->json([
                'message' => "This role is still assigned to {$inUse} user(s). Reassign them first.",
                'error' => 'role_in_use',
                'users' => $inUse,
            ], 422));
        }

        $name = $role->display_name ?: $role->name;

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            DB::table('role_user')->where('role_id', $role->id)->delete();
            $role->delete();
        });

        $this->bumpPermissionsVersion();
        $this->audit('role_deleted', $role, [
            'summary' => "Deleted role {$name}",
        ]);

        return $this->ok(['deleted' => true]);
    }

    public function userEffective(User $user): JsonResponse
    {
        $this->denyUnless('role view');

        if ($user->business_id !== $this->tenantId() || $user->type === 'customer') {
            abort(404);
        }

        return $this->ok(['access' => ApiShape::effectiveAccess($user)]);
    }

    public function syncOverrides(Request $request, User $user): JsonResponse
    {
        $this->denyUnless('role manage');

        if ($user->business_id !== $this->tenantId() || $user->type === 'customer') {
            abort(404);
        }

        $validated = $request->validate([
            'allow' => ['present', 'array'],
            'allow.*' => ['string'],
            'deny' => ['present', 'array'],
            'deny.*' => ['string'],
        ]);

        $allow = $this->validatedPermissionSet($validated['allow']);
        $deny = $this->validatedPermissionSet($validated['deny']);

        $overlap = array_intersect($allow, $deny);
        if ($overlap !== []) {
            throw ValidationException::withMessages([
                'allow' => 'Permission(s) cannot be both allowed and denied: '.implode(', ', $overlap),
            ]);
        }

        $oldRows = DB::table('user_permission_overrides')
            ->join('permissions', 'permissions.id', '=', 'user_permission_overrides.permission_id')
            ->where('business_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->get(['mode', 'permissions.name']);

        $old = [
            'allow' => $oldRows->where('mode', 'allow')->pluck('name')->values()->all(),
            'deny' => $oldRows->where('mode', 'deny')->pluck('name')->values()->all(),
        ];

        DB::transaction(function () use ($user, $allow, $deny) {
            DB::table('user_permission_overrides')
                ->where('business_id', $this->tenantId())
                ->where('user_id', $user->id)
                ->delete();

            $tenantId = $this->tenantId();

            foreach (['allow' => $allow, 'deny' => $deny] as $mode => $names) {
                foreach (Permission::whereIn('name', $names)->pluck('id') as $permissionId) {
                    DB::table('user_permission_overrides')->insert([
                        'business_id' => $tenantId,
                        'user_id' => $user->id,
                        'permission_id' => $permissionId,
                        'mode' => $mode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        TenantAuthorizer::flushUser($user->id);
        $this->bumpPermissionsVersion();
        $this->audit('user_permissions_overridden', $user, [
            'summary' => "Changed permission overrides for {$user->name}",
            'old' => $old['allow'] ?? [],
            'new' => $allow,
            'denied' => $deny,
        ]);

        return $this->ok(['access' => ApiShape::effectiveAccess($user->fresh())]);
    }

    // ==================== internals ====================

    private function systemRank(Role $role): int
    {
        $index = array_search($role->name, PermissionCatalog::SYSTEM_ROLES, true);

        return $index === false ? count(PermissionCatalog::SYSTEM_ROLES) : $index;
    }

    /** All user ids of the ACTIVE tenant — the owners of its roles. */
    private function tenantUserIds(): \Illuminate\Support\Collection
    {
        return User::query()->where('business_id', $this->tenantId())->pluck('id');
    }

    private function tenantOwnerId(): int
    {
        return Business::find($this->tenantId())?->created_by
            ?? User::where('business_id', $this->tenantId())->where('type', 'admin')->value('id')
            ?? 0;
    }

    private function tenantRoleOrFail(Role $role): void
    {
        if (! $this->tenantUserIds()->contains($role->created_by)) {
            abort(404);
        }
    }

    private function normalizeRoleName(string $name): string
    {
        return \Illuminate\Support\Str::snake(trim($name));
    }

    private function assertUniqueName(string $name): void
    {
        $exists = Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->whereIn('created_by', $this->tenantUserIds())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "A role named [{$name}] already exists in this clinic.",
            ]);
        }
    }

    /** Reject unknown permission names, then complete the dependency closure. */
    private function validatedPermissionSet(array $names): array
    {
        $unknown = collect($names)
            ->reject(fn (string $name) => PermissionCatalog::isValid($name))
            ->unique()
            ->values();

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Unknown permissions: '.$unknown->implode(', '),
            ]);
        }

        return PermissionCatalog::expand($names);
    }

    private function userCount(Role $role): int
    {
        // Users are hard-deleted in this schema (no soft deletes) — the
        // role_user pivot is the single source for assignment counts.
        return (int) DB::table('role_user')
            ->where('role_id', $role->id)
            ->distinct()
            ->count('role_user.user_id');
    }

    /** Laratrust caches per-user role/permission snapshots — drop them. */
    private function flushRoleUsers(Role $role): void
    {
        $userIds = DB::table('role_user')->where('role_id', $role->id)->pluck('user_id');

        User::whereIn('id', $userIds)->get()->each(function (User $user) {
            if (method_exists($user, 'flushCache')) {
                $user->flushCache();
            }
            TenantAuthorizer::flushUser($user->id);
        });
    }

    private function bumpPermissionsVersion(): void
    {
        if ($tenantId = $this->tenantId()) {
            Business::whereKey($tenantId)->increment('permissions_version');
        }
    }
}
