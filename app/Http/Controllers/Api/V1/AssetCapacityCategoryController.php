<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Actions\CreateAssetCapacityCategoryAction;
use App\Domain\Assets\Actions\UpdateAssetCapacityCategoryAction;
use App\Domain\Assets\Exports\AssetCapacityCategoriesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetCapacityCategories\StoreAssetCapacityCategoryRequest;
use App\Http\Requests\AssetCapacityCategories\UpdateAssetCapacityCategoryRequest;
use App\Http\Resources\AssetCapacityCategoryResource;
use App\Models\AssetCapacityCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * FRD §1.7.2 — full CRUD for asset capacity categories (create/edit/view;
 * no delete, no deactivate).
 *
 * `index` is deliberately NOT gated: it's read-only reference data every
 * tenant role needs for the vehicle and asset form dropdowns (covered by
 * AssetCapacityCategoryReadTest). The mutations and the single-row fetch
 * behind the management pages go through AssetCapacityCategoryPolicy.
 *
 * Rows are tenant-scoped by BelongsToTenant. Raw-int `{category}` param,
 * same reasoning as VehicleController's docblock.
 */
class AssetCapacityCategoryController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['name', 'applies_to', 'capacity_cbm', 'capacity_ton', 'assets_count', 'created_at'];

    public function index(Request $request)
    {
        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $query = $this->filteredQuery($request)
            ->withCount('assets')
            // `assets_count` is a valid orderable alias from withCount().
            ->orderBy($sortBy, $sortDir);

        // Genuine server-side paging for the management list page (same
        // pattern as /vehicles) — it sends `per_page`. The vehicle & asset
        // form dropdowns call this same endpoint WITHOUT `per_page` and
        // need the full unpaginated reference list.
        return AssetCapacityCategoryResource::collection(
            $request->filled('per_page')
                ? $query->paginate($request->integer('per_page', 15))
                : $query->get()
        );
    }

    public function store(StoreAssetCapacityCategoryRequest $request, CreateAssetCapacityCategoryAction $action)
    {
        $category = $action->execute($request->validated(), $request->user());

        return AssetCapacityCategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function show(int $category)
    {
        $category = AssetCapacityCategory::withCount('assets')->with('updatedBy')->findOrFail($category);

        Gate::authorize('view', $category);

        return AssetCapacityCategoryResource::make($category);
    }

    public function update(UpdateAssetCapacityCategoryRequest $request, int $category, UpdateAssetCapacityCategoryAction $action)
    {
        $category = AssetCapacityCategory::findOrFail($category);

        $category = $action->execute($category, $request->validated(), $request->user());

        return AssetCapacityCategoryResource::make($category->loadCount('assets'));
    }

    public function export(Request $request)
    {
        Gate::authorize('export', AssetCapacityCategory::class);

        $categories = $this->filteredQuery($request)
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.asset-categories.export-pdf', ['categories' => $categories]);

            return $pdf->download('asset-capacity-categories.pdf');
        }

        return Excel::download(new AssetCapacityCategoriesExport($categories), 'asset-capacity-categories.xlsx');
    }

    /**
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen — they used to be two copies that drifted apart (export
     * ignored the search box and any filter the UI didn't forward).
     */
    private function filteredQuery(Request $request): Builder
    {
        return AssetCapacityCategory::query()
            // The asset form AND the vehicle form's embedded-container
            // dropdown (FRD §1.5.3) ask for the capacities matching a chosen
            // type (container / tank) — `both` fits either.
            ->when($request->filled('for_type'), fn ($q) => $q->whereIn('applies_to', [$request->string('for_type')->toString(), 'both']))
            ->when($request->filled('applies_to'), fn ($q) => $q->where('applies_to', $request->string('applies_to')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'));
    }
}
