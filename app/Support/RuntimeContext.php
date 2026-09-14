<?php

namespace App\Support;

/**
 * Per-process runtime memoization for tenant resolution. In classic PHP-FPM
 * each request starts with fresh process state, so this is naturally
 * request-scoped; tests and long-running workers reuse the process and MUST
 * call reset() when the database identity or tenant context changes
 * (see flush_active_business_cache() and TenantAuthorizer::flushAll()).
 */
final class RuntimeContext
{
    /** @var array<string, int> user-key → active business id */
    public static array $activeBusiness = [];

    public static function reset(): void
    {
        self::$activeBusiness = [];
    }
}
