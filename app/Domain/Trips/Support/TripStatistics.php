<?php

namespace App\Domain\Trips\Support;

use App\Models\Asset;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;

/**
 * The trip figures the other modules' details pages show (FRD V01.14: the statistics cards of a client company, a
 * project, a main / sub service and a vehicle) — in one place so every page counts the same way.
 *
 * A cancelled trip never counts: it is "as if it was not recorded" for quantities (ج2), and it doesn't use a service
 * or a resource (ج5). Every other status counts, whether the client approved it yet or not. The quantity is the
 * waste moved (a supply trip has none — ج11 — so it adds nothing). Both quantities are summed as stored (the
 * converter fixed them when the trip was saved, ج10).
 *
 * Each call is a handful of aggregate queries over the indexed foreign key — meant for ONE record's details page,
 * not for a list row (lists use withCount).
 */
final class TripStatistics
{
    /** @return array{trips: int, waste_tons: float} */
    public static function forCompany(int $companyId): array
    {
        return self::summary(self::counted()->where('company_id', $companyId));
    }

    /** @return array{trips: int, waste_tons: float} */
    public static function forProject(int $projectId): array
    {
        return self::summary(self::counted()->where('project_id', $projectId));
    }

    /** @return array{trips: int, projects: int, quantity_ton: float, quantity_cbm: float, drivers: int, vehicles: int, containers: int, tanks: int} */
    public static function forMainService(int $mainServiceId): array
    {
        return self::forService(self::counted()->where('main_service_id', $mainServiceId));
    }

    /** @return array{trips: int, projects: int, quantity_ton: float, quantity_cbm: float, drivers: int, vehicles: int, containers: int, tanks: int} */
    public static function forSubService(int $subServiceId): array
    {
        return self::forService(self::counted()->where('sub_service_id', $subServiceId));
    }

    /** @return array{trips: int, waste_tons: float} */
    public static function forVehicle(int $vehicleId): array
    {
        return self::summary(self::counted()->where('vehicle_id', $vehicleId));
    }

    /** The trips that count: everything but the cancelled. */
    public static function counted(): Builder
    {
        return Trip::query()->where('status', '!=', Trip::STATUS_CANCELLED);
    }

    /** @return array{trips: int, waste_tons: float} */
    private static function summary(Builder $trips): array
    {
        $row = (clone $trips)->selectRaw('COUNT(*) as trips, COALESCE(SUM(quantity_ton), 0) as tons')->toBase()->first();

        return ['trips' => (int) $row->trips, 'waste_tons' => round((float) $row->tons, 3)];
    }

    private static function forService(Builder $trips): array
    {
        $row = (clone $trips)->selectRaw(
            'COUNT(*) as trips, COUNT(DISTINCT project_id) as projects, COUNT(DISTINCT driver_id) as drivers, COUNT(DISTINCT vehicle_id) as vehicles, '.
            'COALESCE(SUM(quantity_ton), 0) as tons, COALESCE(SUM(quantity_cbm), 0) as cbm'
        )->toBase()->first();

        // The containers and tanks the trips moved: every asset that was a going or a returning container, once.
        $assets = Asset::query()
            ->where(fn ($q) => $q
                ->whereIn('id', (clone $trips)->whereNotNull('going_asset_id')->select('going_asset_id'))
                ->orWhereIn('id', (clone $trips)->whereNotNull('returning_asset_id')->select('returning_asset_id')))
            ->selectRaw('asset_type, COUNT(*) as total')
            ->groupBy('asset_type')
            ->pluck('total', 'asset_type');

        return [
            'trips' => (int) $row->trips,
            'projects' => (int) $row->projects,
            'quantity_ton' => round((float) $row->tons, 3),
            'quantity_cbm' => round((float) $row->cbm, 3),
            'drivers' => (int) $row->drivers,
            'vehicles' => (int) $row->vehicles,
            'containers' => (int) ($assets['container'] ?? 0),
            'tanks' => (int) ($assets['tank'] ?? 0),
        ];
    }
}
