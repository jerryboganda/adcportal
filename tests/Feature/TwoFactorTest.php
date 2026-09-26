<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use App\Services\Totp;
use Illuminate\Support\Facades\Hash;

/**
 * End-to-end 2FA enforcement: enrollment via the API, the login challenge
 * round-trip, control-plane gating, support-session gating, and disable.
 * Unenrolled users (tenant staff, new platform users) must be unaffected.
 */
class TwoFactorTest extends ApiTestCase
{
    private function platformUser(string $type = 'super_admin'): User
    {
        return User::create([
            'name' => 'Platform '.$type.' '.uniqid(),
            'email' => 'p2fa.'.md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => $type,
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    private function enroll(User $user): void
    {
        $secret = Totp::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
        ])->save();
    }

    /** Real stateful enrollment journey: setup → confirm. Marks the test session unlocked. */
    private function enrollViaApi(User $user): string
    {
        $this->withHeaders(['Referer' => 'http://localhost:3000']);

        $secret = $this->actingAs($user)->postJson('/api/v1/two-factor/setup')->assertOk()->json('data.secret');
        $this->actingAs($user)->postJson('/api/v1/two-factor/confirm', ['code' => Totp::currentCode($secret)])->assertOk();

        return $secret;
    }

    public function test_unenrolled_platform_user_is_unaffected(): void
    {
        $super = $this->platformUser();

        $response = $this->actingAs($super)->getJson('/api/v1/platform/overview');
        $response->assertOk();
    }

    public function test_enrolled_user_without_challenge_is_blocked_with_typed_error(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);

        $this->actingAs($super)
            ->getJson('/api/v1/platform/overview')
            ->assertStatus(403)
            ->assertJsonPath('error', 'two_factor_required');
    }

    public function test_login_returns_two_factor_required_for_enrolled_user(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);

        $this->withHeaders(['Referer' => 'http://localhost:3000']);

        $response = $this->postJson('/api/v1/login', [
            'email' => $super->email,
            'password' => 'R1s!T3st#2026x',
        ]);

