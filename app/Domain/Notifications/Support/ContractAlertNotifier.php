<?php

namespace App\Domain\Notifications\Support;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The contract alert (decision ب9, 2026-11-09): when a PO line reaches "about to run out" or "exhausted" everyone
 * who may create POs is told, once per change of status.
 *
 * "Once" is kept in `purchase_order_services.alert_status` — the status the line was last notified about (null =
 * valid). A write (a trip deducted or released, a quota edit) reaches here through PurchaseOrderLineStatusChanged,
 * the calendar alone (the date part crossing its alert line, the PO ending) through the daily check
 * (`notifications:check-purchase-orders`); both end in sync(), which moves the column with one conditional UPDATE so
 * two writers can't both notify. A line that goes back to valid and later runs low again is notified again.
 * Readings of ours: in-app only (no e-mail), and the recipients are asked of the Policy — `create` on
 * PurchaseOrder, i.e. the System Admin today — so they follow it if it changes.
 */
final class ContractAlertNotifier
{
    /**
     * Records `$status` as the line's alert status and notifies when it is a bad one that wasn't notified yet.
     *
     * @return int how many notifications were written
     */
    public static function sync(PurchaseOrderService $line, string $status): int
    {
        $stored = $status === PurchaseOrderStatus::VALID ? null : $status;

        $changed = PurchaseOrderService::query()->withoutGlobalScopes()
            ->whereKey($line->id)
            ->where(fn ($q) => $stored === null
                ? $q->whereNotNull('alert_status')
                : $q->whereNull('alert_status')->orWhere('alert_status', '!=', $stored))
            ->update(['alert_status' => $stored]);

        if ($changed === 0 || $stored === null) {
            return 0;
        }

        $line->loadMissing('purchaseOrder', 'mainService');
        $po = $line->purchaseOrder;

        // An inactive PO is never offered to a new trip: nothing to warn about.
        if ($po->operational_status !== 'active') {
            return 0;
        }

        return Notifier::send(
            (int) $line->tenant_id,
            $stored === PurchaseOrderStatus::EXHAUSTED ? NotificationType::PO_EXHAUSTED : NotificationType::PO_NEAR_EXHAUSTION,
            self::recipients((int) $line->tenant_id),
            ['po' => (string) $po->number, 'service' => $line->mainService?->name ?? '', 'line_id' => $line->id, 'status' => $stored],
            purchaseOrderId: $po->id,
            subject: $po->number,
        );
    }

    /**
     * The daily check of one PO: every line's CURRENT status against the one it was last notified about.
     *
     * @return int notifications written
     */
    public static function checkOrder(PurchaseOrder $po): int
    {
        $po->loadMissing('services.mainService');
        $state = PurchaseOrderStatus::describe($po);
        $written = 0;

        foreach ($po->services as $line) {
            $written += self::sync($line, $state['services'][$line->id]['status'] ?? PurchaseOrderStatus::VALID);
        }

        return $written;
    }

    /** @return Collection<int, User> */
    private static function recipients(int $tenantId): Collection
    {
        return User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'on_vacation'])
            ->get()
            ->filter(fn (User $user) => Gate::forUser($user)->allows('create', PurchaseOrder::class))
            ->values();
    }
}
