<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Vehicles\Actions\CreateVehicleCategoryAction;
use App\Domain\Vehicles\Actions\DeleteVehicleCategoryAction;
use App\Domain\Vehicles\Actions\UpdateVehicleCategoryAction;
use App\Domain\Vehicles\Exceptions\PrimaryVehicleCategoryException;
use App\Domain\Vehicles\Exceptions\VehicleCategoryInUseException;
use App\Domain\Vehicles\Exports\VehicleCategoriesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleCategories\StoreVehicleCategoryRequest;
use App\Http\Requests\VehicleCategories\UpdateVehicleCategoryRequest;
use App\Http\Resources\VehicleCategoryResource;
use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Vehicle categories — tenant-scoped, managed by the system_admin only
 * (client request, outside the FRD's fixed five).
 *
 * `index` is deliberately NOT gated: it's read-only reference data every
 * tenant role needs for the vehicle/driver/asset form dropdowns and the
 * vehicle list's filter. The single-row fetch and the mutations go
 * through VehicleCategoryPolicy. Raw-int `{category}` param, same
 * reasoning as VehicleController's docblock.
 */
class VehicleCategoryController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['id', 'name_en', 'name_ar', 'vehicles_count', 'created_at'];

    public function index(Request $request)
    {
        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'id';

        $paged = $request->filled('per_page');

        $query = $this->filteredQuery($request)
            // Usage counts only for the management list; the dropdown
            // calls (no per_page) skip the extra sub-selects.
            ->when($paged, fn ($q) => $q->withCount(['vehicles', 'drivers', 'assets']))
            ->orderBy($sortBy === 'vehicles_count' && ! $paged ? 'id' : $sortBy, $sortDir);

        return VehicleCategoryResource::collection(
            $paged ? $query->paginate($request->integer('per_page', 15)) : $query->get()
        );
    }

    public function store(StoreVehicleCategoryRequest $request, CreateVehicleCategoryAction $action)
    {
        $category = $action->execute($request->validated());

        return VehicleCategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function show(int $category)
    {
        $category = VehicleCategory::withCount(['vehicles', 'drivers', 'assets'])->findOrFail($category);

        Gate::authorize('view', $category);

        return VehicleCategoryResource::make($category);
    }

    public function update(UpdateVehicleCategoryRequest $request, int $category, UpdateVehicleCategoryAction $action)
    {
        $category = $action->execute(VehicleCategory::findOrFail($category), $request->validated());

        return VehicleCategoryResource::make($category->loadCount(['vehicles', 'drivers', 'assets']));
    }

    public function destroy(int $category, DeleteVehicleCategoryAction $action)
    {
        $category = VehicleCategory::findOrFail($category);

        Gate::authorize('delete', $category);

        try {
            $action->execute($category);
        } catch (PrimaryVehicleCategoryException|VehicleCategoryInUseException $e) {
            abort(422, $e->getMessage());
        }

        return response()->noContent();
    }

    public function export(Request $request)
    {
        Gate::authorize('export', VehicleCategory::class);

        $categories = $this->filteredQuery($request)
            ->withCount('vehicles')
            ->orderBy('id')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.vehicle-categories.export-pdf', ['categories' => $categories]);

            return $pdf->download('vehicle-categories.pdf');
        }

        return Excel::download(new VehicleCategoriesExport($categories), 'vehicle-categories.xlsx');
    }

    /**
     * The list's search box, shared by index() and export() so an export
     * always holds exactly the rows the user sees on screen.
     */
    private function filteredQuery(Request $request): Builder
    {
        return VehicleCategory::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('name_en', 'like', $term)->orWhere('name_ar', 'like', $term));
            });
    }
}
