<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every route action must resolve to a real class and method.
 *
 * Regression guard for a real defect: `PlatformBrandingController` was wired
 * into `routes/api.php` without a `use` import, so `PlatformBrandingController::class`
 * silently resolved to the GLOBAL namespace. Nothing static caught it — `php -l`
 * only parses, `tsc` never sees the backend — and the entire white-label +
 * custom-domain surface answered 500 the moment it was first hit.
 *
 * This test walks the router and fails loudly on any unresolvable action, so a
 * dead endpoint can never ship again.
 */
class RouteIntegrityTest extends TestCase
{
    public function test_every_route_action_resolves_to_a_real_class_and_method(): void
    {
        $broken = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            // Closures are fine by definition; they cannot dangle.
            if ($action === 'Closure' || str_contains($action, '{closure}')) {
                continue;
            }

            [$class, $method] = str_contains($action, '@')
                ? explode('@', $action, 2)
                : [$action, '__invoke'];

            $signature = $route->methods()[0].' /'.$route->uri();

            if (! class_exists($class)) {
                $broken[] = "{$signature} → class [{$class}] does not exist (missing `use` import?)";

                continue;
            }

            if (! method_exists($class, $method)) {
                $broken[] = "{$signature} → method [{$class}::{$method}] does not exist";

                continue;
            }

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'The router exposed no controller-backed routes — the guard is not actually testing anything.');
        $this->assertSame([], $broken, "Unresolvable route actions:\n".implode("\n", $broken));
    }

    /**
     * Every platform route must sit behind the control-plane guard: a missing
     * `platform` middleware would expose the vendor control plane to tenants.
     */
    public function test_every_platform_route_carries_the_platform_guard(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/platform')) {
                continue;
            }

            if (! in_array('platform', $route->gatherMiddleware(), true)) {
                $unguarded[] = $route->methods()[0].' /'.$route->uri();
            }
        }

        $this->assertSame([], $unguarded, "Platform routes without the `platform` guard:\n".implode("\n", $unguarded));
    }
}
