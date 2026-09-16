<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Models\User;
use App\Services\PlatformAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform staff management (control-plane identities only — tenant staff
 * are managed inside their tenant). Role changes are capability-gated and
 * audited; the platform can never end up without an active super admin.
 */
class PlatformUserController extends PlatformController
{
    public function index(): JsonResponse
    {
        $this->denyUnlessCapability('platform.users.view');

        $users = User::query()
            ->whereIn('type', ['super_admin', 'platform_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'type', 'platform_role', 'active_status', 'last_login_at']);

        return $this->ok(['users' => $users->map(fn ($u) => $this->shape($u))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('platform.users.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => \App\Services\PasswordPolicy::rules(),
            'role' => ['required', Rule::in(['super_admin', ...array_keys(config('ris.platform_roles', []))])],
        ]);

        // Only a super admin may mint another super admin.
        if ($validated['role'] === 'super_admin') {
            $this->denyUnlessCapability('*');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'type' => $validated['role'] === 'super_admin' ? 'super_admin' : 'platform_admin',
            'platform_role' => $validated['role'] === 'super_admin' ? null : $validated['role'],
            'active_status' => 1,
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        \App\Models\AuditLog::record('platform_user_created', $user, [
            'summary' => "Platform user {$user->email} created with role {$validated['role']}.",
        ]);

        return response()->json(['data' => ['user' => $this->shape($user)]], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->denyUnlessCapability('platform.users.manage');
        abort_unless(in_array($user->type, ['super_admin', 'platform_admin'], true), 404);

        $validated = $request->validate([
            'role' => ['sometimes', Rule::in(['super_admin', ...array_keys(config('ris.platform_roles', []))])],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        // Guard: never disable or demote the final active super admin.
        if (($validated['role'] ?? null) === 'super_admin') {
            $this->denyUnlessCapability('*');
        }

        $demoting = isset($validated['role']) && $user->type === 'super_admin' && $validated['role'] !== 'super_admin';
        $disabling = isset($validated['isActive']) && $validated['isActive'] === false && $user->type === 'super_admin';

        if (($demoting || $disabling) && ! $this->otherActiveSuperAdminExists($user)) {
            abort(422, 'At least one active super admin must remain.');
        }

        if (isset($validated['role'])) {
            $user->type = $validated['role'] === 'super_admin' ? 'super_admin' : 'platform_admin';
            $user->platform_role = $validated['role'] === 'super_admin' ? null : $validated['role'];
        }

        if (isset($validated['isActive'])) {
            $user->active_status = $validated['isActive'] ? 1 : 0;
        }

        $user->save();

        \App\Models\AuditLog::record('platform_user_updated', $user, [
            'summary' => "Platform user {$user->email} updated (".implode(', ', array_keys($validated)).').',
        ]);

        return $this->ok(['user' => $this->shape($user->fresh())]);
    }

    private function otherActiveSuperAdminExists(User $except): bool
    {
        return User::query()
            ->where('type', 'super_admin')
            ->where('id', '!=', $except->id)
            ->where('active_status', 1)
            ->exists();
    }

    private function shape(User $u): array
    {
        return [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'type' => $u->type,
            'role' => $u->type === 'super_admin' ? 'super_admin' : ($u->platform_role ?? 'ops'),
            'capabilities' => PlatformAuthorizer::capabilities($u),
            'isActive' => (bool) $u->active_status,
            'lastLogin' => $u->last_login_at?->toIso8601String(),
        ];
    }
}
