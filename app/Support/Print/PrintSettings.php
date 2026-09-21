<?php

namespace App\Support\Print;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

/**
 * Per-tenant print configuration, including the per-workstation thermal
 * calibration.
 *
 * Tenant-scoped by construction: the blob is keyed by business_id, never by
 * anything the client sends. Values are clamped on the way IN (a tenant cannot
 * configure a 900 mm receipt) and normalised on the way OUT (an older record
 * that predates a new setting still renders).
 *
 * Calibration is stored here rather than in a device table because the only
 * thing a device contributes is *dimensions*: the safe printable width and the
 * feed before the cutter. That is printer-model knowledge, not user data, and
 * the same workstation keeps the same id in localStorage.
 */
final class PrintSettings
{
    public const KEY = 'ris_print_profile';

    /** A tenant has a handful of counters; an unbounded map would be a leak. */
    public const MAX_CALIBRATED_DEVICES = 20;

    /** @var array<int, array<string, mixed>> request-scoped cache */
    private static array $cache = [];

    public static function artifactKeys(): array
    {
        return array_keys(PrintArtifactRegistry::all());
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $papers = [];
        foreach (PrintArtifactRegistry::all() as $key => $meta) {
            $papers[$key] = $meta['defaultPaper'];
        }

        return [
            'defaultPapers' => $papers,
            'a4MarginPreset' => 'standard',
            'thermalSafeWidthMm' => 72.0,
            'thermalFeedMm' => 8.0,
            'thermalFontScale' => 1.0,
            'showLogo' => true,
            'showBarcode' => true,
            'showSignature' => true,
            'currencyStyle' => 'symbol',
            'dateFormat' => 'd M Y, h:i A',
            'invoiceFooterText' => '',
            'receiptFooterText' => '',
            'markReprints' => true,
            'calibration' => [],
        ];
    }

    /** @return array<string, mixed> */
    public static function for(int $businessId): array
    {
        if (isset(self::$cache[$businessId])) {
            return self::$cache[$businessId];
        }

        $blob = Setting::where('business', $businessId)->where('key', self::KEY)->value('value');
        $stored = $blob ? json_decode((string) $blob, true) : [];

        return self::$cache[$businessId] = self::normalize(is_array($stored) ? $stored : []);
    }

    /**
     * Clamp/whitelist an incoming blob. Unknown keys are dropped (this is a
     * tenant-facing API — nothing technical or arbitrary reaches a document),
     * numbers are bounded, and the result is always complete.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $defaults = self::defaults();
        $out = $defaults;

        $papers = is_array($input['defaultPapers'] ?? null) ? $input['defaultPapers'] : [];
        foreach ($defaults['defaultPapers'] as $artifact => $fallback) {
            $candidate = (string) ($papers[$artifact] ?? $fallback);
            $out['defaultPapers'][$artifact] = PrintArtifactRegistry::allowsPaper($artifact, $candidate)
                ? $candidate
                : $fallback;
        }

        $presets = PrintTokens::marginPresetNames();
        $out['a4MarginPreset'] = in_array((string) ($input['a4MarginPreset'] ?? ''), $presets, true)
            ? (string) $input['a4MarginPreset']
            : $defaults['a4MarginPreset'];

        $out['thermalSafeWidthMm'] = round(self::clamp($input['thermalSafeWidthMm'] ?? null, 48, 80, 72), 2);
        $out['thermalFeedMm'] = round(self::clamp($input['thermalFeedMm'] ?? null, 0, 30, 8), 2);
        $out['thermalFontScale'] = round(self::clamp($input['thermalFontScale'] ?? null, 0.85, 1.25, 1.0), 3);

        foreach (['showLogo', 'showBarcode', 'showSignature', 'markReprints'] as $flag) {
            $out[$flag] = (bool) ($input[$flag] ?? $defaults[$flag]);
        }

        $out['currencyStyle'] = array_key_exists((string) ($input['currencyStyle'] ?? ''), PrintFormat::CURRENCY_STYLES)
            ? (string) $input['currencyStyle']
            : $defaults['currencyStyle'];

        $out['dateFormat'] = in_array((string) ($input['dateFormat'] ?? ''), PrintFormat::DATE_FORMATS, true)
            ? (string) $input['dateFormat']
            : $defaults['dateFormat'];

        $out['invoiceFooterText'] = self::plainText($input['invoiceFooterText'] ?? '');
        $out['receiptFooterText'] = self::plainText($input['receiptFooterText'] ?? '');
        $out['calibration'] = self::normalizeCalibration($input['calibration'] ?? []);

        return $out;
    }

    /** @param array<string, mixed> $input */
    public static function save(int $businessId, array $input): array
    {
        $normalized = self::normalize($input);

        Setting::updateOrCreate(
            ['key' => self::KEY, 'business' => $businessId],
            ['value' => json_encode($normalized), 'created_by' => Auth::id()],
        );

        self::$cache[$businessId] = $normalized;

        return $normalized;
    }

