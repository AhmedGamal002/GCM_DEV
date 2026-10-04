<?php

namespace App\Http\Resources;

use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Contractor
 */
class ContractorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'trading_name' => $this->trading_name,
            'logo_url' => $this->logo ? Storage::disk('public')->url($this->logo) : null,

            'phone' => $this->phone,
            'address' => $this->address,
            'location_url' => $this->location_url,

            // "Certified services": the main services the contractor supports.
            // An empty list means it supports every service (FRD §1.10.2).
            'supports_all_services' => $this->relationLoaded('certifiedServices') ? $this->certifiedServices->isEmpty() : null,
            'certified_services' => $this->whenLoaded('certifiedServices', fn () => $this->certifiedServices
                ->sortBy('name')
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'operational_status' => $s->operational_status])
                ->values()),

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

            // "Contractor representative account" (FRD §1.10.2) — one of the contractor's users.
            'representative' => $this->whenLoaded('representative', fn () => $this->representative ? [
                'id' => $this->representative->id,
                'name' => $this->representative->name,
                'email' => $this->representative->email,
            ] : null),

            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            // The resources the contractor supplies — from withCount() in the controller.
            'drivers_count' => (int) ($this->drivers_count ?? 0),
            'vehicles_count' => (int) ($this->vehicles_count ?? 0),
            'containers_count' => (int) ($this->containers_count ?? 0),
            'tanks_count' => (int) ($this->tanks_count ?? 0),

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The attachments are on the private disk — expose only the
     * Gate-checked download route (null when nothing was uploaded), never
     * a storage URL.
     */
    private function downloadUrl(string $type): ?string
    {
        return $this->{Contractor::DOCUMENTS[$type]}
            ? route('api.contractors.documents.download', ['contractor' => $this->id, 'type' => $type])
            : null;
    }
}
