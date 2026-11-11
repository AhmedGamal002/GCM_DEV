<?php

namespace App\Domain\PurchaseOrders\Quota;

/**
 * Decision ب5 (2026-11-09): when does a PO line stop "holding" its main
 * service, so a new PO for the same project + service may overlap it?
 *
 * As soon as ANY ONE of its components has run out — the date range has
 * ended, the trips are used up, the trip-cost total is used up, or (when the
 * line counts it) the ancillary-cost total is used up. The FRD's example: PO
 * 1200 of the Riyadh hotel, 90 trips / 9,000 SAR — once its trips or its cost
 * run out, "the service has no balance left for new trips" and a new PO may
 * include it even inside 1200's period.
 *
 * The same test marks a line "exhausted" in PurchaseOrderStatus, so the PO
 * pages and the overlap rule never disagree.
 */
final class ServiceExhaustion
{
    public const DATE = 'date';

    public const TRIPS = 'trips';

    public const TRIP_COST = 'trip_cost';

    public const ANCILLARY_COST = 'ancillary_cost';

    public static function isExhausted(QuotaBalance $balance): bool
    {
        return self::exhaustedComponents($balance) !== [];
    }

    /** @return list<string> the components that ran out (DATE, TRIPS, TRIP_COST, ANCILLARY_COST) */
    public static function exhaustedComponents(QuotaBalance $balance): array
    {
        $out = [];

        if ($balance->timeline === QuotaBalance::TIMELINE_ENDED) {
            $out[] = self::DATE;
        }

        if ($balance->tripsRemaining() <= 0) {
            $out[] = self::TRIPS;
        }

        if ($balance->tripCostMax > 0 && QuotaBalance::cents($balance->tripCostRemaining()) <= 0) {
            $out[] = self::TRIP_COST;
        }

        if ($balance->tracksAncillary && $balance->ancillaryMax > 0 && QuotaBalance::cents($balance->ancillaryRemaining()) <= 0) {
            $out[] = self::ANCILLARY_COST;
        }

        return $out;
    }
}
