<?php

namespace App\Http\Resources;

use App\Domain\Users\Actions\StoreUserImages;
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
            // spec: GCM staff and drivers show the operating company (the
            // tenant), client accounts their client company. Contractor
            // affiliations land with the Contractor module.
            'affiliation' => $this->affiliation,
            'entity_name' => $this->entityName(),
            // Client accounts only (FRD §1.4): the company and the projects
            // they may see — 'all' = every project of the company, current
            // and future; 'specific' = the listed ones.
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ] : null),
            'projects_scope' => $this->company_id === null ? null : ($this->all_projects ? 'all' : 'specific'),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
            ])->values()),
            // Private uploads, served only by the Gate-checked download route.
            'signature_url' => $this->imageUrl('signature'),
            'stamp_url' => $this->imageUrl('stamp'),
            // Only present for a driver-role user — lets the frontend
            // deep-link straight to /app/driver/edit/{driver_id} instead
            // of the generic (and, for a driver, policy-blocked) user
            // edit page. Relies on 'driver' being eager-loaded wherever
            // this Resource is used in bulk — see UserController.
            'driver_id' => $this->driver?->id,
            // FRD: view/edit pages show "Last updated by X — <datetime>".
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }

    private function imageUrl(string $type): ?string
    {
        return $this->{StoreUserImages::COLUMNS[$type]}
            ? route('api.users.images.download', ['user' => $this->id, 'type' => $type])
            : null;
    }
}
