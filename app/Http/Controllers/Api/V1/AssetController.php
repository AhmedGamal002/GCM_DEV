<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Actions\CreateAssetAction;
use App\Domain\Assets\Actions\UpdateAssetAction;
use App\Domain\Assets\Actions\UpdateAssetStatusAction;
use App\Domain\Assets\Exceptions\CannotDeactivateAssetException;
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
    private const LIST_WITH = ['tenant', 'capacityCategory', 'compatibleVehicleCategories'];

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

        try {
            $asset = $action->execute($asset, $request->validated('status'), $request->user());
        } catch (CannotDeactivateAssetException $e) {
            abort(422, $e->getMessage());
        }

        return AssetResource::make($asset->load(self::LIST_WITH));
    }

    /**
     * FRD §1.7.3 stat cards — containers and tanks counted separately,
     * each: available / in projects / on maintenance / deactivated.
     * "in_projects" needs the Project module (Week 4) — 0 for now.
     */
    public function stats()
    {
        Gate::authorize('viewAny', Asset::class);

        $rows = Asset::query()
            ->selectRaw('asset_type, operational_status, count(*) as total')
            ->groupBy('asset_type', 'operational_status')
            ->get();

        $shape = fn (string $type): array => [
            'available' => (int) $rows->where('asset_type', $type)->firstWhere('operational_status', 'active')?->total,
            'in_projects' => 0,
            'on_maintenance' => (int) $rows->where('asset_type', $type)->firstWhere('operational_status', 'on_maintenance')?->total,
            'deactivated' => (int) $rows->where('asset_type', $type)->firstWhere('operational_status', 'deactivated')?->total,
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
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen — they used to be two copies that drifted apart (export
     * ignored the search box and any filter the UI didn't forward).
     */
    private function filteredQuery(Request $request): Builder
    {
        return Asset::query()
            ->with(self::LIST_WITH)
            ->when($request->filled('asset_type'), fn ($q) => $q->where('asset_type', $request->string('asset_type')))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('affiliation'), fn ($q) => $q->where('affiliation', $request->string('affiliation')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where('name', 'like', "%{$search}%");
            });
    }
}
