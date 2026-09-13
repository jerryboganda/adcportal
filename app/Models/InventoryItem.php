<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'ris_inventory_items';

    protected $fillable = [
        'code',
        'name',
        'generic_name',
        'category',
        'modality',
        'unit',
        'current_stock',
        'min_threshold',
        'unit_cost',
        'selling_price',
        'batches',
        'supplier',
        'storage_location',
        'requires_cold_chain',
        'is_billable',
        'notes',
        'business_id',
    ];

    protected $casts = [
        'batches' => 'array',
        'requires_cold_chain' => 'boolean',
        'is_billable' => 'boolean',
    ];

    public function scopeForClinic($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'inventory_item_id');
    }
}
