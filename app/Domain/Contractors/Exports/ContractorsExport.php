<?php

namespace App\Domain\Contractors\Exports;

use App\Support\ExportHeader;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContractorsExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    public function __construct(private readonly Collection $contractors) {}

    public function collection(): Collection
    {
        return $this->contractors;
    }

    public function headings(): array
    {
        return ['ID', 'Contractor', 'Short name', 'Trading name', 'Drivers', 'Vehicles', 'Containers', 'Tanks', 'Status', 'Created At'];
    }

    public function map($contractor): array
    {
        return [
            $contractor->code,
            $contractor->name,
            $contractor->prefix,
            $contractor->trading_name,
            $contractor->drivers_count,
            $contractor->vehicles_count,
            $contractor->containers_count,
            $contractor->tanks_count,
            $contractor->operational_status,
            $contractor->created_at,
        ];
    }
}
