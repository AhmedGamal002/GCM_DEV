<?php

namespace App\Domain\Assets\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetCapacityCategoriesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $categories) {}

    public function collection(): Collection
    {
        return $this->categories;
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Applies To', 'Capacity (CBM)', 'Capacity (TON)', 'Created At'];
    }

    public function map($category): array
    {
        return [
            $category->id,
            $category->name,
            $category->applies_to,
            $category->capacity_cbm,
            $category->capacity_ton,
            $category->created_at,
        ];
    }
}
