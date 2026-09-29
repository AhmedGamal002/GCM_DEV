<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'business_sector' => $this->business_sector,
            'logo_url' => $this->logo ? Storage::disk('public')->url($this->logo) : null,

            // The client representative account needs client user accounts
            // (FRD §1.4) — null until those exist.
            'representative' => null,

            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'location_url' => $this->location_url,

            'contract' => [
                'number' => $this->contract_number,
                'start_date' => $this->contract_start_date?->toDateString(),
                'end_date' => $this->contract_end_date?->toDateString(),
                'download_url' => $this->downloadUrl('contract'),
            ],
            'commercial_registration' => [
                'number' => $this->cr_number,
                'download_url' => $this->downloadUrl('cr'),
            ],
            'tax_registration' => [
                'number' => $this->tax_number,
                'download_url' => $this->downloadUrl('tax'),
            ],

            'operational_status' => $this->operational_status,
            'additional_data' => $this->additional_data,

            // Client users (§1.4), contracts and trips don't exist yet, so
            // those counts are 0 until those modules land. The project count
            // comes from withCount('projects') in the controller.
            'projects_count' => (int) ($this->projects_count ?? 0),
            'users_count' => 0,
            'stats' => [
                'projects' => (int) ($this->projects_count ?? 0),
                'contracts' => 0,
                'trips' => 0,
                'waste_tons' => 0,
            ],

            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The attachments are on the private disk — expose only the
     * Gate-checked download route (null when nothing was uploaded), never
     * a storage URL.
     */
    private function downloadUrl(string $type): ?string
    {
        return $this->{Company::DOCUMENTS[$type]}
            ? route('api.companies.documents.download', ['company' => $this->id, 'type' => $type])
            : null;
    }
}
