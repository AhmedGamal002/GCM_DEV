<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Drivers\Actions\AddDriverEntryPermitAction;
use App\Domain\Drivers\Actions\CreateDriverAction;
use App\Domain\Drivers\Actions\UpdateDriverAction;
use App\Exports\DriversExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drivers\AddDriverEntryPermitRequest;
use App\Http\Requests\Drivers\StoreDriverRequest;
use App\Http\Requests\Drivers\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Models\DriverEntryPermit;
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

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Driver::class);

        $drivers = Driver::query()
            ->with(['user' => fn ($q) => $q->with(['tenant', 'roles'])])
            ->when($request->filled('status'), fn ($q) => $q->whereHas(
                'user',
                fn ($q2) => $q2->where('status', $request->string('status'))
            ))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return DriverResource::collection($drivers);
    }

    /**
     * Same export pattern (and column set) as UserController::export()
     * — the FRD's list-table/export button is one spec shared by every
     * system user, drivers included.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', Driver::class);

        $drivers = Driver::query()
            ->with(['user' => fn ($q) => $q->with(['tenant', 'roles'])])
            ->when($request->filled('status'), fn ($q) => $q->whereHas(
                'user',
                fn ($q2) => $q2->where('status', $request->string('status'))
            ))
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            $pdf = app('dompdf.wrapper')->loadView('exports.drivers-pdf', ['drivers' => $drivers]);

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
        $driver = Driver::with(['user.tenant', 'user.roles', 'entryPermits'])->findOrFail($driver);

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
}
