<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-configured payment method. The `code` is the stable wire format
 * stored on invoice_payments.method; `name` is the tenant-editable label.
 * Codes are immutable once payments reference them — deactivate instead.
 */
class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'is_active', 'sort_order', 'business_id', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        // Tenant boundary is business_id ONLY. Creator scoping must be explicit.
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== null && $creatorId !== false, fn ($q) => $q->where('created_by', $creatorId));
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
