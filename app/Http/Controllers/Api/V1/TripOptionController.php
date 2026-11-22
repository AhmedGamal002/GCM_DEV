<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\Trips\Options\TripCompatibility;
use App\Domain\Trips\Options\TripOptions;
use App\Domain\Trips\Quota\TripPurchaseOrderLine;
use App\Domain\Trips\Support\ClientApproverResolver;
use App\Domain\Trips\Support\TripCapacity;
use App\Domain\Trips\Support\WasteQuantityConverter;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Company;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * GET /api/v1/trips/options/* — the cascading lists of the trip form (part
 * 7.2). Each endpoint answers "what may be chosen here, given the choices
 * made before it"; the rules are TripOptions (and the server re-checks them
 * on save — TripChoiceCheck — whatever the form sends).
 *
 * Who: the staff form (System Admin, Data Entry) and the driver's own form;
 * the driver, the POs and the default approvers are staff-only lists, and a
 * driver's vehicle list is always his own (TripPolicy). The lists that can be
 * long (drivers, vehicles, containers, companies, projects…) take `search`
 * and are paged (`page`, `per_page` — default 20, capped like every list);
 * the response is `{data: [...], meta: {current_page, last_page, per_page,
 * total, …}}`. A form editing a trip sends `trip_id` (staff only): that
 * trip's own containers stay free for it.
 *
 * Every id parameter is looked up in the tenant (BelongsToTenant) — another
 * tenant's id is the same 422 as a missing one. Names are returned raw: the
 * page escapes them.
 */
class TripOptionController extends Controller
{
    private const PER_PAGE = 20;

    // 1. Driver → vehicles

