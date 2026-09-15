<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A row from Laravel's `failed_jobs` table, surfaced as a model so the control
 * plane can inspect, retry and forget failed background work with the same
 * audit treatment as every other platform action.
 *
 * `payload` and `exception` are long text that may contain patient data, so
 * they are NEVER serialized to the API as-is — see App\Services\JobInspector.
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $fillable = ['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'];

    protected $casts = ['failed_at' => 'datetime'];
}
