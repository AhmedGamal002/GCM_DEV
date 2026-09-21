<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleCategory;

/**
 * Managing vehicle categories is system_admin only (client request —
 * outside the FRD, which fixed the list at five).
 *
 * NOTE: the list endpoint (index) is intentionally NOT gated — like the
 * asset capacity categories it's read-only reference data every tenant
 * role needs for the vehicle/driver/asset form dropdowns. Only the
 * single-row fetch behind the management pages and the mutations go
 * through this policy.
 */
class VehicleCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function view(User $actor, VehicleCategory $category): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function update(User $actor, VehicleCategory $category): bool
    {
        return $actor->hasRole('system_admin');
    }

    public function delete(User $actor, VehicleCategory $category): bool
    {
        return $actor->hasRole('system_admin');
    }
}
