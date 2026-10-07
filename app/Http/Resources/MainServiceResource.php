<?php

namespace App\Http\Resources;

use App\Models\SubService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MainServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_type' => $this->service_type,
            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            'vehicle_categories' => $this->whenLoaded('vehicleCategories', fn () => $this->vehicleCategories
                ->map(fn ($c) => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name()])
                ->values()),

            'sub_services' => $this->whenLoaded('subServices', fn () => $this->subServices
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'operational_status' => $s->operational_status,
                    // What the Sub Services accordion shows on each row.
                    'asset_type' => $s->asset_type,
                    'requires_recycle_receipt' => (bool) $s->requires_recycle_receipt,
                    'skip_color_code' => $s->skip_color_code,
                    'skip_color_label' => SubService::skipColorLabel($s->skip_color_code),
                    'skip_color_hex' => $s->skip_color_code ? (SubService::SKIP_COLORS[$s->skip_color_code][1] ?? null) : null,
                ])
                ->values()),
            'sub_services_count' => $this->whenCounted('subServices'),

            // Locks the edit form and the status card (a PO includes it).
            'in_use' => $this->isInUse(),

            // FRD §1.9.1 statistics — all built on trips / POs, which don't
            // exist yet. Real numbers replace these zeros when they land.
            'stats' => [
                'trips' => 0,
                'projects' => 0,
                'quantity_ton' => 0,
                'quantity_cbm' => 0,
                'drivers' => 0,
                'vehicles' => 0,
                'containers' => 0,
                'tanks' => 0,
            ],

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
