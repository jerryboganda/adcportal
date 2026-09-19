/**
 * Booking financials for the SPA — the ONE place where price, discount,
 * payable and outstanding are derived.
 *
 * This mirrors `App\Support\BookingMoney` on the server, which remains
 * authoritative: the SPA uses it for instant, honest feedback while the
 * receptionist types, and the API recomputes every amount from the tenant's
 * configured price on submit. Money moves in INTEGER MINOR UNITS (paisa) so no
 * floating-point drift can decide what a patient owes; values cross the
 * boundary as numbers.
 *
 *   basePrice       tenant-configured procedure price
 *   discount        fixed-amount discount entered at reception
 *   payable         basePrice - discount          (never below zero)
 *   amountReceived  money actually collected now
 *   outstanding     payable - amountReceived      (never below zero)
 */

export const MINOR_UNITS_PER_UNIT = 100;

/** Canonical wording — mirrors the server's BookingMoney constants. */
export const MONEY_ERRORS = {
  discountExceedsPrice: 'Discount cannot exceed the study price.',
  discountNegative: 'The discount cannot be negative.',
  amountRequired: 'An amount greater than zero is required for this payment status.',
  amountExceedsPayable: 'Payment amount exceeds the final payable amount.',
  amountMustMatchPayable: 'The paid amount must match the final payable amount.',
  nothingToCollect: 'This study is fully discounted; there is nothing to collect.',
  methodRequired: 'Select the payment method used for this collection.',
} as const;

export interface BookingMoneyTotals {
  basePrice: number;
  discount: number;
  payable: number;
  amountReceived: number;
  outstanding: number;
  /** 100% discount: issued, but nothing left to collect. */
  fullyDiscounted: boolean;
}

export type BookingPaymentStatus = 'unpaid' | 'partial' | 'paid';

/** Exact conversion to minor units; null/undefined/NaN mean "no amount". */
export function toMinor(amount: number | string | null | undefined): number {
  const value = typeof amount === 'string' ? Number(amount) : amount;
  const safe = typeof value === 'number' && Number.isFinite(value) ? value : 0;

  return Math.round(safe * MINOR_UNITS_PER_UNIT);
}

/** Back to the display/API representation (2 decimal places). */
export function fromMinor(minor: number): number {
  return Math.round(minor) / MINOR_UNITS_PER_UNIT;
}

export function formatMoney(amount: number | string | null | undefined): string {
  return fromMinor(toMinor(amount)).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

/**
 * Derive every financial figure from the canonical inputs. `amountReceived`
 * is clamped for display only — validation (not silent clamping) is what
 * rejects an invalid amount before submit.
 */
export function computeBookingTotals(
  basePrice: number | string | null | undefined,
  discount: number | string | null | undefined,
  amountReceived: number | string | null | undefined = 0,
): BookingMoneyTotals {
  const baseMinor = Math.max(0, toMinor(basePrice));
  const discountMinor = Math.max(0, toMinor(discount));
  const receivedMinor = Math.max(0, toMinor(amountReceived));
  const payableMinor = Math.max(0, baseMinor - discountMinor);

  return {
    basePrice: fromMinor(baseMinor),
    discount: fromMinor(discountMinor),
    payable: fromMinor(payableMinor),
    amountReceived: fromMinor(receivedMinor),
    outstanding: fromMinor(Math.max(0, payableMinor - receivedMinor)),
    // A TRUE 100% discount (discount === price). An over-discount also floors
    // the payable at zero, but it is invalid — never presented as "waived".
    fullyDiscounted: baseMinor > 0 && discountMinor === baseMinor,
  };
}

/** Null when acceptable, otherwise the user-facing reason. */
export function validateDiscount(
  basePrice: number | string | null | undefined,
  discount: number | string | null | undefined,
): string | null {
  if (toMinor(basePrice) < 0) return 'The study price cannot be negative.';
  if (toMinor(discount) < 0) return MONEY_ERRORS.discountNegative;
  if (toMinor(discount) > toMinor(basePrice)) return MONEY_ERRORS.discountExceedsPrice;

  return null;
}

/**
 * Validate what is about to be collected. Mirrors the server's
 * settleBookingPayment rules so the receptionist is never told "server error"
 * for something the form could have caught.
 */
export function validateSettlement(
  totals: BookingMoneyTotals,
  status: BookingPaymentStatus,
  amountReceived: number | string | null | undefined,
  hasPaymentMethod: boolean,
): string | null {
  if (totals.fullyDiscounted) {
    // Settled by the discount itself; collecting anything is an error.
    if (toMinor(amountReceived) > 0) return MONEY_ERRORS.nothingToCollect;
    return null;
  }

  if (status === 'unpaid') return null;

  const payableMinor = toMinor(totals.payable);
  const receivedMinor = toMinor(amountReceived);

  if (receivedMinor <= 0) return MONEY_ERRORS.amountRequired;
  if (!hasPaymentMethod) return MONEY_ERRORS.methodRequired;

  if (status === 'partial') {
    if (receivedMinor >= payableMinor) {
      return `A partial payment must be less than the payable amount (${formatMoney(totals.payable)}).`;
    }
    return null;
  }

  if (receivedMinor > payableMinor) return MONEY_ERRORS.amountExceedsPayable;
  if (receivedMinor !== payableMinor) return MONEY_ERRORS.amountMustMatchPayable;

  return null;
}

/**
 * The amount actually collected for a given status: `paid` always means the
 * full final payable (receptionists should never have to retype it), while
 * `partial` uses what was entered.
 */
export function amountReceivedFor(
  status: BookingPaymentStatus,
  totals: BookingMoneyTotals,
  enteredAmount: number | string | null | undefined,
): number {
  if (status === 'paid') return totals.payable;
  if (status === 'unpaid') return 0;

  return fromMinor(Math.max(0, toMinor(enteredAmount)));
}

export function paymentStatusLabel(status: BookingPaymentStatus, totals: BookingMoneyTotals): string {
  if (totals.fullyDiscounted) return 'Fully discounted';
  if (status === 'paid') return 'Paid';
  if (status === 'partial') return 'Partially paid';

  return 'Unpaid';
}
