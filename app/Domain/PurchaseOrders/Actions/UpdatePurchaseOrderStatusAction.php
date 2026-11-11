<?php

namespace App\Domain\PurchaseOrders\Actions;

use App\Domain\PurchaseOrders\Rules\NoOverlappingMainServiceRule;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FRD V01.14 §1.13: deactivating / re-activating a PO — System Admin only,
 * and outside the edit lock (decision ب2: allowed with or without trips).
 *
 * A deactivated PO drops out of everything live: it is not offered for new
 * trips (ب6), it doesn't block another PO (ب5) and it doesn't make its main
 * services "in a PO" (ج5). Re-activating it brings all of that back, so it is
 * refused when another active PO of the project now holds one of its
 * services over a shared day (ب5: the check runs on re-activation too). Its
 * number stays taken either way (ب4).
 */
class UpdatePurchaseOrderStatusAction
{
    public function execute(PurchaseOrder $purchaseOrder, string $status, User $actor): PurchaseOrder
    {
        if (! in_array($status, ['active', 'deactivated'], true)) {
            throw new InvalidArgumentException("Unknown purchase order status [{$status}].");
        }

        return DB::transaction(function () use ($purchaseOrder, $status, $actor) {
            $po = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if ($status === 'active' && ! $po->isActive()) {
                Project::whereKey($po->project_id)->lockForUpdate()->first();

                NoOverlappingMainServiceRule::assertNone(
                    $po->project_id,
                    $po->services()->pluck('main_service_id')->map(fn ($id) => (int) $id)->all(),
                    $po->starts_on,
                    $po->ends_on,
                    $po->id,
                    'status',
                );
            }

            // operational_status is outside $fillable — set explicitly.
            $po->operational_status = $status;
            $po->updated_by = $actor->id;
            $po->save();

            return $po->load(['company', 'project', 'services.mainService', 'updatedBy']);
        });
    }
}
