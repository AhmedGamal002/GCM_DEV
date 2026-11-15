<?php

namespace App\Domain\Projects\Exports;

use App\Models\PurchaseOrder;
use App\Support\ExportHeader;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectsExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    public function __construct(private readonly Collection $projects) {}

    private function canSeeContracts(): bool
    {
        return (bool) auth()->user()?->can('viewAny', PurchaseOrder::class);
    }

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
            // Blank for a role without access to purchase orders (Data Entry), like the list's "—".
            $this->canSeeContracts() ? $project->purchase_orders_count : null,
            0,
            $project->operational_status,
            $project->created_at,
        ];
    }
}
