<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Services\Actions\CreateMainServiceAction;
use App\Domain\Services\Actions\UpdateMainServiceAction;
use App\Domain\Services\Actions\UpdateMainServiceStatusAction;
use App\Domain\Services\Exceptions\MainServiceInUseException;
use App\Domain\Services\Exports\MainServicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreMainServiceRequest;
use App\Http\Requests\Services\UpdateMainServiceRequest;
use App\Http\Requests\Services\UpdateMainServiceStatusRequest;
use App\Http\Resources\MainServiceResource;
use App\Models\MainService;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Main services — FRD V01.14 §1.9.1.
 *
 * Same "no implicit route-model binding" reasoning as FacilityController:
 * SubstituteBindings runs before the 'tenant' middleware, so the model is
 * looked up explicitly inside each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class MainServiceController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['name', 'service_type', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', MainService::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $services = $this->filteredQuery($request)
            ->with(['subServices' => fn ($q) => $q->orderBy('name'), 'vehicleCategories'])
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 15));

        return MainServiceResource::collection($services);
    }

    public function store(StoreMainServiceRequest $request, CreateMainServiceAction $action)
    {
        $service = $action->execute($request->validated(), $request->user());

        return MainServiceResource::make($service)->response()->setStatusCode(201);
    }

    /**
     * Also returns, as meta, the embedded containers of the vehicles in the
     * supported categories — the sub-service form shows them "as
     * information only" (FRD §1.9.2): a vehicle with a built-in container
     * doesn't pick an asset from the pool.
     */
    public function show(int $service)
    {
        $service = MainService::with(['vehicleCategories', 'subServices' => fn ($q) => $q->orderBy('name'), 'updatedBy'])
            ->findOrFail($service);

        Gate::authorize('view', $service);

        return MainServiceResource::make($service)->additional([
            'meta' => ['embedded_capacities' => $this->embeddedCapacities($service)],
        ]);
    }

    public function update(UpdateMainServiceRequest $request, int $service, UpdateMainServiceAction $action)
    {
        $service = MainService::findOrFail($service);

        try {
            $service = $action->execute($service, $request->validated(), $request->user());
        } catch (MainServiceInUseException $e) {
            abort(422, $e->getMessage());
        }

        return MainServiceResource::make($service);
    }

    public function status(UpdateMainServiceStatusRequest $request, int $service, UpdateMainServiceStatusAction $action)
    {
        $service = MainService::findOrFail($service);

        try {
            $service = $action->execute($service, $request->validated('status'), $request->user());
        } catch (MainServiceInUseException $e) {
            abort(422, $e->getMessage());
        }

        return MainServiceResource::make($service->load(['vehicleCategories', 'subServices', 'updatedBy']));
    }

    public function export(Request $request)
    {
        Gate::authorize('export', MainService::class);

        $services = $this->filteredQuery($request)
            ->with(['subServices' => fn ($q) => $q->orderBy('name'), 'vehicleCategories'])
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.main-services.export-pdf', ['services' => $services]);

            return $pdf->download('main-services.pdf');
        }

        return Excel::download(new MainServicesExport($services), 'main-services.xlsx');
    }

    /**
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen. Search covers both text columns the list shows: the main
     * service's name and its sub-services' names.
     */
    private function filteredQuery(Request $request): Builder
    {
        return MainService::query()
            ->when($request->filled('service_type'), fn ($q) => $q->where('service_type', $request->string('service_type')->toString()))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhereHas('subServices', fn ($s) => $s->where('name', 'like', $term)));
            });
    }

    /**
     * Distinct (category, type, capacity) combinations of the embedded
     * containers on non-deactivated vehicles of the service's categories.
     *
     * @return array<int, array<string, mixed>>
     */
    private function embeddedCapacities(MainService $service): array
    {
        $categoryIds = $service->vehicleCategories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return Vehicle::query()
            ->whereIn('vehicle_category_id', $categoryIds)
            ->where('has_embedded_container', true)
            ->where('operational_status', '!=', 'deactivated')
            ->whereNotNull('embedded_asset_capacity_category_id')
            ->with(['category', 'embeddedCapacityCategory'])
            ->get()
            ->groupBy(fn (Vehicle $v) => $v->vehicle_category_id.'-'.$v->embedded_container_type.'-'.$v->embedded_asset_capacity_category_id)
            ->map(function ($group) {
                $v = $group->first();

                return [
                    'vehicle_category' => $v->category?->name(),
                    'asset_type' => $v->embedded_container_type,
                    'capacity_name' => $v->embeddedCapacityCategory?->name,
                    'capacity_cbm' => (float) $v->embeddedCapacityCategory?->capacity_cbm,
                    'capacity_ton' => (float) $v->embeddedCapacityCategory?->capacity_ton,
                    'vehicles' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }
}