    public function drivers(Request $request): JsonResponse
    {
        Gate::authorize('pickStaffOptions', Trip::class);

        $query = TripOptions::drivers()
            ->with(['user', 'contractor'])
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->select('drivers.*')
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('users.name', 'like', "%{$s}%")
                ->orWhere('users.code', 'like', "%{$s}%")
                ->orWhere('users.phone', 'like', "%{$s}%")))
            ->orderBy('users.name');

        return $this->page($request, $query, fn (Driver $driver) => [
            'id' => $driver->id,
            'name' => $driver->user->name,
            'code' => $driver->user->code,
            'phone' => $driver->user->phone,
            ...$this->affiliation($driver->contractor_id === null ? 'gcm' : 'contractor', $driver->contractor?->name),
            'default_vehicle_id' => TripOptions::defaultVehicle($driver)?->id,
        ]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $driver = $this->driverFor($request);
        $default = TripOptions::defaultVehicle($driver);

        $query = TripOptions::vehicles($driver)
            ->with(['category', 'contractor', 'embeddedCapacityCategory'])
            ->when($this->search($request), fn (Builder $q, string $s) => $this->plateSearch($q, $s))
            ->orderBy('vehicles.plate_letters')->orderBy('vehicles.plate_numbers');

        return $this->page($request, $query, fn (Vehicle $vehicle) => [
            'id' => $vehicle->id,
            'plate' => $vehicle->plate(),
            'category' => $vehicle->category ? ['id' => $vehicle->category->id, 'slug' => $vehicle->category->slug, 'name' => $vehicle->category->name()] : null,
            ...$this->affiliation($vehicle->affiliation, $vehicle->contractor?->name),
            'has_embedded_container' => (bool) $vehicle->has_embedded_container,
            'embedded_container' => $vehicle->has_embedded_container ? [
                'type' => $vehicle->embedded_container_type,
                'capacity' => $this->capacityOf($vehicle->embeddedCapacityCategory),
            ] : null,
            'is_default' => $default !== null && $default->id === $vehicle->id,
        ], ['driver_id' => $driver->id, 'default_vehicle_id' => $default?->id]);
    }

    // 2. Company → projects

    public function companies(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $query = TripOptions::companies()
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('companies.name', 'like', "%{$s}%")
                ->orWhere('companies.prefix', 'like', "%{$s}%")
                ->orWhere('companies.code', 'like', "%{$s}%")))
            ->orderBy('companies.name');

        return $this->page($request, $query, fn (Company $company) => [
            'id' => $company->id,
            'name' => $company->name,
            'prefix' => $company->prefix,
            'code' => $company->code,
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $company = $this->required($request, 'company_id', Company::class);

        $query = TripOptions::projects($company->id)
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('projects.name', 'like', "%{$s}%")
                ->orWhere('projects.code', 'like', "%{$s}%")))
            ->orderBy('projects.name');

        return $this->page($request, $query, fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'operational_region' => $project->operational_region,
        ]);
    }

    // 3. Project → POs (staff)

    /**
     * The project's POs that are not deactivated — running or not (ب6) — newest first. With
     * `main_service_id`: only those covering it, each with the line's per-trip costs and status, and the
     * one a trip would default to. `trip_at` (default now) dates the statuses. No balances (security
     * checklist 7.2). An empty list means the trip can only go without a PO.
     */
    public function purchaseOrders(Request $request): JsonResponse
    {
        Gate::authorize('pickStaffOptions', Trip::class);

        $project = $this->required($request, 'project_id', Project::class);
        $mainService = $this->optional($request, 'main_service_id', MainService::class);
        $at = $this->dateTime($request);

        $orders = TripOptions::purchaseOrders($project, $mainService?->id)
            ->with(['services' => fn ($q) => $mainService === null ? $q->whereRaw('1 = 0') : $q->where('main_service_id', $mainService->id)])
            ->get();

        $lines = $orders->map(fn (PurchaseOrder $po) => $po->services->first()?->setRelation('purchaseOrder', $po))->filter();
        $balances = $lines->isEmpty() ? [] : QuotaBalances::forMany($lines, $at);
        $default = $mainService === null ? null : TripPurchaseOrderLine::defaultFor($project, $mainService->id, $at);

        $data = $orders->map(function (PurchaseOrder $po) use ($balances, $default, $at) {
            $dates = QuotaBalances::dates($po->starts_on, $po->ends_on, $at);
            $line = $po->services->first();
            $state = $line === null ? null : PurchaseOrderStatus::line($balances[$line->id]);

            return [
                'id' => $po->id,
                'number' => $po->number,
                'starts_on' => $po->starts_on->toDateString(),
                'ends_on' => $po->ends_on->toDateString(),
                'timeline' => $dates['timeline'],
                'timeline_label' => PurchaseOrderStatus::timelineLabel($dates['timeline']),
                'days_label' => PurchaseOrderStatus::daysLabel($dates['timeline'], $dates['days_remaining'], $dates['days_until_start'], $dates['days_since_end']),
                'line' => $line === null ? null : [
                    'quota_method' => $line->quota_method,
                    'trip_cost' => (float) $line->trip_cost,
                    'ancillary_cost' => $line->quota_method === 'trips_and_ancillary' ? (float) $line->ancillary_cost_per_trip : 0.0,
                    'status' => $state,
                    'status_label' => PurchaseOrderStatus::lineLabel($state),
                ],
                'is_default' => $default !== null && (int) $default->purchase_order_id === $po->id,
            ];
        })->values();

        return response()->json(['data' => $data, 'meta' => [
            'trip_at' => $at->toIso8601String(),
            'default_purchase_order_id' => $default?->purchase_order_id,
            // ب6 / FRD: no PO (not deactivated) for the service → the trip goes "without PO", with a hand-entered cost.
            'without_purchase_order_only' => $data->isEmpty(),
            'cost_source' => $data->isEmpty() ? 'manual' : 'purchase_order',
        ]]);
    }

    // 4. Main services

    public function mainServices(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $vehicle = $this->required($request, 'vehicle_id', Vehicle::class);
        $project = $this->required($request, 'project_id', Project::class);

        // The driver names no PO: his list is what the project's POs cover (FRD). Staff choose.
        $isStaff = Gate::allows('pickStaffOptions', Trip::class);
        $requires = ! $isStaff || $request->boolean('requires_purchase_order', true);
        $purchaseOrder = $isStaff && $requires ? $this->optional($request, 'purchase_order_id', PurchaseOrder::class) : null;

        if ($purchaseOrder !== null && ((int) $purchaseOrder->project_id !== $project->id || ! $purchaseOrder->isActive())) {
            throw ValidationException::withMessages(['purchase_order_id' => __('Choose a purchase order of the trip\'s project.')]);
        }

        $query = TripOptions::mainServices($vehicle, $project, $requires, $purchaseOrder)
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where('main_services.name', 'like', "%{$s}%"))
            ->orderBy('main_services.name');

        return $this->page($request, $query, fn (MainService $service) => [
            'id' => $service->id,
            'name' => $service->name,
            'service_type' => $service->service_type,
            'is_supplying' => $service->isSupplying(),
        ], ['project_has_purchase_orders' => TripOptions::purchaseOrders($project)->exists()]);
    }

    // 5. Sub-services

    public function subServices(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $mainService = $this->required($request, 'main_service_id', MainService::class);
        $container = $this->container($request);

        $query = TripOptions::subServices($mainService->id, $container)
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where('sub_services.name', 'like', "%{$s}%"))
            ->orderBy('sub_services.name');

        return $this->page($request, $query, fn (SubService $sub) => [
            'id' => $sub->id,
            'name' => $sub->name,
            'asset_type' => $sub->asset_type,
            'requires_recycle_receipt' => (bool) $sub->requires_recycle_receipt,
            'skip_color_code' => $sub->skip_color_code,
            'skip_color_label' => SubService::skipColorLabel($sub->skip_color_code),
        ], ['container' => $container === null ? null : ['type' => $container['type'], 'capacity' => $this->capacityOf($container['capacity'])]]);
    }

    // 6. Capacity

    /**
     * The most the trip can carry and the quantity it starts with (FRD: the capacity, which staff may
     * lower but never raise), with the ton ↔ m³ factor (ج10). `null` when there is no container yet or the
     * trip supplies (no waste, ج11).
     */
    public function capacity(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $vehicle = $this->required($request, 'vehicle_id', Vehicle::class);
        $mainService = $this->optional($request, 'main_service_id', MainService::class);
        $returning = $this->optional($request, 'returning_asset_id', Asset::class);
        $capacity = TripOptions::capacity($vehicle, $mainService, $returning);

        return response()->json(['data' => [
            'capacity' => $this->capacityOf($capacity),
            'source' => $mainService?->isSupplying() ? 'supplying' : ($capacity === null ? null : ($vehicle->has_embedded_container ? 'embedded' : 'returning_container')),
            'default_quantity' => $capacity === null ? null : [
                'ton' => WasteQuantityConverter::round((float) $capacity->capacity_ton),
                'cbm' => WasteQuantityConverter::round((float) $capacity->capacity_cbm),
            ],
            'converter' => WasteQuantityConverter::toArray(),
        ]]);
    }

    // 7. Containers

    public function goingAssets(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $vehicle = $this->required($request, 'vehicle_id', Vehicle::class);

        return $this->assets($request, TripOptions::goingAssets($vehicle, $this->exceptTrip($request)), [
            'takes_external_containers' => TripCompatibility::takesExternalContainers($vehicle),
        ]);
    }

    public function returningAssets(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $project = $this->required($request, 'project_id', Project::class);
        $vehicle = $this->optional($request, 'vehicle_id', Vehicle::class);
        $going = $this->optional($request, 'going_asset_id', Asset::class);

        return $this->assets($request, TripOptions::returningAssets($project, $vehicle, $going?->id, $this->exceptTrip($request)), [
            'takes_external_containers' => $vehicle === null || TripCompatibility::takesExternalContainers($vehicle),
        ]);
    }

    // 8. Facilities

    public function facilities(Request $request): JsonResponse
    {
        Gate::authorize('pickOptions', Trip::class);

        $sub = $this->required($request, 'sub_service_id', SubService::class);

        $query = TripOptions::facilities($sub)
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where('intermediate_facilities.name', 'like', "%{$s}%"))
            ->orderBy('intermediate_facilities.name');

        return $this->page($request, $query, fn (IntermediateFacility $facility) => [
            'id' => $facility->id,
            'name' => $facility->name,
            'prefix' => $facility->prefix,
            'environmental_service' => $facility->environmental_service,
        ]);
    }

    // The default approvers (staff form, "approvals & services" card)

    public function clientApprovers(Request $request, ClientApproverResolver $resolver): JsonResponse
    {
        Gate::authorize('pickStaffOptions', Trip::class);

        $project = $this->required($request, 'project_id', Project::class);

        $query = $resolver->approversOf($project)->with('roles')
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where('users.name', 'like', "%{$s}%"))
            ->orderBy('users.name');

        return $this->page($request, $query, fn (User $user) => $this->person($user), [
            'default_id' => $resolver->defaultFor($project)?->id,
        ]);
    }

    public function gcmApprovers(Request $request): JsonResponse
    {
        Gate::authorize('pickStaffOptions', Trip::class);

        // Data Entry never sees the System Admin's account (User::scopeVisibleTo).
        $query = User::query()->visibleTo($request->user())->with('roles')
            ->where('users.status', 'active')
            ->whereHas('roles', fn (Builder $r) => $r->whereIn('name', ['system_admin', 'data_entry']))
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where('users.name', 'like', "%{$s}%"))
            ->orderBy('users.name');

        return $this->page($request, $query, fn (User $user) => $this->person($user), [
            'default_id' => $request->user()->id,
        ]);
    }

    // ------------------------------------------------------------------ helpers

    private function assets(Request $request, Builder $query, array $meta): JsonResponse
    {
        $query->with(['capacityCategory', 'contractor'])
            ->when($this->search($request), fn (Builder $q, string $s) => $q->where('assets.name', 'like', "%{$s}%"))
            ->orderBy('assets.name');

        return $this->page($request, $query, fn (Asset $asset) => [
            'id' => $asset->id,
            'name' => $asset->name,
            'asset_type' => $asset->asset_type,
            'capacity' => $this->capacityOf($asset->capacityCategory),
            ...$this->affiliation($asset->affiliation, $asset->contractor?->name),
            'at_project' => $asset->project_id !== null,
        ], $meta);
    }

    /** The driver whose vehicles are listed: the driver himself, or the one the staff form chose. */
    private function driverFor(Request $request): Driver
    {
        $actor = $request->user();

        if (Gate::denies('pickStaffOptions', Trip::class)) {
            return $actor->driver ?? abort(403);
        }

        return $this->required($request, 'driver_id', Driver::class);
    }

    /** The trip a staff form is editing — its own containers stay free for it. */
    private function exceptTrip(Request $request): ?int
    {
        if (! $request->filled('trip_id') || Gate::denies('pickStaffOptions', Trip::class)) {
            return null;
        }

        return $this->required($request, 'trip_id', Trip::class)->id;
    }

    /** @return array{type: string, capacity: AssetCapacityCategory}|null */
    private function container(Request $request): ?array
    {
        return TripCompatibility::container(
            $this->optional($request, 'vehicle_id', Vehicle::class)?->loadMissing('embeddedCapacityCategory'),
            $this->optional($request, 'going_asset_id', Asset::class)?->loadMissing('capacityCategory'),
            $this->optional($request, 'returning_asset_id', Asset::class)?->loadMissing('capacityCategory'),
        );
    }

    private function page(Request $request, Builder $query, \Closure $map, array $meta = []): JsonResponse
    {
        $page = $query->paginate($this->perPage($request, self::PER_PAGE));

        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                ...$meta,
            ],
        ]);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function required(Request $request, string $key, string $model): Model
    {
        if (! $request->filled($key)) {
            throw ValidationException::withMessages([$key => __('validation.required', ['attribute' => __('validation.attributes.'.$key)])]);
        }

        return $this->optional($request, $key, $model);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function optional(Request $request, string $key, string $model): ?Model
    {
        if (! $request->filled($key)) {
            return null;
        }

        $id = $request->input($key);
        $found = is_numeric($id) ? $model::find((int) $id) : null;

        if ($found === null) {
            throw ValidationException::withMessages([$key => __('The selected :attribute is invalid.', ['attribute' => __('validation.attributes.'.$key)])]);
        }

        return $found;
    }

    private function dateTime(Request $request): Carbon
    {
        if (! $request->filled('trip_at')) {
            return now();
        }

        try {
            return Carbon::parse($request->string('trip_at')->toString());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['trip_at' => __('The trip date and time is not valid.')]);
        }
    }

    private function search(Request $request): ?string
    {
        $search = trim($request->string('search')->toString());

        return $search === '' ? null : $search;
    }

    /** Matches the plate as shown ("ABC 1234") and without its space — like the vehicles list. */
    private function plateSearch(Builder $query, string $search): Builder
    {
        $collapsed = preg_replace('/\s+/', '', $search);
        [$withSpace, $noSpace] = $query->getConnection()->getDriverName() === 'sqlite'
            ? ["vehicles.plate_letters || ' ' || vehicles.plate_numbers", 'vehicles.plate_letters || vehicles.plate_numbers']
            : ["CONCAT(vehicles.plate_letters, ' ', vehicles.plate_numbers)", 'CONCAT(vehicles.plate_letters, vehicles.plate_numbers)'];

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("{$withSpace} LIKE ?", ["%{$search}%"])
            ->orWhereRaw("{$noSpace} LIKE ?", ["%{$collapsed}%"]));
    }

    /** @return array{affiliation: ?string, entity_name: ?string} GCM's own name, or the contractor's (as every resource). */
    private function affiliation(?string $affiliation, ?string $contractorName): array
    {
        return [
            'affiliation' => $affiliation,
            'entity_name' => match ($affiliation) {
                'gcm' => app('tenant')->name,
                'contractor' => $contractorName,
                default => null,
            },
        ];
    }

    private function capacityOf(?AssetCapacityCategory $capacity): ?array
    {
        return $capacity === null ? null : [
            'id' => $capacity->id,
            'name' => $capacity->name,
            'capacity_ton' => (float) $capacity->capacity_ton,
            'capacity_cbm' => (float) $capacity->capacity_cbm,
        ];
    }

    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'role' => $user->getRoleNames()->first()];
    }
}
