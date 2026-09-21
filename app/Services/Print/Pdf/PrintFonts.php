<?php

namespace App\Services\Print\Pdf;

use Barryvdh\DomPDF\PDF;

/**
 * Brand fonts for the PDF engines.
 *
 * The browser print path and the PDF path must use the same typeface, or the two
 * documents differ in every line's width. Chromium loads the web font itself;
 * DomPDF can only use a font it has metrics for, so any TTF/OTF a deployment
 * places in `resources/fonts/` is registered here under its file name
 * (`PlusJakartaSans-Bold.ttf` → family "Plus Jakarta Sans", weight bold).
 *
 * Nothing is registered by default: the design system's font stack falls back to
 * DejaVu Sans (which DomPDF ships) so a PDF is never rendered in a font the
 * layout was not measured against — and never as missing glyphs.
 */
final class PrintFonts
{
    /** @return list<string> */
    public static function files(): array
    {
        $found = glob(resource_path('fonts/*.ttf')) ?: [];
        $found = array_merge($found, glob(resource_path('fonts/*.otf')) ?: []);
        sort($found);

        return $found;
    }

    public static function register(PDF $pdf): void
    {
        $files = self::files();
        if ($files === []) {
            return;
        }

        try {
            $metrics = $pdf->getFontMetrics();

            foreach ($files as $file) {
                [$family, $weight, $style] = self::describe(basename($file));
                $metrics->registerFont([
                    'family' => $family,
                    'weight' => $weight,
                    'style' => $style,
                ], $file);
            }
        } catch (\Throwable $e) {
            // A font that cannot be registered must not fail a patient document:
            // the CSS stack falls back to a font DomPDF already knows.
            report($e);
        }
    }

    /** @return array{0: string, 1: string, 2: string} */
    private static function describe(string $filename): array
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $weight = 'normal';
        $style = 'normal';

        foreach (['Bold' => 'bold', 'SemiBold' => '600', 'Medium' => '500', 'Black' => '900', 'ExtraBold' => '800'] as $needle => $value) {
            if (str_contains($stem, $needle)) {
                $weight = $value;
            }
        }
        if (str_contains($stem, 'Italic')) {
            $style = 'italic';
        }

        // "PlusJakartaSans-Bold" → "Plus Jakarta Sans"
        $family = preg_replace('/-(Bold|SemiBold|Medium|Black|ExtraBold|Italic|Regular)$/', '', $stem) ?? $stem;
        $family = trim((string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $family));

        return [$family, $weight, $style];
    }
}
