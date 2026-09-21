<?php

namespace Tests\Unit;

use App\Support\Print\Code128;
use App\Support\Print\PaperProfile;
use App\Support\Print\PrintFormat;
use App\Support\Print\PrintTokens;
use Tests\TestCase;

/**
 * The design system's arithmetic.
 *
 * These are the numbers every renderer trusts, so they are asserted directly
 * rather than being rediscovered through a rendered document: a paper width, a
 * clamp, a currency string and a barcode pattern table are all things that fail
 * silently ("the receipt is just a bit narrow today") and expensively (a barcode
 * that will not scan at the desk).
 */
class PrintDesignSystemTest extends TestCase
{
    public function test_every_paper_profile_has_complete_geometry(): void
    {
        foreach (PaperProfile::papers() as $paper) {
            $profile = new PaperProfile($paper);
            $render = $profile->toArray();

            $this->assertGreaterThan(0, $render['widthMm'], "{$paper} has no width");
            $this->assertGreaterThan(0, $render['safeWidthMm'], "{$paper} has no safe width");
            $this->assertLessThanOrEqual(
                $render['widthMm'] + 0.001,
                $render['safeWidthMm'],
                "{$paper} claims a printable area wider than the paper"
            );
            $this->assertGreaterThan(
                10,
                $render['contentWidthMm'],
                "{$paper} has no room for content"
            );
            $this->assertStringContainsString('@page', $profile->pageRule());
        }

        // A4 is a page, a receipt roll grows with its content.
        $this->assertSame(297.0, (new PaperProfile(PaperProfile::A4))->heightMm());
        $this->assertNull((new PaperProfile(PaperProfile::THERMAL80))->heightMm());
    }

    public function test_thermal_calibration_is_clamped_to_the_physical_paper(): void
    {
        $tooWide = new PaperProfile(PaperProfile::THERMAL80, ['safeWidthMm' => 500, 'feedMm' => 900]);
        $this->assertSame(80.0, $tooWide->safeWidthMm(), 'A printable width can never exceed the paper.');
        $this->assertSame(30.0, $tooWide->feedMm());

        $tooNarrow = new PaperProfile(PaperProfile::THERMAL80, ['safeWidthMm' => 1, 'feedMm' => -5]);
        $this->assertSame(48.0, $tooNarrow->safeWidthMm());
        $this->assertSame(0.0, $tooNarrow->feedMm());

        // A calibrated narrow printer must actually narrow the printable body.
        $calibrated = new PaperProfile(PaperProfile::THERMAL80, ['safeWidthMm' => 58]);
        $this->assertSame(58.0, $calibrated->safeWidthMm());
        $this->assertStringContainsString('58mm', PrintTokens::css(PaperProfile::THERMAL80, 1.0, 58.0));
    }

    public function test_the_thermal_page_rule_is_roll_paper_not_a_sheet(): void
    {
        $rule = (new PaperProfile(PaperProfile::THERMAL80))->pageRule();

        $this->assertStringContainsString('80mm', $rule);
        $this->assertStringContainsString('auto', $rule, 'A receipt page must grow with its content.');
        $this->assertStringNotContainsString('A4', $rule);

        // The PDF engine needs a concrete box; roll paper is estimated from the
        // document's own line count plus the configured feed.
        $box = (new PaperProfile(PaperProfile::THERMAL80, ['feedMm' => 8]))->dompdfPageBox(40);
        $this->assertEqualsWithDelta(226.77, $box[2], 0.5, '80 mm in points');
        $this->assertGreaterThan(200, $box[3]);
    }

