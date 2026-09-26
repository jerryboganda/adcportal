<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'ris_app_notifications';

    protected $fillable = [
        'title',
        'message',
        'category',
        'priority',
        'appointment_id',
        'token_number',
        'patient_name',
        'target_tab',
        'action_label',
        'is_read',
        'business_id',
        // Addresses one row to one person. It was being passed in and dropped on
        // the floor, which turned every "assigned to you" into a tenant-wide
        // broadcast carrying a patient name.
        'target_user_id',
    ];

    protected $casts = ['is_read' => 'boolean'];

    /**
     * The notifications a user is allowed to see.
     *
     * Tenant first, then `target_user_id IS NULL OR = me`. A clinic-wide alert
     * (STAT, critical result, low stock) has no target and is everyone's; a
     * message addressed to one person is nobody else's — including nobody else's
     * `mark-read`, `delete`, or "clear all".
     *
     * Every read AND every write in the notification centre goes through this, so
     * there is exactly one place where the boundary is drawn.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVisibleTo(Builder $query, ?int $userId, int $businessId): Builder
    {
        return $query->where('business_id', $businessId)
            ->where(function (Builder $scope) use ($userId) {
                $scope->whereNull('target_user_id');

                if ($userId !== null) {
                    $scope->orWhere('target_user_id', $userId);
                }
            });
    }
}
