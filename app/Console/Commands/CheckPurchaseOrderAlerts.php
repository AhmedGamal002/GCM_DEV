<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Support\ContractAlertNotifier;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * The daily half of the contract alert (part 8.2, decision ب9): a PO line can run low or out with no write at all —
 * its end date gets near, or passes. Every active PO of every tenant is checked against the status each line was
 * last notified about (ContractAlertNotifier), so a line that changed because of the calendar is notified once.
 */
class CheckPurchaseOrderAlerts extends Command
{
    protected $signature = 'notifications:check-purchase-orders';

    protected $description = 'Notify about purchase-order lines that ran low or out because of the calendar';

    public function handle(): int
    {
        $written = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$written) {
            app()->instance('tenant', $tenant);

            PurchaseOrder::query()->where('operational_status', 'active')->with('services.mainService')->chunkById(100, function ($orders) use (&$written) {
                foreach ($orders as $order) {
                    $written += ContractAlertNotifier::checkOrder($order);
                }
            });
        });

        $this->info("Contract alerts written: {$written}");

        return self::SUCCESS;
    }
}
