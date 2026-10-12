<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $counted = array_key_exists('vehicles_count', $this->resource->getAttributes());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name(),
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            // Primary (built-in) category: renamable, never deletable.
            'is_default' => $this->isDefault(),
            // Embedded-container values the create-vehicle form pre-fills
            // when this category is picked (editable on the vehicle).
            'default_has_embedded_container' => (bool) $this->default_has_embedded_container,
            'default_embedded_container_type' => $this->default_has_embedded_container ? $this->default_embedded_container_type : null,
            'default_embedded_asset_capacity_category_id' => $this->default_has_embedded_container ? $this->default_embedded_asset_capacity_category_id : null,
            // Only present when the controller asked for usage counts
            // (the management list / single fetch) — the plain dropdown
            // call skips the three sub-selects.
            'vehicles_count' => $this->whenCounted('vehicles'),
            'drivers_count' => $this->whenCounted('drivers'),
            'assets_count' => $this->whenCounted('assets'),
            'in_use' => $this->when(
                $counted,
                fn () => ($this->vehicles_count + $this->drivers_count + $this->assets_count) > 0
            ),
        ];
    }
}
