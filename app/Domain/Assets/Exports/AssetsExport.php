<?php

namespace App\Domain\Assets\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $assets) {}

    public function collection(): Collection
    {
        return $this->assets;
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Type', 'Capacity Category', 'Affiliation', 'Status', 'Created At'];
    }

    public function map($asset): array
    {
        return [
            $asset->id,
            $asset->name,
            $asset->asset_type,
            $asset->capacityCategory?->name,
            $asset->affiliation === 'gcm' ? 'GCM' : $asset->affiliation,
            $asset->operational_status,
            $asset->created_at,
        ];
    }
}
