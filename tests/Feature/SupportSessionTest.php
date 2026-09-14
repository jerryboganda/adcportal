<?php

namespace Tests\Feature;

use App\Models\SupportSession;
use App\Models\User;

/**
 * Break-glass support sessions: explicit, time-boxed, reason-mandated,
 * audited tenant access for platform staff. Without an active session a
 * platform user holds ZERO tenant permissions and sees zero tenant data.
 */
class SupportSessionTest extends ApiTestCase
{
    private function supportUser(): User
    {
        return User::create([
            'name' => 'Support Engineer',
            'email' => 'support.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Secret#12345',
            'email_verified_at' => now(),
            'type' => 'platform_admin',
            'platform_role' => 'support',
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    private function openSession(User $user, int $businessId, int $minutes = 60): SupportSession
    {
        return SupportSession::create([
            'platform_user_id' => $user->id,
            'business_id' => $businessId,
            'reason' => 'Test: verifying break-glass behavior',
            'started_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    public function test_platform_user_without_session_has_no_tenant_access(): void
    {
        $support = $this->supportUser();

        // Tenant endpoints resolve tenant 0 → no permissions, no data.
        $this->actingAs($support)->getJson('/api/v1/studies')->assertStatus(403);

        $platform = $this->actingAs($support)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        $this->assertTrue($platform->json('data.platform'));
        $this->assertNull($platform->json('data.patients'), 'Platform bootstrap must not carry clinical payloads.');
        $this->assertNull($platform->json('data.studies'));
    }

    public function test_session_entry_and_tenant_operation_flow(): void
    {
        $support = $this->supportUser();

        $this->actingAs($support)->postJson('/api/v1/platform/support-sessions', [
            'businessId' => $this->businessA->id,
            'reason' => 'Ticket 7 — radiologist cannot sign reports',
            'minutes' => 30,
        ])->assertStatus(201);

        // Entering the tenant context is verified server-side against the session row.
        $this->actingAs($support)->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessA->id])
            ->assertOk()
            ->assertJsonPath('data.user.supportSession.businessId', $this->businessA->id);

        // Inside the context the platform user operates with the tenant's
        // full operational permission set (this is what break-glass means).
        $this->actingAs($support)->getJson('/api/v1/bootstrap')->assertOk();

        // Leaving the context revokes it immediately.
        $this->actingAs($support)->postJson('/api/v1/tenant/leave')->assertOk();
        $this->actingAs($support)->getJson('/api/v1/studies')->assertStatus(403);
    }

    public function test_entering_a_tenant_without_a_session_is_refused(): void
    {
        $support = $this->supportUser();

        $this->actingAs($support)->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessA->id])
            ->assertStatus(403);
    }

    public function test_entering_a_tenant_outside_the_session_scope_is_refused(): void
    {
        $support = $this->supportUser();
        $this->openSession($support, $this->businessA->id);

        // Session is for tenant A — entering B is refused.
        $this->actingAs($support)->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessB->id])
            ->assertStatus(403);

        // Even with a stale client-supplied context value, authorization
        // re-checks the session row: B stays inaccessible.
        $this->actingAs($support)->getJson('/api/v1/studies')->assertStatus(403);
    }

    public function test_expired_sessions_no_longer_grant_access(): void
    {
        $support = $this->supportUser();

        $session = SupportSession::create([
            'platform_user_id' => $support->id,
            'business_id' => $this->businessA->id,
            'reason' => 'Test: expired window',
            'started_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
        $this->assertFalse($session->isActive());

        $this->actingAs($support)->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessA->id])
            ->assertStatus(403);
    }

    public function test_session_expiry_sweep_closes_stale_rows(): void
    {
        $support = $this->supportUser();
        $this->openSession($support, $this->businessA->id, minutes: -5);

        $this->artisan('ris:subscription-sweep')->assertExitCode(0);

        $this->assertNotNull(SupportSession::where('platform_user_id', $support->id)->first()->ended_at);
    }

    public function test_support_actions_are_audited_against_the_tenant(): void
    {
        $support = $this->supportUser();

        $created = $this->actingAs($support)->postJson('/api/v1/platform/support-sessions', [
            'businessId' => $this->businessA->id,
            'reason' => 'Ticket 99 — audit trail verification',
        ])->assertStatus(201)->decodeResponseJson();
        $sessionId = $created->json('data.session.id');

        $this->actingAs($support)->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessA->id])->assertOk();
        $this->actingAs($support)->postJson('/api/v1/tenant/leave')->assertOk();

        // Leaving the context drops tenant access; closing the session ends
        // the grant entirely — and both edges are audited.
        $this->actingAs($support)->postJson("/api/v1/platform/support-sessions/{$sessionId}/end", ['note' => 'Resolved.'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['business_id' => $this->businessA->id, 'action' => 'support_session_started']);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $this->businessA->id, 'action' => 'support_session_ended']);
    }

    public function test_short_reasons_are_rejected(): void
    {
        $support = $this->supportUser();

        $this->actingAs($support)->postJson('/api/v1/platform/support-sessions', [
            'businessId' => $this->businessA->id,
            'reason' => 'curiosity',
        ])->assertStatus(422);
    }
}
