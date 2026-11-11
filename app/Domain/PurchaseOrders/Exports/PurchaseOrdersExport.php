<?php

namespace App\Domain\PurchaseOrders\Exports;

use App\Domain\PurchaseOrders\Support\PurchaseOrderStates;
use App\Support\ExportHeader;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The FRD's list columns (§1.13): ID, client company, project, duration
 * (from / to), days left, items status, account status. The states are read
 * from each order's attached state (PurchaseOrderStates::attach), computed
 * once for the whole export.
 */
class PurchaseOrdersExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    public function __construct(private readonly Collection $orders) {}

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return ['PO No.', 'Client Company', 'Project', 'From', 'To', 'Days Left', 'Items Status', 'Account Status'];
    }

    public function map($order): array
    {
        $state = PurchaseOrderStates::of($order);

        return [
            $order->number,
            $order->company?->name,
            $order->project?->name,
            $order->starts_on?->toDateString(),
            $order->ends_on?->toDateString(),
            $state['days_label'],
            $state['status_label'],
            $order->operational_status,
        ];
    }
}
