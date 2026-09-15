<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\TenantBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated presentation lookup (master-prompt §38).
 *
 * The login screen calls this with the host it is being served from so it can
 * paint the right customer brand BEFORE anyone signs in. This is the only
 * place a request host influences anything, and what it influences is purely
 * cosmetic: it returns no tenant data, no user data and no permissions, and it
 * is never consulted when authorizing a request. Only DNS-verified domains
 * resolve to a tenant; everything else gets the platform defaults.
 */
class PublicTenantContextController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        $host = $request->query('host') ?: $request->getHost();

        return $this->ok([
            'host' => $host,
            // `matched` means "a verified custom domain maps to a tenant" —
            // it is a branding hint, NOT an identity or authorization claim.
            'branding' => TenantBrandingService::forHost($host),
        ]);
    }
}
