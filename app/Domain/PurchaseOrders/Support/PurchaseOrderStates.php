<?php

namespace App\Domain\PurchaseOrders\Support;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\QuotaBalance;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * PurchaseOrderStatus::describe() for MANY purchase orders with one ledger
 * query instead of one per order — what the list, the export and the
 * "general status" filter of part 6.2 need. It builds the same array from the
 * same public pieces (QuotaBalances, PurchaseOrderStatus), so the rules stay
 * in the Quota classes; a test pins the two to each other.
 *
 * attach() puts the array on each order as the `describedState` relation,
 * where PurchaseOrderResource and the exports read it, so a page of POs never
 * pays the per-order query.
 */
final class PurchaseOrderStates
{
    public const RELATION = 'describedState';

    /**
     * @param  iterable<PurchaseOrder>  $orders  with `services` loaded (loaded here when missing)
     * @return array<int, array<string, mixed>> PO id → what PurchaseOrderStatus::describe() returns
     */
    public static function forMany(iterable $orders, ?CarbonInterface $asOf = null): array
    {
        $orders = new EloquentCollection(is_array($orders) ? $orders : iterator_to_array($orders, false));
        $orders->loadMissing('services');

        $lines = new EloquentCollection;
        foreach ($orders as $order) {
            foreach ($order->services as $line) {
                $line->setRelation('purchaseOrder', $order);
                $lines->push($line);
            }
        }

        $balances = QuotaBalances::forMany($lines, $asOf);
        $day = ($asOf ?? now())->copy()->startOfDay();

        $states = [];

        foreach ($orders as $order) {
            $own = [];
            foreach ($order->services as $line) {
                $own[$line->id] = $balances[$line->id];
            }

            $dates = QuotaBalances::dates($order->starts_on, $order->ends_on, $day);
            $status = PurchaseOrderStatus::order($own);

            $states[$order->id] = [
                'status' => $status,
                'status_label' => PurchaseOrderStatus::orderLabel($status),
                'timeline' => $dates['timeline'],
                'timeline_label' => PurchaseOrderStatus::timelineLabel($dates['timeline']),
                'days_remaining' => $dates['timeline'] === QuotaBalance::TIMELINE_RUNNING ? $dates['days_remaining'] : null,
                'days_until_start' => $dates['days_until_start'],
                'days_since_end' => $dates['days_since_end'],
                'days_label' => PurchaseOrderStatus::daysLabel($dates['timeline'], $dates['days_remaining'], $dates['days_until_start'], $dates['days_since_end']),
                'services' => array_map(fn (QuotaBalance $b) => PurchaseOrderStatus::describeLine($b), $own),
            ];
        }

        return $states;
    }

    /**
     * Computes the states of `$orders` and hangs each on its order.
     *
     * @param  iterable<PurchaseOrder>  $orders
     * @return Collection<int, PurchaseOrder> the same orders
     */
    public static function attach(iterable $orders, ?CarbonInterface $asOf = null): Collection
    {
        $orders = collect(is_array($orders) ? $orders : iterator_to_array($orders, false));
        $states = self::forMany($orders, $asOf);

        foreach ($orders as $order) {
            $order->setRelation(self::RELATION, $states[$order->id]);
        }

        return $orders;
    }

    /** The state attached to `$order`, or computed for it alone. */
    public static function of(PurchaseOrder $order): array
    {
        return $order->relationLoaded(self::RELATION)
            ? $order->getRelation(self::RELATION)
            : PurchaseOrderStatus::describe($order->loadMissing('services'));
    }
}
