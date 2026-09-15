<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unauthenticated access must be a 401 — whatever the Accept header says.
 *
 * This is an API-only application, so there is no `login` web route for the
 * framework to redirect a guest to. Laravel's default guest redirect is
 * `fn () => route('login')`, and it is invoked from *inside* the `auth`
 * middleware (`Authenticate::redirectTo`) — before the exception handler can
 * turn an AuthenticationException into a 401. Any request that did not send
 * `Accept: application/json` therefore answered
 * `500 Route [login] not defined.` instead of `401`.
 *
 * That is not cosmetic. It reports "you are not signed in" as a server fault,
 * so monitoring pages for a real outage and clients retry the wrong way; with
 * `APP_DEBUG=true` the response also leaks a stack trace. The gap survived
 * because every other guest test in this suite uses `getJson()`, which sets the
 * Accept header and so never reached the broken branch.
 *
 * The fix lives in `bootstrap/app.php` (`redirectGuestsTo(fn () => null)` plus
 * `shouldRenderJsonWhen` for `api/*`). These tests pin the behaviour.
 */
class UnauthenticatedResponseTest extends ApiTestCase
{
    /**
     * A spread of protected surfaces: control plane, identity/context, and the
     * tenant data plane — they sit behind different middleware stacks.
     *
     * @return array<string, array{0: string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'platform overview' => ['/api/v1/platform/overview'],
            'platform operations (§80)' => ['/api/v1/platform/operations'],
            'platform failed jobs (§81)' => ['/api/v1/platform/operations/jobs'],
            'platform tenants' => ['/api/v1/platform/tenants'],
            'identity me' => ['/api/v1/me'],
            'memberships' => ['/api/v1/memberships'],
            'tenant bootstrap' => ['/api/v1/bootstrap'],
            'tenant studies' => ['/api/v1/studies'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_unauthenticated_request_is_401_without_a_json_accept_header(string $route): void
    {
        $response = $this->get($route);

        $response->assertStatus(401);

        // The exact defect: a routing exception rendered as a 500.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('Route [login] not defined', $body);
        $this->assertStringNotContainsString('RouteNotFoundException', $body);
    }

    #[DataProvider('protectedRoutes')]
    public function test_unauthenticated_request_is_401_with_a_json_accept_header(string $route): void
    {
        $this->getJson($route)->assertStatus(401);
    }

    #[DataProvider('protectedRoutes')]
    public function test_unauthenticated_request_is_never_a_redirect(string $route): void
    {
        // A redirect towards a non-existent login page is what produced the 500;
        // for an API client a 3xx is just as wrong as a 5xx.
        $this->assertFalse($this->get($route)->isRedirect());
    }

    public function test_public_routes_are_unaffected(): void
    {
        // Positive control: the fix must not make public surfaces require auth.
        $this->getJson('/api/v1/plans')->assertOk();
        $this->get('/api/v1/health')->assertOk();
    }

    public function test_the_unauthenticated_body_is_the_standard_api_shape(): void
    {
        $this->get('/api/v1/platform/overview')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
