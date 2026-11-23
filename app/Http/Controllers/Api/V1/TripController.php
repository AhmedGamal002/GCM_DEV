<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Trips\Actions\AcceptClientObjectionAction;
use App\Domain\Trips\Actions\CancelTripAction;
use App\Domain\Trips\Actions\CreateTripByStaffAction;
use App\Domain\Trips\Actions\FinalApproveTripAction;
use App\Domain\Trips\Actions\RejectClientObjectionAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Domain\Trips\Exports\TripsExport;
use App\Domain\Trips\Support\TripFieldVisibility;
use App\Domain\Trips\Support\TripFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\CancelTripRequest;
use App\Http\Requests\Trips\CompleteTripRequest;
use App\Http\Requests\Trips\StoreTripRequest;
use App\Http\Requests\Trips\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Support\PdfExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * /api/v1/trips — GCM's trips (FRD V01.14 §1.14, part 7.4): the list with
 * its filters, the five status counts, the export, the details with the
 * history, the staff create / edit, and the staff transitions. The client's,
 * driver's and contractor's own trip pages come in 7.5–7.7 on the same
 * scope (Trip::scopeVisibleTo) and resource (TripResource, ج18). Part 7.5
 * opened the list, counters, export, details and files to the client's
 * accounts (their approve / reject is ClientTripReviewController).
 *
 * Every rule of a change lives in its Action (who, from which status, the
 * data, the quota, the assets, the history); this controller only shapes the
 * input and the answer. The final approval, rejecting the client's objection
 * and cancelling sit behind the `otp` middleware (ج15).
 *
 * {trip} is not route-model-bound (SubstituteBindings runs before the tenant
 * middleware) — it is looked up here, among the trips the user sees, so
 * another tenant's trip — or another project's for a client — is a 404.
 */
class TripController extends Controller
{
    /** Explicit allowlist for `sort_by` — never the raw request value. */
    private const SORTABLE_COLUMNS = ['trip_at', 'code', 'status'];

    /** What a list row / the export prints (no N+1 — tested). */
    private const LIST_RELATIONS = [
        'company', 'project', 'driver.user', 'driver.contractor', 'vehicle.contractor', 'vehicle.category',
        'vehicle.embeddedCapacityCategory', 'goingAsset.capacityCategory', 'goingAsset.contractor',
        'returningAsset.capacityCategory', 'returningAsset.contractor', 'mainService', 'subService',
        'purchaseOrderService.purchaseOrder', 'facility', 'updatedBy', 'clientApprover',
    ];

