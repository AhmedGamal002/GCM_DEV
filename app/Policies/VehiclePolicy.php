<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

/**
 * FRD §1.5: creating/editing vehicles is (System Admin / Data Entry).
 * Auditor is view/export only. Driver has no fleet-management access.
 *
 * Tenant isolation is already guaranteed by BelongsToTenant before any
 * of these run — a user literally cannot load another tenant's Vehicle.
 *
 * The finer status split (on_maintenance = admin/data_entry, deactivate
 * and reactivate-from-deactivated = admin only) lives in
 * UpdateVehicleStatusAction, not here.
 */
class VehiclePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function view(User $actor, Vehicle $vehicle): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function export(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function update(User $actor, Vehicle $vehicle): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function updateStatus(User $actor, Vehicle $vehicle): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }
}
