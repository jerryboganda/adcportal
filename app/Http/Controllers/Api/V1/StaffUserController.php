<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Business;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Staff account administration. Passwords are required at creation — there
 * is no login without credentials. Server-side authorization comes from the
 * per-tenant laratrust roles, independent of the UI capability flags stored
 * on the user row.
 */
class StaffUserController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $this->denyUnless('user manage');

        $users = User::where('business_id', $this->tenantId())
            ->where('type', '!=', 'customer')
            ->orderBy('name')
            ->get();

        return $this->ok(['staff' => $users->map(fn ($u) => ApiShape::staffUser($u))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->denyUnless('user create');

        $validated = $this->validated($request, required: true);

        // Plan entitlement: user seats are enforced server-side.
        if ($business = Business::find($this->tenantId())) {
            EntitlementService::enforce($business, 'users', 'user seat');
        }

        // Account + role + membership must land atomically: a partial staff
        // row (user without role/membership) is an unusable, invisible account.
        $user = DB::transaction(function () use ($validated) {
            $targetRole = $this->resolveTargetRole($validated);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'mobile_no' => $validated['phone'] ?? null,
                'email_verified_at' => now(),
                'type' => 'staff',
                'active_status' => (int) ($validated['isActive'] ?? true),
                'business_id' => $this->tenantId(),
                'created_by' => $this->tenantId(),
                'department' => $validated['department'] ?? null,
                'initials' => $this->initials($validated['name']),
                'capabilities' => $this->capabilities($validated),
                'lang' => 'en',
            ]);

            $this->syncRole($user, $targetRole);

            TenantMembership::create([
                'user_id' => $user->id,
                'business_id' => $this->tenantId(),
                'role' => $this->membershipRoleLabel($targetRole),
                'is_default' => false,
                'status' => 'active',
            ]);

            return $user;
        });

        $this->audit('user_created', $user, [
            'summary' => "Provisioned user account for {$user->name} ({$user->portalRole()})",
        ]);

        return response()->json(['data' => ['staff' => ApiShape::staffUser($user)]], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->denyUnless('user edit');

        if ($user->business_id !== $this->tenantId() || $user->type === 'customer') {
            abort(404);
        }

        $validated = $this->validated($request, required: false);

        $updates = [
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'mobile_no' => $validated['phone'] ?? $user->mobile_no,
            'active_status' => (int) ($validated['isActive'] ?? $user->active_status),
            'department' => $validated['department'] ?? $user->department,
            'initials' => $this->initials($validated['name'] ?? $user->name),
            'capabilities' => $this->capabilities($validated) ?? $user->capabilities,
        ];

        if (! empty($validated['password'])) {
            $updates['password'] = $validated['password']; // auto-hashed by cast
        }

        $user->update($updates);

        if (isset($validated['role']) || isset($validated['roleId'])) {
            $previous = \App\Services\TenantAuthorizer::roleNamesFor($user, $this->tenantId());
            $targetRole = $this->resolveTargetRole($validated);
            $this->syncRole($user, $targetRole);

            // Keep the membership label in step with the effective role.
            $membership = TenantMembership::where('user_id', $user->id)
                ->where('business_id', $this->tenantId())
                ->first();
            if ($membership) {
                $membership->update(['role' => $this->membershipRoleLabel($targetRole)]);
            }

            $this->audit('user_role_assigned', $user, [
                'summary' => "Role of {$user->name} changed: ".(implode(', ', $previous) ?: 'none').' → '.($targetRole?->display_name ?: $targetRole?->name ?: 'none'),
            ]);
        }

        $this->audit('user_updated', $user, [
            'summary' => "Updated profile and access permissions for {$user->name}",
        ]);

        return $this->ok(['staff' => ApiShape::staffUser($user->fresh())]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->denyUnless('user delete');

        if ($user->business_id !== $this->tenantId() || $user->type === 'customer') {
            abort(404);
        }

        if ($user->id === Auth::id()) {
            abort(422, 'You cannot revoke your own account.');
        }

        $name = $user->name;
        $user->forceFill(['active_status' => 0, 'is_enable_login' => 0])->save();
        $user->delete(); // soft delete — preserves clinical authorship history

        $this->audit('user_deleted', $user->id ? $user : Auth::user(), [
            'summary' => "Revoked access for user {$name}",
        ]);

        return $this->ok(['deleted' => true]);
    }

    // ==================== internals ====================

    private function validated(Request $request, bool $required): array
    {
        return $request->validate([
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$required ? 'required' : 'sometimes', 'email', 'max:255', 'unique:users,email'.($required ? '' : ','.(int) $request->route('staff').',id')],
            'password' => [$required ? 'required' : 'sometimes', ...array_slice(\App\Services\PasswordPolicy::rules(), 1)],
            'role' => [$required ? 'required_without:roleId' : 'sometimes', 'nullable', 'in:admin,radiologist,technologist,receptionist,billing'],
            'roleId' => [$required ? 'required_without:role' : 'sometimes', 'nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'isActive' => ['nullable', 'boolean'],
            'canSignReports' => ['nullable', 'boolean'],
            'canVoidInvoices' => ['nullable', 'boolean'],
            'canOverrideScreening' => ['nullable', 'boolean'],
            'canEditMasters' => ['nullable', 'boolean'],
            'canAccessPacs' => ['nullable', 'boolean'],
        ]);
    }

    private function capabilities(array $validated): array
    {
        return [
            'canSignReports' => (bool) ($validated['canSignReports'] ?? false),
            'canVoidInvoices' => (bool) ($validated['canVoidInvoices'] ?? false),
            'canOverrideScreening' => (bool) ($validated['canOverrideScreening'] ?? false),
            'canEditMasters' => (bool) ($validated['canEditMasters'] ?? false),
            'canAccessPacs' => (bool) ($validated['canAccessPacs'] ?? false),
        ];
    }

    /**
     * Resolve the target role from the request: a tenant-scoped `roleId`
     * (any role of this clinic, including custom ones) wins over the legacy
     * SPA vocabulary. Out-of-tenant ids are validation errors, never 500s.
     */
    private function resolveTargetRole(array $validated): ?\App\Models\Role
    {
        if (! empty($validated['roleId'])) {
            $role = \App\Models\Role::query()
                ->whereKey((int) $validated['roleId'])
                ->where('guard_name', 'web')
                ->whereIn('created_by', User::where('business_id', $this->tenantId())->pluck('id'))
                ->first();

            if (! $role) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'roleId' => 'The selected role does not exist in this clinic.',
                ]);
            }

            return $role;
        }

        $portalRole = $validated['role'] ?? null;

        if (! $portalRole) {
            return null;
        }

        $roleName = match ($portalRole) {
            'radiologist' => 'radiologist',
            'technologist' => 'technician',
            'receptionist' => 'receptionist',
            'billing' => 'billing',
            'admin' => 'admin',
            default => 'receptionist',
        };

        return \App\Models\Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('created_by', $this->tenantId() === 0 ? null : $this->tenantOwnerId())
            ->first();
    }

    /**
     * REPLACE semantics: the user's tenant roles are swapped for the target.
     * Roles must never accumulate across edits — overlapping roles would
     * silently stack their permission sets.
     */
    private function syncRole(User $user, ?\App\Models\Role $targetRole): void
    {
        DB::transaction(function () use ($user, $targetRole) {
            $tenantRoleIds = \App\Models\Role::query()
                ->whereIn('created_by', User::where('business_id', $this->tenantId())->pluck('id'))
                ->pluck('id');

            DB::table('role_user')
                ->where('user_id', $user->id)
                ->whereIn('role_id', $tenantRoleIds)
                ->delete();

            if ($targetRole && ! $user->hasRole($targetRole->name)) {
                $user->addRole($targetRole);
            }
        });

        if (method_exists($user, 'flushCache')) {
            $user->flushCache();
        }
        \App\Services\TenantAuthorizer::flushUser($user->id);
    }

    /** SPA-facing label stored on the tenant membership row. */
    private function membershipRoleLabel(?\App\Models\Role $role): string
    {
        if (! $role) {
            return 'receptionist';
        }

        return $role->name === 'technician' ? 'technologist' : $role->name;
    }

    private function tenantOwnerId(): int
    {
        return \App\Models\Business::find($this->tenantId())?->created_by
            ?? User::where('business_id', $this->tenantId())->where('type', 'admin')->value('id')
            ?? 0;
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->take(3)
            ->implode('');
    }
}
