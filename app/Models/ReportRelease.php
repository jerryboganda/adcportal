<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportRelease extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_id', 'channel', 'recipient_email', 'released_by', 'released_at',
    ];

    protected $casts = ['released_at' => 'datetime'];

    protected static function booted()
    {
        static::creating(function (ReportRelease $release) {
            $release->released_at = $release->released_at ?? now();
        });
    }

    public function report()
    {
        return $this->belongsTo(RadiologyReport::class, 'report_id');
    }
}
