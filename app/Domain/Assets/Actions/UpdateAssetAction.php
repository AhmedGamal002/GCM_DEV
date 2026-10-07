<?php

namespace App\Domain\Assets\Actions;

use App\Models\Asset;
use App\Models\User;

/**
 * FRD §1.7.3 asset edit page: "أسم الأصل فقط المسموح بتعديله اما السعة
 * والتصنيف وما سواها" — the name is the only field the FRD lets through; the
 * client also asked for the additional data (plain notes) to stay editable.
 * Capacity, type, affiliation, purchase date and the compatible vehicle
 * categories stay locked because they ripple into contracts and trips.
 * operational_status has its own action / endpoint.
 */
class UpdateAssetAction
{
    /**
     * @param  array{name: string, additional_data?: ?string}  $data
     */
    public function execute(Asset $asset, array $data, User $actor): Asset
    {
        $asset->name = $data['name'];
        // Only touched when sent, so a name-only request never wipes the notes.
        if (array_key_exists('additional_data', $data)) {
            $asset->additional_data = $data['additional_data'];
        }
        $asset->updated_by = $actor->id;
        $asset->save();

        return $asset->load(['capacityCategory', 'compatibleVehicleCategories', 'updatedBy']);
    }
}
