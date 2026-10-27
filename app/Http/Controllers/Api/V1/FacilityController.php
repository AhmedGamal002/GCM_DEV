<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Facilities\Actions\CreateFacilityAction;
use App\Domain\Facilities\Actions\UpdateFacilityAction;
use App\Domain\Facilities\Actions\UpdateFacilityStatusAction;
use App\Domain\Facilities\Exports\FacilitiesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facilities\StoreFacilityRequest;
use App\Http\Requests\Facilities\UpdateFacilityRequest;
use App\Http\Requests\Facilities\UpdateFacilityStatusRequest;
use App\Http\Resources\IntermediateFacilityResource;
use App\Models\IntermediateFacility;
use App\Support\PdfExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Intermediate waste facilities — FRD V01.14 §1.8.
 *
 * Same "no implicit route-model binding" reasoning as AssetController:
 * SubstituteBindings runs before the 'tenant' middleware, so an implicit
 * IntermediateFacility param would throw TenantContextMissingException. The
 * model is looked up explicitly inside each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class FacilityController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['code', 'name', 'environmental_service', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', IntermediateFacility::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $facilities = $this->filteredQuery($request)
            ->orderBy($sortBy, $sortDir)
            ->paginate($this->perPage($request));

        return IntermediateFacilityResource::collection($facilities);
    }

    public function store(StoreFacilityRequest $request, CreateFacilityAction $action)
    {
        $facility = $action->execute($request->validated(), $request->user(), $this->extractFiles($request));

        return IntermediateFacilityResource::make($facility)->response()->setStatusCode(201);
    }

    public function show(int $facility)
    {
        $facility = IntermediateFacility::with(['updatedBy', 'subServices' => fn ($q) => $q->with('mainService')->orderBy('name')])
            ->findOrFail($facility);

        Gate::authorize('view', $facility);

        return IntermediateFacilityResource::make($facility);
    }

    public function update(UpdateFacilityRequest $request, int $facility, UpdateFacilityAction $action)
    {
        $facility = IntermediateFacility::findOrFail($facility);

        $facility = $action->execute($facility, $request->validated(), $request->user(), $this->extractFiles($request));

        return IntermediateFacilityResource::make($facility);
    }

    public function status(UpdateFacilityStatusRequest $request, int $facility, UpdateFacilityStatusAction $action)
    {
        $facility = IntermediateFacility::findOrFail($facility);

        return IntermediateFacilityResource::make(
            $action->execute($facility, $request->validated('status'), $request->user())->load('updatedBy')
        );
    }

    /**
     * FRD §1.8 stat cards — how many facilities offer each environmental
     * service (all of them, plus how many of those are currently active).
     */
    public function stats()
    {
        Gate::authorize('viewAny', IntermediateFacility::class);

        $rows = IntermediateFacility::query()
            ->selectRaw('environmental_service, operational_status, count(*) as total')
            ->groupBy('environmental_service', 'operational_status')
            ->get();

        $data = [];
        foreach (IntermediateFacility::SERVICES as $service) {
            $ofService = $rows->where('environmental_service', $service);
            $data[$service] = [
                'total' => (int) $ofService->sum('total'),
                'active' => (int) $ofService->where('operational_status', 'active')->sum('total'),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Live check behind the create form's short-name field: is this prefix
     * still free? The store request stays the real gatekeeper — this only
     * lets the form say so before "Submit".
     */
    public function prefixAvailable(Request $request)
    {
        Gate::authorize('create', IntermediateFacility::class);

        $prefix = strtoupper(trim($request->string('prefix')->toString()));

        return response()->json([
            'available' => ! IntermediateFacility::query()->where('prefix', $prefix)->exists(),
        ]);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', IntermediateFacility::class);

        // `id` breaks ties so the chunked read of a big PDF never repeats or skips a row.
        $query = $this->filteredQuery($request)->orderBy('name')->orderBy('id');

        if ($request->query('format', 'xlsx') === 'pdf') {
            return PdfExport::downloadQuery('tenant.facilities.export-pdf', 'facilities', $query, 'facilities.pdf');
        }

        return Excel::download(new FacilitiesExport($query->get()), 'facilities.xlsx');
    }

    public function downloadContract(int $facility)
    {
        $facility = IntermediateFacility::findOrFail($facility);

        Gate::authorize('view', $facility);

        abort_if($facility->contract_attachment_path === null, 404);

        // A readable name instead of the random stored one: "GLR-contract.pdf".
        $extension = pathinfo($facility->contract_attachment_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $facility->contract_attachment_path,
            $facility->prefix.'-contract'.($extension ? ".{$extension}" : '')
        );
    }

    /**
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen. Search covers every column the list shows that holds free
     * text: the ID (code), the name and the prefix.
     */
    private function filteredQuery(Request $request): Builder
    {
        return IntermediateFacility::query()
            ->when($request->filled('environmental_service'), fn ($q) => $q->where('environmental_service', $request->string('environmental_service')->toString()))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('prefix', 'like', $term)->orWhere('code', 'like', $term));
            });
    }

    /**
     * @return array{logo: ?\Illuminate\Http\UploadedFile, contract_attachment: ?\Illuminate\Http\UploadedFile}
     */
    private function extractFiles(Request $request): array
    {
        return [
            'logo' => $request->file('logo'),
            'contract_attachment' => $request->file('contract_attachment'),
        ];
    }
}
