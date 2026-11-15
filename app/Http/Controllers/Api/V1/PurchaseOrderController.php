<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderAction;
use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderStatusAction;
use App\Domain\PurchaseOrders\Exports\PurchaseOrdersExport;
use App\Domain\PurchaseOrders\Support\PurchaseOrderStates;
use App\Domain\PurchaseOrders\Support\PurchaseOrderStatusSql;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrders\UpdatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrders\UpdatePurchaseOrderStatusRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use App\Models\User;
use App\Support\PdfExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Purchase orders — FRD V01.14 §1.13 (part 6.2 of Phase 3).
 *
 * Same "no implicit route-model binding" reasoning as ProjectController:
 * SubstituteBindings runs before the 'tenant' middleware, so the model is
 * looked up explicitly inside each method.
 *
 * The rules (quota, status, overlap, edit lock) are not here — they live in
 * App\Domain\PurchaseOrders, and the actions enforce them for any caller.
 * This class is the HTTP shell: who may do what (PurchaseOrderPolicy), the
 * list's filters (filteredQuery, shared by index() and export()), and the
 * shape of the answers.
 *
 * No hard-delete route — deactivation goes through status().
 */
class PurchaseOrderController extends Controller
{
    /** Explicit allowlist for `sort_by` — never the raw request value. */
    private const SORTABLE_COLUMNS = ['number', 'starts_on', 'ends_on', 'operational_status', 'created_at', 'status'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'desc')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'starts_on';

        $query = $this->filteredQuery($request);

        // "status" is the items status (valid / about to run out / exhausted), ranked by its SQL twin.
        $sortBy === 'status'
            ? PurchaseOrderStatusSql::orderBy($query, $sortDir)
            : $query->orderBy("purchase_orders.{$sortBy}", $sortDir);

        $orders = $query
            ->orderBy('purchase_orders.id', $sortDir)
            ->paginate($this->perPage($request));

        // One ledger query for the whole page, not one per row.
        PurchaseOrderStates::attach($orders->getCollection());

