<?php

namespace App\Domain\Trips\Quota;

use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ServiceExhaustion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Which PO line a trip is charged to.
 *
 *  - Staff pick a PO of the project (decision ب6: ANY of the project's POs
 *    for the trip's main service — running or not, even with its balance
 *    used up or already negative — but never a deactivated one), and the
 *    line is that PO's line for the main service: resolve().
 *  - The driver picks no PO: the system charges the newest PO of the project
 *    that still has balance for the service (decision ب5): defaultFor().
 *    Our reading for the gaps (change it here if corrected): "still has
 *    balance" on the trip's date, preferring a PO whose period covers that
 *    date; when every PO of the service has run out, the newest one is still
 *    charged (ب6 — a trip is recorded after it happened; "without PO" is only
 *    for a project with no PO for the service, or the user's choice).
 */
final class TripPurchaseOrderLine
{
    /** The project's lines for the main service in POs that are not deactivated, newest PO first. */
    public static function candidates(Project $project, int $mainServiceId): Collection
    {
        return PurchaseOrderService::query()
            ->select('purchase_order_services.*')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_services.purchase_order_id')
            ->where('purchase_orders.project_id', $project->id)
            ->where('purchase_orders.operational_status', 'active')
            ->where('purchase_order_services.main_service_id', $mainServiceId)
            ->orderByDesc('purchase_orders.starts_on')
            ->orderByDesc('purchase_orders.id')
            ->with('purchaseOrder')
            ->get();
    }

    /** Whether the project has any PO (not deactivated) for the main service. */
    public static function projectHasAny(Project $project, int $mainServiceId): bool
    {
        return self::candidates($project, $mainServiceId)->isNotEmpty();
    }

    /** The line of the chosen PO for the trip's main service — or a validation error on `purchase_order_id`. */
    public static function resolve(Project $project, int $mainServiceId, mixed $purchaseOrderId): PurchaseOrderService
    {
        $order = is_numeric($purchaseOrderId) ? PurchaseOrder::find((int) $purchaseOrderId) : null;

        if ($order === null || (int) $order->project_id !== (int) $project->id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => __('Choose a purchase order of the trip\'s project.'),
            ]);
        }

        if (! $order->isActive()) {
            throw ValidationException::withMessages([
                'purchase_order_id' => __('This purchase order is deactivated; a trip cannot be charged to it.'),
            ]);
        }

        $line = $order->services()->where('main_service_id', $mainServiceId)->first();

        if ($line === null) {
            throw ValidationException::withMessages([
                'purchase_order_id' => __('This purchase order does not include the trip\'s main service.'),
            ]);
        }

        return $line->setRelation('purchaseOrder', $order);
    }

    /** The line a driver's trip is charged to, or null when the project has no PO for the service. */
    public static function defaultFor(Project $project, int $mainServiceId, CarbonInterface $at): ?PurchaseOrderService
    {
        $lines = self::candidates($project, $mainServiceId);

        if ($lines->isEmpty()) {
            return null;
        }

        $day = $at->toDateString();
        $balances = QuotaBalances::forMany($lines, $at);
        $hasBalance = fn (PurchaseOrderService $line) => ! ServiceExhaustion::isExhausted($balances[$line->id]);
        $covers = fn (PurchaseOrderService $line) => $line->purchaseOrder->starts_on->toDateString() <= $day
            && $line->purchaseOrder->ends_on->toDateString() >= $day;

        return $lines->first(fn ($l) => $covers($l) && $hasBalance($l))
            ?? $lines->first($hasBalance)
            ?? $lines->first();
    }
}
