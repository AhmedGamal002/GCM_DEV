<?php

namespace App\Domain\Vehicles\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VehiclesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $vehicles) {}

    public function collection(): Collection
    {
        return $this->vehicles;
    }

    public function headings(): array
    {
        return ['ID', 'Plate', 'Category', 'Affiliation', 'Status', 'Created At'];
    }

    public function map($vehicle): array
    {
        return [
            $vehicle->id,
            $vehicle->plate(),
            $vehicle->category?->name(),
            $vehicle->affiliation === 'gcm' ? 'GCM' : $vehicle->affiliation,
            $vehicle->operational_status,
            $vehicle->created_at,
        ];
    }
}
