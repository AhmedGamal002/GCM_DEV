<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Domain\PurchaseOrders\Events\PurchaseOrderLineStatusChanged;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives back what a trip consumed: for every `consume` row of the trip that
 * no release has reversed yet, a `release` row with the opposite deltas that
 * points at it (`reverses_id`). Used when a trip is cancelled ("as if it
 * never was", FRD) and when its date, project or service changes before it
 * is deducted again (decision ب7).
 *
 * Idempotent: a second call finds nothing open and writes nothing, also
 * when two releases of the same trip race each other — the lines involved
 * are locked in id order first (the same order everywhere, so two releases
 * can't deadlock), and the trip's rows are then read with a locking read,
 * so the second one sees what the first wrote. `reverses_id` is unique on
 * top of that, so no consume can ever be reversed twice. Returns how many
 * rows it wrote.
 */
class ReleaseQuotaAction
{
    public function execute(int $tripId, User $actor): int
    {
        return DB::transaction(function () use ($tripId, $actor) {
            $lineIds = QuotaLedgerEntry::openConsumptionsOf($tripId)->distinct()->pluck('purchase_order_service_id')->all();

            if ($lineIds === []) {
                return 0;
            }

            $lines = PurchaseOrderService::whereIn('id', $lineIds)->orderBy('id')->lockForUpdate()->get();

            // Re-read under the locks with a locking read (the latest committed rows, not this
            // transaction's snapshot): a release that ran meanwhile has already reversed some.
            $rows = QuotaLedgerEntry::where('trip_id', $tripId)->orderBy('id')->lockForUpdate()->get();
            $reversed = $rows->where('type', QuotaLedgerEntry::TYPE_RELEASE)->pluck('reverses_id')->filter()->map(fn ($id) => (int) $id)->all();
            $open = $rows
                ->where('type', QuotaLedgerEntry::TYPE_CONSUME)
                ->reject(fn (QuotaLedgerEntry $row) => in_array($row->id, $reversed, true))
                ->values();

            if ($open->isEmpty()) {
                return 0;
            }

            $before = array_map(fn (QuotaBalance $b) => PurchaseOrderStatus::line($b), QuotaBalances::forMany($lines, null, true));

            foreach ($open as $consume) {
                QuotaLedgerEntry::create([
                    'purchase_order_service_id' => $consume->purchase_order_service_id,
                    'trip_id' => $tripId,
                    'type' => QuotaLedgerEntry::TYPE_RELEASE,
                    'trips_delta' => -$consume->trips_delta,
                    'trip_cost_delta' => -(float) $consume->trip_cost_delta,
                    'ancillary_cost_delta' => -(float) $consume->ancillary_cost_delta,
                    'reverses_id' => $consume->id,
                    'occurred_at' => now(),
                    'created_by' => $actor->id,
                ]);
            }

            foreach (QuotaBalances::forMany($lines, null, true) as $lineId => $balance) {
                $after = PurchaseOrderStatus::line($balance);

                if ($after !== $before[$lineId]) {
                    PurchaseOrderLineStatusChanged::dispatch($lines->find($lineId), $before[$lineId], $after);
                }
            }

            return $open->count();
        });
    }
}
