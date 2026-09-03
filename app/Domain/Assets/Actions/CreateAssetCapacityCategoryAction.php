<?php

namespace App\Domain\Assets\Actions;

use App\Models\AssetCapacityCategory;
use App\Models\User;

class CreateAssetCapacityCategoryAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function execute(array $data, User $actor): AssetCapacityCategory
    {
        // tenant_id is stamped by BelongsToTenant's creating hook.
        $category = new AssetCapacityCategory([
            'name' => $data['name'],
            'applies_to' => $data['applies_to'],
            'capacity_cbm' => $data['capacity_cbm'],
            'capacity_ton' => $data['capacity_ton'],
            'additional_data' => $data['additional_data'] ?? null,
        ]);

        $category->updated_by = $actor->id;
        $category->save();

        return $category->load('updatedBy');
    }
}
