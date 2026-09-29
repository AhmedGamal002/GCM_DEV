<?php

namespace App\Policies;

use App\Models\IntermediateFacility;
use App\Models\User;

/**
 * FRD V01.14 §1.8: creating / editing / deactivating a facility is
 * (System Admin / Data Entry). Auditor is view/export only. Driver has no
 * access. Tenant isolation is already guaranteed by BelongsToTenant.
 */
class IntermediateFacilityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'auditor']);
    }

    public function view(User $actor, IntermediateFacility $facility): bool
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

    public function update(User $actor, IntermediateFacility $facility): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    public function updateStatus(User $actor, IntermediateFacility $facility): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }
}
