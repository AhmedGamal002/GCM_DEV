<?php

namespace App\Domain\Services\Exports;

use App\Models\SubService;
use App\Support\ExportHeader;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The sub-services table of a main service's details page (FRD §1.9.1) — one
 * row per sub-service, exactly the rows the table shows.
 */
class SubServicesExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    public function __construct(private readonly Collection $subServices) {}

    public function collection(): Collection
    {
        return $this->subServices;
    }

    public function headings(): array
    {
        return ['Name', 'Main Service', 'Type', 'Compatible Capacities', 'Intermediate Facilities', 'Requires Recycle Receipt', 'Skip Color Code', 'Status', 'Created At'];
    }

    public function map($sub): array
    {
        return [
            $sub->name,
            $sub->mainService?->name,
            $sub->asset_type,
            $sub->capacities->pluck('name')->implode(', '),
            $sub->facilities->pluck('name')->implode(', '),
            $sub->requires_recycle_receipt ? 'Yes' : 'No',
            SubService::skipColorLabel($sub->skip_color_code),
            $sub->operational_status,
            $sub->created_at,
        ];
    }
}
