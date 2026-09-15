<?php

namespace App\Services;

use App\Models\User;

/**
 * Control-plane authorization: platform capabilities are completely separate
 * from clinical/tenant permissions. `super_admin` holds every capability;
 * other platform staff hold the set mapped to their role in config/ris.php.
 */
class PlatformAuthorizer
{
    public const CAPABILITIES = [
        'tenants.view', 'tenants.manage', 'tenants.lifecycle', 'provisioning.manage',
        'plans.manage', 'subscriptions.manage', 'usage.view', 'health.view',
        'audit.view', 'support.manage', 'platform.users.view', 'platform.users.manage',
        'infrastructure.manage', 'integrations.manage', 'operations.manage',
    ];

    public static function capabilities(User $user): array
    {
        if ($user->type === 'super_admin') {
            return ['*'];
        }

        if ($user->type === 'platform_admin' && $user->platform_role) {
            return array_values(config("ris.platform_roles.{$user->platform_role}", []));
        }

        return [];
    }

    public static function isPlatformAdmin(User $user): bool
    {
        return self::capabilities($user) !== [];
    }

    public static function allows(User $user, string $capability): bool
    {
        $caps = self::capabilities($user);

        return in_array('*', $caps, true) || in_array($capability, $caps, true);
    }

    public static function denyUnless(User $user, string $capability): void
    {
        if (! self::allows($user, $capability)) {
            abort(403, "Platform capability [{$capability}] is required.");
        }
    }
}
