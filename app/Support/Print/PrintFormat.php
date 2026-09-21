<?php

namespace App\Support\Print;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Document formatting — money, dates, quantities, text.
 *
 * Every printed string is produced HERE and then travels inside the print
 * document model, so the preview, the browser print and the server PDF render
 * the same characters. The previous system formatted money with
 * `toLocaleString()` in the browser (locale-dependent, and blind to the
 * tenant's currency) and `number_format()` in PHP, which is how the same
 * invoice could show two different totals.
 *
 * There is intentionally NO locale argument: a document handed to a patient in
 * Pakistan must not change shape because the workstation's browser is set to
 * another region.
 */
final class PrintFormat
{
    /** Currency presentation styles a tenant may choose between. */
    public const CURRENCY_STYLES = ['symbol' => 'Rs.', 'code' => 'PKR', 'sign' => '₨'];

    /** Date formats a tenant may choose between (PHP date() syntax). */
    public const DATE_FORMATS = [
        'd M Y, h:i A',
        'd/m/Y H:i',
        'd M Y',
        'Y-m-d H:i',
    ];

    public static function currencyPrefix(string $style, ?string $tenantSymbol = null): string
    {
        return match ($style) {
            'code' => 'PKR',
            'sign' => '₨',
            default => ($tenantSymbol !== null && trim($tenantSymbol) !== '') ? trim($tenantSymbol) : 'Rs.',
        };
    }

    /** Two decimals — the form of record on A4 tax invoices. */
    public static function money(float|int|string|null $amount, string $prefix = 'Rs.'): string
    {
        return $prefix.' '.number_format((float) ($amount ?? 0), 2, '.', ',');
    }

    /** Whole units where they are whole — a receipt must not waste a line on ".00". */
    public static function moneyCompact(float|int|string|null $amount, string $prefix = 'Rs.'): string
    {
        $value = (float) ($amount ?? 0);
        $decimals = abs($value - round($value)) < 0.005 ? 0 : 2;

        return $prefix.' '.number_format($value, $decimals, '.', ',');
    }

    /** A signed delta, for discounts and variance lines. */
    public static function moneyDelta(float|int|string|null $amount, string $prefix = 'Rs.'): string
    {
        $value = (float) ($amount ?? 0);
        $sign = $value < 0 ? '-' : '';

        return $sign.self::moneyCompact(abs($value), $prefix);
    }

    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', ',');
    }

    public static function dateTime(CarbonInterface|string|null $value, string $format = 'd M Y, h:i A'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::parse($value)?->format($format) ?? '—';
    }

    public static function date(CarbonInterface|string|null $value, string $format = 'd M Y'): string
    {
        return self::dateTime($value, $format);
    }

    /**
     * Tenant timezone first, then the app timezone. Printing a UTC clock into a
     * clinical document is a factual error, not a cosmetic one.
     */
    private static function parse(CarbonInterface|string $value): ?CarbonInterface
    {
        try {
            if ($value instanceof CarbonInterface) {
                return $value->copy()->timezone(config('app.timezone'));
            }

            return Carbon::parse($value)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Null-safe text. A printed document must never contain "null", "undefined"
     * or an empty box where a field belongs.
     */
    public static function text(?string $value, string $fallback = '—'): string
    {
        $clean = trim((string) $value);

        return $clean === '' ? $fallback : $clean;
    }

    public static function quantity(float|int|string|null $value): string
    {
        $number = (float) ($value ?? 0);

        return abs($number - round($number)) < 0.005
            ? number_format($number, 0, '.', ',')
            : number_format($number, 2, '.', ',');
    }

    /**
     * Clinical narrative: preserved verbatim, with Windows line endings
     * normalised so a report written on one workstation prints the same number
     * of blank lines on another. Paragraphs, lists, measurements and units are
     * NEVER reflowed, collapsed or re-typed.
     */
    public static function narrative(?string $value): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
    }

    /** "21 Sep 2026, 03:40 PM (PKT)" — the zone is stated, never assumed. */
    public static function dateTimeWithZone(CarbonInterface|string|null $value, string $format = 'd M Y, h:i A'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $parsed = self::parse($value);

        return $parsed === null ? '—' : $parsed->format($format).' ('.$parsed->format('T').')';
    }
}
