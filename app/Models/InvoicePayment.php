<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'amount', 'method', 'reference',
        'paid_at', 'received_by', 'business_id', 'created_by',
    ];

    protected $casts = ['paid_at' => 'datetime', 'amount' => 'decimal:2'];

    protected static function booted()
    {
        static::created(function (InvoicePayment $payment) {
            optional($payment->invoice)->recalculateFromPayments();
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
