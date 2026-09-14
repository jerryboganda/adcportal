<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\PlatformAuthorizer;

/**
 * Shared plumbing for control-plane controllers: every action requires an
 * explicit platform capability; tenant permissions are never consulted here.
 */
abstract class PlatformController extends BaseApiController
{
    protected function denyUnlessCapability(string $capability): void
    {
        PlatformAuthorizer::denyUnless(auth()->user(), $capability);
    }

    protected function actor()
    {
        return auth()->user();
    }
}
