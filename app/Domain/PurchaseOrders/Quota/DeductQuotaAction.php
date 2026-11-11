<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Domain\PurchaseOrders\Events\PurchaseOrderLineStatusChanged;
use App\Domain\PurchaseOrders\Exceptions\QuotaAlreadyConsumedException;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Charges one trip to a PO line: a `consume` row of +1 trip, +the trip cost
 * and +the ancillary cost (0 on a trips-only line).
 *
 * It never refuses for lack of balance — a trip is recorded after it
 * happened, so it is deducted from the line the user chose even if that
 * turns the balance negative (decision ب6). The caller (the trip actions,
 * part 7.1) decides which line, or "without PO"; QuotaBalance::canCover()
 * is there to warn, not to block.
 *
 * Runs under a row lock on the line so two trips charged at the same moment
 * are counted one after the other (and their status events see the right
 * before / after). A trip holds one open consumption at most: to move it to
 * another line, release it first.
 */
class DeductQuotaAction
{
    public function execute(PurchaseOrderService $service, int $tripId, float $tripCost, float $ancillaryCost, User $actor): QuotaLedgerEntry
    {
        if ($tripCost < 0 || $ancillaryCost < 0) {
            throw new InvalidArgumentException('Trip costs cannot be negative.');
        }

        return DB::transaction(function () use ($service, $tripId, $tripCost, $ancillaryCost, $actor) {
            $line = PurchaseOrderService::whereKey($service->id)->lockForUpdate()->firstOrFail();

            if (! $line->tracksAncillary() && QuotaBalance::cents($ancillaryCost) !== 0) {
                throw new InvalidArgumentException('This purchase order line counts trips only; it has no ancillary cost.');
            }

            // A guard against a caller bug, not a race: the trip actions (7.1) lock the trip row first.
            if (QuotaLedgerEntry::openConsumptionsOf($tripId)->exists()) {
                throw QuotaAlreadyConsumedException::forTrip($tripId);
            }

            // Locking reads: the latest committed rows, even inside a caller's longer transaction.
            $before = PurchaseOrderStatus::line(QuotaBalances::for($line, null, true));

            $entry = QuotaLedgerEntry::create([
                'purchase_order_service_id' => $line->id,
                'trip_id' => $tripId,
                'type' => QuotaLedgerEntry::TYPE_CONSUME,
                'trips_delta' => 1,
                'trip_cost_delta' => round($tripCost, 2),
                'ancillary_cost_delta' => round($ancillaryCost, 2),
                'occurred_at' => now(),
                'created_by' => $actor->id,
            ]);

            $after = PurchaseOrderStatus::line(QuotaBalances::for($line, null, true));

            if ($after !== $before) {
                PurchaseOrderLineStatusChanged::dispatch($line, $before, $after);
            }

            return $entry;
        });
    }
}
