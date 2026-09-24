<?php

namespace App\Domain\Vehicles\Actions;

use App\Domain\Vehicles\Exceptions\PrimaryVehicleCategoryException;
use App\Domain\Vehicles\Exceptions\VehicleCategoryInUseException;
use App\Models\VehicleCategory;

class DeleteVehicleCategoryAction
{
    /**
     * @throws PrimaryVehicleCategoryException
     * @throws VehicleCategoryInUseException
     */
    public function execute(VehicleCategory $category): void
    {
        if ($category->isDefault()) {
            throw new PrimaryVehicleCategoryException;
        }

        $vehicles = $category->vehicles()->count();
        $drivers = $category->drivers()->count();
        $assets = $category->assets()->count();

        if ($vehicles || $drivers || $assets) {
            throw new VehicleCategoryInUseException($vehicles, $drivers, $assets);
        }

        $category->delete();
    }
}
