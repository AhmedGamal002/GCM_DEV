<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetCapacityCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'applies_to' => $this->applies_to,
            'capacity_cbm' => $this->capacity_cbm,
            'capacity_ton' => $this->capacity_ton,
            'additional_data' => $this->additional_data,
            'assets_count' => $this->whenCounted('assets'),
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
