<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Fleet\Actions\CreateVehicleAction;
use App\Domain\Fleet\Actions\UpdateVehicleAction;
use App\Domain\Fleet\Actions\UpdateVehicleStatusAction;
use App\Domain\Fleet\Exceptions\CannotDeactivateVehicleException;
use App\Exports\VehiclesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleStatusRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
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

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::query()
            ->with(self::LIST_WITH)
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('affiliation'), fn ($q) => $q->where('affiliation', $request->string('affiliation')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(fn ($q2) => $q2->where('plate_letters', 'like', "%{$search}%")
                    ->orWhere('plate_numbers', 'like', "%{$search}%"));
            })
            ->latest()
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
        $vehicle = Vehicle::with([...self::LIST_WITH, 'documents'])->findOrFail($vehicle);

        Gate::authorize('view', $vehicle);

        return VehicleResource::make($vehicle);
    }

    public function update(UpdateVehicleRequest $request, int $vehicle, UpdateVehicleAction $action)
    {
        $vehicle = Vehicle::findOrFail($vehicle);

        $vehicle = $action->execute($vehicle, $request->validated(), $this->extractFiles($request));

        return VehicleResource::make($vehicle->load([...self::LIST_WITH, 'documents']));
    }

    public function status(UpdateVehicleStatusRequest $request, int $vehicle, UpdateVehicleStatusAction $action)
    {
        $vehicle = Vehicle::findOrFail($vehicle);

        try {
            $vehicle = $action->execute($vehicle, $request->validated('status'), $request->user());
        } catch (CannotDeactivateVehicleException $e) {
            abort(422, $e->getMessage());
        }

        return VehicleResource::make($vehicle->load(self::LIST_WITH));
    }

    /**
     * Counts for the list page's stat cards. `on_trip` needs the Trip
     * module (Week 7) — 0 for now.
     */
    public function stats()
    {
        Gate::authorize('viewAny', Vehicle::class);

        $byCategory = Vehicle::query()
            ->selectRaw('vehicle_categories.slug as slug, count(*) as total')
            ->join('vehicle_categories', 'vehicle_categories.id', '=', 'vehicles.vehicle_category_id')
            ->groupBy('vehicle_categories.slug')
            ->pluck('total', 'slug');

        $byStatus = Vehicle::query()
            ->selectRaw('operational_status, count(*) as total')
            ->groupBy('operational_status')
            ->pluck('total', 'operational_status');

        return response()->json([
            'data' => [
                'by_category' => [
                    'hook_lift' => (int) ($byCategory['hook_lift'] ?? 0),
                    'compactor' => (int) ($byCategory['compactor'] ?? 0),
                    'dump_truck' => (int) ($byCategory['dump_truck'] ?? 0),
                    'water_tanker' => (int) ($byCategory['water_tanker'] ?? 0),
                    'dyna_box' => (int) ($byCategory['dyna_box'] ?? 0),
                ],
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

        $vehicles = Vehicle::query()
            ->with(self::LIST_WITH)
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('affiliation'), fn ($q) => $q->where('affiliation', $request->string('affiliation')))
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            $pdf = app('dompdf.wrapper')->loadView('exports.vehicles-pdf', ['vehicles' => $vehicles]);

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
}
