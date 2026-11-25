<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Support\ContractAlertNotifier;
use App\Domain\PurchaseOrders\Events\PurchaseOrderLineStatusChanged;
use Throwable;

/**
 * Part 8.2: a write moved a PO line to another status — warn about "about to run out" / "exhausted" (ب9). A failure
 * is reported and swallowed: the trip or edit that caused it already succeeded.
 */
class NotifyAboutContractLineStatus
{
    public function handle(PurchaseOrderLineStatusChanged $event): void
    {
        try {
            ContractAlertNotifier::sync($event->line, $event->to);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
