<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One radiologist's reporting preferences within one clinic.
 *
 * Deliberately per USER and per CLINIC: a radiologist who works at two sites
 * keeps two independent setups, and these are clinical workflow choices rather
 * than device settings, so they follow the person rather than the workstation.
 *
 * Contains no patient data.
 */
class ReportingPreference extends Model
{
    protected $table = 'reporting_preferences';

    protected $fillable = [
        'business_id', 'user_id',
        'dictation_language', 'dictation_provider',
        'template_autoload', 'default_tab',
    ];

    protected $casts = [
        'template_autoload' => 'boolean',
    ];

    /**
     * Dictation languages the reporting workspace offers. Kept server-side so a
     * preference cannot be set to something the UI has no way to render, and so
     * an unsupported tag is rejected rather than silently stored.
     */
    public const DICTATION_LANGUAGES = ['en-US', 'en-GB', 'en-IN', 'ur-PK', 'ar-SA'];

    public const DICTATION_PROVIDERS = ['browser', 'server'];

    /** Queue tabs a user may choose as their landing tab. */
    public const DEFAULT_TABS = [
        'unreported', 'assigned', 'priority', 'in_progress', 'drafts',
        'preliminary', 'finalized', 'addenda', 'recent', 'all',
    ];

    public function scopeForClinic($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }

    /** The row for one user in one clinic, or a fresh unsaved default. */
    public static function forUser(int $businessId, int $userId): self
    {
        return static::forClinic($businessId)->where('user_id', $userId)->first()
            ?? new static([
                'business_id' => $businessId,
                'user_id' => $userId,
                'dictation_language' => 'en-US',
                'dictation_provider' => 'browser',
                'template_autoload' => true,
                'default_tab' => 'unreported',
            ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
