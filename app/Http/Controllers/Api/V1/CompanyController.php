<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Companies\Actions\CreateCompanyAction;
use App\Domain\Companies\Actions\UpdateCompanyAction;
use App\Domain\Companies\Actions\UpdateCompanyStatusAction;
use App\Domain\Companies\Exports\CompaniesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyStatusRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Client companies — FRD V01.14 §1.11.
 *
 * Same "no implicit route-model binding" reasoning as VehicleController /
 * AssetController: SubstituteBindings runs before the 'tenant'
 * middleware, so an implicit Company $company param would throw
 * TenantContextMissingException. The model is looked up explicitly inside
 * each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class CompanyController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['code', 'name', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Company::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $companies = $this->filteredQuery($request)
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 15));

        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request, CreateCompanyAction $action)
    {
        $company = $action->execute($request->validated(), $this->extractFiles($request), $request->user());

        return CompanyResource::make($company)->response()->setStatusCode(201);
    }

    public function show(int $company)
    {
        $company = Company::withCount('projects')->with('updatedBy')->findOrFail($company);

        Gate::authorize('view', $company);

        return CompanyResource::make($company);
    }

    public function update(UpdateCompanyRequest $request, int $company, UpdateCompanyAction $action)
    {
        $company = Company::findOrFail($company);

        $company = $action->execute($company, $request->validated(), $this->extractFiles($request), $request->user());
        $company->loadCount('projects');

        return CompanyResource::make($company);
    }

    public function status(UpdateCompanyStatusRequest $request, int $company, UpdateCompanyStatusAction $action)
    {
        $company = Company::findOrFail($company);
        $company = $action->execute($company, $request->validated('status'), $request->user());

        return CompanyResource::make($company->load('updatedBy')->loadCount('projects'));
    }

    /**
     * Contract / commercial-registration / tax-registration attachment —
     * private disk, so it's only reachable through this Gate-checked route.
     */
    public function downloadDocument(int $company, string $type)
    {
        abort_unless(array_key_exists($type, Company::DOCUMENTS), 404);

        $company = Company::findOrFail($company);

        Gate::authorize('view', $company);

        $path = $company->{Company::DOCUMENTS[$type]};

        abort_if($path === null, 404);

        return Storage::disk('local')->download($path);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Company::class);

        $companies = $this->filteredQuery($request)
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.companies.export-pdf', ['companies' => $companies]);

            return $pdf->download('client-companies.pdf');
        }

        return Excel::download(new CompaniesExport($companies), 'client-companies.xlsx');
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

    /**
     * The list's filters (status dropdown + search box), shared by index()
     * and export() so an export always contains exactly the rows the user
     * sees on screen. The search box covers every column the table shows
     * that the database holds (ID, name) plus the short name.
     */
    private function filteredQuery(Request $request): Builder
    {
        return Company::query()
            ->withCount('projects')
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('prefix', 'like', "%{$search}%");
                });
            });
    }
}
