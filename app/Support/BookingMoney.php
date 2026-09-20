<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Booking financials: the ONE place where price/discount/payable/balance are
 * defined, shared by the booking API (validation + settlement) and covered by
 * unit tests.
 *
 * Money is calculated in INTEGER MINOR UNITS (paisa) so no binary-float drift
 * can creep into a bill — `0.1 + 0.2` style arithmetic never decides what a
 * patient owes. Values cross the boundary as floats/strings (the column type
 * and the existing API contract), and are converted exactly once on the way in.
 *
 *   basePrice        = tenant-configured procedure price
 *   discountAmount   = entered discount (fixed amount)
 *   payable          = basePrice - discountAmount        (never below zero)
 *   outstanding      = payable - amountReceived          (never below zero)
 */
final class BookingMoney
{
    /** Rupiah/paisa style minor units: 1 rupee = 100 paisa. */
    public const MINOR_UNITS_PER_UNIT = 100;

    /** Canonical wording — the SPA mirrors these strings verbatim. */
    public const ERROR_DISCOUNT_EXCEEDS_PRICE = 'Discount cannot exceed the study price.';
    public const ERROR_DISCOUNT_NEGATIVE = 'The discount cannot be negative.';
    public const ERROR_AMOUNT_REQUIRED = 'An amount greater than zero is required for this payment status.';
    public const ERROR_AMOUNT_EXCEEDS_PAYABLE = 'Payment amount exceeds the final payable amount.';
    public const ERROR_AMOUNT_MUST_MATCH_PAYABLE = 'The paid amount must match the final payable amount.';
    public const ERROR_NOTHING_TO_COLLECT = 'This study is fully discounted; there is nothing to collect.';

    /** Exact conversion from an API/DB money value to minor units. */
    public static function toMinor(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * self::MINOR_UNITS_PER_UNIT);
    }

    /** Back to the API/DB representation (2 decimal places). */
    public static function fromMinor(int $minor): float
    {
        return round($minor / self::MINOR_UNITS_PER_UNIT, 2);
    }

    public static function decimalToMinor(?string $amount, int $precision = 2): ?int
    {
        if ($precision < 0 || $precision > 4) {
            throw new InvalidArgumentException('Unsupported minor-unit precision.');
        }
        if ($amount === null) {
            return null;
        }
        if (! preg_match('/\A(-?)([0-9]+)(?:\.([0-9]+))?\z/D', $amount, $parts)) {
            throw new InvalidArgumentException('Money must be a decimal value.');
        }

        $fraction = $parts[3] ?? '';
        if (strlen($fraction) > $precision && trim(substr($fraction, $precision), '0') !== '') {
            throw new InvalidArgumentException('Money exceeds the currency minor-unit precision.');
        }
        $digits = ltrim($parts[2].str_pad(substr($fraction, 0, $precision), $precision, '0'), '0');
        $limit = '9007199254740991';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new InvalidArgumentException('Money exceeds the safe integer limit.');
        }

        return ($parts[1] === '-' ? -1 : 1) * (int) $digits;
    }

    public static function minorToDecimal(?int $minor, int $precision = 2): ?string
    {
        if ($precision < 0 || $precision > 4) {
            throw new InvalidArgumentException('Unsupported minor-unit precision.');
        }
        if ($minor === null) {
            return null;
        }
        if ($minor < -9007199254740991 || $minor > 9007199254740991) {
            throw new InvalidArgumentException('Money exceeds the safe integer limit.');
        }

        $digits = str_pad((string) abs($minor), $precision + 1, '0', STR_PAD_LEFT);
        $sign = $minor < 0 ? '-' : '';

        return $sign.($precision === 0 ? $digits : substr($digits, 0, -$precision).'.'.substr($digits, -$precision));
    }

    /** Final payable: price minus discount, floored at zero. */
    public static function payable(float|int|string|null $basePrice, float|int|string|null $discount): float
    {
        $minor = max(0, self::toMinor($basePrice) - self::toMinor($discount));

        return self::fromMinor($minor);
    }

    /** What is still owed after the money actually received. */
    public static function outstanding(float|int|string|null $payable, float|int|string|null $amountReceived): float
    {
        $minor = max(0, self::toMinor($payable) - self::toMinor($amountReceived));

        return self::fromMinor($minor);
    }

    /** Exact money equality (avoids `abs($a - $b) > 0.001` guesswork). */
    public static function equals(float|int|string|null $a, float|int|string|null $b): bool
    {
        return self::toMinor($a) === self::toMinor($b);
    }

    /**
     * Canonical discount validation. Returns null when acceptable, otherwise
     * the user-facing reason (mirrored verbatim by the SPA so both layers
     * speak with one voice).
     */
    public static function validateDiscount(float|int|string|null $basePrice, float|int|string|null $discount): ?string
    {
        if (self::toMinor($basePrice) < 0) {
            return 'The study price cannot be negative.';
        }

        if (self::toMinor($discount) < 0) {
            return self::ERROR_DISCOUNT_NEGATIVE;
        }

        // A 100% discount (discount === price) is valid: it produces a zero
        // payable booking. Only exceeding the price is rejected.
        if (self::toMinor($discount) > self::toMinor($basePrice)) {
            return self::ERROR_DISCOUNT_EXCEEDS_PRICE;
        }

        return null;
    }

    /** Human-readable money for audit summaries and API error messages. */
    public static function format(float|int|string|null $amount): string
    {
        return number_format(self::fromMinor(self::toMinor($amount)), 2);
    }
}
