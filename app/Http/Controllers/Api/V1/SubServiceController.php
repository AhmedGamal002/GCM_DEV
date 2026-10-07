<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Services\Actions\CreateSubServiceAction;
use App\Domain\Services\Actions\UpdateSubServiceAction;
use App\Domain\Services\Actions\UpdateSubServiceStatusAction;
use App\Domain\Services\Exceptions\MainServiceDeactivatedException;
use App\Domain\Services\Exceptions\SubServiceInUseException;
use App\Domain\Services\Exports\SubServicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreSubServiceRequest;
use App\Http\Requests\Services\UpdateSubServiceRequest;
use App\Http\Requests\Services\UpdateSubServiceStatusRequest;
use App\Http\Resources\SubServiceResource;
use App\Models\SubService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Sub-services — FRD V01.14 §1.9.2.
 *
 * Same "no implicit route-model binding" reasoning as FacilityController.
 * No export: the FRD's sub-services page is an accordion without one (the
 * main-services export lists each main service's sub-services). No
 * hard-delete route — deactivation goes through status().
 */
class SubServiceController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['name', 'asset_type', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', SubService::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $subs = $this->filteredQuery($request)
            ->with(['mainService', 'capacities', 'facilities'])
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 15));

        return SubServiceResource::collection($subs);
    }

    /**
     * Excel / PDF of the sub-services table on a main service's details page —
     * the same filters and search as the table (filteredQuery), so the file
     * holds exactly the rows on screen.
     */
    public function export(Request $request)
    {
        Gate::authorize('export', SubService::class);

        $subServices = $this->filteredQuery($request)
            ->with(['mainService', 'capacities', 'facilities'])
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.sub-services.export-pdf', ['subServices' => $subServices]);

            return $pdf->download('sub-services.pdf');
        }

        return Excel::download(new SubServicesExport($subServices), 'sub-services.xlsx');
    }

    public function store(StoreSubServiceRequest $request, CreateSubServiceAction $action)
    {
        $sub = $action->execute($request->validated(), $request->user());

        return SubServiceResource::make($sub)->response()->setStatusCode(201);
    }

    public function show(int $service)
    {
        $sub = SubService::with(['mainService.vehicleCategories', 'capacities', 'facilities', 'updatedBy'])->findOrFail($service);

        Gate::authorize('view', $sub);

        return SubServiceResource::make($sub);
    }

    public function update(UpdateSubServiceRequest $request, int $service, UpdateSubServiceAction $action)
    {
        $sub = SubService::with('mainService')->findOrFail($service);

        try {
            $sub = $action->execute($sub, $request->validated(), $request->user());
        } catch (SubServiceInUseException $e) {
            abort(422, $e->getMessage());
        }

        return SubServiceResource::make($sub);
    }

    public function status(UpdateSubServiceStatusRequest $request, int $service, UpdateSubServiceStatusAction $action)
    {
        $sub = SubService::with('mainService')->findOrFail($service);

        try {
            $sub = $action->execute($sub, $request->validated('status'), $request->user());
        } catch (SubServiceInUseException|MainServiceDeactivatedException $e) {
            abort(422, $e->getMessage());
        }

        return SubServiceResource::make($sub->load(['mainService.vehicleCategories', 'capacities', 'facilities', 'updatedBy']));
    }

    /**
     * Filters + search. Search covers the text the pages show next to a
     * sub-service: its own name and its main service's name.
     */
    private function filteredQuery(Request $request): Builder
    {
        return SubService::query()
            ->when($request->filled('main_service_id'), fn ($q) => $q->where('main_service_id', $request->integer('main_service_id')))
            ->when($request->filled('asset_type'), fn ($q) => $q->where('asset_type', $request->string('asset_type')->toString()))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';

                // `search_in=sub`: the sub-service's own name only (the Sub Services
                // page's "Search in" selector); otherwise its name or its main service's.
                if ($request->string('search_in')->toString() === 'sub') {
                    $q->where('name', 'like', $term);

                    return;
                }

                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhereHas('mainService', fn ($m) => $m->where('name', 'like', $term)));
            });
    }
}