    public static function forget(int $businessId): void
    {
        unset(self::$cache[$businessId]);
    }

    /**
     * Per-device thermal dimensions. A workstation submits only its own entry,
     * so one counter cannot overwrite another's calibration.
     *
     * @param  array<string, mixed>  $entries
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeCalibration(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $clean = [];
        foreach ($entries as $deviceId => $entry) {
            $deviceId = substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) $deviceId) ?? '', 0, 64);
            if ($deviceId === '' || ! is_array($entry)) {
                continue;
            }

            $clean[$deviceId] = [
                'label' => substr(self::plainText($entry['label'] ?? ''), 0, 60),
                'safeWidthMm' => round(self::clamp($entry['safeWidthMm'] ?? null, 48, 80, 72), 2),
                'feedMm' => round(self::clamp($entry['feedMm'] ?? null, 0, 30, 8), 2),
                'fontScale' => round(self::clamp($entry['fontScale'] ?? null, 0.85, 1.25, 1.0), 3),
                'updatedAt' => substr(self::plainText($entry['updatedAt'] ?? '', date('Y-m-d H:i')), 0, 32),
            ];

            if (count($clean) >= self::MAX_CALIBRATED_DEVICES) {
                break;
            }
        }

        return $clean;
    }

    public static function device(int $businessId, ?string $deviceId): ?array
    {
        if ($deviceId === null || $deviceId === '') {
            return null;
        }

        $settings = self::for($businessId);

        return $settings['calibration'][$deviceId] ?? null;
    }

    /**
     * Paper profile for one artifact.
     *
     * Semantics decide the paper: a radiology report is never printed on a
     * receipt roll by accident, and a receipt is never forced onto A4. An
     * explicit request wins only when the artifact actually allows that paper.
     */
    public static function profile(int $businessId, string $artifact, ?string $paper = null, ?string $deviceId = null): PaperProfile
    {
        $settings = self::for($businessId);

        $resolved = $paper !== null && PrintArtifactRegistry::allowsPaper($artifact, $paper)
            ? $paper
            : (string) ($settings['defaultPapers'][$artifact] ?? PrintArtifactRegistry::for($artifact)['defaultPaper']);

        $overrides = ['marginPreset' => $settings['a4MarginPreset']];

        if ($resolved === PaperProfile::THERMAL80) {
            $device = self::device($businessId, $deviceId);
            $overrides['safeWidthMm'] = $device['safeWidthMm'] ?? $settings['thermalSafeWidthMm'];
            $overrides['feedMm'] = $device['feedMm'] ?? $settings['thermalFeedMm'];
            $overrides['fontScale'] = $device['fontScale'] ?? $settings['thermalFontScale'];
        }

        return new PaperProfile($resolved, $overrides);
    }

    public static function dateFormat(int $businessId): string
    {
        return (string) self::for($businessId)['dateFormat'];
    }

    public static function currencyPrefix(int $businessId, ?string $tenantSymbol = null): string
    {
        $settings = self::for($businessId);

        return PrintFormat::currencyPrefix((string) $settings['currencyStyle'], $tenantSymbol);
    }

    /** Footer text, falling back to the tenant's configured disclaimer. */
    public static function footerText(int $businessId, string $artifact, string $fallback = ''): string
    {
        $settings = self::for($businessId);
        $key = match ($artifact) {
            'invoice' => 'invoiceFooterText',
            'receipt', 'token' => 'receiptFooterText',
            default => null,
        };

        $text = $key !== null ? (string) $settings[$key] : '';

        return $text !== '' ? $text : $fallback;
    }

    private static function clamp(mixed $value, float $min, float $max, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($min, min($max, (float) $value));
    }

    /** Tenant-authored footer text: plain text only, never markup. */
    private static function plainText(mixed $value): string
    {
        $text = strip_tags((string) $value);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';

        return trim($text);
    }
}
