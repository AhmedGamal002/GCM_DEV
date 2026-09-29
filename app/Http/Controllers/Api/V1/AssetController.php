<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Actions\CreateAssetAction;
use App\Domain\Assets\Actions\UpdateAssetAction;
use App\Domain\Assets\Actions\UpdateAssetStatusAction;
use App\Domain\Assets\Exports\AssetsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\StoreAssetRequest;
use App\Http\Requests\Assets\UpdateAssetRequest;
use App\Http\Requests\Assets\UpdateAssetStatusRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Same "no implicit route-model binding" reasoning as VehicleController /
 * UserController: SubstituteBindings runs before the 'tenant' middleware,
 * so an implicit Asset $asset param would throw
 * TenantContextMissingException. The model is looked up explicitly inside
 * each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class AssetController extends Controller
{
    private const LIST_WITH = ['tenant', 'capacityCategory', 'compatibleVehicleCategories', 'project'];

    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['name', 'asset_type', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Asset::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $assets = $this->filteredQuery($request)
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 15));

        return AssetResource::collection($assets);
    }

    public function store(StoreAssetRequest $request, CreateAssetAction $action)
    {
        $asset = $action->execute($request->validated(), $request->user());

        return AssetResource::make($asset)->response()->setStatusCode(201);
    }

    public function show(int $asset)
    {
        $asset = Asset::with([...self::LIST_WITH, 'updatedBy'])->findOrFail($asset);

        Gate::authorize('view', $asset);

        return AssetResource::make($asset);
    }

    public function update(UpdateAssetRequest $request, int $asset, UpdateAssetAction $action)
    {
        $asset = Asset::findOrFail($asset);

        $asset = $action->execute($asset, $request->validated(), $request->user());

        return AssetResource::make($asset->load([...self::LIST_WITH, 'updatedBy']));
    }

    public function status(UpdateAssetStatusRequest $request, int $asset, UpdateAssetStatusAction $action)
    {
        $asset = Asset::findOrFail($asset);
        $asset = $action->execute($asset, $request->validated('status'), $request->user());

        return AssetResource::make($asset->load(self::LIST_WITH));
    }

    /**
     * FRD §1.7.3 stat cards — containers and tanks counted separately,
     * each: available / in projects / on maintenance / deactivated.
     * "In projects" = active assets placed in a project; "available" =
     * active assets still in the pool — same split as the availability
     * filter (applyStatusFilter).
     */
    public function stats()
    {
        Gate::authorize('viewAny', Asset::class);

        $rows = Asset::query()
            ->selectRaw('asset_type, operational_status, (project_id is not null) as in_project, count(*) as total')
            ->groupBy('asset_type', 'operational_status', 'in_project')
            ->get();

        $count = fn (string $type, string $status, ?bool $inProject = null): int => (int) $rows
            ->where('asset_type', $type)
            ->where('operational_status', $status)
            ->when($inProject !== null, fn ($r) => $r->filter(fn ($row) => (bool) $row->in_project === $inProject))
            ->sum('total');

        $shape = fn (string $type): array => [
            'available' => $count($type, 'active', false),
            'in_projects' => $count($type, 'active', true),
            'on_maintenance' => $count($type, 'on_maintenance'),
            'deactivated' => $count($type, 'deactivated'),
        ];

        return response()->json([
            'data' => [
                'container' => $shape('container'),
                'tank' => $shape('tank'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Asset::class);

        $assets = $this->filteredQuery($request)
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.assets.export-pdf', ['assets' => $assets]);

            return $pdf->download('assets.pdf');
        }

        return Excel::download(new AssetsExport($assets), 'assets.xlsx');
    }

    /**
     * "Availability" (FRD V01.14 §1.7.3): `active` means active AND in the
     * pool (not placed in a project), `in_project` means active and placed
     * in one; on_maintenance / deactivated are the raw statuses (an asset
     * in maintenance is shown as such even if it is still at a project).
     */
    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->where('operational_status', 'active')->whereNull('project_id'),
            'in_project' => $query->where('operational_status', 'active')->whereNotNull('project_id'),
            default => $query->where('operational_status', $status),
        };
    }

    /**
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen — they used to be two copies that drifted apart (export
     * ignored the search box and any filter the UI didn't forward).
     */
    private function filteredQuery(Request $request): Builder
    {
        return Asset::query()
            ->with(self::LIST_WITH)
            ->when($request->filled('asset_capacity_category_id'), fn ($q) => $q->where('asset_capacity_category_id', $request->integer('asset_capacity_category_id')))
            ->when($request->filled('asset_type'), fn ($q) => $q->where('asset_type', $request->string('asset_type')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('operational_status'), fn ($q) => $this->applyStatusFilter($q, $request->string('operational_status')->toString()))
            ->when($request->filled('affiliation'), fn ($q) => $q->where('affiliation', $request->string('affiliation')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where('name', 'like', "%{$search}%");
            });
    }
}
