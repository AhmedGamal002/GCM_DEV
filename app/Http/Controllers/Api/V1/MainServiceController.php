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
use App\Models\AssetCapacityCategory;
use App\Models\MainService;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Support\PdfExport;
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

        // A main service can group hundreds of sub-services. The pages that only
        // preview them (the Sub Services accordion, the main services table) ask
        // for `subs_limit` — each row then carries just its first N sub-services
        // plus the true `sub_services_count`; without it every sub-service is
        // returned, as before. `subs_match` (with a search term) narrows them to
        // the ones the search matched, unless the main service itself matched.
        $limit = $request->integer('subs_limit');
        $limit = $limit > 0 ? min($limit, 50) : null;
        $scope = $this->searchScope($request);
        $matching = $request->boolean('subs_match') && $request->filled('search') && $scope !== 'main';
        $term = '%'.$request->string('search')->toString().'%';
        $narrow = fn ($q) => $matching
            ? ($scope === 'sub'
                ? $q->where('name', 'like', $term)
                : $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhereHas('mainService', fn ($m) => $m->where('name', 'like', $term))))
            : $q;

        $services = $this->filteredQuery($request)
            ->with([
                'subServices' => function ($q) use ($limit, $narrow) {
                    $narrow($q->orderBy('name'));
                    if ($limit !== null) {
                        $q->limit($limit);
                    }
                },
                'vehicleCategories',
            ])
            ->withCount(['subServices as sub_services_count' => fn ($q) => $narrow($q)])
            ->orderBy($sortBy, $sortDir)
            ->paginate($this->perPage($request));

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
        // Just the number of sub-services (a service can have hundreds) — the
        // details page lists them in its own server-side table.
        $service = MainService::with(['vehicleCategories', 'updatedBy'])
            ->withCount('subServices')
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

        // `id` breaks ties so the chunked read of a big PDF never repeats or skips a row.
        $query = $this->filteredQuery($request)
            ->with(['subServices' => fn ($q) => $q->orderBy('name'), 'vehicleCategories'])
            ->orderBy('name')
            ->orderBy('id');

        if ($request->query('format', 'xlsx') === 'pdf') {
            return PdfExport::downloadQuery('tenant.main-services.export-pdf', 'services', $query, 'main-services.pdf');
        }

        return Excel::download(new MainServicesExport($query->get()), 'main-services.xlsx');
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
                $scope = $this->searchScope($request);

                $q->where(function ($w) use ($term, $scope) {
                    if ($scope !== 'sub') {
                        $w->where('name', 'like', $term);
                    }
                    if ($scope !== 'main') {
                        $w->orWhereHas('subServices', fn ($s) => $s->where('name', 'like', $term));
                    }
                });
            });
    }

    /**
     * What the search box looks in: `main` (the main service's name only),
     * `sub` (its sub-services' names only) or — the default — both. Used by the
     * Sub Services page's "Search in" selector; every other caller sends none.
     */
    private function searchScope(Request $request): string
    {
        $scope = $request->string('search_in')->toString();

        return in_array($scope, ['main', 'sub'], true) ? $scope : 'all';
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

        // Counted in SQL: a category can hold thousands of vehicles, and loading
        // them as models just to group them was this page's whole cost.
        $groups = Vehicle::query()
            ->whereIn('vehicle_category_id', $categoryIds)
            ->where('has_embedded_container', true)
            ->where('operational_status', '!=', 'deactivated')
            ->whereNotNull('embedded_asset_capacity_category_id')
            ->selectRaw('vehicle_category_id, embedded_container_type, embedded_asset_capacity_category_id, count(*) as vehicles')
            ->groupBy('vehicle_category_id', 'embedded_container_type', 'embedded_asset_capacity_category_id')
            ->get();

        $categories = VehicleCategory::query()->whereIn('id', $groups->pluck('vehicle_category_id')->unique())->get()->keyBy('id');
        $capacities = AssetCapacityCategory::query()->whereIn('id', $groups->pluck('embedded_asset_capacity_category_id')->unique())->get()->keyBy('id');

        return $groups
            ->map(function ($g) use ($categories, $capacities) {
                $capacity = $capacities->get($g->embedded_asset_capacity_category_id);

                return [
                    'vehicle_category' => $categories->get($g->vehicle_category_id)?->name(),
                    'asset_type' => $g->embedded_container_type,
                    'capacity_name' => $capacity?->name,
                    'capacity_cbm' => (float) $capacity?->capacity_cbm,
                    'capacity_ton' => (float) $capacity?->capacity_ton,
                    'vehicles' => (int) $g->vehicles,
                ];
            })
            ->values()
            ->all();
    }
}
