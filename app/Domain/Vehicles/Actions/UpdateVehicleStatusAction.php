<?php

namespace App\Domain\Vehicles\Actions;

use App\Models\User;
use App\Models\Vehicle;

/**
 * FRD V01.14 role page for Data Entry ("إدارة الموارد: كل الصلاحيات
 * (انشاء / تعديل / تعطيل)") widens this beyond V01.09, which reserved
 * deactivating a vehicle for System Admin only — data_entry may now
 * deactivate/reactivate too, same as on_maintenance. (V01.14 §1.5.1's
 * own policy paragraph still says "مدير النظام فقط" for this — an
 * internal contradiction in that FRD version; confirmed with the client
 * as their mistake, intent is admin-parity for data_entry here.)
 */
class UpdateVehicleStatusAction
{
    public function execute(Vehicle $vehicle, string $status, User $actor): Vehicle
    {
        // operational_status is outside $fillable — set explicitly.
        $vehicle->operational_status = $status;
        $vehicle->updated_by = $actor->id;
        $vehicle->save();

        return $vehicle;
    }
}
