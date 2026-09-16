<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * RFC 6238 TOTP (30s step, SHA1, 6 digits) + Base32 — implemented natively so
 * the platform 2FA feature adds zero composer dependencies. Compatible with
 * Google Authenticator, Authy, 1Password, Aegis, FreeOTP.
 */
class Totp
{
    public const STEP = 30;

    public const DIGITS = 6;

    /** ±1 step (30s) clock drift tolerance. */
    public const WINDOW = 1;

    private const B32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a 160-bit secret (32 Base32 chars). */
    public static function generateSecret(): string
    {
        return self::b32encode(random_bytes(20)); // 160 bits → exactly 32 chars
    }

    /**
     * Constant-time verification of a 6-digit code with ±1 step drift.
     * Accepts spaced "123 456" input.
     */
    public static function verify(string $secret, ?string $code): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);

        if (! $secret || ! preg_match('/^\d{6}$/', (string) $code)) {
            return false;
        }

        $secretBinary = self::b32decode($secret);
        if ($secretBinary === '') {
            return false;
        }

        $now = (int) floor(Carbon::now()->getTimestamp() / self::STEP);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $expected = self::hotp($secretBinary, $now + $i);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /** Current code — tests and local tooling only. */
    public static function currentCode(string $secret): string
    {
        return self::hotp(self::b32decode($secret), (int) floor(Carbon::now()->getTimestamp() / self::STEP));
    }

    /** Code at an explicit counter — RFC 6238 test vectors and deterministic tests. */
    public static function codeAt(string $secret, int $counter): string
    {
        return self::hotp(self::b32decode($secret), $counter);
    }

    /** otpauth:// URI for authenticator-app enrollment (QR-ready). */
    public static function otpauthUri(string $secret, string $email, string $issuer = 'PolytronX RIS'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($email)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::STEP;
    }

    private static function hotp(string $secretBinary, int $counter): string
    {
        $binCounter = pack('N', 0).pack('N', $counter); // 64-bit big-endian
        $hash = hash_hmac('sha1', $binCounter, $secretBinary, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function b32encode(string $bytes): string
    {
        $output = '';
        $bits = 0;
        $buffer = 0;

        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $index = ($buffer >> ($bits - 5)) & 0x1F;
                $output .= self::B32_CHARS[$index];
                $bits -= 5;
            }
        }

        if ($bits > 0) {
            $output .= self::B32_CHARS[($buffer << (5 - $bits)) & 0x1F];
        }

        return $output;
    }

    private static function b32decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = 0;
        $buffer = 0;
        $output = '';

        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $position = strpos(self::B32_CHARS, $b32[$i]);
            if ($position === false) {
                return '';
            }
            $buffer = ($buffer << 5) | $position;
            $bits += 5;

            if ($bits >= 8) {
                $output .= chr(($buffer >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }

        return $output;
    }
}
