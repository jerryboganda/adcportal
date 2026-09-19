<?php

namespace Tests\Unit;

use App\Support\BookingMoney;
use Tests\TestCase;

/**
 * The booking financial formula, isolated from HTTP and the database:
 *
 *   payable = basePrice − discount      (never below zero)
 *   outstanding = payable − received    (never below zero)
 *
 * Money is computed in integer minor units, so no binary-float artefact can
 * decide what a patient owes.
 */
class BookingMoneyTest extends TestCase
{
    public function test_payable_subtracts_discount_from_price(): void
    {
        $this->assertSame(6500.0, BookingMoney::payable(6500, 0));
        $this->assertSame(3000.0, BookingMoney::payable(6500, 3500));
        // Same discount, different procedure: the price drives the payable.
        $this->assertSame(4500.0, BookingMoney::payable(8000, 3500));
    }

    public function test_full_discount_yields_zero_payable(): void
    {
        $this->assertSame(0.0, BookingMoney::payable(6500, 6500));
    }

    public function test_discount_above_price_is_never_negative(): void
    {
        // Defensive floor: the request layer REJECTS this (see
        // validateDiscount), the math itself must still never go negative.
        $this->assertSame(0.0, BookingMoney::payable(6500, 7000));
    }

    public function test_validate_discount_accepts_valid_amounts(): void
    {
        $this->assertNull(BookingMoney::validateDiscount(6500, 0));
        $this->assertNull(BookingMoney::validateDiscount(6500, 3500));
        $this->assertNull(BookingMoney::validateDiscount(6500, 6500), '100% discount is valid');
    }

    public function test_validate_discount_rejects_exceeding_the_price(): void
    {
        $this->assertSame(
            BookingMoney::ERROR_DISCOUNT_EXCEEDS_PRICE,
            BookingMoney::validateDiscount(6500, 6501),
        );
    }

    public function test_validate_discount_rejects_negative_values(): void
    {
        $this->assertSame(BookingMoney::ERROR_DISCOUNT_NEGATIVE, BookingMoney::validateDiscount(6500, -500));
        $this->assertSame('The study price cannot be negative.', BookingMoney::validateDiscount(-1, 0));
    }

    public function test_outstanding_is_payable_minus_amount_received(): void
    {
        $this->assertSame(3000.0, BookingMoney::outstanding(3000, 0));
        $this->assertSame(2000.0, BookingMoney::outstanding(3000, 1000));
        $this->assertSame(0.0, BookingMoney::outstanding(3000, 3000));
        $this->assertSame(0.0, BookingMoney::outstanding(0, 0));
    }

    public function test_overpayment_is_floored_at_zero_outstanding(): void
    {
        $this->assertSame(0.0, BookingMoney::outstanding(3000, 3500));
    }

    public function test_null_and_missing_values_are_treated_as_zero(): void
    {
        $this->assertSame(6500.0, BookingMoney::payable(6500, null));
        $this->assertSame(0.0, BookingMoney::payable(null, null));
        $this->assertSame(6500.0, BookingMoney::payable('6500.00', ''));
        $this->assertSame(0, BookingMoney::toMinor(null));
        $this->assertSame(0, BookingMoney::toMinor(''));
    }

    public function test_string_payloads_convert_exactly(): void
    {
        // The SPA and JSON both deliver numbers as strings at times.
        $this->assertSame(3000.0, BookingMoney::payable('6500.00', '3500'));
        $this->assertSame(300000, BookingMoney::toMinor('3000.00'));
        $this->assertTrue(BookingMoney::equals('3000', 3000.0));
        $this->assertTrue(BookingMoney::equals('6500.00', '6500'));
        $this->assertFalse(BookingMoney::equals('3000', '3000.01'));
    }

    public function test_minor_units_avoid_floating_point_drift(): void
    {
        // The classic 0.1 + 0.2 trap must not appear in a bill.
        $this->assertSame(0.2, BookingMoney::payable(0.3, 0.1));
        $this->assertSame(0.3, BookingMoney::fromMinor(BookingMoney::toMinor(0.1) + BookingMoney::toMinor(0.2)));
        $this->assertSame(0.29, BookingMoney::fromMinor(BookingMoney::toMinor(0.29)));
        $this->assertSame(6500.0, BookingMoney::payable(6500.29, 0.29));
        $this->assertSame(0.0, BookingMoney::payable(0.29, 0.29));
    }

    public function test_large_amounts_stay_exact(): void
    {
        $this->assertSame(999999999.99, BookingMoney::payable(999999999.99, 0));
        $this->assertSame(999999999.0, BookingMoney::payable(999999999.99, 0.99));
        $this->assertTrue(BookingMoney::equals(999999999.99, '999999999.99'));
    }

    public function test_fractional_paisa_input_rounds_to_the_minor_unit(): void
    {
        // 3500.005 is not representable in paisa; it rounds deterministically
        // instead of leaking a third decimal into the invoice.
        $this->assertSame(3500.01, BookingMoney::fromMinor(BookingMoney::toMinor(3500.005)));
        $this->assertSame(3.33, BookingMoney::fromMinor(BookingMoney::toMinor(3.333)));
    }

    public function test_format_renders_two_decimals(): void
    {
        $this->assertSame('6,500.00', BookingMoney::format(6500));
        $this->assertSame('3,000.00', BookingMoney::format('3000'));
        $this->assertSame('0.00', BookingMoney::format(null));
    }
}
