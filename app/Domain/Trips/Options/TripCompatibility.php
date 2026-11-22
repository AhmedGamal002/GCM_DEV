<?php

namespace App\Domain\Trips\Options;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * What fits what on a trip (FRD V01.14 §1.14, the trip form) — the
 * compatibility half of the cascading choices; WHO is available at all is
 * TripResourceAvailability. Each rule is a query constraint, so the form's
 * lists (TripOptions) and the server re-check of a saved trip
 * (TripChoiceCheck) run the very same SQL.
 *
 *  - vehicle ↔ driver: the vehicle's category is one the driver is qualified
 *    for (the only condition between them — ج4, no affiliation match);
 *  - main service ↔ vehicle: the service supports the vehicle's category;
 *  - container ↔ vehicle: a going container is compatible with the vehicle's
 *    category (§1.7.3 "compatible vehicle categories"); a vehicle with an
 *    embedded container takes no external container (our reading of §1.7.3
 *    "embedded … is part of the vehicle" + §1.7.5 "normally, when the
 *    vehicle has NO embedded container, a going and a returning container
 *    are chosen");
 *  - sub-service ↔ container: the sub-service's asset type and one of its
 *    capacities match THE trip's container (container()).
 */
final class TripCompatibility
{
    /** Vehicles whose category the driver is qualified for. */
    public static function vehiclesForDriver(Builder $vehicles, Driver $driver): Builder
    {
        return $vehicles->whereIn('vehicles.vehicle_category_id', fn ($q) => $q
            ->select('vehicle_category_id')->from('driver_vehicle_categories')->where('driver_id', $driver->id));
    }

    /** Main services that support the vehicle's category. */
    public static function mainServicesForVehicle(Builder $services, Vehicle $vehicle): Builder
    {
        return $services->whereIn('main_services.id', fn ($q) => $q
            ->select('main_service_id')->from('main_service_vehicle_category')->where('vehicle_category_id', $vehicle->vehicle_category_id));
    }

    /** Assets compatible with the vehicle's category — none for a vehicle with an embedded container. */
    public static function assetsForVehicle(Builder $assets, Vehicle $vehicle): Builder
    {
        if (! self::takesExternalContainers($vehicle)) {
            return $assets->whereRaw('1 = 0');
        }

        return $assets->whereIn('assets.id', fn ($q) => $q
            ->select('asset_id')->from('asset_vehicle_categories')->where('vehicle_category_id', $vehicle->vehicle_category_id));
    }

    public static function takesExternalContainers(Vehicle $vehicle): bool
    {
        return ! $vehicle->has_embedded_container;
    }

    /**
     * Sub-services whose asset type and capacities include the container — every one when the trip has
     * no container yet (the driver picks the sub-service before, or without, the containers).
     *
     * @param  array{type: string, capacity: AssetCapacityCategory}|null  $container
     */
    public static function subServicesForContainer(Builder $subs, ?array $container): Builder
    {
        if ($container === null) {
            return $subs;
        }

        return $subs
            ->where('sub_services.asset_type', $container['type'])
            ->whereIn('sub_services.id', fn ($q) => $q
                ->select('sub_service_id')->from('sub_service_asset_capacity')->where('asset_capacity_category_id', $container['capacity']->id));
    }

    /**
     * THE container of the trip — the one its sub-service must suit: the vehicle's embedded container;
     * otherwise the returning container (the one that carries the waste back, whose capacity bounds the
     * quantity — TripCapacity); otherwise the going one (a supplying trip delivers a container and may
     * bring none back). Our reading of "the sub-service suits the container's type and capacity" — change
     * it here if corrected.
     *
     * @return array{type: string, capacity: AssetCapacityCategory}|null
     */
    public static function container(?Vehicle $vehicle, ?Asset $going, ?Asset $returning): ?array
    {
        if ($vehicle?->has_embedded_container) {
            $capacity = $vehicle->embeddedCapacityCategory;

            return $capacity === null || $vehicle->embedded_container_type === null
                ? null
                : ['type' => $vehicle->embedded_container_type, 'capacity' => $capacity];
        }

        $asset = $returning ?? $going;

        return $asset?->capacityCategory === null
            ? null
            : ['type' => $asset->asset_type, 'capacity' => $asset->capacityCategory];
    }
}
