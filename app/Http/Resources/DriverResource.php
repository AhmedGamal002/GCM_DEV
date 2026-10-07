<?php

namespace App\Http\Resources;

use App\Domain\Settings\Support\ExpiryWarning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // `expiry` on every dated document = days left, green/red/expired and the label for the progress
        // bars on the driver page (threshold: General Settings → driver documents).
        $expiry = fn ($date) => app(ExpiryWarning::class)->describe($date, ExpiryWarning::SCOPE_DRIVER);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'code' => $this->user->code,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'photo_url' => $this->user->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->user->photo) : null,
            'status' => $this->user->status,
            'roles' => $this->user->getRoleNames(),
            'affiliation' => $this->user->affiliation,
            'entity_name' => $this->user->entityName(),
            'contractor_id' => $this->user->contractor_id,
            'contractor' => $this->user->relationLoaded('contractor') && $this->user->contractor ? [
                'id' => $this->user->contractor->id,
                'code' => $this->user->contractor->code,
                'name' => $this->user->contractor->name,
            ] : null,
            'additional_data' => $this->user->additional_data,

            'qualified_vehicle_category_ids' => $this->whenLoaded(
                'qualifiedVehicleCategories',
                fn () => $this->qualifiedVehicleCategories->pluck('id')
            ),
            'default_vehicle' => $this->whenLoaded('defaultVehicle', fn () => $this->defaultVehicle ? [
                'id' => $this->defaultVehicle->id,
                'plate' => $this->defaultVehicle->plate(),
                'category' => $this->defaultVehicle->category?->name(),
            ] : null),

            // The list column: Valid / Need review / Expired across the four documents and every permit.
            'documents_status' => $this->whenLoaded('entryPermits', fn () => $this->documentsStatus()),

            'residence' => [
                'number' => $this->residence_number,
                'valid_to' => $this->residence_valid_to?->toDateString(),
                'expiry' => $expiry($this->residence_valid_to),
                'has_attachment' => (bool) $this->residence_attachment,
            ],
            'license' => [
                'number' => $this->license_number,
                'valid_to' => $this->license_valid_to?->toDateString(),
                'expiry' => $expiry($this->license_valid_to),
                'has_attachment' => (bool) $this->license_attachment,
            ],
            'operational_license' => [
                'number' => $this->operational_license_number,
                'valid_to' => $this->operational_license_valid_to?->toDateString(),
                'expiry' => $expiry($this->operational_license_valid_to),
                'has_attachment' => (bool) $this->operational_license_attachment,
            ],
            'insurance' => [
                'number' => $this->insurance_number,
                'valid_to' => $this->insurance_valid_to?->toDateString(),
                'expiry' => $expiry($this->insurance_valid_to),
                'has_attachment' => (bool) $this->insurance_attachment,
            ],

            'entry_permits' => $this->whenLoaded('entryPermits', fn () => $this->entryPermits->map(fn ($permit) => [
                'id' => $permit->id,
                'area_name' => $permit->area_name,
                'permit_number' => $permit->permit_number,
                'valid_to' => $permit->valid_to?->toDateString(),
                'expiry' => $expiry($permit->valid_to),
                'has_attachment' => (bool) $permit->attachment,
            ])),

            // FRD: view/edit pages show "Last updated by X — <datetime>".
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
