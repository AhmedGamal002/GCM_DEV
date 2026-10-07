<?php

namespace App\Domain\Vehicles\Exports;

use App\Support\ExportHeader;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VehiclesExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    public function __construct(private readonly Collection $vehicles) {}

    public function collection(): Collection
    {
        return $this->vehicles;
    }

    public function headings(): array
    {
        return ['ID', 'Plate', 'Category', 'Affiliation', 'Status', 'Documents Status', 'Created At'];
    }

    public function map($vehicle): array
    {
        return [
            $vehicle->id,
            $vehicle->plate(),
            $vehicle->category?->name(),
            $vehicle->affiliation === 'gcm' ? 'GCM' : ($vehicle->contractor?->name ?? $vehicle->affiliation),
            $vehicle->operational_status,
            $vehicle->documentsStatus()['status_label'] ?? '—',
            $vehicle->created_at,
        ];
    }
}
