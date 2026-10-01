<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,

            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
                'operational_status' => $this->company->operational_status,
            ]),

            'operational_region' => $this->operational_region,

            // FRD §1.12 "حساب ممثل المشروع" — one of the project's client accounts.
            'representative' => $this->representative ? [
                'id' => $this->representative->id,
                'name' => $this->representative->name,
                'email' => $this->representative->email,
            ] : null,

            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'location_url' => $this->location_url,

            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            // Contracts (FRD §1.13) and trips don't exist yet, so those
            // figures are 0 until those modules land. The user count (client
            // accounts that can see the project) comes from withUsersCount().
            'contracts_count' => 0,
            'users_count' => (int) ($this->users_count ?? 0),
            'stats' => [
                'contracts' => 0,
                'trips' => 0,
                'waste_tons' => 0,
            ],

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
