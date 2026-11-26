<?php

namespace App\Domain\Reports\Exports;

use App\Domain\Reports\TripsSummary;
use App\Models\User;
use App\Support\ExportHeader;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The trips summary as Excel (FRD V01.14 "تقرير ملخص الرحلات (Trips Summary) — جدول اكسل", part 8.4): the filtered trips
 * the user sees, oldest first, with the FRD's columns (TripsSummary). Read from the database in chunks (FromQuery), so a
 * year of trips doesn't load at once; the logo / "exported by" / date header comes from ExportHeader like every export.
 */
class TripsSummaryExport implements FromQuery, WithEvents, WithHeadings, WithMapping
{
    use ExportHeader;

    private int $number = 0;

    public function __construct(private readonly Builder $trips, private readonly User $viewer) {}

    public function query(): Builder
    {
        return $this->trips;
    }

    public function headings(): array
    {
        return array_map(fn (string $h) => __($h), TripsSummary::headings());
    }

    public function map($trip): array
    {
        return TripsSummary::line(++$this->number, $trip, $this->viewer);
    }
}
