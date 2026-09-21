<?php

namespace App\Domain\Vehicles\Actions;

use App\Models\VehicleCategory;

class UpdateVehicleCategoryAction
{
    /**
     * Names only — the slug is deliberately never touched (see the model).
     *
     * @param  array{name_en: string, name_ar: string}  $data
     */
    public function execute(VehicleCategory $category, array $data): VehicleCategory
    {
        $category->name_en = $data['name_en'];
        $category->name_ar = $data['name_ar'];
        $category->save();

        return $category;
    }
}
