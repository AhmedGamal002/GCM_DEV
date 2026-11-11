<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the balance of PO lines from the ledger: the used part is the sum of
 * the line's ledger rows, the maximums come from the line itself (so a quota
 * edit applies at once), and the days from its PO's date range.
 *
 * `$asOf` is the day the balance is read on (default: today, Riyadh time):
 * it sets the day counts, and only the ledger rows written up to the end of
 * that day count.
 *
 * Lists use forMany() — one grouped query for any number of lines.
 *
 * `$locking` is for the writers that already hold the line's row lock
 * (Deduct / Release / the PO edit): it makes the sum a locking read, which
 * on MySQL always sees the latest committed rows. A plain read inside a
 * longer transaction (a trip action that read something before charging the
 * PO) would otherwise see the snapshot taken at its first read and miss
 * what another transaction committed while it waited for the lock.
 */
final class QuotaBalances
{
    public static function for(PurchaseOrderService $line, ?CarbonInterface $asOf = null, bool $locking = false): QuotaBalance
    {
        return self::forMany([$line], $asOf, $locking)[$line->id];
    }

    /**
     * @param  iterable<PurchaseOrderService>  $lines
     * @return array<int, QuotaBalance> keyed by line id
     */
    public static function forMany(iterable $lines, ?CarbonInterface $asOf = null, bool $locking = false): array
    {
        $lines = $lines instanceof Collection ? $lines : new Collection(is_array($lines) ? $lines : iterator_to_array($lines));

        if ($lines->isEmpty()) {
            return [];
        }

        $lines->loadMissing('purchaseOrder');

        $used = QuotaLedgerEntry::query()
            ->whereIn('purchase_order_service_id', $lines->modelKeys())
            ->when($asOf, fn ($q) => $q->where('occurred_at', '<=', $asOf->copy()->endOfDay()))
            ->groupBy('purchase_order_service_id')
            ->selectRaw('purchase_order_service_id, SUM(trips_delta) AS trips, SUM(trip_cost_delta) AS trip_cost, SUM(ancillary_cost_delta) AS ancillary')
            ->when($locking, fn ($q) => $q->sharedLock())
            ->toBase()
            ->get()
            ->keyBy('purchase_order_service_id');

        $day = ($asOf ?? now())->copy()->startOfDay();

        $balances = [];

        foreach ($lines as $line) {
            $row = $used->get($line->id);
            $balances[$line->id] = self::build($line, $row, $day);
        }

        return $balances;
    }

    /**
     * Where `$day` falls in a PO's date range (both ends included).
     *
     * @return array{timeline: string, total_days: int, days_remaining: int, days_until_start: ?int, days_since_end: ?int}
     */
    public static function dates(CarbonInterface $startsOn, CarbonInterface $endsOn, CarbonInterface $day): array
    {
        $starts = $startsOn->copy()->startOfDay();
        $ends = $endsOn->copy()->startOfDay();
        $day = $day->copy()->startOfDay();
        $totalDays = self::days($starts, $ends) + 1;

        if ($day->lt($starts)) {
            return ['timeline' => QuotaBalance::TIMELINE_NOT_STARTED, 'total_days' => $totalDays, 'days_remaining' => $totalDays, 'days_until_start' => self::days($day, $starts), 'days_since_end' => null];
        }

        if ($day->gt($ends)) {
            return ['timeline' => QuotaBalance::TIMELINE_ENDED, 'total_days' => $totalDays, 'days_remaining' => 0, 'days_until_start' => null, 'days_since_end' => self::days($ends, $day)];
        }

        return ['timeline' => QuotaBalance::TIMELINE_RUNNING, 'total_days' => $totalDays, 'days_remaining' => self::days($day, $ends) + 1, 'days_until_start' => null, 'days_since_end' => null];
    }

    private static function build(PurchaseOrderService $line, ?object $used, CarbonInterface $day): QuotaBalance
    {
        $po = $line->purchaseOrder;
        $dates = self::dates($po->starts_on, $po->ends_on, $day);

        return new QuotaBalance(
            tripsUsed: (int) ($used->trips ?? 0),
            tripsMax: (int) $line->max_trips,
            tripCostUsed: round((float) ($used->trip_cost ?? 0), 2),
            tripCostMax: $line->maxTripCost(),
            ancillaryUsed: round((float) ($used->ancillary ?? 0), 2),
            ancillaryMax: $line->maxAncillaryCost(),
            daysRemaining: $dates['days_remaining'],
            totalDays: $dates['total_days'],
            timeline: $dates['timeline'],
            daysUntilStart: $dates['days_until_start'],
            daysSinceEnd: $dates['days_since_end'],
            alertPercent: (int) $line->alert_percent,
            tracksAncillary: $line->tracksAncillary(),
        );
    }

    private static function days(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) round($from->diffInDays($to, true));
    }
}
