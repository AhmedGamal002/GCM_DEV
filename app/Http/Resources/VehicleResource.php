<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_letters' => mb_strtoupper((string) $this->plate_letters),
            'plate_numbers' => $this->plate_numbers,
            'plate' => $this->plate(),

            'category' => [
                'id' => $this->vehicle_category_id,
                'slug' => $this->whenLoaded('category', fn () => $this->category->slug),
                'name' => $this->whenLoaded('category', fn () => $this->category->name()),
            ],

            'has_embedded_container' => $this->has_embedded_container,
            'embedded_container_type' => $this->embedded_container_type,
            'embedded_capacity_category' => $this->when(
                $this->has_embedded_container && $this->relationLoaded('embeddedCapacityCategory') && $this->embeddedCapacityCategory,
                fn () => [
                    'id' => $this->embeddedCapacityCategory->id,
                    'name' => $this->embeddedCapacityCategory->name,
                    'capacity_cbm' => $this->embeddedCapacityCategory->capacity_cbm,
                    'capacity_ton' => $this->embeddedCapacityCategory->capacity_ton,
                ],
            ),

            'operational_status' => $this->operational_status,
            'affiliation' => $this->affiliation,
            // Same pattern as UserResource — Contractor affiliations are
            // Week 5, so an affiliated vehicle's entity is always the
            // tenant's own name for now.
            'entity_name' => $this->affiliation === 'gcm' ? $this->tenant->name : null,

            'additional_data' => $this->additional_data,
            'photo_front_url' => $this->photo_front ? Storage::disk('public')->url($this->photo_front) : null,
            'photo_back_url' => $this->photo_back ? Storage::disk('public')->url($this->photo_back) : null,

            // Trip data depends on the Trip module (Week 7) — always 0 for now.
            'trips_count' => 0,

            'documents' => VehicleDocumentResource::collection($this->whenLoaded('documents')),

            'created_at' => $this->created_at,
        ];
    }
}