    /** What the details page adds. */
    private const DETAIL_RELATIONS = [
        'project.representative', 'gcmApprover', 'clientApprovedBy', 'completedBy',
        'cancelledBy', 'createdBy',
    ];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Trip::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'desc')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'trip_at';

        // FRD: "sorted automatically from the newest to the oldest"; `id` breaks ties.
        $trips = $this->filteredQuery($request)
            ->orderBy("trips.{$sortBy}", $sortDir)
            ->orderBy('trips.id', $sortDir)
            ->paginate($this->perPage($request));

        return TripResource::collection($trips);
    }

    /** The FRD's counters: trips per status (+ the total), over the trips the user sees. */
    public function stats(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Trip::class);

        $counts = Trip::query()->visibleTo($request->user())
            ->toBase()
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $byStatus = collect(Trip::STATUSES)->map(fn (string $status) => [
            'status' => $status,
            'label' => Trip::statusLabel($status),
            'count' => (int) ($counts[$status] ?? 0),
        ])->values();

        return response()->json(['data' => [
            'total' => $byStatus->sum('count'),
            'by_status' => $byStatus,
        ]]);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Trip::class);

        $query = $this->filteredQuery($request)->orderByDesc('trips.trip_at')->orderByDesc('trips.id');

        // A big list is streamed in chunks (PdfExport::downloadQuery) instead of loaded whole.
        if ($request->query('format', 'xlsx') === 'pdf') {
            return PdfExport::downloadQuery('tenant.trips.export-pdf', 'trips', $query, 'trips.pdf');
        }

        return Excel::download(new TripsExport($query->get(), $request->user()), 'trips.xlsx');
    }

    public function show(Request $request, int $trip)
    {
        $model = self::find($request, $trip);
        Gate::authorize('view', $model);

        return $this->present($model->id, withHistory: TripFieldVisibility::seesInternals($request->user()));
    }

    public function store(StoreTripRequest $request, CreateTripByStaffAction $action)
    {
        $trip = $action->execute($request->user(), $request->tripData(), $request->tripFiles(), $request->completion());

        return $this->present($trip->id)->response()->setStatusCode(201);
    }

    public function update(UpdateTripRequest $request, int $trip, UpdateTripAction $action)
    {
        $model = $this->managed($trip);

        $action->execute($model, $request->user(), $request->tripData(), $request->tripFiles());

        return $this->present($model->id);
    }

    public function finalApprove(CompleteTripRequest $request, int $trip, FinalApproveTripAction $action)
    {
        $model = $this->managed($trip);

        $action->execute($model, $request->user(), $request->completion(), $request->tripData(), $request->tripFiles());

        return $this->present($model->id);
    }

    public function acceptObjection(UpdateTripRequest $request, int $trip, AcceptClientObjectionAction $action)
    {
        $model = $this->managed($trip);

        $action->execute($model, $request->user(), $request->tripData(), $request->tripFiles());

        return $this->present($model->id);
    }

    public function rejectObjection(CompleteTripRequest $request, int $trip, RejectClientObjectionAction $action)
    {
        $model = $this->managed($trip);

        $action->execute($model, $request->user(), $request->completion(), $request->tripData(), $request->tripFiles());

        return $this->present($model->id);
    }

    public function cancel(CancelTripRequest $request, int $trip, CancelTripAction $action)
    {
        $model = $this->managed($trip);

        $action->execute($model, $request->user(), $request->validated('reason'));

        return $this->present($model->id);
    }

    /**
     * GET /trips/{trip}/files/{kind} — a trip file, inline (the browser's viewer opens it, like the
     * driver's documents). Safe inline: every trip upload is pdf / jpg / png (TripFiles). Which kinds a role
     * opens is TripFieldVisibility::fileKinds (staff: all; client: the live photo and its own attachment).
     */
    public function file(Request $request, int $trip, string $kind)
    {
        $model = self::find($request, $trip);
        Gate::authorize('view', $model);

        $column = collect(TripFiles::COLUMNS)->search(fn (array $spec) => $spec[0] === $kind);
        abort_if($column === false, 404);
        abort_unless(in_array($kind, TripFieldVisibility::fileKinds($request->user()), true), 403);

        $path = $model->{$column};
        abort_if($path === null || ! Storage::disk(TripFiles::DISK)->exists($path), 404);

        return Storage::disk(TripFiles::DISK)->response($path);
    }

    // ------------------------------------------------------------------ helpers

    /** A trip the user may manage (System Admin / Data Entry); the action re-checks the status. */
    private function managed(int $id): Trip
    {
        $trip = self::find(request(), $id);
        Gate::authorize('update', $trip);

        return $trip;
    }

    /** A trip among those the user sees (Trip::scopeVisibleTo) — any other is a 404, never a 403 that confirms it exists. */
    public static function find(Request $request, int $id): Trip
    {
        return Trip::query()->visibleTo($request->user())->findOrFail($id);
    }

    private function present(int $id, bool $withHistory = true): TripResource
    {
        $trip = Trip::with([...self::LIST_RELATIONS, ...self::DETAIL_RELATIONS])->findOrFail($id);

        if ($withHistory && TripFieldVisibility::seesInternals(request()->user())) {
            $trip->load(['events' => fn ($q) => $q->orderBy('id'), 'events.actor']);
        }

        return TripResource::make($trip);
    }

    /**
     * The list's filters, shared by index() and export() so an export holds exactly the rows on screen
     * (FRD filters): the time range, driver, vehicle, the driver's and the vehicle's affiliation, a
     * contractor (the driver's or the vehicle's — ج19's rule), client company, project, main / sub
     * service, status, linked to a PO or not, and a search over every text column of the table.
     */
    private function filteredQuery(Request $request): Builder
    {
        $int = fn (string $key) => $request->integer($key);
        $str = fn (string $key) => $request->string($key)->toString();

        return Trip::query()->visibleTo($request->user())
            ->with(self::LIST_RELATIONS)
            ->when($request->filled('date_from'), fn ($q) => $q->where('trips.trip_at', '>=', $str('date_from').' 00:00:00'))
            ->when($request->filled('date_to'), fn ($q) => $q->where('trips.trip_at', '<=', $str('date_to').' 23:59:59'))
            ->when($request->filled('status'), fn ($q) => $q->where('trips.status', $str('status')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $int('driver_id')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('trips.vehicle_id', $int('vehicle_id')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('trips.company_id', $int('company_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('trips.project_id', $int('project_id')))
            ->when($request->filled('main_service_id'), fn ($q) => $q->where('trips.main_service_id', $int('main_service_id')))
            ->when($request->filled('sub_service_id'), fn ($q) => $q->where('trips.sub_service_id', $int('sub_service_id')))
            ->when($request->filled('driver_affiliation'), fn ($q) => $this->affiliation($q, 'driver', $str('driver_affiliation')))
            ->when($request->filled('vehicle_affiliation'), fn ($q) => $this->affiliation($q, 'vehicle', $str('vehicle_affiliation')))
            ->when($request->filled('contractor_id'), fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('driver', fn ($d) => $d->where('drivers.contractor_id', $int('contractor_id')))
                ->orWhereHas('vehicle', fn ($v) => $v->where('vehicles.contractor_id', $int('contractor_id')))))
            ->when($request->filled('purchase_order'), fn ($q) => match ($str('purchase_order')) {
                'linked' => $q->whereNotNull('trips.purchase_order_service_id'),
                'none' => $q->whereNull('trips.purchase_order_service_id'),
                default => $q,
            })
            ->when($request->filled('search'), fn ($q) => $this->search($q, $str('search'), TripFieldVisibility::seesPurchaseOrderNumber($request->user())));
    }

    private function affiliation(Builder $query, string $relation, string $value): void
    {
        $column = $relation === 'driver' ? 'drivers.contractor_id' : 'vehicles.contractor_id';

        match ($value) {
            'gcm' => $query->whereHas($relation, fn ($r) => $r->whereNull($column)),
            'contractor' => $query->whereHas($relation, fn ($r) => $r->whereNotNull($column)),
            default => null,
        };
    }

    /**
     * Every text column of the table: trip No., driver, vehicle plate (as shown), client, project, sub-service,
     * PO No. — the PO number only for whoever sees it (ج18), or a search would tell the client's auditor which
     * number a trip carries.
     */
    private function search(Builder $query, string $search, bool $poNumber): void
    {
        $collapsed = preg_replace('/\s+/', '', $search);
        [$withSpace, $noSpace] = $query->getConnection()->getDriverName() === 'sqlite'
            ? ["plate_letters || ' ' || plate_numbers", 'plate_letters || plate_numbers']
            : ["CONCAT(plate_letters, ' ', plate_numbers)", 'CONCAT(plate_letters, plate_numbers)'];

        $query->where(fn (Builder $q) => $q
            ->where('trips.code', 'like', "%{$search}%")
            ->orWhereHas('driver.user', fn ($u) => $u->where('users.name', 'like', "%{$search}%"))
            ->orWhereHas('vehicle', fn ($v) => $v
                ->whereRaw("{$withSpace} LIKE ?", ["%{$search}%"])
                ->orWhereRaw("{$noSpace} LIKE ?", ["%{$collapsed}%"]))
            ->orWhereHas('company', fn ($c) => $c->where('companies.name', 'like', "%{$search}%"))
            ->orWhereHas('project', fn ($p) => $p->where('projects.name', 'like', "%{$search}%"))
            ->orWhereHas('subService', fn ($s) => $s->where('sub_services.name', 'like', "%{$search}%"))
            ->when($poNumber, fn ($q) => $q
                ->orWhereHas('purchaseOrderService.purchaseOrder', fn ($p) => $p->where('purchase_orders.number', 'like', "%{$search}%"))));
    }
}
