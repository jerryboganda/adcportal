<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /** Control-plane-relevant actions — the ONLY audit entries exposed to platform staff. */
    public const PLATFORM_ACTIONS = [
        'tenant_created', 'tenant_provisioned', 'tenant_imported', 'tenant_activated', 'tenant_suspended',
        'tenant_reactivated', 'tenant_offboarding_started', 'tenant_terminated', 'tenant_data_destroyed',
        'subscription_updated', 'subscription_expired', 'tenant_features_updated', 'tenant_data_exported',
        'tenant_updated', 'tenant_registered', 'provisioning_retried',
        'support_session_started', 'support_session_ended',
        'plan_created', 'plan_updated', 'platform_user_created', 'platform_user_updated',
        'tenant_user_created', 'tenant_user_updated', 'tenant_user_password_reset',
        'tenant_facility_created', 'tenant_facility_updated', 'tenant_facility_deleted',
    ];

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
