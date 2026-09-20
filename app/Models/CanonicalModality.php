<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanonicalModality extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'acquisition_codes' => 'array',
        'source_identity' => 'array',
    ];
}