<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'business_sector' => $this->business_sector,
            'logo_url' => $this->logo ? Storage::disk('public')->url($this->logo) : null,

            // FRD §1.11 "حساب ممثل العميل" — one of the company's project managers.
            'representative' => $this->representative ? [
                'id' => $this->representative->id,
                'name' => $this->representative->name,
                'email' => $this->representative->email,
            ] : null,

            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'location_url' => $this->location_url,

            'contract' => [
                'number' => $this->contract_number,
                'start_date' => $this->contract_start_date?->toDateString(),
                'end_date' => $this->contract_end_date?->toDateString(),
                'download_url' => $this->downloadUrl('contract'),
            ],
            'commercial_registration' => [
                'number' => $this->cr_number,
                'download_url' => $this->downloadUrl('cr'),
            ],
            'tax_registration' => [
                'number' => $this->tax_number,
                'download_url' => $this->downloadUrl('tax'),
            ],

            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            // Trips don't exist yet, so those figures are 0 until that module lands. The project, contract and
            // user counts come from withCount() in the controller (contracts: null for a role without PO access).
            'projects_count' => (int) ($this->projects_count ?? 0),
            'contracts_count' => $contracts = $this->contractsCount($request),
            'users_count' => (int) ($this->users_count ?? 0),
            'stats' => [
                'projects' => (int) ($this->projects_count ?? 0),
                'contracts' => $contracts,
                'trips' => 0,
                'waste_tons' => 0,
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

    /**
     * The attachments are on the private disk — expose only the
     * Gate-checked download route (null when nothing was uploaded), never
     * a storage URL.
     */
    private function downloadUrl(string $type): ?string
    {
        return $this->{Company::DOCUMENTS[$type]}
            ? route('api.companies.documents.download', ['company' => $this->id, 'type' => $type])
            : null;
    }
}
