<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'service_id', 'description',
        'quantity', 'unit_price', 'discount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function serviceData()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function computeLineTotal(): float
    {
        return round(((float) $this->unit_price * (int) $this->quantity) - (float) $this->discount, 2);
    }

    protected static function booted()
    {
        static::saving(function (InvoiceItem $item) {
            if ($item->line_total === null || $item->isDirty(['quantity', 'unit_price', 'discount'])) {
                $item->line_total = $item->computeLineTotal();
            }
        });
    }
}
