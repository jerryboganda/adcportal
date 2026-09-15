<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'invoice_number', 'patient_id', 'appointment_id', 'status',
        'subtotal', 'discount_total', 'manual_discount', 'tax_rate', 'tax_amount',
        'total', 'paid_total', 'notes',
        'issued_by', 'issued_at', 'voided_by', 'voided_at',
        'business_id', 'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'manual_discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::nextNumber($invoice->business_id);
            }
        });

        // Keep status consistent with payments.
        static::saving(function (Invoice $invoice) {
            if ($invoice->status === self::STATUS_VOID) {
                return;
            }
            $invoice->recalculateStatus();
        });
    }

        public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        // Tenant boundary is business_id ONLY. Creator scoping must be explicit.
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== null && $creatorId !== false, fn ($q) => $q->where('created_by', $creatorId));
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return round(max(0, (float) $this->total - (float) $this->paid_total), 2);
    }

    /** Recompute paid_total from payment rows and derive the status. */
    public function recalculateFromPayments(): void
    {
        $this->paid_total = (float) $this->payments()->sum('amount');
        $this->save();
    }

    /**
     * Single source of truth for invoice money math (StudyController and
     * BillingController both previously carried private copies of this).
     * Totals = Σ line totals − Σ item discounts − manual cash discount, plus
     * tax on the discounted remainder. Never negative.
     */
    public function recalculateTotals(): void
    {
        $this->refresh();
        $subtotal = (float) $this->items()->sum('line_total');
        $itemDiscounts = (float) $this->items()->sum('discount');
        $discountTotal = min($subtotal, $itemDiscounts + max(0, (float) $this->manual_discount));
        $taxable = max(0, $subtotal - $discountTotal);
        $taxAmount = round($taxable * ((float) $this->tax_rate / 100), 2);

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_amount' => $taxAmount,
            'total' => round($taxable + $taxAmount, 2),
        ])->save();
    }

    public function recalculateStatus(): void
    {
        if ((float) $this->total <= 0 || (float) $this->paid_total <= 0) {
            $this->attributes['status'] = in_array($this->status, [self::STATUS_DRAFT, self::STATUS_VOID], true)
                ? $this->status
                : ($this->issued_at ? self::STATUS_ISSUED : self::STATUS_DRAFT);

            return;
        }

        if ((float) $this->paid_total + 0.001 >= (float) $this->total) {
            $this->attributes['status'] = self::STATUS_PAID;
        } else {
            $this->attributes['status'] = self::STATUS_PARTIAL;
        }
    }

    /** INV-{YYYY}-{seq} per clinic. */
    public static function nextNumber($businessId): string
    {
        $year = now()->format('Y');
        $prefix = "INV-{$year}-";

        $last = static::withTrashed()
            ->where('business_id', $businessId ?? getActiveBusiness())
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
