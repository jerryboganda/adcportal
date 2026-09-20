<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanonicalProcedure extends Model
{
    protected $guarded = ['*'];

    public function revisions()
    {
        return $this->hasMany(CanonicalProcedureRevision::class);
    }
}