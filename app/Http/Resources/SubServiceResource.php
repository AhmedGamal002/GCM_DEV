<?php

namespace App\Http\Resources;

use App\Models\SubService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'main_service_id' => $this->main_service_id,
            'main_service' => $this->whenLoaded('mainService', fn () => [
                'id' => $this->mainService->id,
                'name' => $this->mainService->name,
                'service_type' => $this->mainService->service_type,
                'operational_status' => $this->mainService->operational_status,
                // Inherited: the FRD shows the main service's categories on the sub-service.
                'vehicle_categories' => $this->mainService->relationLoaded('vehicleCategories')
                    ? $this->mainService->vehicleCategories->map(fn ($c) => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name()])->values()
                    : null,
            ]),

            'asset_type' => $this->asset_type,
            'requires_recycle_receipt' => $this->requires_recycle_receipt,
            'skip_color_code' => $this->skip_color_code,
            'skip_color_label' => SubService::skipColorLabel($this->skip_color_code),
            'skip_color_hex' => $this->skip_color_code ? (SubService::SKIP_COLORS[$this->skip_color_code][1] ?? null) : null,
            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            'capacities' => $this->whenLoaded('capacities', fn () => $this->capacities
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'applies_to' => $c->applies_to,
                    'capacity_cbm' => (float) $c->capacity_cbm,
                    'capacity_ton' => (float) $c->capacity_ton,
                ])->values()),

            'facilities' => $this->whenLoaded('facilities', fn () => $this->facilities
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'code' => $f->code,
                    'name' => $f->name,
                    'environmental_service' => $f->environmental_service,
                    'operational_status' => $f->operational_status,
                ])->values()),

            // Locks the edit form and the status card (ongoing trip / main in a PO).
            'locked' => $this->whenLoaded('mainService', fn () => $this->isLocked()),

            // FRD §1.9.2 statistics — from the trips (`trip_stats`, set by the show endpoint; 0 in a list row).
            'stats' => $this->trip_stats ?? ['trips' => 0, 'projects' => 0, 'quantity_ton' => 0, 'quantity_cbm' => 0, 'drivers' => 0, 'vehicles' => 0, 'containers' => 0, 'tanks' => 0],

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
