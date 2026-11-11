<?php

namespace App\Domain\PurchaseOrders\Events;

use App\Models\PurchaseOrderService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A PO line moved from one status to another (PurchaseOrderStatus::VALID /
 * NEAR_EXHAUSTION / EXHAUSTED) because of a write: a trip deducted from it
 * or released from it, or its quota edited. Fired once the transaction
 * commits, so a rolled-back write never notifies anyone.
 *
 * Part 8.2 listens to it for the "PO about to run out / exhausted"
 * notification (decision ب9: to everyone allowed to create POs). A change
 * caused by the calendar alone (the date part crossing the alert line, or
 * the PO ending) writes nothing, so it doesn't fire this — 8.2 adds the
 * daily check for that.
 */
class PurchaseOrderLineStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly PurchaseOrderService $line,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
