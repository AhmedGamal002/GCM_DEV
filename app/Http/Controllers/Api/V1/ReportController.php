<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Exports\TripsSummaryExport;
use App\Domain\Reports\ReportAccess;
use App\Domain\Reports\ReportCharts;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\TripsSummary;
use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The reports section (FRD V01.14 "التقارير والاحصائيات", part 8.4): the trips summary (table + Excel), the charts,
 * and the lists the nine filters choose from. Who may use what is ReportAccess; which trips is ReportFilters on top of
 * Trip::scopeVisibleTo — the same rows in the table, the charts and the file.
 */
class ReportController extends Controller
{
    /** The trips summary rows, paginated (the page's table). */
    public function tripsSummary(Request $request): JsonResponse
    {
        abort_unless(ReportAccess::canUse($request->user()), 403);

        $page = ReportFilters::query($request->user(), $request)
            ->with(TripsSummary::RELATIONS)
            ->orderByDesc('trips.trip_at')->orderByDesc('trips.id')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Trip $trip) => TripsSummary::row($trip, $request->user()))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /** The trips summary as Excel — the same filtered trips, oldest first. */
    public function exportTripsSummary(Request $request)
    {
        abort_unless(ReportAccess::canUse($request->user()), 403);

        $query = ReportFilters::query($request->user(), $request)
            ->with(TripsSummary::RELATIONS)
            ->orderBy('trips.trip_at')->orderBy('trips.id');

        return Excel::download(new TripsSummaryExport($query, $request->user()), 'trips-summary.xlsx');
    }

    /** The charts and figures — not for the client's project auditor (the Excel only). */
    public function charts(Request $request, ReportCharts $charts): JsonResponse
    {
        abort_unless(ReportAccess::seesCharts($request->user()), 403);

        return response()->json(['data' => $charts->for($request->user(), $request)]);
    }

    /**
     * What the filters choose from: only what appears in the trips the user sees (a client gets his projects, his
     * companies, the vehicles and drivers of his trips…) — never a list he couldn't read otherwise.
     */
    public function filters(Request $request): JsonResponse
    {
        abort_unless(ReportAccess::canUse($request->user()), 403);

        $visible = fn (string $column) => Trip::query()->visibleTo($request->user())->whereNotNull("trips.{$column}")->select("trips.{$column}")->distinct();
        $names = fn ($query, string $label = 'name') => $query->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->{$label}])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $projects = Project::query()->whereIn('id', $visible('project_id'))->with('company:id,name')->get(['id', 'name', 'company_id']);
        $contractorIds = Driver::query()->whereIn('id', $visible('driver_id'))->whereNotNull('contractor_id')->pluck('contractor_id')
            ->merge(Vehicle::query()->whereIn('id', $visible('vehicle_id'))->whereNotNull('contractor_id')->pluck('contractor_id'))->unique();

        return response()->json(['data' => [
            'periods' => collect(ReportFilters::PERIOD_LABELS)->map(fn ($label, $p) => ['id' => $p, 'name' => __($label)])->values(),
            'statuses' => collect(Trip::STATUSES)->map(fn ($s) => ['id' => $s, 'name' => Trip::statusLabel($s)])->values(),
            'companies' => $projects->pluck('company')->filter()->unique('id')->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->sortBy('name')->values(),
            'projects' => $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'company_id' => $p->company_id])->sortBy('name')->values(),
            'sub_services' => $names(SubService::query()->whereIn('id', $visible('sub_service_id'))),
            'vehicles' => Vehicle::query()->whereIn('id', $visible('vehicle_id'))->get()->map(fn ($v) => ['id' => $v->id, 'name' => $v->plate()])->sortBy('name')->values(),
            'drivers' => Driver::query()->whereIn('drivers.id', $visible('driver_id'))->with('user:id,name')->get()->map(fn ($d) => ['id' => $d->id, 'name' => $d->user?->name])->sortBy('name')->values(),
            'contractors' => $names(Contractor::query()->whereIn('id', $contractorIds)),
            'facilities' => $names(IntermediateFacility::query()->whereIn('id', $visible('intermediate_facility_id'))),
            'sees_charts' => ReportAccess::seesCharts($request->user()),
        ]]);
    }
}
