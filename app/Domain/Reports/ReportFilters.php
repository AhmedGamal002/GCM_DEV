<?php

namespace App\Domain\Reports;

use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The nine filters of every report (FRD V01.14 "وهذه التقارير قابلة للتخصيص على مستوي عناصر التصفية"), applied in ONE
 * place so the page's table, its charts and the Excel always show the same trips (and part 8.5's sustainability
 * reports can use them too):
 *
 *  period       today | week | month | quarter | year | custom (date_from / date_to) — the date of the trip;
 *  company_id   the client company;            project_id   the project;
 *  sub_service_id                              status       the trip's approval status (any of the five);
 *  vehicle_id   driver_id                      contractor_id  whose driver OR vehicle it is (like the trips list);
 *  facility_id  the recycling facility (the trip's intermediate facility).
 *
 * Always on top of Trip::scopeVisibleTo — a client never reaches another project's trip, whatever he sends.
 * Readings of ours: the presets are the CURRENT calendar period up to now (the week starts on Sunday, the Saudi work
 * week); a cancelled trip is a row like any other (ج2: it shows in the trips summary) unless the status filter says.
 */
final class ReportFilters
{
    public const PERIODS = ['today', 'week', 'month', 'quarter', 'year', 'custom'];

    public const PERIOD_LABELS = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'quarter' => 'This quarter', 'year' => 'This year', 'custom' => 'Custom range'];

    public static function query(User $user, Request $request): Builder
    {
        return self::apply(Trip::query()->visibleTo($user), $request);
    }

    public static function apply(Builder $trips, Request $request): Builder
    {
        [$from, $to] = self::range($request);
        $int = fn (string $key) => $request->integer($key);

        return $trips
            ->when($from !== null, fn ($q) => $q->where('trips.trip_at', '>=', $from->toDateTimeString()))
            ->when($to !== null, fn ($q) => $q->where('trips.trip_at', '<=', $to->toDateTimeString()))
            ->when($request->filled('company_id'), fn ($q) => $q->where('trips.company_id', $int('company_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('trips.project_id', $int('project_id')))
            ->when($request->filled('sub_service_id'), fn ($q) => $q->where('trips.sub_service_id', $int('sub_service_id')))
            ->when(in_array($request->string('status')->toString(), Trip::STATUSES, true), fn ($q) => $q->where('trips.status', $request->string('status')->toString()))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('trips.vehicle_id', $int('vehicle_id')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $int('driver_id')))
            ->when($request->filled('contractor_id'), fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('driver', fn ($d) => $d->where('drivers.contractor_id', $int('contractor_id')))
                ->orWhereHas('vehicle', fn ($v) => $v->where('vehicles.contractor_id', $int('contractor_id')))))
            ->when($request->filled('facility_id'), fn ($q) => $q->where('trips.intermediate_facility_id', $int('facility_id')));
    }

    /**
     * The period the request asks for, as [from, to] in the app's timezone (either may be null = open).
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public static function range(Request $request): array
    {
        $now = CarbonImmutable::now();

        return match ($request->string('period')->toString()) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(CarbonImmutable::SUNDAY), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'quarter' => [$now->startOfQuarter(), $now->endOfDay()],
            'year' => [$now->startOfYear(), $now->endOfDay()],
            default => [
                self::date($request, 'date_from')?->startOfDay(),
                self::date($request, 'date_to')?->endOfDay(),
            ],
        };
    }

    private static function date(Request $request, string $key): ?CarbonImmutable
    {
        $value = $request->string($key)->toString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
