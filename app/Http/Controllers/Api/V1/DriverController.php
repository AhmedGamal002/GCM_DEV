<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Drivers\Actions\AddDriverEntryPermitAction;
use App\Domain\Drivers\Actions\CreateDriverAction;
use App\Domain\Drivers\Actions\UpdateDriverAction;
use App\Domain\Drivers\Exports\DriversExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drivers\AddDriverEntryPermitRequest;
use App\Http\Requests\Drivers\StoreDriverRequest;
use App\Http\Requests\Drivers\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Models\DriverEntryPermit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same explicit-lookup pattern as UserController (never implicit
 * route-model binding — see that controller's docblock for why).
 */
class DriverController extends Controller
{
    private const DOCUMENT_FIELDS = [
        'residence' => 'residence_attachment',
        'license' => 'license_attachment',
        'operational-license' => 'operational_license_attachment',
        'insurance' => 'insurance_attachment',
    ];

    /**
     * name/code/status live on `users`, not `drivers` — same allowlist
     * reasoning as UserController::SORTABLE_COLUMNS, but sorting by any
     * of these three needs a join (whereHas() only filters, it can't
     * ORDER BY a related table's column).
     */
    private const SORTABLE_COLUMNS = ['code', 'name', 'status', 'created_at'];

    private const USER_TABLE_SORT_COLUMNS = ['code', 'name', 'status'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Driver::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $drivers = $this->filteredQuery($request)
            ->when(
                in_array($sortBy, self::USER_TABLE_SORT_COLUMNS, true),
                fn ($q) => $q->join('users', 'users.id', '=', 'drivers.user_id')
                    ->select('drivers.*')
                    ->orderBy("users.{$sortBy}", $sortDir),
                fn ($q) => $q->orderBy("drivers.{$sortBy}", $sortDir)
            )
            ->paginate($request->integer('per_page', 15));

        return DriverResource::collection($drivers);
    }

    /**
     * The 4 stat cards on the Drivers list — counted directly in SQL
     * against the full tenant-scoped set, not from whatever page of
     * results the table happens to have loaded (that was a real bug:
     * the cards used to be computed client-side from the DataTables
     * ajax response, so at a few thousand drivers they'd silently only
     * count the first page's worth). "on_trips" stays 0 — no Trips
     * module until Week 7, same as the DataTables column it mirrors.
     */
    public function stats()
    {
        Gate::authorize('viewAny', Driver::class);

        $byStatus = Driver::query()
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->selectRaw('users.status, count(*) as total')
            ->groupBy('users.status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => [
                'available' => (int) ($byStatus['active'] ?? 0),
                'on_trips' => 0,
                'on_vacation' => (int) ($byStatus['on_vacation'] ?? 0),
                'deactivated' => (int) ($byStatus['deactivated'] ?? 0),
            ],
        ]);
    }

    /**
     * Same export pattern (and column set) as UserController::export()
     * — the FRD's list-table/export button is one spec shared by every
     * system user, drivers included.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', Driver::class);

        $drivers = $this->filteredQuery($request)
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.*')
            ->orderBy('users.name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.drivers.export-pdf', ['drivers' => $drivers]);

            return $pdf->download('drivers.pdf');
        }

        return Excel::download(new DriversExport($drivers), 'drivers.xlsx');
    }

    public function store(StoreDriverRequest $request, CreateDriverAction $action)
    {
        $entryPermits = [];
        foreach ($request->input('entry_permits', []) as $i => $permit) {
            $entryPermits[] = array_merge($permit, [
                'attachment' => $request->file("entry_permits.{$i}.attachment"),
            ]);
        }

        $driver = $action->execute(
            $request->validated(),
            $request->file('photo'),
            [
                'residence' => $request->file('residence_attachment'),
                'license' => $request->file('license_attachment'),
                'operational_license' => $request->file('operational_license_attachment'),
                'insurance' => $request->file('insurance_attachment'),
            ],
            $entryPermits
        );

        return DriverResource::make($driver)->response()->setStatusCode(201);
    }

    public function show(int $driver)
    {
        $driver = Driver::with(['user.tenant', 'user.roles', 'entryPermits', 'defaultVehicle.category', 'qualifiedVehicleCategories', 'updatedBy'])->findOrFail($driver);

        Gate::authorize('view', $driver);

        return DriverResource::make($driver);
    }

    public function update(UpdateDriverRequest $request, int $driver, UpdateDriverAction $action)
    {
        $driver = Driver::findOrFail($driver);

        $driver = $action->execute($driver, $request->validated(), $request->file('photo'), [
            'residence' => $request->file('residence_attachment'),
            'license' => $request->file('license_attachment'),
            'operational_license' => $request->file('operational_license_attachment'),
            'insurance' => $request->file('insurance_attachment'),
        ]);

        return DriverResource::make($driver);
    }

    public function addEntryPermit(AddDriverEntryPermitRequest $request, int $driver, AddDriverEntryPermitAction $action)
    {
        $driver = Driver::findOrFail($driver);

        $action->execute($driver, $request->validated(), $request->file('attachment'));

        return DriverResource::make($driver->fresh(['user.tenant', 'user.roles', 'entryPermits']));
    }

    /**
     * Documents live on the `local` (non-public) disk — this is the only
     * way to read one back, and it re-checks the same 'view' policy as
     * the driver record itself on every request (no static/guessable
     * URL, unlike the profile photo on the `public` disk).
     */
    public function downloadDocument(int $driver, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, self::DOCUMENT_FIELDS), 404);

        $driver = Driver::findOrFail($driver);

        Gate::authorize('view', $driver);

        $path = $driver->{self::DOCUMENT_FIELDS[$type]};

        abort_if(is_null($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function downloadEntryPermitAttachment(int $permit): StreamedResponse
    {
        $permit = DriverEntryPermit::with('driver')->findOrFail($permit);

        Gate::authorize('view', $permit->driver);

        abort_if(is_null($permit->attachment) || ! Storage::disk('local')->exists($permit->attachment), 404);

        return Storage::disk('local')->response($permit->attachment);
    }

    /**
     * The list's filters (dropdowns + search box), shared by index() and
     * export() so an export always contains exactly the rows the user sees
     * on screen — they used to be two copies that drifted apart (export
     * ignored the search box and any filter the UI didn't forward).
     */
    private function filteredQuery(Request $request): Builder
    {
        return Driver::query()
            ->with(['user' => fn ($q) => $q->with(['tenant', 'roles'])])
            ->when($request->filled('status'), fn ($q) => $q->whereHas(
                'user',
                fn ($q2) => $q2->where('status', $request->string('status'))
            ))
            // Was client-side-only (the JS filter dropdown existed, but
            // nothing on the server ever read it) — worked by accident
            // while the list still fetched everything in one batch.
            ->when($request->filled('affiliation'), fn ($q) => $q->whereHas(
                'user',
                fn ($q2) => $q2->where('affiliation', $request->string('affiliation'))
            ))
            ->when($request->filled('search'), function ($q) use ($request) {
                // Same reasoning as UserController's search fix — `code`
                // (on `users`, same as name/email here) is the list's own
                // first column, so it needs to be searchable too.
                $search = $request->string('search')->toString();
                $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            });
    }
}
