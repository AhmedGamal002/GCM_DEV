<?php

namespace App\Domain\Trips\Options;

use App\Domain\Trips\Support\TripCapacity;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Company;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * The cascading choices of the trip form (part 7.2) — what each list offers
 * once the choices before it are made. The nine steps of WEEKLY_PLAN 7.2,
 * from the FRD's trip form (V01.14 §1.14):
 *
 *  1. driver → the vehicles he is qualified for, his default one prefilled;
 *  2. company → its active projects;
 *  3. project → EVERY PO of the project (for the service), running or not,
 *     except the deactivated ones (ب6), plus "without PO" — the only choice
 *     when there is none;
 *  4. main services → those supporting the vehicle's category AND covered by
 *     the chosen PO (or by any PO of the project); every one when the trip
 *     goes without a PO or the project has no PO at all (FRD);
 *  5. sub-services → of the main service, suiting the trip's container
 *     (its type and capacity);
 *  6. container capacity → the most the trip can carry (embedded container,
 *     else the returning one; a supplying trip carries no waste — ج11);
 *  7. returning containers → the project's assets, plus the going container
 *     chosen in the same form (ج3 — it may come back the same);
 *  8. facilities → active, serving the sub-service;
 *  9. cost source → fixed from the PO line, or entered by hand without PO.
 *
 * Only active records are offered (and the resources of TripResourceAvailability,
 * decision ج4). Each list is a query: the API (TripOptionController) pages /
 * maps it, and TripChoiceCheck re-runs the same rules on a saved trip — the
 * lists are a convenience, never the guarantee.
 */
final class TripOptions
{
    // 1. Driver → vehicles

    /** Active drivers of active contractors (or GCM's), as the staff's driver list. */
    public static function drivers(): Builder
    {
        return TripResourceAvailability::drivers(Driver::query());
    }

    /** The vehicles the driver may take: available and of a category he is qualified for. */
    public static function vehicles(Driver $driver): Builder
    {
        return TripCompatibility::vehiclesForDriver(TripResourceAvailability::vehicles(Vehicle::query()), $driver);
    }

    /** The driver's default vehicle — only when it is one he may take now (a reading of ج4). */
    public static function defaultVehicle(Driver $driver): ?Vehicle
    {
        return $driver->default_vehicle_id === null
            ? null
            : self::vehicles($driver)->whereKey($driver->default_vehicle_id)->first();
    }

    // 2. Company → projects

    public static function companies(): Builder
    {
        return Company::query()->where('companies.operational_status', 'active');
    }

    public static function projects(int $companyId): Builder
    {
        return Project::query()
            ->where('projects.company_id', $companyId)
            ->where('projects.operational_status', 'active');
    }

    // 3. Project → POs

    /** The project's POs that are not deactivated (running or not — ب6), for the service when given, newest first. */
    public static function purchaseOrders(Project $project, ?int $mainServiceId = null): Builder
    {
        return PurchaseOrder::query()
            ->where('purchase_orders.project_id', $project->id)
            ->where('purchase_orders.operational_status', 'active')
            ->when($mainServiceId !== null, fn (Builder $q) => $q->whereHas('services', fn (Builder $s) => $s->where('main_service_id', $mainServiceId)))
            ->orderByDesc('purchase_orders.starts_on')
            ->orderByDesc('purchase_orders.id');
    }

    // 4. Main services

    /**
     * The main services the trip may be for: active, supporting the vehicle's category, and — when the
     * trip needs a PO — covered by the chosen PO, or by any PO of the project when none is chosen yet. A
     * project with no PO at all (and a trip without PO) offers every service (FRD).
     */
    public static function mainServices(Vehicle $vehicle, Project $project, bool $requiresPurchaseOrder = true, ?PurchaseOrder $purchaseOrder = null): Builder
    {
        $query = TripCompatibility::mainServicesForVehicle(
            MainService::query()->where('main_services.operational_status', 'active'),
            $vehicle,
        );

        if (! $requiresPurchaseOrder) {
            return $query;
        }

        if ($purchaseOrder !== null) {
            return $query->whereIn('main_services.id', PurchaseOrderService::query()
                ->where('purchase_order_id', $purchaseOrder->id)->select('main_service_id'));
        }

        $orders = self::purchaseOrders($project)->reorder()->select('purchase_orders.id');

        if (! $orders->exists()) {
            return $query;
        }

        return $query->whereIn('main_services.id', PurchaseOrderService::query()
            ->whereIn('purchase_order_id', $orders)->select('main_service_id'));
    }

    // 5. Sub-services

    /**
     * Active sub-services of the main service that suit the trip's container (TripCompatibility::container()).
     *
     * @param  array{type: string, capacity: AssetCapacityCategory}|null  $container
     */
    public static function subServices(int $mainServiceId, ?array $container): Builder
    {
        return TripCompatibility::subServicesForContainer(
            SubService::query()
                ->where('sub_services.main_service_id', $mainServiceId)
                ->where('sub_services.operational_status', 'active'),
            $container,
        );
    }

    // 6. Capacity

    /** The capacity that bounds the trip's quantity (TripCapacity), or null — no container yet, or a supplying trip. */
    public static function capacity(?Vehicle $vehicle, ?MainService $mainService, ?Asset $returning): ?AssetCapacityCategory
    {
        $trip = new Trip;
        $trip->setRelation('vehicle', $vehicle?->loadMissing('embeddedCapacityCategory'));
        $trip->setRelation('mainService', $mainService);
        $trip->setRelation('returningAsset', $returning?->loadMissing('capacityCategory'));

        return TripCapacity::of($trip);
    }

    // 7. Containers

    /**
     * Going containers (ج3, item 5): available (ج4, not held by another unfinished trip), in the pool —
     * not placed at a project — and compatible with the vehicle's category.
     */
    public static function goingAssets(Vehicle $vehicle, ?int $exceptTrip = null): Builder
    {
        return TripCompatibility::assetsForVehicle(
            TripResourceAvailability::assets(Asset::query(), $exceptTrip)->whereNull('assets.project_id'),
            $vehicle,
        );
    }

    /**
     * Returning containers: the available assets placed at the project, plus the going container of the
     * same form (it may come back the same — ج3). None for a vehicle with an embedded container.
     */
    public static function returningAssets(Project $project, ?Vehicle $vehicle = null, ?int $goingAssetId = null, ?int $exceptTrip = null): Builder
    {
        $query = TripResourceAvailability::assets(Asset::query(), $exceptTrip)
            ->where(fn (Builder $q) => $q
                ->where('assets.project_id', $project->id)
                ->when($goingAssetId !== null, fn (Builder $q) => $q->orWhere('assets.id', $goingAssetId)));

        return $vehicle !== null && ! TripCompatibility::takesExternalContainers($vehicle)
            ? $query->whereRaw('1 = 0')
            : $query;
    }

    // 8. Facilities

    public static function facilities(SubService $sub): Builder
    {
        return IntermediateFacility::query()
            ->where('intermediate_facilities.operational_status', 'active')
            ->whereIn('intermediate_facilities.id', fn ($q) => $q
                ->select('intermediate_facility_id')->from('sub_service_facility')->where('sub_service_id', $sub->id));
    }
}
