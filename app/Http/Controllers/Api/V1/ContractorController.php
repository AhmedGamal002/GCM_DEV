<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contractors\Actions\CreateContractorAction;
use App\Domain\Contractors\Actions\UpdateContractorAction;
use App\Domain\Contractors\Actions\UpdateContractorStatusAction;
use App\Domain\Contractors\Exports\ContractorsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contractors\StoreContractorRequest;
use App\Http\Requests\Contractors\UpdateContractorRequest;
use App\Http\Requests\Contractors\UpdateContractorStatusRequest;
use App\Http\Resources\ContractorResource;
use App\Models\Asset;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Contractor companies — FRD V01.14 §1.10.
 *
 * Same "no implicit route-model binding" reasoning as CompanyController:
 * SubstituteBindings runs before the 'tenant' middleware, so an implicit
 * Contractor $contractor param would throw TenantContextMissingException. The
 * model is looked up explicitly inside each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class ContractorController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['code', 'name', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Contractor::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $contractors = $this->filteredQuery($request)
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 15));

        return ContractorResource::collection($contractors);
    }

    /**
     * The four cards on top of the list (FRD §1.10): how many contractors
     * there are and how many vehicles / assets / drivers they supply.
     */
    public function stats()
    {
        Gate::authorize('viewAny', Contractor::class);

        $byStatus = Contractor::query()
            ->selectRaw('operational_status, count(*) as total')
            ->groupBy('operational_status')
            ->pluck('total', 'operational_status');

        $assets = Asset::query()
            ->whereNotNull('contractor_id')
            ->selectRaw('asset_type, count(*) as total')
            ->groupBy('asset_type')
            ->pluck('total', 'asset_type');

        return response()->json([
            'data' => [
                'contractors' => [
                    'total' => (int) $byStatus->sum(),
                    'active' => (int) ($byStatus['active'] ?? 0),
                    'deactivated' => (int) ($byStatus['deactivated'] ?? 0),
                ],
                'vehicles' => Vehicle::query()->whereNotNull('contractor_id')->count(),
                'assets' => [
                    'container' => (int) ($assets['container'] ?? 0),
                    'tank' => (int) ($assets['tank'] ?? 0),
                ],
                'drivers' => Driver::query()->whereNotNull('contractor_id')->count(),
            ],
        ]);
    }

    public function store(StoreContractorRequest $request, CreateContractorAction $action)
    {
        $contractor = $action->execute($request->validated(), $this->extractFiles($request), $request->user());

        return ContractorResource::make($this->withCounts($contractor))->response()->setStatusCode(201);
    }

    public function show(int $contractor)
    {
        $contractor = $this->withCounts(Contractor::with(['updatedBy', 'certifiedServices'])->findOrFail($contractor));

        Gate::authorize('view', $contractor);

        return ContractorResource::make($contractor);
    }

    public function update(UpdateContractorRequest $request, int $contractor, UpdateContractorAction $action)
    {
        $contractor = Contractor::findOrFail($contractor);

        $contractor = $action->execute($contractor, $request->validated(), $this->extractFiles($request), $request->user());

        return ContractorResource::make($this->withCounts($contractor));
    }

    public function status(UpdateContractorStatusRequest $request, int $contractor, UpdateContractorStatusAction $action)
    {
        $contractor = Contractor::findOrFail($contractor);
        $contractor = $action->execute($contractor, $request->validated('status'), $request->user());

        return ContractorResource::make($this->withCounts($contractor->load(['updatedBy', 'certifiedServices'])));
    }

    /**
     * Contract / commercial-registration / tax-registration attachment —
     * private disk, so it's only reachable through this Gate-checked route.
     */
    public function downloadDocument(int $contractor, string $type)
    {
        abort_unless(array_key_exists($type, Contractor::DOCUMENTS), 404);

        $contractor = Contractor::findOrFail($contractor);

        Gate::authorize('view', $contractor);

        $path = $contractor->{Contractor::DOCUMENTS[$type]};

        abort_if($path === null, 404);

        return Storage::disk('local')->download($path);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Contractor::class);

        $contractors = $this->filteredQuery($request)
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.contractors.export-pdf', ['contractors' => $contractors]);

            return $pdf->download('contractors.pdf');
        }

        return Excel::download(new ContractorsExport($contractors), 'contractors.xlsx');
    }

    /**
     * Flatten the multipart file inputs into the shape the actions expect.
     *
     * @return array{logo: ?\Illuminate\Http\UploadedFile, contract: ?\Illuminate\Http\UploadedFile, cr: ?\Illuminate\Http\UploadedFile, tax: ?\Illuminate\Http\UploadedFile}
     */
    private function extractFiles(Request $request): array
    {
        return [
            'logo' => $request->file('logo'),
            'contract' => $request->file('contract_attachment'),
            'cr' => $request->file('cr_attachment'),
            'tax' => $request->file('tax_attachment'),
        ];
    }

    /** The counts of drivers / vehicles / containers / tanks the contractor supplies. */
    private function withCounts(Contractor $contractor): Contractor
    {
        return $contractor->loadCount($this->countRelations());
    }

    /** @return array<int|string, mixed> */
    private function countRelations(): array
    {
        return [
            'drivers',
            'vehicles',
            'assets as containers_count' => fn ($q) => $q->where('asset_type', 'container'),
            'assets as tanks_count' => fn ($q) => $q->where('asset_type', 'tank'),
        ];
    }

    /**
     * The list's filters (status dropdown + search box), shared by index()
     * and export() so an export always contains exactly the rows the user
     * sees on screen. The search box covers every column the table shows
     * that the database holds (ID, name) plus the short name and the
     * trading name.
     */
    private function filteredQuery(Request $request): Builder
    {
        return Contractor::query()
            ->withCount($this->countRelations())
            ->with('certifiedServices')
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('trading_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('prefix', 'like', "%{$search}%");
                });
            });
    }
}
