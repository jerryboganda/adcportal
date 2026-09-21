<?php

namespace App\Support\Print;

/**
 * Print design tokens.
 *
 * The values live in ONE physical file (`resources/print/print-tokens.json`)
 * because preview/print/PDF parity is impossible if two renderers own their own
 * numbers. The SPA imports the JSON directly; the server PDF engines cannot
 * (DomPDF has no support for CSS custom properties), so this class emits
 * *literal* CSS from the same values.
 *
 * Nothing here resolves at request time except the font scale a tenant may
 * apply to their thermal printer — every other number is a constant of the
 * design system.
 */
final class PrintTokens
{
    private const PATH = 'resources/print/print-tokens.json';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = base_path(self::PATH);
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::$cache = is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    public static function profile(string $paper): array
    {
        $profiles = self::all()['profiles'] ?? [];

        return $profiles[$paper] ?? $profiles[PaperProfile::A4] ?? [];
    }

    /**
     * The type scale for a paper profile.
     *
     * The key is the paper name itself (`a4`, `thermal80`, `label`) so the
     * profile block and the type block in the JSON cannot disagree — the names
     * used to differ (`thermal` vs `thermal80` in the profile list), which made
     * a receipt fall back to the A4 scale: proportional type and grey ink on a
     * monochrome 203 dpi printer. `PrintDesignSystemTest` asserts every paper
     * resolves its own scale so a rename cannot reintroduce it.
     *
     * @return array<string, mixed>
     */
    public static function typeScale(string $paper): array
    {
        return self::all()[$paper] ?? self::all()[PaperProfile::A4] ?? [];
    }

    public static function marginPreset(string $paper, string $preset): array
    {
        $presets = self::profile($paper)['marginPresets'] ?? [];
        $preset = $presets[$preset] ?? $presets['standard'] ?? ['top' => 14, 'right' => 14, 'bottom' => 16, 'left' => 14];

        return array_map(static fn ($v) => (float) $v, $preset);
    }

    /** Page geometry presets a tenant may choose between, for the admin UI. */
    public static function marginPresetNames(): array
    {
        return array_keys((array) (self::profile(PaperProfile::A4)['marginPresets'] ?? []));
    }

    /**
     * Literal CSS for one paper profile.
     *
     * Deliberately engine-agnostic CSS 2.1: no custom properties, no flexbox,
     * no grid, no transforms — the same sheet is fed to DomPDF, to headless
     * Chromium and to the browser print path, so a value that one engine
     * ignores would be a silent divergence.
     *
     * `$fontScale` is a per-workstation thermal calibration (a printer may
     * render 8pt slightly wide, and a receipt that wraps mid-amount is worse
     * than a smaller font). It multiplies font sizes only — never widths.
     */
    public static function css(string $paper, float $fontScale = 1.0, ?float $safeWidthMm = null): string
    {
        $scale = max(0.7, min(1.4, $fontScale));
        $profile = self::profile($paper);
        $type = self::typeScale($paper);

        $sizes = [];
        foreach ((array) ($type['fontSizePt'] ?? []) as $key => $pt) {
            $sizes["--font-{$key}"] = round(((float) $pt) * $scale, 3).'pt';
        }

        $colors = (array) ($type['color'] ?? []) + [
            'ink' => '#000000', 'body' => '#000000', 'muted' => '#444444',
            'rule' => '#000000', 'ruleStrong' => '#000000', 'accent' => '#000000',
            'critical' => '#000000', 'success' => '#000000', 'warn' => '#000000', 'tint' => '#ffffff',
        ];

        /*
         * Every selector is namespaced `pd-` (print document). The documents
         * carry NO Tailwind utility classes anywhere: app CSS (including its
         * responsive `sm:`/`md:` variants) must not be able to change printed
         * geometry. `src/print/tokens.ts` emits byte-identical rule names for
         * the browser renderer.
         */
        $lines = [];
        $lines[] = '/* generated from resources/print/print-tokens.json — do not edit */';
        foreach ($sizes as $name => $value) {
            $key = str_replace('--font-', '', $name);
            $lines[] = ".pd-f-{$key} { font-size: {$value}; }";
        }
        foreach ($colors as $name => $value) {
            $lines[] = ".pd-c-{$name} { color: {$value}; }";
        }
        $lines[] = sprintf(
            '.pd-doc { font-family: %s; font-size: %s; line-height: %s; color: %s; }',
            (string) ($type['fontFamily'] ?? 'sans-serif'),
            $sizes['--font-md'] ?? '9pt',
            (string) ($type['lineHeight'] ?? 1.4),
            $colors['ink']
        );
        $lines[] = sprintf('.pd-rule { border-top: %smm solid %s; }', self::num($type['ruleMm'] ?? 0.3, 2), $colors['rule']);
        $lines[] = sprintf('.pd-rule-strong { border-top: %smm solid %s; }', self::num($type['strongRuleMm'] ?? 0.7, 2), $colors['ruleStrong']);
        $lines[] = sprintf('.pd-block { margin-top: %smm; }', self::num($type['blockGapMm'] ?? 3, 2));
        $lines[] = sprintf('.pd-section { margin-top: %smm; }', self::num($type['sectionGapMm'] ?? 4, 2));
        $lines[] = sprintf('.pd-cell { padding: %smm 1.2mm; }', self::num($type['cellPadMm'] ?? 1.2, 2));
        $lines[] = '.pd-doc p, .pd-doc table, .pd-doc h1, .pd-doc h2, .pd-doc h3, .pd-doc ul, .pd-doc ol { margin: 0; }';
        $lines[] = '.pd-doc table { border-collapse: collapse; width: 100%; }';
        $lines[] = '.pd-doc img { max-width: 100%; }';
        $lines[] = '.pd-doc *, .pd-doc { box-sizing: border-box; }';

        // The thermal/label printable width is a PHYSICAL constraint, in mm, so
        // no viewport size can reflow (or clip) the receipt.
        if ($paper !== PaperProfile::A4) {
            $width = $safeWidthMm ?? (float) ($profile['safeWidthMm'] ?? 72);
            $lines[] = sprintf('.pd-body { width: %smm; }', self::num($width, 2));
        }

        return implode("\n", $lines);
    }

    /** Public so the SPA-side generator can be asserted against it in tests. */
    public static function num(mixed $value, int $decimals): string
    {
        return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
    }
}
