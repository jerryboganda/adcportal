<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnatomicalRegion extends Model
{
    protected $guarded = ['*'];

    protected $casts = ['source_identity' => 'array'];
}