        $response->assertOk()->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonMissing(['user']);
    }

    public function test_full_challenge_round_trip_unlocks_platform_access(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);

        // The SPA always talks to the API statefully (Sanctum SPA mode).
        $this->withHeaders(['Referer' => 'http://localhost:3000']);

        // Step 1: password → challenge pending.
        $this->postJson('/api/v1/login', [
            'email' => $super->email,
            'password' => 'R1s!T3st#2026x',
        ])->assertOk()->assertJsonPath('data.two_factor_required', true);

        // Step 2: wrong code rejected.
        $this->postJson('/api/v1/two-factor/challenge', ['code' => '000000'])
            ->assertStatus(422);

        // Step 3: correct code completes the challenge and yields a session.
        $this->postJson('/api/v1/two-factor/challenge', ['code' => Totp::currentCode($super->two_factor_secret)])
            ->assertOk();

        $this->getJson('/api/v1/platform/overview')->assertOk();
        $this->getJson('/api/v1/platform/tenants')->assertOk();
    }

    /**
     * The regression: `CHALLENGE_TTL_MINUTES` was declared and never read, so a
     * pending identity — password already verified — sat in the session waiting
     * for a code for as long as the session lived.
     */
    public function test_a_pending_challenge_expires(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);
        $this->withHeaders(['Referer' => 'http://localhost:3000']);

        $this->postJson('/api/v1/login', [
            'email' => $super->email,
            'password' => 'R1s!T3st#2026x',
        ])->assertOk()->assertJsonPath('data.two_factor_required', true);

        // The code that WOULD have worked...
        $code = Totp::currentCode($super->two_factor_secret);

        // ...is refused once the challenge is older than the stated window.
        $this->travel(TwoFactorService::CHALLENGE_TTL_MINUTES + 1)->minutes();

        $this->postJson('/api/v1/two-factor/challenge', ['code' => $code])->assertStatus(422);
        $this->getJson('/api/v1/platform/overview')->assertStatus(401);
    }

    /**
     * A TOTP code is valid for its whole ±1 step window — up to 90 seconds — so
     * an observed code could be spent twice. Once a step has been accepted, that
     * step and every earlier one are refused.
     */
    public function test_an_accepted_code_cannot_be_replayed(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);
        $this->withHeaders(['Referer' => 'http://localhost:3000']);

        $this->postJson('/api/v1/login', [
            'email' => $super->email,
            'password' => 'R1s!T3st#2026x',
        ])->assertOk();

        $code = Totp::currentCode($super->two_factor_secret);
        $this->postJson('/api/v1/two-factor/challenge', ['code' => $code])->assertOk();

        // A second session for the same user presents the same code again.
        $this->post('/api/v1/logout');
        $this->postJson('/api/v1/login', [
            'email' => $super->email,
            'password' => 'R1s!T3st#2026x',
        ])->assertOk();

        $this->postJson('/api/v1/two-factor/challenge', ['code' => $code])
            ->assertStatus(422, 'A TOTP code must not be spendable twice.');
    }

    public function test_challenge_rejects_without_pending_login(): void    {
        // No login happened in this session: there is nothing to complete.
        $this->postJson('/api/v1/two-factor/challenge', ['code' => '123456'])
            ->assertStatus(422);
    }

    public function test_support_session_entry_requires_completed_challenge(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);

        // Authenticated but challenge NOT completed (actingAs bypasses login()).
        $this->actingAs($super)
            ->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessA->id])
            ->assertStatus(403)
            ->assertJsonPath('error', 'two_factor_required');
    }

    public function test_setup_confirm_enable_flow(): void
    {
        $super = $this->platformUser();
        $this->withHeaders(['Referer' => 'http://localhost:3000']);

        // Setup returns secret + otpauth URL and stashes a pending secret.
        $setup = $this->actingAs($super)->postJson('/api/v1/two-factor/setup')->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('data.otpauthUrl'));

        // Wrong code does not activate.
        $this->actingAs($super)->postJson('/api/v1/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422);
        $this->assertNull($super->fresh()->two_factor_enabled_at);

        // Correct code activates.
        $this->actingAs($super)->postJson('/api/v1/two-factor/confirm', ['code' => Totp::currentCode($secret)])
            ->assertOk();
        $this->assertNotNull($super->fresh()->two_factor_enabled_at);
        $this->assertSame($secret, $super->fresh()->two_factor_secret);
    }

    public function test_disable_requires_password_and_current_code(): void
    {
        $super = $this->platformUser();

        // Enroll through the real stateful flow — this session is now unlocked.
        $secret = $this->enrollViaApi($super);

        // Wrong password refused.
        $this->actingAs($super)
            ->postJson('/api/v1/two-factor/disable', [
                'password' => 'wrong-password',
                'code' => Totp::currentCode($super->fresh()->two_factor_secret),
            ])->assertStatus(422);

        // Wrong code refused.
        $this->actingAs($super)
            ->postJson('/api/v1/two-factor/disable', [
                'password' => 'R1s!T3st#2026x',
                'code' => '000000',
            ])->assertStatus(422);

        // Correct pair disables.
        $this->actingAs($super)
            ->postJson('/api/v1/two-factor/disable', [
                'password' => 'R1s!T3st#2026x',
                'code' => Totp::currentCode($super->fresh()->two_factor_secret),
            ])->assertOk();

        $this->assertNull($super->fresh()->two_factor_enabled_at);

        // And platform access works again without a challenge.
        $this->actingAs($super)->getJson('/api/v1/platform/overview')->assertOk();
    }

    public function test_tenant_staff_cannot_enroll(): void
    {
        $this->actingAs($this->adminA)
            ->postJson('/api/v1/two-factor/setup')
            ->assertStatus(403);
    }

    public function test_secret_is_never_serialized(): void
    {
        $super = $this->platformUser();
        $this->enroll($super);

        $payload = $this->actingAs($super)->getJson('/api/v1/me')->json('data.user');

        $this->assertArrayNotHasKey('two_factor_secret', $payload);
    }

    public function test_password_policy_enforced_on_registration(): void
    {
        $weak = $this->postJson('/api/v1/register', [
            'clinic_name' => 'Weak PW Clinic',
            'name' => 'Owner',
            'email' => 'weak.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Password1!', // 10 chars — below policy
        ]);

        $weak->assertStatus(422);

        $strong = $this->postJson('/api/v1/register', [
            'clinic_name' => 'Strong PW Clinic',
            'name' => 'Owner',
            'email' => 'strong.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Str0ng!Passphrase',
        ]);

        $strong->assertStatus(201);
    }

    public function test_password_policy_enforced_on_staff_creation(): void
    {
        $weak = $this->actingAs($this->adminA)->postJson('/api/v1/staff', [
            'name' => 'Weak Staff',
            'email' => 'weak.staff.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Password1!',
            'role' => 'receptionist',
        ]);
        $weak->assertStatus(422);

        $strong = $this->actingAs($this->adminA)->postJson('/api/v1/staff', [
            'name' => 'Strong Staff',
            'email' => 'strong.staff.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Str0ng!Passphrase',
            'role' => 'receptionist',
        ]);
        $strong->assertStatus(201);
    }

    public function test_password_policy_enforced_on_platform_user_creation(): void
    {
        $super = $this->platformUser();

        $this->actingAs($super)->confirmStepUp()->postJson('/api/v1/platform/users', [
            'name' => 'Weak Platform User',
            'email' => 'weak.plat.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Password1!',
            'role' => 'ops',
        ])->assertStatus(422);

        $this->actingAs($super)->confirmStepUp()->postJson('/api/v1/platform/users', [
            'name' => 'Strong Platform User',
            'email' => 'strong.plat.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Str0ng!Passphrase',
            'role' => 'ops',
        ])->assertStatus(201);
    }
}
