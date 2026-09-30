<?php

namespace App\Domain\Assets\Actions;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAssetAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function execute(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $asset = new Asset([
                'name' => $data['name'],
                'asset_type' => $data['asset_type'],
                'asset_capacity_category_id' => $data['asset_capacity_category_id'],
                // Contractor affiliation isn't selectable yet (no Contractor
                // entity until Week 5) — always 'gcm' for now.
                'affiliation' => 'gcm',
                'purchase_date' => $data['purchase_date'] ?? null,
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            // operational_status is outside $fillable — set explicitly.
            $asset->operational_status = $data['operational_status'] ?? 'active';
            $asset->updated_by = $actor->id;
            $asset->save();

            $asset->compatibleVehicleCategories()->sync($data['compatible_vehicle_category_ids']);

            return $asset->load(['capacityCategory', 'compatibleVehicleCategories', 'project', 'updatedBy']);
        });
    }
}
