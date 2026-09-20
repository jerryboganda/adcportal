<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadiologyCatalogRelease extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'schema_version' => 'integer',
        'manifest' => 'array',
        'counts' => 'array',
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function revisions()
    {
        return $this->hasMany(CanonicalProcedureRevision::class, 'release_id');
    }
}