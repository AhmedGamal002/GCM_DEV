<?php

namespace App\Domain\Trips\Options;

use App\Models\Asset;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Who and what may be picked for a trip — decision ج4 (2026-11-09, product
 * owner) in one class, used by the form's lists (TripOptions) and by the
 * server re-check of a saved trip (TripChoiceCheck):
 *
 *  1. Affiliation is no condition: a contractor's driver may drive a GCM
 *     vehicle or another contractor's, and the other way round.
 *  2. Only ACTIVE resources, even for a trip recorded after the fact: a
 *     driver on vacation or deactivated, a vehicle / asset on maintenance or
 *     deactivated is not offered (unlike the PO, which a trip may still be
 *     charged to after it ended — ب6 is about POs only).
 *  3. The resources of a deactivated contractor (its drivers, vehicles and
 *     assets) are not offered for new trips; old trips keep them.
 *
 * Plus our reading of ج3 (change it here if corrected): a trip that is not
 * finished yet (neither completed nor cancelled) RESERVES its going and
 * returning containers — another trip can't pick them until it is.
 *
 * Readings of ours on top of ج4: a driver's default vehicle that is not
 * available is not prefilled (TripOptions), and a returning container of a
 * deactivated contractor is not offered either.
 */
final class TripResourceAvailability
{
    /** Drivers that may drive a new trip: the account is active and the contractor (if any) is active. */
    public static function drivers(Builder $query): Builder
    {
        return $query
            ->whereHas('user', fn (Builder $u) => $u->where('users.status', 'active'))
            ->where(fn (Builder $q) => self::contractorActive($q, 'drivers.contractor_id'));
    }

    /** Vehicles that may be used on a new trip: active, and their contractor (if any) is active. */
    public static function vehicles(Builder $query): Builder
    {
        return $query
            ->where('vehicles.operational_status', 'active')
            ->where(fn (Builder $q) => self::contractorActive($q, 'vehicles.contractor_id'));
    }

    /**
     * Assets that may go on a new trip: active, their contractor (if any) active, and not reserved by
     * another unfinished trip. `$exceptTrip` is the trip being edited — its own containers stay free for it.
     */
    public static function assets(Builder $query, ?int $exceptTrip = null): Builder
    {
        return $query
            ->where('assets.operational_status', 'active')
            ->where(fn (Builder $q) => self::contractorActive($q, 'assets.contractor_id'))
            ->whereNotExists(fn ($q) => self::openTripsHolding($q, $exceptTrip));
    }

    public static function isDriverAvailable(Driver $driver): bool
    {
        return self::drivers(Driver::query())->whereKey($driver->id)->exists();
    }

    public static function isVehicleAvailable(Vehicle $vehicle): bool
    {
        return self::vehicles(Vehicle::query())->whereKey($vehicle->id)->exists();
    }

    public static function isAssetAvailable(Asset $asset, ?int $exceptTrip = null): bool
    {
        return self::assets(Asset::query(), $exceptTrip)->whereKey($asset->id)->exists();
    }

    /**
     * isAssetAvailable() for a trip about to be saved (call it inside the save's transaction): the asset
     * row is locked first, so two saves picking the same container run one after the other, and the
     * open-trip test is a LOCKING read — a plain read (or the NOT EXISTS subquery above) would see the
     * transaction's older snapshot on MySQL and miss the trip the other save just committed (CLAUDE.md:
     * "any check that decides a write reads with a lock, and with a join, not whereHas").
     */
    public static function isAssetAvailableForSave(Asset $asset, ?int $exceptTrip = null): bool
    {
        $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->first();

        if ($locked === null || ! self::assets(Asset::query(), $exceptTrip)->whereKey($asset->id)->exists()) {
            return false;
        }

        return ! Trip::query()
            ->whereNotIn('trips.status', [Trip::STATUS_COMPLETED, Trip::STATUS_CANCELLED])
            ->where(fn (Builder $q) => $q->where('trips.going_asset_id', $asset->id)->orWhere('trips.returning_asset_id', $asset->id))
            ->when($exceptTrip !== null, fn (Builder $q) => $q->whereKeyNot($exceptTrip))
            ->lockForUpdate()
            ->exists();
    }

    /**
     * "An unfinished trip (other than `$exceptTrip`) holds assets.id" — the body of a NOT EXISTS. Asset ids
     * are unique across tenants and a trip only ever points at its own tenant's assets, so no tenant filter.
     */
    private static function openTripsHolding(QueryBuilder $query, ?int $exceptTrip): void
    {
        $query->selectRaw('1')->from('trips')
            ->whereNotIn('trips.status', [Trip::STATUS_COMPLETED, Trip::STATUS_CANCELLED])
            ->where(fn (QueryBuilder $q) => $q
                ->whereColumn('trips.going_asset_id', 'assets.id')
                ->orWhereColumn('trips.returning_asset_id', 'assets.id'))
            ->when($exceptTrip !== null, fn (QueryBuilder $q) => $q->where('trips.id', '!=', $exceptTrip));
    }

    /** GCM's own resource, or a contractor that is active. */
    private static function contractorActive(Builder $query, string $column): void
    {
        $query->whereNull($column)->orWhereIn($column, fn ($q) => $q
            ->select('id')->from('contractors')->where('operational_status', 'active'));
    }
}
