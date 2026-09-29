<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'asset_type' => $this->asset_type,

            'capacity_category' => [
                'id' => $this->asset_capacity_category_id,
                'name' => $this->whenLoaded('capacityCategory', fn () => $this->capacityCategory->name),
                'applies_to' => $this->whenLoaded('capacityCategory', fn () => $this->capacityCategory->applies_to),
                'capacity_cbm' => $this->whenLoaded('capacityCategory', fn () => $this->capacityCategory->capacity_cbm),
                'capacity_ton' => $this->whenLoaded('capacityCategory', fn () => $this->capacityCategory->capacity_ton),
            ],

            'compatible_vehicle_categories' => VehicleCategoryResource::collection(
                $this->whenLoaded('compatibleVehicleCategories')
            ),

            'operational_status' => $this->operational_status,
            // FRD V01.14 §1.7.3 "availability": an active asset placed in a
            // project shows as "in a project" instead of plain active.
            'availability' => $this->operational_status === 'active' && $this->project_id !== null
                ? 'in_project'
                : $this->operational_status,
            'affiliation' => $this->affiliation,
            // Same pattern as VehicleResource — Contractor affiliations are
            // Week 5, so an affiliated asset's entity is always the
            // tenant's own name for now.
            'entity_name' => $this->affiliation === 'gcm' ? $this->tenant->name : null,

            // The project the asset was inserted into (null = in the pool).
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),

            'purchase_date' => $this->purchase_date?->toDateString(),
            'additional_data' => $this->additional_data,

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
