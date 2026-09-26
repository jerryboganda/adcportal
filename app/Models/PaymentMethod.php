<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-configured payment method. The `code` is the stable wire format
 * stored on invoice_payments.method; `name` is the tenant-editable label.
 * Codes are immutable once payments reference them �?" deactivate instead.
 *
 * `kind` is what the method IS, as opposed to what it is called: the drawer
 * (`cash`), the POS (`card`), a wallet/Raast (`digital`), a panel
 * (`insurance`). Money arithmetic asks for a kind — "what is in the drawer" is a
 * question about behaviour, not about a label — so it must not be derived from
 * the code.
 */
class PaymentMethod extends Model
{
    use SoftDeletes;

    public const KIND_CASH = 'cash';

    public const KIND_CARD = 'card';

    public const KIND_DIGITAL = 'digital';

    public const KIND_INSURANCE = 'insurance';

    public const KIND_OTHER = 'other';

    public const KINDS = [
        self::KIND_CASH, self::KIND_CARD, self::KIND_DIGITAL, self::KIND_INSURANCE, self::KIND_OTHER,
    ];

    protected $fillable = [
        'code', 'name', 'kind', 'is_active', 'sort_order', 'business_id', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The tenant's drawer method — the one whose total is physical cash.
     *
     * Null when the tenant has configured no cash method at all, which is a
     * legitimate (cashless) clinic and must read as "expected in drawer 0", not
     * as the first method in the list.
     */
    public static function cashCodeFor(int $businessId): ?string
    {
        return static::forClinic($businessId)
            ->where('kind', self::KIND_CASH)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('code');
    }

    /** The tenant's POS/card method, for the "card collected" comparison. */
    public static function cardCodeFor(int $businessId): ?string
    {
        return static::forClinic($businessId)
            ->where('kind', self::KIND_CARD)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('code');
    }

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
