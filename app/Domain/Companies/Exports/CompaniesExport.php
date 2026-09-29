<?php

namespace App\Domain\Companies\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CompaniesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $companies) {}

    public function collection(): Collection
    {
        return $this->companies;
    }

    public function headings(): array
    {
        return ['ID', 'Company', 'Short name', 'Business sector', 'Projects', 'Users', 'Status', 'Created At'];
    }

    public function map($company): array
    {
        return [
            $company->code,
            $company->name,
            $company->prefix,
            $company->business_sector,
            // Client users don't exist yet (FRD §1.4).
            $company->projects_count,
            0,
            $company->operational_status,
            $company->created_at,
        ];
    }
}
