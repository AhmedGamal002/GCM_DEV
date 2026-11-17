<?php

namespace App\Domain\Trips\Quota;

use App\Models\Trip;

/**
 * Decision ج1 (product owner, 2026-11-09): which trips hold their PO quota.
 *
 * Every trip that is not cancelled — awaiting the client's approval,
 * approved by them, rejected by them (it stays consumed until it is either
 * cancelled or completed) or completed. A cancelled trip is "as if it never
 * was" (FRD), so cancelling gives its quota back. If the answer changes,
 * this is the one place to change it.
 */
final class TripConsumesQuota
{
    public static function status(string $status): bool
    {
        return $status !== Trip::STATUS_CANCELLED;
    }

    public static function trip(Trip $trip): bool
    {
        return self::status($trip->status);
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return array_values(array_filter(Trip::STATUSES, fn (string $s) => self::status($s)));
    }
}
