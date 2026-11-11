<?php

namespace App\Domain\PurchaseOrders\Exceptions;

use LogicException;

/**
 * A programming error: a trip holds at most one open consumption. Changing
 * the line a trip is charged to (decision ب7: its date, project or service
 * changed) is ReleaseQuotaAction first, then DeductQuotaAction — deducting
 * twice would count the trip twice.
 */
class QuotaAlreadyConsumedException extends LogicException
{
    public static function forTrip(int $tripId): self
    {
        return new self("Trip {$tripId} already consumes a purchase order line; release it before deducting again.");
    }
}
