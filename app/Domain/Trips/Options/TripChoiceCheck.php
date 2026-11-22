<?php

namespace App\Domain\Trips\Options;

use App\Models\Asset;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

/**
 * The server's re-check of a trip's choices (security checklist §1.3: the
 * form's lists are no guarantee) — the rules of TripOptions, run against the
 * trip TripInput is about to save, with the error on the field the form shows.
 *
 * Only what this save CHANGES is checked: an old trip keeps its driver,
 * vehicle and containers when they have gone on vacation / maintenance since
 * (ج4: "old trips stay as they are"), and an edit of another field doesn't
 * fail on them. A changed field is checked for availability; a pair that
 * depends on each other (vehicle ↔ driver, main service ↔ vehicle, container
 * ↔ vehicle, sub-service ↔ container, quantity ↔ capacity) is re-checked
 * when either side changes.
 *
 * Nothing here looks at the PO: a trip is recorded after it happened, so it
 * is never refused on the PO side (ب6, product owner 2026-11-09 / 11-22). A
 * driver's main-service LIST is the FRD's (what the project's POs cover —
 * TripOptions::mainServices), but a driver's trip on a service no PO covers
 * is not refused: it is recorded "without PO" (TripPurchaseOrderLine::defaultFor
 * finds no line) and GCM enters its cost.
 */
final class TripChoiceCheck
{
    /** @throws ValidationException */
    public static function assert(Trip $trip, bool $creating): void
    {
        $changed = fn (string ...$keys) => $creating || $trip->isDirty($keys);
        $exceptTrip = $creating ? null : $trip->id;

        $driver = Driver::find($trip->driver_id);
        $vehicle = Vehicle::with('embeddedCapacityCategory')->find($trip->vehicle_id);
        $going = $trip->going_asset_id === null ? null : Asset::with('capacityCategory')->find($trip->going_asset_id);
        $returning = $trip->returning_asset_id === null ? null : Asset::with('capacityCategory')->find($trip->returning_asset_id);
        $mainService = MainService::find($trip->main_service_id);

        if ($changed('project_id')) {
            self::project($trip->project ?? Project::find($trip->project_id), $creating);
        }

        if ($changed('driver_id') && ! TripResourceAvailability::drivers(Driver::query())->whereKey($driver->id)->exists()) {
            self::fail('driver_id', __('This driver is not available for trips (on vacation, deactivated, or of a deactivated contractor).'));
        }

        if ($changed('vehicle_id') && ! TripResourceAvailability::isVehicleAvailable($vehicle)) {
            self::fail('vehicle_id', __('This vehicle is not available for trips (on maintenance, deactivated, or of a deactivated contractor).'));
        }

        if ($changed('vehicle_id', 'driver_id') && ! TripCompatibility::vehiclesForDriver(Vehicle::query(), $driver)->whereKey($vehicle->id)->exists()) {
            self::fail('vehicle_id', __('The driver is not qualified for this vehicle\'s category.'));
        }

        self::containers($trip, $vehicle, $going, $returning, $changed, $exceptTrip);

        if ($changed('main_service_id') && ! $mainService->isActive()) {
            self::fail('main_service_id', __('This main service is deactivated.'));
        }

        if ($changed('main_service_id', 'vehicle_id') && ! TripCompatibility::mainServicesForVehicle(MainService::query(), $vehicle)->whereKey($mainService->id)->exists()) {
            self::fail('main_service_id', __('This main service does not support the vehicle\'s category.'));
        }

        if ($trip->sub_service_id !== null) {
            $sub = SubService::find($trip->sub_service_id);

            if ($changed('sub_service_id') && ! $sub->isActive()) {
                self::fail('sub_service_id', __('This sub-service is deactivated.'));
            }

            if ($changed('sub_service_id', 'vehicle_id', 'going_asset_id', 'returning_asset_id')) {
                $container = TripCompatibility::container($vehicle, $going, $returning);

                if (! TripCompatibility::subServicesForContainer(SubService::query(), $container)->whereKey($sub->id)->exists()) {
                    self::fail('sub_service_id', __('The sub-service does not suit the trip\'s container type and capacity.'));
                }
            }
        }

        if ($trip->intermediate_facility_id !== null && $changed('intermediate_facility_id')
            && IntermediateFacility::whereKey($trip->intermediate_facility_id)->value('operational_status') !== 'active') {
            self::fail('intermediate_facility_id', __('This intermediate facility is deactivated.'));
        }

        if ($changed('quantity_ton', 'quantity_cbm', 'vehicle_id', 'returning_asset_id', 'main_service_id')) {
            self::quantity($trip, TripOptions::capacity($vehicle, $mainService, $returning));
        }
    }

