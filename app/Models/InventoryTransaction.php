<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $table = 'ris_inventory_transactions';

    protected $fillable = [
        'inventory_item_id',
        'item_name',
        'type',
        'quantity',
        'batch_number',
        'appointment_id',
        'token_number',
        'patient_name',
        'notes',
        'performed_by',
        'business_id',
    ];

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
