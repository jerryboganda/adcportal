<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;

/**
 * The day's cash ledger.
 *
 * Extracted from BillingController so the shift-closing DOCUMENT and the
 * billing screen read the same arithmetic. A printed reconciliation that
 * computes its own totals from a second query is a reconciliation that can
 * disagree with the register it is supposed to certify — the previous
 * client-side statement was exactly that, and it also drifted from the screen
 * it was printed from.
 */
final class ShiftLedgerService
{
    /**
     * @return array{
     *     date: string,
     *     totalCollected: float,
     *     refundedTotal: float,
     *     invoiceCount: int,
     *     paymentCount: int,
     *     byMethod: list<array{method: string, total: float, count: int}>
     * }
     */
    public function forDay(int $businessId, ?string $date = null): array
    {
        $day = $date ?: now()->toDateString();

        $payments = InvoicePayment::query()
            ->where('business_id', $businessId)
            ->whereDate('paid_at', $day)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', Invoice::STATUS_VOID))
            ->with('invoice:id,invoice_number,appointment_id')
            ->orderBy('paid_at')
            ->get();

        $byMethod = $payments->groupBy('method')
            ->map(fn ($group) => [
                'method' => (string) $group->first()->method,
                'total' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        return [
            'date' => $day,
            'totalCollected' => round((float) $payments->sum('amount'), 2),
            'refundedTotal' => round((float) $payments->filter(fn ($p) => (float) $p->amount < 0)->sum('amount'), 2),
            'invoiceCount' => $payments->filter(fn ($p) => (float) $p->amount > 0)->unique('invoice_id')->count(),
            'paymentCount' => $payments->filter(fn ($p) => (float) $p->amount > 0)->count(),
            'byMethod' => $byMethod,
        ];
    }

    /**
     * Cash actually expected in the drawer for the shift (negative rows included).
     *
     * Resolved from the tenant's `kind`, not from the literal string 'cash'. This
     * figure goes on a shift-closing statement an operator signs, so a tenant that
     * coded its drawer method `currency` or `notes` was getting 0.00 — the same
     * class of error the SPA had with its change calculator.
     */
    public function cashExpected(array $ledger, int $businessId): float
    {
        $cashCode = PaymentMethod::cashCodeFor($businessId);

        if ($cashCode === null) {
            return 0.0; // a legitimately cashless clinic
        }

        foreach ($ledger['byMethod'] as $row) {
            if (strcasecmp((string) $row['method'], $cashCode) === 0) {
                return round((float) $row['total'], 2);
            }
        }

        return 0.0;
    }
}
