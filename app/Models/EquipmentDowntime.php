<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentDowntime extends Model
{
    protected $fillable = ['room_id', 'starts_at', 'ends_at', 'reason'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
