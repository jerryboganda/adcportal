<?php

namespace App\Support\Print;

/**
 * Code 128-B barcode, rendered to vector SVG.
 *
 * ONE encoder for every renderer. The browser used to draw barcodes with
 * jsbarcode while the server-side PDF had none at all, so a printed invoice and
 * its PDF were not the same document. The server now produces the symbol once
 * and everybody — preview, browser print, DomPDF, headless Chromium — draws
 * that same geometry. `tests/print/barcode.spec.js` decodes a real scanner
 * library's output and compares it against this encoder, because a wrong
 * pattern table would otherwise fail silently and unscannably.
 *
 * Code 128-B covers the printable ASCII range, which is everything the platform
 * encodes today: invoice numbers, MRNs, tokens, accession identifiers.
 *
 * The symbol carries no text: the human-readable value is document markup
 * (`.pd-code-value`) so both engines set it in the document's own font instead
 * of depending on SVG text support, which DomPDF's SVG renderer does not have.
 */
final class Code128
{
    /**
     * Element widths (bar,space,bar,space,bar,space) per code word, in modules.
     * Code 0–102 are data, 103–105 the start characters, 106 the stop pattern
     * (which is 13 modules wide — its 7 elements are asserted in the unit test).
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public const START_B = 104;
    public const STOP = 106;

    /** @return list<string> */
    public static function patterns(): array
    {
        return self::PATTERNS;
    }

    /** Values Code 128-B can represent (printable ASCII). */
    public static function sanitize(string $value): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }

    /** @return list<int> start + data code words */
    public static function codeWords(string $value): array
    {
        $value = self::sanitize($value);
        $codes = [self::START_B];

        foreach (str_split($value) as $char) {
            $codes[] = ord($char) - 32;
        }

        return $codes;
    }

    public static function checksum(array $codes): int
    {
        $sum = $codes[0];
        $count = count($codes);

        for ($i = 1; $i < $count; $i++) {
            $sum += $codes[$i] * $i;
        }

        return $sum % 103;
    }

    /**
     * The module string: '1' for a dark module, '0' for a light one, including
     * the quiet zones (a symbol printed against a paper edge or a neighbouring
     * rule is a symbol that fails to scan).
     */
    public static function modules(string $value, int $quietModules = 10): string
    {
        $value = self::sanitize($value);
        if ($value === '') {
            return '';
        }

        $codes = self::codeWords($value);
        $codes[] = self::checksum($codes);
        $codes[] = self::STOP;

        $symbol = '';
        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code] ?? '';
            $isBar = true;
            foreach (str_split($pattern) as $width) {
                $symbol .= str_repeat($isBar ? '1' : '0', (int) $width);
                $isBar = ! $isBar;
            }
        }

        $quiet = str_repeat('0', max(0, $quietModules));

        return $quiet.$symbol.$quiet;
    }

    /**
     * Vector SVG at physical size.
     *
     * Widths/heights are emitted in mm so the symbol prints at the size the
     * design system asked for instead of being scaled from a bitmap (the classic
     * cause of a barcode that scans on screen and not on paper).
     */
    public static function svg(
        string $value,
        float $moduleMm = 0.25,
        float $barHeightMm = 8.0,
        int $quietModules = 10,
        float $maxWidthMm = 60.0,
    ): string {
        $modules = self::modules($value, $quietModules);
        if ($modules === '') {
            return '';
        }

        $count = strlen($modules);

        // Keep the symbol inside the printable width: a barcode wider than the
        // receipt is clipped paper, not a barcode.
        $moduleMm = min($moduleMm, round($maxWidthMm / max(1, $count), 4));
        $moduleMm = max(0.12, $moduleMm);

        $totalWidthMm = round($moduleMm * $count, 3);
        $barHeightMm = max(3.0, $barHeightMm);

        $rects = [];
        $runStart = null;
        for ($i = 0; $i < $count; $i++) {
            if ($modules[$i] === '1') {
                $runStart ??= $i;
                continue;
            }
            if ($runStart !== null) {
                $rects[] = sprintf(
                    '<rect x="%d" y="0" width="%d" height="100" fill="#000000"/>',
                    $runStart,
                    $i - $runStart
                );
                $runStart = null;
            }
        }
        if ($runStart !== null) {
            $rects[] = sprintf(
                '<rect x="%d" y="0" width="%d" height="100" fill="#000000"/>',
                $runStart,
                $count - $runStart
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barcode %s" '
            .'width="%smm" height="%smm" viewBox="0 0 %d 100" preserveAspectRatio="none" shape-rendering="crispEdges">'
            .'<rect x="0" y="0" width="%d" height="100" fill="#ffffff"/>%s</svg>',
            htmlspecialchars(self::sanitize($value), ENT_QUOTES),
            PrintTokens::num($totalWidthMm, 3),
            PrintTokens::num($barHeightMm, 2),
            $count,
            $count,
            implode('', $rects)
        );
    }

    /** Same symbol as an inline SVG data URI, for engines that only take images. */
    public static function dataUri(string $value, float $moduleMm = 0.25, float $barHeightMm = 8.0, int $quietModules = 10, float $maxWidthMm = 60.0): string
    {
        $svg = self::svg($value, $moduleMm, $barHeightMm, $quietModules, $maxWidthMm);

        return $svg === '' ? '' : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
