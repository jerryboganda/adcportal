<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;

/**
 * Single recording path for invoice payments — booking-time settlement
 * (StudyController) and POS collection (BillingController) both flow through
 * here so money math and payment rows can never diverge. Callers own the
 * balance-due check, the row lock and the surrounding transaction.
 */
class InvoicePaymentService
{
    public function record(Invoice $invoice, float $amount, string $method, ?string $reference, ?int $receivedBy): InvoicePayment
    {
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => $reference,
            'paid_at' => now(),
            'received_by' => $receivedBy,
            'business_id' => $invoice->business_id,
            'created_by' => $receivedBy,
        ]);

        // Recomputes paid_total and derives paid/partial status in one save.
        $invoice->recalculateFromPayments();

        return $payment;
    }
}
