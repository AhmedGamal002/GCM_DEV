<?php

namespace App\Domain\Notifications\Support;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

/**
 * Writes one notification row per recipient (a single insert). A recipient is notified once per call even if
 * several rules named them, and the person who caused the change never notifies themself.
 */
final class Notifier
{
    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $data
     * @return int how many rows were written
     */
    public static function send(int $tenantId, string $type, Collection $recipients, array $data, ?int $tripId = null, ?int $purchaseOrderId = null, ?string $subject = null, ?int $exceptUserId = null): int
    {
        $now = now();
        $rows = $recipients
            ->unique('id')
            ->reject(fn ($user) => $user->id === $exceptUserId)
            ->map(fn ($user) => [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'type' => $type,
                'trip_id' => $tripId,
                'purchase_order_id' => $purchaseOrderId,
                'subject' => $subject,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            UserNotification::query()->withoutGlobalScopes()->insert($rows);
        }

        return count($rows);
    }
}
