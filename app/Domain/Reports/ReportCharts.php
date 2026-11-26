<?php

namespace App\Domain\Reports;

use App\Models\SubService;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The charts and figures of the reports page (FRD V01.14: "مجموعة من الاشكال البيانية التي تظهر الاحصائيات الأهم",
 * part 8.4) for the filtered trips the user sees: the headline figures (trips, waste in tons and m³, vehicles,
 * drivers), the trips over the period, the five statuses, and the sub-services by trips and tons.
 *
 * Like the dashboard (8.3), a cancelled trip counts only in the status breakdown — not in the figures, the timeline
 * or the sub-services (ج2: "as if it didn't happen" for quantities). The timeline is by day for up to 62 days and by
 * month beyond that (a year of days is unreadable).
 */
final class ReportCharts
{
    private const DAILY_UP_TO = 62;

    public function for(User $user, Request $request): array
    {
        $filtered = fn () => ReportFilters::query($user, $request);
        $counted = fn () => $filtered()->where('trips.status', '!=', Trip::STATUS_CANCELLED);

        return [
            'totals' => $this->totals($counted()),
            'timeline' => $this->timeline($counted(), $request),
            'statuses' => $this->statuses($filtered()),
            'sub_services' => $this->subServices($counted()),
        ];
    }

    private function totals(Builder $trips): array
    {
        $row = $trips->toBase()->selectRaw(
            'COUNT(*) AS trips, COALESCE(SUM(trips.quantity_ton), 0) AS tons, COALESCE(SUM(trips.quantity_cbm), 0) AS cbm, '.
            'COUNT(DISTINCT trips.vehicle_id) AS vehicles, COUNT(DISTINCT trips.driver_id) AS drivers'
        )->first();

        return [
            'trips' => (int) $row->trips,
            'quantity_ton' => round((float) $row->tons, 3),
            'quantity_cbm' => round((float) $row->cbm, 3),
            'vehicles' => (int) $row->vehicles,
            'drivers' => (int) $row->drivers,
        ];
    }

    /** @return array{unit: string, points: list<array{key: string, count: int}>} */
    private function timeline(Builder $trips, Request $request): array
    {
        [$from, $to] = ReportFilters::range($request);
        $span = $from !== null && $to !== null ? $from->diffInDays($to) : null;
        $daily = $span !== null && $span <= self::DAILY_UP_TO;

        $driver = $trips->getConnection()->getDriverName();
        $month = $driver === 'sqlite' ? "strftime('%Y-%m', trips.trip_at)" : "DATE_FORMAT(trips.trip_at, '%Y-%m')";
        $key = $daily ? 'DATE(trips.trip_at)' : $month;

        $counts = $trips->toBase()->selectRaw("{$key} AS k, COUNT(*) AS n")->groupBy('k')->orderBy('k')->pluck('n', 'k');

        // daily: every day of the period, zero days included, so the chart has no gaps
        if ($daily) {
            $points = [];
            for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
                $points[] = ['key' => $day->toDateString(), 'count' => (int) ($counts[$day->toDateString()] ?? 0)];
            }

            return ['unit' => 'day', 'points' => $points];
        }

        return ['unit' => 'month', 'points' => $counts->map(fn ($n, $k) => ['key' => (string) $k, 'count' => (int) $n])->values()->all()];
    }

    /** @return list<array{status: string, label: string, count: int}> */
    private function statuses(Builder $trips): array
    {
        $counts = $trips->toBase()->selectRaw('trips.status AS s, COUNT(*) AS n')->groupBy('s')->pluck('n', 's');

        return collect(Trip::STATUSES)->map(fn (string $s) => ['status' => $s, 'label' => Trip::statusLabel($s), 'count' => (int) ($counts[$s] ?? 0)])->all();
    }

    /** @return list<array{id: int, name: string, trips: int, quantity_ton: float}> the sub-services, most trips first */
    private function subServices(Builder $trips): array
    {
        $rows = $trips->whereNotNull('trips.sub_service_id')->toBase()
            ->selectRaw('trips.sub_service_id AS id, COUNT(*) AS n, COALESCE(SUM(trips.quantity_ton), 0) AS tons')
            ->groupBy('id')->get();
        $names = SubService::query()->whereIn('id', $rows->pluck('id'))->pluck('name', 'id');

        return $rows->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) ($names[$r->id] ?? ''), 'trips' => (int) $r->n, 'quantity_ton' => round((float) $r->tons, 3)])
            ->sortByDesc('trips')->values()->all();
    }
}
