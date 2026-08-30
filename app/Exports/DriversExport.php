<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Columns match the FRD's dedicated "إدارة السائقين" list-table spec —
 * الرقم التعريفي / اسم السائق / التبعية / توفر السائق — a smaller,
 * different column set than UsersExport (no Entity/Role: this table is
 * driver-only, so Role is always "driver" and redundant here).
 */
class DriversExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $drivers)
    {
    }

    public function collection(): Collection
    {
        return $this->drivers;
    }

    public function headings(): array
    {
        return ['ID', 'Driver Name', 'Affiliation', 'Driver Availability'];
    }

    public function map($driver): array
    {
        $user = $driver->user;

        return [
            $user->code,
            $user->name,
            $user->affiliation === 'gcm' ? 'GCM' : $user->affiliation,
            $user->status,
        ];
    }
}
