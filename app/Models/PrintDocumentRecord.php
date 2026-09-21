<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A rendered document, stored once.
 *
 * Finalized paper (a signed report, an issued invoice, a shift closing) must
 * reprint byte-identically years later, after the tenant has changed its logo,
 * its letterhead or even its currency presentation. Storing the rendered PDF and
 * reusing it is the only way to guarantee that — re-rendering from today's
 * branding would silently produce a different historical document.
 *
 * It also records WHICH engine produced the file, so a parity investigation can
 * tell a DomPDF-rendered A4 from a Chromium-rendered one without guessing.
 */
class PrintDocumentRecord extends Model
{
    protected $fillable = [
        'business_id', 'artifact', 'document_key', 'paper', 'driver',
        'path', 'bytes', 'checksum', 'finalized', 'generated_by', 'generated_at',
    ];

    protected $casts = [
        'finalized' => 'boolean',
        'bytes' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeForClinic($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }

    /** Documents whose content is legally frozen once rendered. */
    public const FINALIZED_ARTIFACTS = ['report'];

    public static function isFinalizedArtifact(string $artifact, bool $signed): bool
    {
        return in_array($artifact, self::FINALIZED_ARTIFACTS, true) && $signed;
    }
}