    public function test_design_tokens_cover_both_paper_families(): void
    {
        foreach ([PaperProfile::A4, PaperProfile::THERMAL80, PaperProfile::LABEL] as $paper) {
            $type = PrintTokens::typeScale($paper);

            $this->assertNotEmpty($type['fontFamily'] ?? null, "{$paper} has no font family");
            foreach (['xs', 'sm', 'md', 'lg', 'xl', 'total'] as $token) {
                $this->assertArrayHasKey($token, $type['fontSizePt'], "{$paper} is missing font token {$token}");
            }
            $this->assertNotEmpty($type['color']['ink'] ?? null);

            $css = PrintTokens::css($paper);

            $this->assertStringContainsString('.pd-doc', $css);
            $this->assertStringContainsString('font-size:', $css);
            // Physical units only: a print layout that reacts to a viewport is a
            // print layout that changes shape on a different monitor.
            $this->assertDoesNotMatchRegularExpression('/\d(vw|vh|dvh)\b/', $css);
        }

        // Each paper resolves its OWN scale. These two used to be the same: the
        // profile list called the roll `thermal80` while the type block called it
        // `thermal`, so a receipt silently inherited the A4 scale — proportional
        // type and grey ink on a monochrome 203 dpi printer, where every shade
        // becomes a dither pattern.
        $thermal = PrintTokens::css(PaperProfile::THERMAL80);
        $a4 = PrintTokens::css(PaperProfile::A4);

        $this->assertStringContainsString('monospace', $thermal, 'A receipt must be set in a monospace face.');
        $this->assertStringNotContainsString('monospace', $a4);
        $this->assertStringContainsString('color: #000000', $thermal, 'Thermal ink is ink or paper — never a tint.');
        $this->assertStringNotContainsString('color: #0f172a', $thermal, 'A4 ink must not leak into a receipt.');
        $this->assertNotSame(
            PrintTokens::typeScale(PaperProfile::THERMAL80)['fontSizePt'],
            PrintTokens::typeScale(PaperProfile::A4)['fontSizePt'],
            'Every paper profile must resolve its own type scale.'
        );
    }

    public function test_money_and_dates_are_formatted_once_and_deterministically(): void
    {
        $this->assertSame('Rs. 2,000.00', PrintFormat::money(2000));
        $this->assertSame('PKR 2,000.00', PrintFormat::money(2000, 'PKR'));
        $this->assertSame('Rs. 2,000', PrintFormat::moneyCompact(2000));
        $this->assertSame('Rs. 1,999.50', PrintFormat::moneyCompact(1999.5));
        $this->assertSame('-Rs. 500', PrintFormat::moneyDelta(-500));
        $this->assertSame('₨', PrintFormat::currencyPrefix('sign'));
        $this->assertSame('PKR', PrintFormat::currencyPrefix('code'));
        $this->assertSame('Rs.', PrintFormat::currencyPrefix('symbol', 'Rs.'));
        $this->assertSame('SAR', PrintFormat::currencyPrefix('symbol', 'SAR'));

        // A date is rendered in the app timezone, never as a raw UTC string.
        $formatted = PrintFormat::dateTime('2026-09-21 12:00:00');
        $this->assertStringContainsString('2026', $formatted);
        $this->assertStringNotContainsString('00:00:00', $formatted);
    }

    public function test_missing_values_never_print_as_null_or_undefined(): void
    {
        $this->assertSame('—', PrintFormat::text(null));
        $this->assertSame('—', PrintFormat::text('   '));
        $this->assertSame('Self / Walk-in', PrintFormat::text('', 'Self / Walk-in'));
        $this->assertSame('—', PrintFormat::dateTime(null));
        $this->assertSame('—', PrintFormat::text(null, '—'));
        $this->assertSame('0', PrintFormat::quantity(null));
        $this->assertSame('Rs. 0.00', PrintFormat::money(null));
    }

    public function test_clinical_narrative_is_preserved_verbatim(): void
    {
        $text = "Findings:\r\n- 8 mm nodule in the right upper lobe.\r\n- No pleural effusion.\r\n\r\nImpression: 8 mm nodule; recommend follow-up CT in 3 months.";

        $clean = PrintFormat::narrative($text);

        $this->assertStringContainsString("- 8 mm nodule in the right upper lobe.", $clean);
        $this->assertStringContainsString('recommend follow-up CT in 3 months.', $clean);
        $this->assertStringNotContainsString("\r", $clean, 'Windows line endings are normalised, content is not.');
        $this->assertStringContainsString("\n\n", $clean, 'Paragraph structure survives printing.');
    }

