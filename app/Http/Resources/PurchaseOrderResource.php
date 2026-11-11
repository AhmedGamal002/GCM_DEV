<?php

namespace App\Http\Resources;

use App\Domain\PurchaseOrders\Quota\ServiceExhaustion;
use App\Domain\PurchaseOrders\Support\PurchaseOrderEditability;
use App\Domain\PurchaseOrders\Support\PurchaseOrderStates;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FRD V01.14 §1.13. Every state the page shows — the items status, the days
 * left, each line's components with their bars — comes from
 * PurchaseOrderStatus (translated on the server), never from the JS. The
 * list attaches the states of the whole page with one ledger query
 * (PurchaseOrderStates::attach); a single order computes its own.
 *
 * `->detailed()` adds what the view and edit pages need and the list doesn't:
 * the lock state of the edit form (PurchaseOrderEditability).
 *
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $state = PurchaseOrderStates::of($this->resource);
        $lines = $this->relationLoaded('services') ? $this->services : collect();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'paper_number' => $this->paperNumber(),

            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'prefix' => $this->company->prefix,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),

            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'region' => $this->region,
            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            // The attachment is on the private disk: only this gate-checked route serves it.
            'attachment' => $this->attachment_path === null ? null : [
                'extension' => pathinfo($this->attachment_path, PATHINFO_EXTENSION),
                'download_url' => route('api.purchase-orders.attachment', ['purchaseOrder' => $this->id]),
            ],

            // "الحالة العامة للبنود" + "عدد الأيام المتبقية" (FRD list columns).
            'status' => $state['status'],
            'status_label' => $state['status_label'],
            'timeline' => $state['timeline'],
            'timeline_label' => $state['timeline_label'],
            'days_remaining' => $state['days_remaining'],
            'days_until_start' => $state['days_until_start'],
            'days_since_end' => $state['days_since_end'],
            'days_label' => $state['days_label'],

            'currency' => config('purchase_orders.currency'),
            'services' => $lines->map(fn (PurchaseOrderService $line) => $this->line($line, $state['services'][$line->id] ?? null))->values(),
            'totals' => $this->totals($lines, $state),

            'editability' => $this->when($this->detailed, fn () => $this->editability()),

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The edit form's lock state. PurchaseOrderEditability keys the per-line part by line id, but Laravel's
     * resources turn an array with numeric keys into a plain list (so line 1 would land at index 0): the lines
     * are sent as a list that names its line in `id`, whatever the ids are.
     *
     * @return array<string, mixed>
     */
    private function editability(): array
    {
        $lock = PurchaseOrderEditability::for($this->resource)->toArray();

        $lock['services'] = collect($lock['services'])
            ->map(fn (array $line, int $id) => ['id' => $id, ...$line])
            ->values()
            ->all();

        return $lock;
    }

    /** One line ("بند التعاقد") with its quota, its computed totals and its state. */
    private function line(PurchaseOrderService $line, ?array $state): array
    {
        return [
            'id' => $line->id,
            'main_service' => $line->relationLoaded('mainService') ? [
                'id' => $line->mainService->id,
                'name' => $line->mainService->name,
                'service_type' => $line->mainService->service_type,
            ] : null,
            'quota_method' => $line->quota_method,
            'max_trips' => $line->max_trips,
            'trip_cost' => (float) $line->trip_cost,
            'ancillary_cost_per_trip' => $line->tracksAncillary() ? (float) $line->ancillary_cost_per_trip : null,
            'alert_percent' => $line->alert_percent,
            // Computed, never stored: "اجمالي الحد الأقصى من ..." (FRD).
            'max_trip_cost' => $line->maxTripCost(),
            'max_ancillary_cost' => $line->tracksAncillary() ? $line->maxAncillaryCost() : null,
            'status' => $state['status'] ?? null,
            'status_label' => $state['status_label'] ?? null,
            'components' => $state['components'] ?? [],
        ];
    }

    /**
     * The PO as a whole, for the detail page's cards: trips and cost used out
     * of their maximums, summed over the lines.
     *
     * @param  iterable<PurchaseOrderService>  $lines
     * @param  array<string, mixed>  $state
     */
    private function totals(iterable $lines, array $state): array
    {
        $totals = ['max_trips' => 0, 'trips_used' => 0, 'max_cost' => 0.0, 'cost_used' => 0.0];

        foreach ($lines as $line) {
            $components = $state['services'][$line->id]['components'] ?? [];

            $totals['max_trips'] += $line->max_trips;
            $totals['trips_used'] += $components[ServiceExhaustion::TRIPS]['used'] ?? 0;
            $totals['max_cost'] += $line->maxTripCost() + $line->maxAncillaryCost();
            $totals['cost_used'] += ($components[ServiceExhaustion::TRIP_COST]['used'] ?? 0) + ($components[ServiceExhaustion::ANCILLARY_COST]['used'] ?? 0);
        }

        $totals['max_cost'] = round($totals['max_cost'], 2);
        $totals['cost_used'] = round($totals['cost_used'], 2);

        return $totals;
    }
}
