<?php

namespace App\Domain\Facilities\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FacilitiesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $facilities) {}

    public function collection(): Collection
    {
        return $this->facilities;
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Prefix', 'Environmental Service', 'Recycling Efficiency (%)', 'Status', 'Created At'];
    }

    public function map($facility): array
    {
        return [
            $facility->id,
            $facility->name,
            $facility->prefix,
            $facility->environmental_service,
            $facility->recycling_efficiency,
            $facility->operational_status,
            $facility->created_at,
        ];
    }
}
