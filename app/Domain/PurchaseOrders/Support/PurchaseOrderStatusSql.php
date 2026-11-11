<?php

namespace App\Domain\PurchaseOrders\Support;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The SQL twin of PurchaseOrderStatus — "الحالة العامة للبنود" as a database
 * condition, so the list can FILTER and SORT by it (and count it) in the
 * database like every other list, instead of loading the POs to decide in PHP.
 * Same shape as ExpiryWarning::filterByOverallStatus() for the documents status.
 *
 * It mirrors PurchaseOrderStatus / ServiceExhaustion exactly, for the day the
 * list is read on (today, app timezone) and ALL the ledger rows (the default
 * balance read):
 *
 *  A LINE is
 *   - exhausted (2) when ANY component ran out: the PO's dates ended; no trips
 *     left; the trip-cost total is used up (when it is above 0); the ancillary
 *     total is used up (when the line counts it and it is above 0);
 *   - near (1) when no component ran out but one is STRICTLY under the line's
 *     alert percentage of its max: the days left of the whole duration (only
 *     while the PO is running), the trips, the trip-cost total, the ancillary
 *     total — each on its own;
 *   - valid (0) otherwise.
 *  A PO is exhausted when EVERY line is exhausted, near when any line is near
 *  or exhausted, valid when every line is valid (a PO with no line is valid).
 *
 * Money is compared in whole halalas (ROUND(x * 100)) like QuotaBalance::cents(),
 * so 0.1 + 0.2 never lands just under a limit. Day counts are whole days, both
 * ends of the PO included (QuotaBalances::dates()).
 *
 * *** Change PurchaseOrderStatus or ServiceExhaustion → change this too. ***
 * PurchaseOrderStatusSqlTest runs both over the edge cases (the boundary of
 * every component, several lines, a released trip...) and fails if they differ.
 */
final class PurchaseOrderStatusSql
{
    /** The rank of a line / PO, as the database sees it. */
    public const VALID = 0;

    public const NEAR = 1;

    public const EXHAUSTED = 2;

    /**
     * Narrows `$query` (over purchase_orders) to the POs whose items status
     * is `$status` (one of PurchaseOrderStatus::STATUSES).
     */
    public static function filter(Builder $query, string $status): Builder
    {
        $ofStatus = self::orders()->select('purchase_order_id');

        match ($status) {
            PurchaseOrderStatus::EXHAUSTED => $ofStatus->havingRaw('MIN(line_status) = ?', [self::EXHAUSTED]),
            PurchaseOrderStatus::NEAR_EXHAUSTION => $ofStatus->havingRaw('MAX(line_status) >= ? AND MIN(line_status) < ?', [self::NEAR, self::EXHAUSTED]),
            PurchaseOrderStatus::VALID => $ofStatus->havingRaw('MAX(line_status) = ?', [self::VALID]),
            default => null,
        };

        if (! in_array($status, PurchaseOrderStatus::STATUSES, true)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($ofStatus, $status) {
            $q->whereIn('purchase_orders.id', $ofStatus);

            // A PO with no line has nothing to run out: valid (PurchaseOrderStatus::order([])).
            if ($status === PurchaseOrderStatus::VALID) {
                $q->orWhereNotExists(fn ($lines) => $lines->from('purchase_order_services as po_lines')->whereColumn('po_lines.purchase_order_id', 'purchase_orders.id'));
            }
        });
    }

    /**
     * Orders `$query` (over purchase_orders) by the items status: valid first
     * ascending, exhausted first descending. Left-joins the rank, so keep
     * selecting `purchase_orders.*` (done here).
     */
    public static function orderBy(Builder $query, string $direction): Builder
    {
        $rank = self::orders()->selectRaw('purchase_order_id, CASE WHEN MIN(line_status) = ? THEN ? WHEN MAX(line_status) >= ? THEN ? ELSE ? END AS status_rank', [
            self::EXHAUSTED, self::EXHAUSTED, self::NEAR, self::NEAR, self::VALID,
        ]);

        return $query
            ->leftJoinSub($rank, 'po_status', 'po_status.purchase_order_id', '=', 'purchase_orders.id')
            ->select('purchase_orders.*')
            ->orderByRaw('COALESCE(po_status.status_rank, 0) '.($direction === 'asc' ? 'asc' : 'desc'));
    }

