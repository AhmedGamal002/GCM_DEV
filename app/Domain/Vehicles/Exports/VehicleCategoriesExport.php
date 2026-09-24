<?php

namespace App\Domain\Vehicles\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The management list's columns (name in both languages + how many
 * vehicles use it) plus the ID and creation date the other exports carry.
 * Expects `vehicles_count` (withCount) on each row.
 */
class VehicleCategoriesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $categories) {}

    public function collection(): Collection
    {
        return $this->categories;
    }

    public function headings(): array
    {
        return ['ID', 'Name (English)', 'Name (Arabic)', 'Vehicles', 'Created At'];
    }

    public function map($category): array
    {
        return [
            $category->id,
            $category->name_en,
            $category->name_ar,
            $category->vehicles_count,
            $category->created_at,
        ];
    }
}
