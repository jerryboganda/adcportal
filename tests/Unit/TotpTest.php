<?php

namespace Tests\Unit;

use App\Services\Totp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TotpTest extends TestCase
{
    /** RFC 4226 Appendix D test secret: the ASCII string "12345678901234567890". */
    private const RFC_SECRET_B32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function tearDown(): void
    {
        // `Totp` reads `Illuminate\Support\Carbon::now()` while these vectors
        // freeze `CarbonImmutable`. Carbon shares one factory behind the two
        // classes, but clearing both is the cheap way to guarantee no test can
        // inherit a frozen clock through whichever class it happened to use.
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_hotp_matches_all_rfc4226_reference_vectors(): void
    {
        // The canonical RFC 4226 Appendix D table (6-digit truncation).
        $vectors = [
            0 => '755224', 1 => '287082', 2 => '359152', 3 => '969429',
            4 => '338314', 5 => '254676', 6 => '287922', 7 => '162583',
            8 => '399871', 9 => '520489',
        ];

        foreach ($vectors as $counter => $code) {
            $this->assertSame($code, Totp::codeAt(self::RFC_SECRET_B32, $counter), "counter {$counter}");
        }
    }

    public function test_generated_secrets_are_base32(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', Totp::generateSecret());
        }
    }

    public function test_verify_accepts_the_current_code(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestampUTC(59)); // counter 1
        $secret = self::RFC_SECRET_B32;

        $this->assertSame('287082', Totp::currentCode($secret));
        $this->assertTrue(Totp::verify($secret, '287082'));
        // Spaced input ("287 082") must be accepted.
        $this->assertTrue(Totp::verify($secret, '287 082'));
    }

    public function test_verify_honours_one_step_of_drift(): void
    {
        $secret = self::RFC_SECRET_B32;
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestampUTC(59)); // counter 1

        $this->assertTrue(Totp::verify($secret, Totp::codeAt($secret, 0)));   // previous step
        $this->assertTrue(Totp::verify($secret, Totp::codeAt($secret, 2)));   // next step
        $this->assertFalse(Totp::verify($secret, Totp::codeAt($secret, 5)));  // far future
    }

    public function test_verify_rejects_stale_codes_and_garbage(): void
    {
        $secret = self::RFC_SECRET_B32;
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestampUTC(59 + 30 * 10)); // counter 11

        $this->assertFalse(Totp::verify($secret, Totp::codeAt($secret, 1)));
        $this->assertFalse(Totp::verify($secret, 'abcdef'));
        $this->assertFalse(Totp::verify($secret, '12345'));
        $this->assertFalse(Totp::verify($secret, '1234567'));
        $this->assertFalse(Totp::verify($secret, ''));
        $this->assertFalse(Totp::verify('', '287082'));
    }

    public function test_otpauth_uri_shape(): void
    {
        $uri = Totp::otpauthUri('ABC234DEF', 'super@example.test', 'PolytronX RIS');

        $this->assertStringStartsWith('otpauth://totp/PolytronX%20RIS:super%40example.test?', $uri);
        $this->assertStringContainsString('secret=ABC234DEF', $uri);
        $this->assertStringContainsString('issuer=PolytronX%20RIS', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
