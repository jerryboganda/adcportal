<?php

namespace App\Support\Print;

use InvalidArgumentException;

/**
 * A paper profile: the physical geometry a document is laid out against.
 *
 * A4 and 80 mm thermal are DIFFERENT PHYSICAL DESIGNS, not one design at two
 * scales. This object is the only place that knows the difference, so a
 * document component never invents its own width.
 *
 * The `@page` rule and the preview "paper box" are generated from the SAME
 * margins, which is what makes the on-screen preview and the printed page share
 * one content width — the single most common cause of preview/print drift is
 * that the preview's padding and the printer's margins are two different
 * numbers owned by two different files.
 */
final class PaperProfile
{
    public const A4 = 'a4';
    public const THERMAL80 = 'thermal80';
    public const LABEL = 'label';

    /** 1 mm in PostScript points (DomPDF/Chromium page boxes are in pt). */
    private const MM_TO_PT = 2.8346456693;

    /**
     * @param  array{safeWidthMm?: float, feedMm?: float, margins?: array<string, float>, marginPreset?: string, fontScale?: float}  $overrides
     */
    public function __construct(
        private readonly string $paper,
        private readonly array $overrides = [],
    ) {
        if (! self::isPaper($paper)) {
            throw new InvalidArgumentException("Unknown paper profile [{$paper}].");
        }
    }

    public static function isPaper(string $paper): bool
    {
        return in_array($paper, [self::A4, self::THERMAL80, self::LABEL], true);
    }

    /** @return list<string> */
    public static function papers(): array
    {
        return [self::A4, self::THERMAL80, self::LABEL];
    }

    public function paper(): string
    {
        return $this->paper;
    }

    public function label(): string
    {
        return (string) (PrintTokens::profile($this->paper)['label'] ?? $this->paper);
    }

    public function widthMm(): float
    {
        return (float) (PrintTokens::profile($this->paper)['paperWidthMm'] ?? 210);
    }

    /** Null means "the page grows with the content" (thermal roll). */
    public function heightMm(): ?float
    {
        $height = PrintTokens::profile($this->paper)['paperHeightMm'] ?? null;

        return $height === null ? null : (float) $height;
    }

    /**
     * Printable width: the physical paper minus the printer's non-printable
     * edges. Never assume an 80 mm printer exposes all 80 mm — most print
     * ~72 mm, some as little as 48 mm, and the value is a per-workstation
     * calibration, not a constant.
     */
    public function safeWidthMm(): float
    {
        $profile = PrintTokens::profile($this->paper);
        $value = $this->overrides['safeWidthMm']
            ?? $profile['safeWidthMm']
            ?? $profile['paperWidthMm']
            ?? 210;

        $min = (float) ($profile['minSafeWidthMm'] ?? 20);
        $max = min(
            (float) ($profile['maxSafeWidthMm'] ?? $profile['paperWidthMm'] ?? 210),
            (float) ($profile['paperWidthMm'] ?? 210)
        );

        return round(max($min, min($max, (float) $value)), 2);
    }

    /** Bottom feed before the cutter fires — configurable, never hardcoded. */
    public function feedMm(): float
    {
        $profile = PrintTokens::profile($this->paper);
        $value = $this->overrides['feedMm'] ?? $profile['feedMm'] ?? 0;

        return round(max(0.0, min((float) ($profile['maxFeedMm'] ?? 30), (float) $value)), 2);
    }

    public function fontScale(): float
    {
        return round(max(0.7, min(1.4, (float) ($this->overrides['fontScale'] ?? 1.0))), 3);
    }

    /** @return array{top: float, right: float, bottom: float, left: float} */
    public function margins(): array
    {
        if (isset($this->overrides['margins'])) {
            $preset = $this->overrides['margins'];

            return [
                'top' => (float) ($preset['top'] ?? 0),
                'right' => (float) ($preset['right'] ?? 0),
                'bottom' => (float) ($preset['bottom'] ?? 0),
                'left' => (float) ($preset['left'] ?? 0),
            ];
        }

        return PrintTokens::marginPreset($this->paper, (string) ($this->overrides['marginPreset'] ?? 'standard'));
    }

    /**
     * The width text actually occupies. For thermal roll paper this is the
     * calibrated safe width minus the horizontal margins — the number every
     * "does it wrap?" decision is made against.
     */
    public function contentWidthMm(): float
    {
        $margins = $this->margins();

        return round(max(10.0, $this->safeWidthMm() - $margins['left'] - $margins['right']), 2);
    }

    /**
     * The print-time page rule.
     *
     * A4 takes its margins from `@page` so the browser paginates natively (a
     * fixed-height preview box cannot break across pages). Thermal/label take
     * zero page margin and constrain the printable body instead, because roll
     * paper has no universal page size and the safe width is calibrated.
     */
    public function pageRule(): string
    {
        if ($this->paper === self::A4) {
            $m = $this->margins();

            return sprintf(
                '@page { size: A4 portrait; margin: %smm %smm %smm %smm; }',
                PrintTokens::num($m['top'], 2),
                PrintTokens::num($m['right'], 2),
                PrintTokens::num($m['bottom'], 2),
                PrintTokens::num($m['left'], 2),
            );
        }

        $height = $this->heightMm();

        return sprintf(
            '@page { size: %smm %s; margin: 0; }',
            PrintTokens::num($this->widthMm(), 2),
            $height === null ? 'auto' : PrintTokens::num($height, 2).'mm'
        );
    }

    /**
     * DomPDF cannot use `auto` height, so a roll document is given a page tall
     * enough for its content (estimated from the model's own line count) plus
     * the configured feed. This is why the thermal PDF is narrow instead of
     * being forced onto A4.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [x0, y0, x1, y1] in pt
     */
    public function dompdfPageBox(int $estimatedLines = 0): array
    {
        $height = $this->heightMm()
            ?? max(40.0, $estimatedLines * $this->lineHeightMm() + $this->feedMm() + 6);

        return [0.0, 0.0, $this->widthMm() * self::MM_TO_PT, $height * self::MM_TO_PT];
    }

    public function lineHeightMm(): float
    {
        $type = PrintTokens::typeScale($this->paper);
        $fontPt = (float) ($type['fontSizePt']['md'] ?? 9) * $this->fontScale();

        return round(($fontPt * 0.3528) * (float) ($type['lineHeight'] ?? 1.4), 3);
    }

    /** Chromium's PDF printer takes explicit CSS lengths, not a page box. */
    public function chromiumSize(): array
    {
        return ['widthMm' => $this->widthMm(), 'heightMm' => $this->heightMm()];
    }

    /**
     * The numbers the SPA needs to render the SAME paper on screen.
     * Presentation-only: the preview may scale down to fit the viewport, but the
     * document geometry stays physical.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'paper' => $this->paper,
            'label' => $this->label(),
            'widthMm' => $this->widthMm(),
            'heightMm' => $this->heightMm(),
            'safeWidthMm' => $this->safeWidthMm(),
            'contentWidthMm' => $this->contentWidthMm(),
            'feedMm' => $this->feedMm(),
            'fontScale' => $this->fontScale(),
            'margins' => $this->margins(),
        ];
    }
}
