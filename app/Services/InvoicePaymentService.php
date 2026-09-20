<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;

/**
 * Single recording path for invoice payments — booking-time settlement
 * (StudyController) and POS collection (BillingController) both flow through
 * here so money math and payment rows can never diverge. Callers own the
 * balance-due check, the row lock and the surrounding transaction.
 *
 * Refunds are recorded through the SAME path as NEGATIVE amounts (with the
 * id of the collection they reverse), so the ledger stays one consistent
 * column: paid_total = Σ amount, and every status derivation keeps working
 * unchanged. Over-collected money therefore becomes refundable money.
 */
class InvoicePaymentService
{
    public function record(
        Invoice $invoice,
        float $amount,
        string $method,
        ?string $reference,
        ?int $receivedBy,
        ?int $refundsPaymentId = null,
    ): InvoicePayment {
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => $reference,
            'paid_at' => now(),
            'received_by' => $receivedBy,
            'business_id' => $invoice->business_id,
            'created_by' => $receivedBy,
            'refunds_payment_id' => $refundsPaymentId,
        ]);

        // Recomputes paid_total and derives paid/partial status in one save.
        $invoice->recalculateFromPayments();

        return $payment;
    }
}