    private static function project(?Project $project, bool $creating): void
    {
        if ($project === null) {
            return; // TripInput already failed on it
        }

        if ($project->operational_status !== 'active') {
            self::fail('project_id', __('This project is deactivated.'));
        }

        if ($creating && $project->company()->value('operational_status') !== 'active') {
            self::fail('company_id', __('This client company is deactivated.'));
        }
    }

    private static function containers(Trip $trip, Vehicle $vehicle, ?Asset $going, ?Asset $returning, \Closure $changed, ?int $exceptTrip): void
    {
        if (($going !== null || $returning !== null) && $changed('vehicle_id', 'going_asset_id', 'returning_asset_id')
            && ! TripCompatibility::takesExternalContainers($vehicle)) {
            self::fail($going !== null ? 'going_asset_id' : 'returning_asset_id', __('The vehicle has an embedded container: the trip takes no going or returning container.'));
        }

        if ($going !== null) {
            if ($changed('going_asset_id')) {
                if (! TripResourceAvailability::isAssetAvailableForSave($going, $exceptTrip)) {
                    self::fail('going_asset_id', __('This container is not available (on maintenance, deactivated, of a deactivated contractor, or on another trip that is not finished).'));
                }

                // A completed trip's going container sits at the project already (TripAssetMovement) — only a new one must come from the pool.
                if ($going->project_id !== null) {
                    self::fail('going_asset_id', __('This container is placed at a project; a going container comes from the available assets.'));
                }
            }

            if ($changed('going_asset_id', 'vehicle_id') && ! TripCompatibility::assetsForVehicle(Asset::query(), $vehicle)->whereKey($going->id)->exists()) {
                self::fail('going_asset_id', __('This container is not compatible with the vehicle\'s category.'));
            }
        }

        if ($returning !== null) {
            if ($changed('returning_asset_id') && ! TripResourceAvailability::isAssetAvailableForSave($returning, $exceptTrip)) {
                self::fail('returning_asset_id', __('This container is not available (on maintenance, deactivated, of a deactivated contractor, or on another trip that is not finished).'));
            }

            // A completed trip has moved its returning container out of the project already: re-check only an open one.
            $atProject = (int) $returning->project_id === (int) $trip->project_id || (int) $returning->id === (int) $trip->going_asset_id;

            if (! $trip->isCompleted() && $changed('returning_asset_id', 'project_id', 'going_asset_id') && ! $atProject) {
                self::fail('returning_asset_id', __('A returning container must be at the trip\'s project (or be the going container).'));
            }
        }
    }

    /**
     * The quantity may be lowered, never raised above the container's capacity (FRD). The capacity has a
     * tonnage and a volume of its own, while the quantity's other unit is derived with the one factor
     * (ج10) — so the quantity is over the capacity only when it is over in BOTH units (a reading of ours:
     * entering the capacity's own volume must pass even when the factor gives a few kilos more).
     */
    private static function quantity(Trip $trip, ?\App\Models\AssetCapacityCategory $capacity): void
    {
        if ($capacity === null || $trip->quantity_ton === null) {
            return;
        }

        $tolerance = 0.0005;
        $overTon = (float) $trip->quantity_ton > (float) $capacity->capacity_ton + $tolerance;
        $overCbm = (float) $trip->quantity_cbm > (float) $capacity->capacity_cbm + $tolerance;

        if ($overTon && $overCbm) {
            self::fail('quantity_ton', __('The quantity cannot exceed the container\'s capacity (:ton t / :cbm m³).', [
                'ton' => rtrim(rtrim(number_format((float) $capacity->capacity_ton, 3, '.', ''), '0'), '.'),
                'cbm' => rtrim(rtrim(number_format((float) $capacity->capacity_cbm, 3, '.', ''), '0'), '.'),
            ]));
        }
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
