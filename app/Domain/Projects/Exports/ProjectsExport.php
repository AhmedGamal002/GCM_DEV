<?php

namespace App\Domain\Projects\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $projects) {}

    public function collection(): Collection
    {
        return $this->projects;
    }

    public function headings(): array
    {
        return ['ID', 'Company', 'Project', 'Operational Region', 'Contracts', 'Users', 'Status', 'Created At'];
    }

    public function map($project): array
    {
        return [
            $project->code,
            $project->company?->name,
            $project->name,
            $project->operational_region,
            // Contracts and client users don't exist yet (FRD §1.13 / §1.4).
            0,
            0,
            $project->operational_status,
            $project->created_at,
        ];
    }
}