    /**
     * One row per line of the tenant: its id, its PO id and its rank (0 valid,
     * 1 near, 2 exhausted). Public so the parity test can compare it with
     * PurchaseOrderStatus::line() line by line.
     */
    public static function lines(): QueryBuilder
    {
        $tenantId = app('tenant')->id;
        $today = now()->toDateString();

        // What each line has used, from the append-only ledger (every row, like QuotaBalances::forMany()).
        $used = DB::table('quota_ledger_entries')
            ->where('tenant_id', $tenantId)
            ->groupBy('purchase_order_service_id')
            ->selectRaw('purchase_order_service_id, SUM(trips_delta) AS trips, SUM(trip_cost_delta) AS trip_cost, SUM(ancillary_cost_delta) AS ancillary');

        $toEnd = self::days('p.ends_on', '?');       // < 0 once the PO ended
        $fromStart = self::days('?', 'p.starts_on'); // < 0 before it starts
        $total = self::days('p.ends_on', 'p.starts_on');

        $figures = DB::table('purchase_order_services as s')
            ->join('purchase_orders as p', 'p.id', '=', 's.purchase_order_id')
            ->leftJoinSub($used, 'u', 'u.purchase_order_service_id', '=', 's.id')
            ->where('s.tenant_id', $tenantId)
            ->selectRaw(<<<SQL
                s.id AS line_id,
                s.purchase_order_id AS purchase_order_id,
                s.alert_percent AS alert,
                s.max_trips AS max_trips,
                COALESCE(u.trips, 0) AS used_trips,
                ROUND(s.max_trips * s.trip_cost * 100) AS max_trip_cost,
                ROUND(COALESCE(u.trip_cost, 0) * 100) AS used_trip_cost,
                CASE WHEN s.quota_method = 'trips_and_ancillary' THEN ROUND(s.max_trips * s.ancillary_cost_per_trip * 100) ELSE 0 END AS max_ancillary,
                ROUND(COALESCE(u.ancillary, 0) * 100) AS used_ancillary,
                {$total} + 1 AS total_days,
                {$toEnd} AS to_end,
                {$fromStart} AS from_start
                SQL, [$today, $today]);

        return DB::query()->fromSub($figures, 'f')->selectRaw(<<<'SQL'
            purchase_order_id,
            line_id,
            CASE
                WHEN to_end < 0
                    OR max_trips - used_trips <= 0
                    OR (max_trip_cost > 0 AND max_trip_cost - used_trip_cost <= 0)
                    OR (max_ancillary > 0 AND max_ancillary - used_ancillary <= 0)
                    THEN 2
                WHEN (from_start >= 0 AND to_end >= 0 AND (to_end + 1) * 100 < alert * total_days)
                    OR (max_trips - used_trips) * 100 < alert * max_trips
                    OR (max_trip_cost > 0 AND (max_trip_cost - used_trip_cost) * 100 < alert * max_trip_cost)
                    OR (max_ancillary > 0 AND (max_ancillary - used_ancillary) * 100 < alert * max_ancillary)
                    THEN 1
                ELSE 0
            END AS line_status
            SQL);
    }

    /** One row per PO that has lines, grouped from lines(): `purchase_order_id` + `line_status` to aggregate. */
    private static function orders(): QueryBuilder
    {
        return DB::query()->fromSub(self::lines(), 'l')->groupBy('purchase_order_id');
    }

    /** `$later - $earlier` in whole days, whichever database runs (the tests use SQLite, production MySQL). */
    private static function days(string $later, string $earlier): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(ROUND(julianday({$later}) - julianday({$earlier})) AS INTEGER)"
            : "DATEDIFF({$later}, {$earlier})";
    }
}
