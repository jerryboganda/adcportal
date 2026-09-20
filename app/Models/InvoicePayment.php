<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'amount', 'method', 'reference',
        'paid_at', 'received_by', 'business_id', 'created_by',
        'refunds_payment_id', 'refunded_at',
    ];

    protected $casts = ['paid_at' => 'datetime', 'amount' => 'decimal:2', 'refunded_at' => 'datetime'];

    protected static function booted()
    {
        static::created(function (InvoicePayment $payment) {
            optional($payment->invoice)->recalculateFromPayments();
        });

        // NOTE: deliberately NO tenant global scope here. The recalculation
        // path (recalculateFromPayments via the created event) must aggregate
        // the invoice's OWN payment rows regardless of the ambient tenant
        // context (seeders, queue workers, cross-tenant sessions) — a scope
        // keyed on getActiveBusiness() could silently zero a paid_total.
        // Tenant isolation is enforced explicitly on every read/write path:
        // invoice listing (Invoice::forClinic), refunds (business_id guard in
        // BillingController) and shift summaries (scoped query).
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** The collection this row reverses (null for normal collections). */
    public function refundsPayment()
    {
        return $this->belongsTo(InvoicePayment::class, 'refunds_payment_id');
    }

    /** Rows that reverse THIS collection. */
    public function refundedBy()
    {
        return $this->hasMany(InvoicePayment::class, 'refunds_payment_id');
    }

    public function isRefund(): bool
    {
        return (float) $this->amount < 0;
    }

    /**
     * Human display label: shows the money's DIRECTION, so a ledger can
     * never render a −2,500.00 row as if it were money the clinic received.
     */
    public function directionLabel(): string
    {
        return $this->isRefund() ? 'REFUND' : 'PAYMENT';
    }
}
