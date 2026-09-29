<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class IntermediateFacilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'logo_url' => $this->logo ? Storage::disk('public')->url($this->logo) : null,

            'environmental_service' => $this->environmental_service,
            'recycling_efficiency' => $this->recycling_efficiency === null ? null : (float) $this->recycling_efficiency,

            'operational_status' => $this->operational_status,

            'address' => $this->address,
            'location_url' => $this->location_url,

            'contract_number' => $this->contract_number,
            'contract_start' => $this->contract_start?->toDateString(),
            'contract_end' => $this->contract_end?->toDateString(),
            // The file is private — the page links to the gated download endpoint.
            'has_contract_attachment' => $this->contract_attachment_path !== null,

            'additional_data' => $this->additional_data,

            // Locks the service + efficiency fields on the edit form.
            'in_use' => $this->isInUse(),

            // The sub-services that list this facility — filled in once the
            // Services module (FRD §1.9) exists.
            'supported_sub_services' => [],

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
