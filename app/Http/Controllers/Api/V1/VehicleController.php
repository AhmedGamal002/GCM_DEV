<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Vehicles\Actions\CreateVehicleAction;
use App\Domain\Vehicles\Actions\UpdateVehicleAction;
use App\Domain\Vehicles\Actions\UpdateVehicleStatusAction;
use App\Domain\Vehicles\Exports\VehiclesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleStatusRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Same "no implicit route-model binding" reasoning as UserController:
 * SubstituteBindings runs before the 'tenant' middleware, so an implicit
 * Vehicle $vehicle param would throw TenantContextMissingException. The
 * model is looked up explicitly inside each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class VehicleController extends Controller
{
    private const LIST_WITH = ['tenant', 'category', 'embeddedCapacityCategory'];

    /**
     * Explicit allowlist for `sort_by` — never the raw request value
     * itself, since that would let a caller name an arbitrary column.
     */
    private const SORTABLE_COLUMNS = ['plate_letters', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Vehicle::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'plate_letters';

        $vehicles = $this->filteredQuery($request)
            // Used by the driver add/edit forms' "Default Vehicle" dropdown
            // — a vehicle already set as another driver's default must not
            // be selectable as anyone else's (see StoreDriverRequest/
            // UpdateDriverRequest's matching server-side rejection; this is
            // the proactive half, keeping already-taken vehicles out of
            // the list in the first place instead of only rejecting after
            // submit). `exclude_default_of_driver` re-includes one
            // specific driver's own already-assigned vehicle — otherwise
            // editing a driver would make their own current default
            // vehicle vanish from their own dropdown (it's "taken", by
            // themselves).
            ->when($request->boolean('unassigned_as_default'), function ($q) use ($request) {
                $keepDriverId = $request->integer('exclude_default_of_driver') ?: null;

                // Driver::query() (not a raw `drivers` table reference)
                // so this stays tenant-scoped via BelongsToTenant, same as
                // the outer Vehicle query — explicit, not just relying on
                // vehicle ids happening not to collide across tenants.
                $takenVehicleIds = Driver::query()
                    ->whereNotNull('default_vehicle_id')
                    ->when($keepDriverId, fn ($d) => $d->where('id', '!=', $keepDriverId))
                    ->pluck('default_vehicle_id');

                $q->whereNotIn('id', $takenVehicleIds);
            })
            ->orderBy($sortBy, $sortDir)
            ->when($sortBy === 'plate_letters', fn ($q) => $q->orderBy('plate_numbers', $sortDir))
            ->paginate($request->integer('per_page', 15));

        return VehicleResource::collection($vehicles);
    }

    public function store(StoreVehicleRequest $request, CreateVehicleAction $action)
    {
        $vehicle = $action->execute($request->validated(), $this->extractFiles($request));

        return VehicleResource::make($vehicle)->response()->setStatusCode(201);
    }

    public function show(int $vehicle)
    {
        $vehicle = Vehicle::with([...self::LIST_WITH, 'documents', 'updatedBy'])->findOrFail($vehicle);

        Gate::authorize('view', $vehicle);

        return VehicleResource::make($vehicle);
    }

    public function update(UpdateVehicleRequest $request, int $vehicle, UpdateVehicleAction $action)
    {
        $vehicle = Vehicle::findOrFail($vehicle);

        $vehicle = $action->execute($vehicle, $request->validated(), $this->extractFiles($request));

        return VehicleResource::make($vehicle->load([...self::LIST_WITH, 'documents', 'updatedBy']));
    }

    public function status(UpdateVehicleStatusRequest $request, int $vehicle, UpdateVehicleStatusAction $action)
    {
        $vehicle = Vehicle::findOrFail($vehicle);
        $vehicle = $action->execute($vehicle, $request->validated('status'), $request->user());

        return VehicleResource::make($vehicle->load(self::LIST_WITH));
    }

    /**
     * Counts for the list page's stat cards. `on_trip` needs the Trip
     * module (Week 7) — 0 for now.
     */
    public function stats()
    {
        Gate::authorize('viewAny', Vehicle::class);

        $countsByCategoryId = Vehicle::query()
            ->selectRaw('vehicle_category_id, count(*) as total')
            ->groupBy('vehicle_category_id')
            ->pluck('total', 'vehicle_category_id');

        // One entry per category that actually exists for this tenant
        // (zero-count ones included) — the list page renders exactly this,
        // so a newly added category shows up as a card immediately.
        $byCategory = VehicleCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn (VehicleCategory $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name(),
                'count' => (int) ($countsByCategoryId[$c->id] ?? 0),
            ])
            ->values();

        $byStatus = Vehicle::query()
            ->selectRaw('operational_status, count(*) as total')
            ->groupBy('operational_status')
            ->pluck('total', 'operational_status');

        return response()->json([
            'data' => [
                'by_category' => $byCategory,
                'availability' => [
                    'available' => (int) ($byStatus['active'] ?? 0),
                    'on_trip' => 0,
                    'on_maintenance' => (int) ($byStatus['on_maintenance'] ?? 0),
                    'deactivated' => (int) ($byStatus['deactivated'] ?? 0),
                ],
            ],
        ]);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Vehicle::class);

        $vehicles = $this->filteredQuery($request)->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.vehicles.export-pdf', ['vehicles' => $vehicles]);

            return $pdf->download('vehicles.pdf');
        }

        return Excel::download(new VehiclesExport($vehicles), 'vehicles.xlsx');
    }

    public function downloadDocument(int $vehicle, int $document)
    {
        $vehicle = Vehicle::findOrFail($vehicle);

        Gate::authorize('view', $vehicle);

        /** @var VehicleDocument $doc */
        $doc = $vehicle->documents()->findOrFail($document);

        abort_if($doc->attachment_path === null, 404);

        return Storage::disk('local')->download($doc->attachment_path);
    }

    /**
     * Flatten the multipart file inputs into the shape the actions expect.
     *
     * @return array{
     *     photo_front: ?UploadedFile,
     *     photo_back: ?UploadedFile,
     *     documents: array<string, ?UploadedFile>,
     *     entry_permits: array<int, ?UploadedFile>
     * }
     */
    private function extractFiles(Request $request): array
    {
        $documents = [];
        foreach (VehicleDocument::SINGLE_TYPES as $type) {
            $documents[$type] = $request->file("documents.{$type}.attachment");
        }

        $entryPermits = [];
        foreach (array_keys((array) $request->input('entry_permits', [])) as $i) {
            $entryPermits[$i] = $request->file("entry_permits.{$i}.attachment");
        }

        return [
            'photo_front' => $request->file('photo_front'),
            'photo_back' => $request->file('photo_back'),
            'documents' => $documents,
            'entry_permits' => $entryPermits,
        ];
    }

    /**
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen — they used to be two copies that drifted apart (export
     * ignored the search box and any filter the UI didn't forward).
     */
    private function filteredQuery(Request $request): Builder
    {
        return Vehicle::query()
            ->with(self::LIST_WITH)
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('affiliation'), fn ($q) => $q->where('affiliation', $request->string('affiliation')))
            ->when($request->filled('search'), function ($q) use ($request) {
                // The UI shows/exposes the plate as "AAA 1234" (Vehicle::plate(),
                // letters + space + numbers), but it's stored as two separate
                // columns — a plain LIKE against either column alone never
                // matches a search for the combined displayed string. Match
                // against the DB-side concatenation (with and without the
                // space) too, so searching what's on screen actually works.
                $search = $request->string('search')->toString();
                $collapsed = trim(preg_replace('/\s+/', '', $search));
                // MySQL (real usage) has CONCAT(); SQLite (the test suite's
                // in-memory DB) doesn't — it uses the `||` operator instead.
                [$withSpace, $noSpace] = $q->getConnection()->getDriverName() === 'sqlite'
                    ? ["plate_letters || ' ' || plate_numbers", 'plate_letters || plate_numbers']
                    : ["CONCAT(plate_letters, ' ', plate_numbers)", 'CONCAT(plate_letters, plate_numbers)'];
                $q->where(function ($q2) use ($search, $collapsed, $withSpace, $noSpace) {
                    $q2->where('plate_letters', 'like', "%{$search}%")
                        ->orWhere('plate_numbers', 'like', "%{$search}%")
                        ->orWhereRaw("{$withSpace} LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("{$noSpace} LIKE ?", ["%{$collapsed}%"]);
                });
            });
    }
}
