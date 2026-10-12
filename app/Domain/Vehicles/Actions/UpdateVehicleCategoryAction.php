<?php

namespace App\Domain\Vehicles\Actions;

use App\Models\VehicleCategory;

class UpdateVehicleCategoryAction
{
    /**
     * Names and the embedded-container defaults — the slug is deliberately
     * never touched (see the model). Changing the defaults only affects
     * vehicles created afterwards; existing vehicles keep their own values.
     *
     * @param  array{name_en: string, name_ar: string, default_has_embedded_container?: bool, default_embedded_container_type?: ?string, default_embedded_asset_capacity_category_id?: ?int}  $data
     */
    public function execute(VehicleCategory $category, array $data): VehicleCategory
    {
        $category->name_en = $data['name_en'];
        $category->name_ar = $data['name_ar'];
        $category->fill(VehicleCategory::embeddedDefaultsFrom($data));
        $category->save();

        return $category;
    }
}
