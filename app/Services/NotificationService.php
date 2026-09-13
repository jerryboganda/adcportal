<?php

namespace App\Services;

use App\Models\AppNotification;

/**
 * Single funnel for the in-app clinical notification center. Every workflow
 * event that the React portal surfaces (check-ins, STAT bookings, report
 * sign-offs, payments, stock alerts…) is recorded here once, server-side.
 */
class NotificationService
{
    public static function push(array $attributes): AppNotification
    {
        return AppNotification::create([
            'category' => 'general',
            'priority' => 'medium',
            'business_id' => getActiveBusiness(),
            ...$attributes,
        ]);
    }

    public static function lowStock(\App\Models\InventoryItem $item, int $newStock): void
    {
        self::push([
            'title' => ($newStock === 0 ? 'Out of Stock: ' : 'Low Stock Warning: ') . $item->name,
            'message' => sprintf(
                '%s stock has reached %d %s (threshold: %d). Reorder recommended.',
                $item->name,
                $newStock,
                $item->unit ?? 'units',
                $item->min_threshold
            ),
            'category' => 'general',
            'priority' => $newStock === 0 ? 'critical' : 'high',
            'target_tab' => 'inventory',
            'action_label' => 'Open Inventory & Restock',
        ]);
    }
}
