<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'business_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip'];

    protected $casts = ['changes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public static function record(string $action, Model $subject, ?array $changes = null, ?int $businessId = null): void
    {
        try {
            self::create([
                'user_id' => auth()->id() ?? 0,
                'business_id' => $businessId,
                'action' => $action,
                'subject_type' => class_basename($subject),
                'subject_id' => $subject->id,
                'changes' => $changes,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e); // never break the request because of audit logging
        }
    }
}