    public function test_code128_symbol_geometry_is_valid_and_self_decoding(): void
    {
        $patterns = Code128::patterns();
        $this->assertCount(107, $patterns);

        // Every code word is 11 modules; the stop pattern is 13. A mistyped
        // pattern would otherwise print an unscannable barcode and look fine.
        foreach ($patterns as $code => $pattern) {
            $width = array_sum(array_map('intval', str_split($pattern)));
            $expected = $code === Code128::STOP ? 13 : 11;

            $this->assertSame($expected, $width, "Code {$code} pattern [{$pattern}] is {$width} modules, expected {$expected}");
            $this->assertSame(
                $code === Code128::STOP ? 7 : 6,
                strlen($pattern),
                "Code {$code} must have {$expected} element widths"
            );
        }

        // Round-trip: encode, then decode with an independent implementation of
        // the same published table to prove the output really carries the value.
        foreach (['INV-2026-00001', 'MRN-7Q4K2', '#1042', '1234567890'] as $value) {
            $modules = Code128::modules($value, 0);
            $this->assertNotSame('', $modules);

            $decoded = $this->decode($modules);

            $this->assertSame(
                Code128::sanitize($value),
                $decoded,
                "The symbol for [{$value}] does not decode back to it."
            );
        }

        // Symbols that cannot be encoded must be refused, not faked.
        $this->assertSame('', Code128::modules("\u{0627}\u{0628}", 0));
        $this->assertSame('', Code128::svg(''));
    }

    public function test_barcode_svg_is_vector_and_physically_sized(): void
    {
        $svg = Code128::svg('INV-2026-00001', 0.25, 8.0, 10, 60.0);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('mm"', $svg, 'The symbol must declare a physical size.');
        $this->assertStringNotContainsString('<image', $svg, 'A barcode must stay vector.');
        $this->assertStringContainsString('<rect', $svg);

        // Quiet zones are part of the standard: a symbol printed without them is
        // a symbol that fails at the desk.
        $withQuiet = Code128::modules('ABC', 10);
        $withoutQuiet = Code128::modules('ABC', 0);
        $this->assertSame(strlen($withoutQuiet) + 20, strlen($withQuiet));

        // A symbol that would exceed the printable width is scaled down instead
        // of overflowing the receipt.
        $narrow = Code128::svg(str_repeat('X', 40), 0.25, 8.0, 10, 60.0);
        $this->assertMatchesRegularExpression('/width="([0-9.]+)mm"/', $narrow);
        preg_match('/width="([0-9.]+)mm"/', $narrow, $matches);
        $this->assertLessThanOrEqual(60.0, (float) $matches[1]);
    }

    /**
     * Decode a module string using only the published table + checksum rule.
     *
     * `Code128::modules()` returns one character per module ('1' dark, '0' light),
     * so a chunk has to be run-length encoded before it can be looked up in the
     * element-width table. This decoder is deliberately independent of the
     * encoder: it is what proves the symbol on the paper really carries the
     * invoice number rather than merely looking like a barcode.
     */
    private function decode(string $modules): string
    {
        $patterns = array_flip(Code128::patterns());
        $value = '';
        $index = 0;
        $codes = [];

        while ($index + 11 <= strlen($modules)) {
            // The stop pattern is 13 modules wide.
            if ($index + 13 <= strlen($modules)) {
                $wide = $this->runLengths(substr($modules, $index, 13));
                if (isset($patterns[$wide]) && $patterns[$wide] === Code128::STOP) {
                    break;
                }
            }

            $chunk = $this->runLengths(substr($modules, $index, 11));

            if (! isset($patterns[$chunk])) {
                break;
            }

            $codes[] = $patterns[$chunk];
            $index += 11;
        }

        $this->assertNotEmpty($codes, 'The symbol contained no decodable code words.');
        $this->assertSame(Code128::START_B, $codes[0]);

        $checksum = array_pop($codes);
        $this->assertSame(
            Code128::checksum($codes),
            $checksum,
            'The check character does not match the data code words.'
        );

        foreach (array_slice($codes, 1) as $code) {
            $value .= chr($code + 32);
        }

        return $value;
    }

    /** Run-length encode modules ('111001' -> '321'), the Code 128 notation. */
    private function runLengths(string $modules): string
    {
        $runs = '';
        $current = $modules[0];
        $length = 0;

        foreach (str_split($modules) as $module) {
            if ($module === $current) {
                $length++;
                continue;
            }

            $runs .= (string) $length;
            $current = $module;
            $length = 1;
        }

        return $runs.(string) $length;
    }
}
