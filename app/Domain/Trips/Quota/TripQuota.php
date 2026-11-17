<?php

namespace App\Domain\Trips\Quota;

use App\Domain\PurchaseOrders\Quota\DeductQuotaAction;
use App\Domain\PurchaseOrders\Quota\ReleaseQuotaAction;
use App\Models\PurchaseOrderService;
use App\Models\Trip;
use App\Models\User;

/**
 * The trip side of the quota ledger (6.1):
 *
 *  - a trip that is not cancelled (TripConsumesQuota, ج1) and is charged to
 *    a PO line holds ONE consume row of +1 trip and its own costs — written
 *    the moment it is created (the FRD: "deducted as soon as it is chosen"),
 *    even if that turns the line's balance negative (ب6);
 *  - cancelling gives it back (a release row) — "as if it never was";
 *  - changing the trip's date, project, main service or PO line gives the
 *    old consumption back and charges again with the line's CURRENT costs
 *    (decision ب7: two new rows, never an edit of the old one). Any other
 *    edit keeps the trip's costs as they were (ب3: a later change of the
 *    PO's cost applies to later trips only).
 *
 * A trip "without PO" writes nothing: its costs are entered by hand.
 */
final class TripQuota
{
    /** Fields whose change re-charges the trip (ب7), plus the line itself. */
    public const RECHARGE_ON = ['trip_at', 'project_id', 'main_service_id', 'purchase_order_service_id'];

    public function __construct(
        private readonly DeductQuotaAction $deduct,
        private readonly ReleaseQuotaAction $release,
    ) {}

    /** The costs a trip charged to `$line` carries: the line's trip cost, and its ancillary cost when the line counts one. */
    public static function costsOf(PurchaseOrderService $line): array
    {
        return [
            'trip_cost' => round((float) $line->trip_cost, 2),
            'ancillary_cost' => $line->tracksAncillary() ? round((float) $line->ancillary_cost_per_trip, 2) : 0.0,
        ];
    }

    /** Puts the line's current costs on the trip (not saved). */
    public static function priceFrom(Trip $trip, PurchaseOrderService $line): void
    {
        $costs = self::costsOf($line);
        $trip->purchase_order_service_id = $line->id;
        $trip->trip_cost = $costs['trip_cost'];
        $trip->ancillary_cost = $costs['ancillary_cost'];
    }

    /** Whether the changes on `$trip` (dirty, not yet saved) call for a re-charge. */
    public static function needsRecharge(Trip $trip): bool
    {
        return $trip->isDirty(self::RECHARGE_ON);
    }

    /** Writes the trip's consumption if it should hold one. Call after the trip is saved. */
    public function charge(Trip $trip, User $actor): void
    {
        if (! TripConsumesQuota::trip($trip) || $trip->purchase_order_service_id === null) {
            return;
        }

        $line = PurchaseOrderService::findOrFail($trip->purchase_order_service_id);

        $this->deduct->execute($line, $trip->id, (float) $trip->trip_cost, (float) $trip->ancillary_cost, $actor);
    }

    /** Gives back whatever the trip holds (idempotent). */
    public function release(Trip $trip, User $actor): int
    {
        return $this->release->execute($trip->id, $actor);
    }

    /** Release, then charge again as the trip now stands (ب7). */
    public function recharge(Trip $trip, User $actor): void
    {
        $this->release($trip, $actor);
        $this->charge($trip, $actor);
    }
}
