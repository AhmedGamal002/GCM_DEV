<?php

namespace App\Domain\Assets\Actions;

use App\Models\Asset;
use App\Models\User;

/**
 * FRD §1.7.3 asset edit page: "أسم الأصل فقط المسموح بتعديله اما السعة
 * والتصنيف وما سواها" — the name is the ONLY editable field. Capacity,
 * type, affiliation, purchase date, notes and the compatible vehicle
 * categories are all locked because they ripple into contracts and
 * trips. operational_status has its own action / endpoint.
 */
class UpdateAssetAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function execute(Asset $asset, array $data, User $actor): Asset
    {
        $asset->name = $data['name'];
        $asset->updated_by = $actor->id;
        $asset->save();

        return $asset->load(['capacityCategory', 'compatibleVehicleCategories', 'updatedBy']);
    }
}
