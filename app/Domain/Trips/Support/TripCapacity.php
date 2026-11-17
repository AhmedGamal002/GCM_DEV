<?php

namespace App\Domain\Trips\Support;

use App\Models\AssetCapacityCategory;
use App\Models\Trip;

/**
 * The capacity that bounds a trip's waste quantity (FRD: "the quantity is
 * computed automatically from the capacity of the vehicle's container — when
 * it has an embedded one — or of the returning container; staff may lower it
 * but never raise it above that capacity"):
 *
 *  - a vehicle with an embedded container → that container's capacity;
 *  - otherwise the returning container's capacity;
 *  - neither, or a supplying trip (no waste at all, decision ج11) → none.
 *
 * Used for the default quantity of a new trip (7.1) and by the cascading
 * choices of the trip form (7.2).
 */
final class TripCapacity
{
    public static function of(Trip $trip): ?AssetCapacityCategory
    {
        $trip->loadMissing(['mainService', 'vehicle.embeddedCapacityCategory', 'returningAsset.capacityCategory']);

        if ($trip->mainService?->isSupplying()) {
            return null;
        }

        if ($trip->vehicle?->has_embedded_container) {
            return $trip->vehicle->embeddedCapacityCategory;
        }

        return $trip->returningAsset?->capacityCategory;
    }

    /** @return array{ton: ?float, cbm: ?float} the capacity as the default quantity (both null when there is none) */
    public static function defaultQuantity(Trip $trip): array
    {
        $capacity = self::of($trip);

        return $capacity === null
            ? ['ton' => null, 'cbm' => null]
            : [
                'ton' => WasteQuantityConverter::round((float) $capacity->capacity_ton),
                'cbm' => WasteQuantityConverter::round((float) $capacity->capacity_cbm),
            ];
    }
}
