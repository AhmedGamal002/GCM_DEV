<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => $this->photo ? Storage::disk('public')->url($this->photo) : null,
            'status' => $this->status,
            'roles' => $this->getRoleNames(),
            'additional_data' => $this->additional_data,
            // Affiliation/entity-name columns from the FRD's list-table
            // spec. Always 'gcm'/the tenant's own name for now — Company
            // and Contractor affiliations land in Week 4-5.
            'affiliation' => $this->affiliation,
            'entity_name' => $this->affiliation === 'gcm' ? $this->tenant->name : null,
            // Only present for a driver-role user — lets the frontend
            // deep-link straight to /app/driver/edit/{driver_id} instead
            // of the generic (and, for a driver, policy-blocked) user
            // edit page. Relies on 'driver' being eager-loaded wherever
            // this Resource is used in bulk — see UserController.
            'driver_id' => $this->driver?->id,
            'created_at' => $this->created_at,
        ];
    }
}
