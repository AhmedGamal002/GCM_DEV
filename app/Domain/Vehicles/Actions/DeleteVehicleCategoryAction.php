<?php

namespace App\Domain\Vehicles\Actions;

use App\Domain\Vehicles\Exceptions\VehicleCategoryInUseException;
use App\Models\VehicleCategory;

class DeleteVehicleCategoryAction
{
    /**
     * @throws VehicleCategoryInUseException
     */
    public function execute(VehicleCategory $category): void
    {
        $vehicles = $category->vehicles()->count();
        $drivers = $category->drivers()->count();
        $assets = $category->assets()->count();

        if ($vehicles || $drivers || $assets) {
            throw new VehicleCategoryInUseException($vehicles, $drivers, $assets);
        }

        $category->delete();
    }
}
