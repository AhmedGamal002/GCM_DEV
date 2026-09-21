<?php

namespace App\Domain\Vehicles\Actions;

use App\Domain\Vehicles\Exceptions\CannotDeactivateVehicleException;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Mirrors UpdateUserStatusAction. The FRD splits authority:
 * on_maintenance is (System Admin / Data Entry); Deactivated is System
 * Admin only. The Policy gates the request; this second check keeps the
 * rule true even if the action is called directly.
 */
class UpdateVehicleStatusAction
{
    public function execute(Vehicle $vehicle, string $status, User $actor): Vehicle
    {
        // Deactivating, or reactivating something that was deactivated,
        // is System Admin only (FRD: "تعطيل / تنشيط من خلال مدير النظام
        // فقط"). Moving to/from on_maintenance stays open to data_entry.
        $touchesDeactivation = $status === 'deactivated' || $vehicle->operational_status === 'deactivated';

        if ($touchesDeactivation && ! $actor->hasRole('system_admin')) {
            throw new CannotDeactivateVehicleException;
        }

        // operational_status is outside $fillable — set explicitly.
        $vehicle->operational_status = $status;
        $vehicle->updated_by = $actor->id;
        $vehicle->save();

        return $vehicle;
    }
}
