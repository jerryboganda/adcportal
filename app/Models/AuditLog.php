<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip'];

    protected $casts = ['changes' => 'array'];

    public static function record(string $action, Model $subject, ?array $changes = null): void
    {
        try {
            self::create([
                'user_id' => auth()->id() ?? 0,
                'action' => $action,
                'subject_type' => class_basename($subject),
                'subject_id' => $subject->id,
                'changes' => $changes,
                'ip' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            report($e); // never break the request because of audit logging
        }
    }
}
