<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,

            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
                'operational_status' => $this->company->operational_status,
            ]),

            'operational_region' => $this->operational_region,

            // FRD §1.12 "حساب ممثل المشروع" — one of the project's client accounts.
            'representative' => $this->representative ? [
                'id' => $this->representative->id,
                'name' => $this->representative->name,
                'email' => $this->representative->email,
            ] : null,

            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'location_url' => $this->location_url,

            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            // The trip figures (`trip_stats`) are set by the show endpoint (TripStatistics) — 0 in a list row. The user count (client
            // accounts that can see the project) comes from withUsersCount(), the contract count from
            // withCount('purchaseOrders') (null for a role without PO access).
            'contracts_count' => $contracts = $this->contractsCount($request),
            'users_count' => (int) ($this->users_count ?? 0),
            'stats' => [
                'contracts' => $contracts,
                'trips' => ($this->trip_stats ?? [])['trips'] ?? 0,
                'waste_tons' => ($this->trip_stats ?? [])['waste_tons'] ?? 0,
            ],

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The number of purchase orders (every one, whatever its status). Only a role the FRD lets see POs gets a number:
     * Data Entry has no access to them at all, not even to read, so for it the figure is null and the page shows "—".
     * The controllers load `purchase_orders_count`; without it the count is asked for here, never reported as 0.
     */
    private function contractsCount(Request $request): ?int
    {
        if (! $request->user()?->can('viewAny', PurchaseOrder::class)) {
            return null;
        }

        return (int) ($this->purchase_orders_count ?? $this->purchaseOrders()->count());
    }
}
