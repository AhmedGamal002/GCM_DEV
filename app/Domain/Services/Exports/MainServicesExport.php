<?php

namespace App\Domain\Services\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MainServicesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $services) {}

    public function collection(): Collection
    {
        return $this->services;
    }

    public function headings(): array
    {
        return ['Name', 'Type of Service', 'Supported Vehicle Categories', 'Sub-services', 'Status', 'Created At'];
    }

    public function map($service): array
    {
        return [
            $service->name,
            $service->service_type,
            $service->vehicleCategories->map(fn ($c) => $c->name())->implode(', '),
            $service->subServices->pluck('name')->implode(', '),
            $service->operational_status,
            $service->created_at,
        ];
    }
}
