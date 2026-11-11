<?php

namespace App\Domain\PurchaseOrders\Rules;

use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ServiceExhaustion;
use App\Models\PurchaseOrderService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * FRD V01.14 §1.13: in one project, a main service can't go into a new PO
 * while another PO that shares even one day of the period already includes
 * it — unless that service's items in the other PO have run out ("فندق
 * الرياض", PO 1200: the new PO may include the service once 1200's trips or
 * cost are used up, because "the service then has no balance for new trips").
 *
 * With decision ب5 (2026-11-09): one exhausted component is enough
 * (ServiceExhaustion — date, trips or cost), the check also runs when a PO's
 * dates are edited and when a deactivated PO is re-activated, and a
 * deactivated PO never blocks anything (nor is it checked while it stays
 * deactivated).
 *
 * The callers (the PO actions) run it inside their transaction after locking
 * the project row, so two POs created at the same moment can't both pass —
 * and it reads with locking reads, so the one that waited sees the other's
 * PO (checked with parallel processes on MySQL).
 */
final class NoOverlappingMainServiceRule
{
    /**
     * The lines of OTHER active POs of the project that block this main
     * service over this period.
     *
     * @return Collection<int, PurchaseOrderService>
     */
    public static function conflicts(int $projectId, int $mainServiceId, CarbonInterface|string $startsOn, CarbonInterface|string $endsOn, ?int $ignorePurchaseOrderId = null): Collection
    {
        $from = Carbon::parse($startsOn)->toDateString();
        $to = Carbon::parse($endsOn)->toDateString();

        // A join (not whereHas) under a shared lock: on MySQL a locking read sees the latest
        // committed rows of every joined table, while a subquery would read the transaction's
        // snapshot — which may predate a PO another admin committed while we waited for the
        // project lock (two overlapping POs created at the same moment both passed that way).
        $candidates = PurchaseOrderService::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_services.purchase_order_id')
            ->where('purchase_order_services.main_service_id', $mainServiceId)
            ->where('purchase_orders.project_id', $projectId)
            ->where('purchase_orders.operational_status', 'active')
            ->whereDate('purchase_orders.starts_on', '<=', $to)
            ->whereDate('purchase_orders.ends_on', '>=', $from)
            ->when($ignorePurchaseOrderId, fn ($q) => $q->where('purchase_orders.id', '!=', $ignorePurchaseOrderId))
            ->select('purchase_order_services.*')
            ->orderBy('purchase_order_services.id')
            ->sharedLock()
            ->get();

        // Same reason: a plain read of a PO committed after our snapshot would find nothing.
        $candidates->load(['purchaseOrder' => fn ($q) => $q->sharedLock()]);
        $balances = QuotaBalances::forMany($candidates, null, true);

        return $candidates
            ->filter(fn (PurchaseOrderService $line) => ! ServiceExhaustion::isExhausted($balances[$line->id]))
            ->values();
    }

    /**
     * Throws a 422 naming each blocked line of the input.
     *
     * @param  array<int|string, int>  $mainServiceIds  the PO's main services, keyed like its `services` input
     * @param  string|null  $errorField  put every message under this one field instead (e.g. `status`
     *                                   when a deactivated PO is re-activated and no lines were sent)
     *
     * @throws ValidationException
     */
    public static function assertNone(int $projectId, array $mainServiceIds, CarbonInterface|string $startsOn, CarbonInterface|string $endsOn, ?int $ignorePurchaseOrderId = null, ?string $errorField = null): void
    {
        $errors = [];

        foreach ($mainServiceIds as $key => $mainServiceId) {
            $blocking = self::conflicts($projectId, (int) $mainServiceId, $startsOn, $endsOn, $ignorePurchaseOrderId)->first();

            if ($blocking !== null) {
                $po = $blocking->purchaseOrder;
                $errors[$errorField ?? "services.{$key}.main_service_id"][] = __('This main service is already in purchase order :number of this project (:from to :to), and its items there have not run out yet.', [
                    'number' => $po->number,
                    'from' => $po->starts_on->toDateString(),
                    'to' => $po->ends_on->toDateString(),
                ]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
