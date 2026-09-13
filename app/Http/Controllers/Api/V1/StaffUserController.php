<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $this->assignRole($user, $validated['role']);

        $this->audit('user_created', $user, [
            'summary' => "Provisioned user account for {$user->name} ({$validated['role']})",
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

        if (isset($validated['role'])) {
            $this->assignRole($user, $validated['role']);
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
            'password' => [$required ? 'required' : 'sometimes', Password::min(8)],
            'role' => [$required ? 'required' : 'sometimes', 'in:admin,radiologist,technologist,receptionist,billing'],
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

    /** Frontend role vocabulary → per-tenant laratrust role name. */
    private function assignRole(User $user, string $portalRole): void
    {
        $roleName = match ($portalRole) {
            'radiologist' => 'radiologist',
            'technologist' => 'technician',
            'receptionist' => 'receptionist',
            'billing' => 'billing',
            'admin' => 'admin',
            default => 'receptionist',
        };

        $role = \App\Models\Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('created_by', $this->tenantId() === 0 ? $user->created_by : $this->tenantOwnerId())
            ->first();

        if ($role && ! $user->hasRole($roleName)) {
            $user->addRole($role);
        }
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