        return PurchaseOrderResource::collection($orders);
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrderAction $action)
    {
        $order = $action->execute(
            Arr::except($request->validated(), ['attachment']),
            $request->file('attachment'),
            $request->user(),
        );

        return $this->present($order->id)->response()->setStatusCode(201);
    }

    public function show(int $purchaseOrder)
    {
        $order = $this->findWithRelations($purchaseOrder);

        Gate::authorize('view', $order);

        return $this->presentModel($order);
    }

    public function update(UpdatePurchaseOrderRequest $request, int $purchaseOrder, UpdatePurchaseOrderAction $action)
    {
        $order = PurchaseOrder::findOrFail($purchaseOrder);

        $order = $action->execute(
            $order,
            Arr::except($request->validated(), ['attachment']),
            $request->file('attachment'),
            $request->user(),
        );

        return $this->present($order->id);
    }

    public function status(UpdatePurchaseOrderStatusRequest $request, int $purchaseOrder, UpdatePurchaseOrderStatusAction $action)
    {
        $order = PurchaseOrder::findOrFail($purchaseOrder);
        $order = $action->execute($order, $request->validated('status'), $request->user());

        return $this->present($order->id);
    }

    /**
     * The five cards of the list page (FRD §1.13): the ACTIVE purchase orders
     * (decision ب10) — how many, how many client companies and projects they
     * cover, the trips recorded on them (every trip that is not cancelled:
     * the ledger's net consume count) and the total of their cost items (the
     * sum of every line's maximum trip cost and ancillary cost). A client
     * manager gets the same figures over their own projects only.
     */
    public function stats(Request $request)
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $orders = $this->visibleTo($request->user(), PurchaseOrder::query()->where('operational_status', 'active'));

        $lines = PurchaseOrderService::query()->whereIn('purchase_order_id', (clone $orders)->select('purchase_orders.id'));

        $money = (clone $lines)
            ->selectRaw('SUM(max_trips * trip_cost) AS trips_total')
            ->selectRaw("SUM(CASE WHEN quota_method = 'trips_and_ancillary' THEN max_trips * ancillary_cost_per_trip ELSE 0 END) AS ancillary_total")
            ->toBase()
            ->first();

        $tripCost = round((float) ($money->trips_total ?? 0), 2);
        $ancillaryCost = round((float) ($money->ancillary_total ?? 0), 2);

        return response()->json([
            'data' => [
                'purchase_orders' => (clone $orders)->count(),
                'companies' => (clone $orders)->distinct()->count('purchase_orders.company_id'),
                'projects' => (clone $orders)->distinct()->count('purchase_orders.project_id'),
                'trips' => (int) QuotaLedgerEntry::query()
                    ->whereIn('purchase_order_service_id', (clone $lines)->select('purchase_order_services.id'))
                    ->sum('trips_delta'),
                'cost_items_total' => round($tripCost + $ancillaryCost, 2),
                'trip_cost_total' => $tripCost,
                'ancillary_cost_total' => $ancillaryCost,
                'currency' => __(config('purchase_orders.currency')),
            ],
        ]);
    }

    /** The PO's single attachment, from the private disk, after the same gate as viewing the PO. */
    public function downloadAttachment(int $purchaseOrder)
    {
        $order = PurchaseOrder::findOrFail($purchaseOrder);

        Gate::authorize('view', $order);

        abort_if($order->attachment_path === null || ! Storage::disk('local')->exists($order->attachment_path), 404);

        return Storage::disk('local')->response($order->attachment_path);
    }

    public function export(Request $request)
    {
        Gate::authorize('export', PurchaseOrder::class);

        // `id` breaks ties so the order of the file is stable.
        $orders = $this->filteredQuery($request)->orderBy('number')->orderBy('id')->get();

        PurchaseOrderStates::attach($orders);

        if ($request->query('format', 'xlsx') === 'pdf') {
            return PdfExport::download('tenant.purchase-orders.export-pdf', ['purchaseOrders' => $orders], 'purchase-orders.pdf');
        }

        return Excel::download(new PurchaseOrdersExport($orders), 'purchase-orders.xlsx');
    }

    /**
     * The list's filters, shared by index() and export() so an export holds
     * exactly the rows on screen: client company, project, the time range
     * (a PO whose dates overlap it), the "items status" (valid /
     * near_exhaustion / exhausted — PurchaseOrderStatusSql, the SQL twin of
     * the computed status), the account status, and a search over the
     * number, the company and the project.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = $this->visibleTo(
            $request->user(),
            PurchaseOrder::query()->with(['company', 'project', 'services.mainService']),
        )
            ->when($request->filled('company_id'), fn ($q) => $q->where('purchase_orders.company_id', $request->integer('company_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('purchase_orders.project_id', $request->integer('project_id')))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('purchase_orders.operational_status', $request->string('operational_status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('purchase_orders.ends_on', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('purchase_orders.starts_on', '<=', $request->string('date_to')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($q2) use ($search) {
                    $q2->where('purchase_orders.number', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%{$search}%"));
                });
            });

        // The items status is computed from the quota ledger; PurchaseOrderStatusSql is its SQL twin, so the
        // filter (and the sort) run in the database like every other filter.
        return $query->when($request->filled('status'), fn ($q) => PurchaseOrderStatusSql::filter($q, $request->string('status')->toString()));
    }

    /**
     * A client manager sees only the POs of the projects they are assigned to
     * — the one visibility rule, Project::scopeVisibleTo(). Staff (the
     * policy lets in only the admin and the auditor besides) see everything.
     */
    private function visibleTo(User $user, Builder $query): Builder
    {
        if ($user->hasAnyRole(['system_admin', 'auditor'])) {
            return $query;
        }

        return $query->whereIn('purchase_orders.project_id', Project::query()->visibleTo($user)->select('projects.id'));
    }

    private function findWithRelations(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['company', 'project', 'services.mainService', 'updatedBy'])->findOrFail($id);
    }

    /** The PO with everything the detail pages show, its state and its edit lock. */
    private function present(int $id): PurchaseOrderResource
    {
        return $this->presentModel($this->findWithRelations($id));
    }

    private function presentModel(PurchaseOrder $order): PurchaseOrderResource
    {
        PurchaseOrderStates::attach([$order]);

        return PurchaseOrderResource::make($order)->detailed();
    }
}
