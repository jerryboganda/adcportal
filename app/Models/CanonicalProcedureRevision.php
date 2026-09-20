<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CanonicalProcedureRevision extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'is_orderable' => 'boolean',
        'workflow_supported' => 'boolean',
        'contrast_routes' => 'array',
        'contrast_agents' => 'array',
        'views' => 'array',
        'attributes' => 'array',
        'source_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Catalog revisions are immutable; import a new release.'));
        static::deleting(fn () => throw new LogicException('Catalog revisions must remain available to historical records.'));
    }

    public function procedure()
    {
        return $this->belongsTo(CanonicalProcedure::class, 'canonical_procedure_id');
    }

    public function release()
    {
        return $this->belongsTo(RadiologyCatalogRelease::class, 'release_id');
    }

    public function modality()
    {
        return $this->belongsTo(CanonicalModality::class, 'canonical_modality_id');
    }

    public function regions()
    {
        return $this->belongsToMany(AnatomicalRegion::class, 'canonical_procedure_regions', 'revision_id', 'anatomical_region_id')
            ->withPivot('role');
    }
}